<?php
use BarnabyWalters\Mf2;

class PushTask {

  public static function verify_subscription($subscription_id, $mode) {

    $subscription = db\get_by_id('subscriptions', $subscription_id);
    if($subscription) {
      $feed = db\get_by_id('feeds', $subscription->feed_id);

      // Choose the expiration for the subscription
      $lease_seconds = 86400*3;
      $exp_ts = time() + $lease_seconds;
      $exp_date = date('Y-m-d H:i:s', $exp_ts);

      $push_params = [
        'hub.mode' => ($mode == 'subscribe' ? 'subscribe' : 'unsubscribe'),
        'hub.topic' => $feed->feed_url,
        'hub.challenge' => $subscription->challenge
      ];
      if($mode == 'subscribe') {
        $push_params['hub.lease_seconds'] = $lease_seconds;
      }

      $url = parse_url($subscription->callback_url);
      if($q=k($url, 'query')) {
        parse_str($q, $existing_params);
        $push_params = array_merge($push_params, $existing_params);
      }
      $url['query'] = http_build_query($push_params);
      $url = build_url($url);

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
    }

  }

}
