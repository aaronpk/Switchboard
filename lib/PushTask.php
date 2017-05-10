<?php
use BarnabyWalters\Mf2;

class PushTask {

  public static function verify_subscription($subscription_id, $mode) {

    $subscription = db\get_by_id('subscriptions', $subscription_id);
    if($subscription) {
      $feed = db\get_by_id('feeds', $subscription->feed_id);

      // Choose the expiration for the subscription
      $lease_seconds = 86400*7 + 3600;
      $exp_ts = time() + $lease_seconds;
      $exp_date = date('Y-m-d H:i:s', $exp_ts);

      if($subscription->namespaced) {
        $prefix = 'hub.';
      } else {
        $prefix = '';
      }

      $push_params = [
        $prefix.'mode' => ($mode == 'subscribe' ? 'subscribe' : 'unsubscribe'),
        $prefix.'topic' => $feed->feed_url,
        $prefix.'challenge' => $subscription->challenge
      ];
      if($mode == 'subscribe') {
        $push_params[$prefix.'lease_seconds'] = $lease_seconds;
      }

      echo "Verifying subscription to ".$feed->feed_url." for callback ".$subscription->callback_url."\n";

      $url = parse_url($subscription->callback_url);
      if($q=k($url, 'query')) {
        parse_str($q, $existing_params);
        $push_params = array_merge($push_params, $existing_params);
      }
      $url['query'] = http_build_query($push_params);
      $url = build_url($url);

      echo "\t".$url."\n";
      $response = request\get_url($url, true);

      $subscription->challenge_response = $response['headers']."\n\n".$response['body'];

      if(request\response_is($response['status'], 2) && $response['body'] == $subscription->challenge) {
        // The subscriber replied with a 2xx status code and confirmed the challenge string.

        if($mode == 'subscribe') {
          // The subscription is confirmed and active.
          $subscription->date_confirmed = db\now();
          $subscription->lease_seconds = $lease_seconds;
          $subscription->date_expires = $exp_date;
          $subscription->active = 1;
          echo "Subscriber verified the request and is now subscribed\n";
        } else {
          $subscription->date_unsubscribed = db\now();
          $subscription->date_expires = null;
          $subscription->active = 0;
          echo "Subscriber verified the request and is now unsubscribed\n";
        }

      } else {
        // The subscriber did not confirm the subscription, so reject the request
        echo "Subscriber did not echo the challenge\n";

      }

      db\set_updated($subscription);
      $subscription->save();

      print_r($response);
    } else {
      echo "Subscription not found\n";
    }

  }

  public static function publish($feed_id) {
    $feed = db\get_by_id('feeds', $feed_id);
    if($feed) {

      echo $feed->feed_url."\n";
      echo "Checking feed for updates...\n";

      // First check the feed to see if the content has changed since the last time we checked
      $response = request\get_url($feed->feed_url, true);

      if($response['status'] != 200) {
        echo "Feed did not return HTTP 200: ".$response['status'].". Skipping publish.\n";
        return;
      }

      $feed->last_retrieved = db\now();
      db\set_updated($feed);
      $content_hash = md5($response['body']);

      if($content_hash != $feed->content_hash) {
        $feed->content_hash = $content_hash;

        $headers = request\parse_headers($response['headers']);
        if(array_key_exists('Content-Type', $headers)) {
          $feed->content_type = $headers['Content-Type'];
        }
        $feed->content = $response['body'];
        $feed->save();

        $subscribers = ORM::for_table('subscriptions')->where('feed_id', $feed->id)->where('active', 1)->find_many();
        foreach($subscribers as $s) {
          echo "Queuing notification for feed_id=$feed_id ($feed->feed_url) subscription_id=$s->id ($s->callback_url)\n";
          DeferredTask::queue('PushTask', 'notify_subscriber', [$feed_id, $s->id, db\now()]);
        }
        if(count($subscribers) == 0) {
          echo "No active subscribers\n";
        }

      } else {
        $feed->save();
        echo "Feed body has the same content hash as last time, not notifying subscribers\n";
      }

    } else {
      echo "Feed not found\n";
    }
  }

  public static function notify_subscriber($feed_id, $subscription_id, $date_queued) {
    $feed = db\get_by_id('feeds', $feed_id);
    if(!$feed) {
      echo "Feed not found\n";
      return;
    }

    $subscription = db\get_by_id('subscriptions', $subscription_id);
    if(!$subscription) {
      echo "Subscription not found\n";
      return;
    }

    // If the job was put on the queue before the last ping was sent, ignore it.
    // This happens when there is a retry job in the delayed queue, and then the
    // publisher sends a new publish request and the subscriber responds to it immediately.
    if(strtotime($date_queued) < strtotime($subscription->date_last_ping_sent)) {
      echo "Job was queued before the last ping was sent by the publisher, skipping\n";
      return;
    }

    echo "Processing subscriber: " . $subscription->callback_url . "\n";

    // Subscription may be "active" but the expiration date may have passed.
    // If so, de-activate the subscription.
    if(strtotime($subscription->date_expires) < time()) {
      echo "Subscription expired!\n";
      $subscription->active = 0;
      db\set_updated($subscription);
      $subscription->save();
      return;
    }

    echo "Notifying subscriber!\n";

    $headers = [
      'Content-Type: ' . ($feed->content_type ?: 'text/plain'),
      'Link: <' . Config::$base_url . '/>; rel="hub", <' . $feed->feed_url . '>; rel="self"',
    ];

    if($subscription->secret) {
      $signature = hash_hmac('sha256', $feed->content, $subscription->secret);
      $headers[] = 'X-Hub-Signature: sha256=' . $signature;
    }

    $subscription->date_last_ping_sent = db\now();
    $response = request\post($subscription->callback_url, $feed->content, false, $headers);
    $subscription->last_ping_status = $response['status'];
    $subscription->last_ping_headers = $response['headers'];
    $subscription->last_ping_body = $response['body'];

    echo "Subscriber return a " . $response['status'] . " HTTP status\n";

    if(request\response_is($response['status'], 2)) {
      $subscription->last_ping_success = 1;
      $subscription->last_ping_error_delay = 0;
    } elseif($response['status'] == 410) {
      // If the subscriber returns HTTP 410, then take that as an indication that they no longer want more notifications and deactivate this subscription
      // https://github.com/w3c/websub/issues/106
      // https://github.com/w3c/websub/issues/103
      echo "Deactivating subscription based on HTTP 410 response returned\n";
      $subscription->last_ping_success = 0;
      $subscription->active = 0;

    } else {
      $subscription->last_ping_success = 0;
      // If the ping failed, queue another ping for a later time with exponential backoff
      if($subscription->last_ping_error_delay == 0)
        $subscription->last_ping_error_delay = 15;

      // If it's timed out after 8 tries, de-activate the subscription
      if($subscription->last_ping_error_delay > 2000) {
        echo "Ping failed after " . $subscription->last_ping_error_delay . " seconds. Deactivating this subscription.\n";
        $subscription->active = 0;
      } else {
        echo "Ping failed, trying again in " . $subscription->last_ping_error_delay . " seconds\n";
        DeferredTask::queue('PushTask', 'notify_subscriber', [$feed_id, $subscription_id, db\now()], $subscription->last_ping_error_delay);
        $subscription->last_ping_error_delay = $subscription->last_ping_error_delay * 2;
      }
    }

    db\set_updated($subscription);
    $subscription->save();

  }

}
