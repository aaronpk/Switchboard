<?php

function push_error(&$app, $msg) {
  $app->response()->status(400);
  echo $msg . "\n";
  die();
}

function push_param($params, $name) {
  // Look 'mode' first, fall back to 'hub_mode'
  if(k($params, $name))
    return k($params, $name);
  return k($params, 'hub_'.$name);
}

///////////////////////////////////////////////////////////////
// These are just test routes
$app->get('/callback-success', function() use($app) {
  $params = $app->request()->params();
  $app->response()->status(200);
  echo $params['hub_challenge'];
});
$app->post('/callback-success', function() use($app) {
  $params = $app->request()->params();
  $app->response()->status(200);
});

$app->get('/callback-fail', function() use($app) {
  $params = $app->request()->params();
  $app->response()->status(404);
});
///////////////////////////////////////////////////////////////

function verify_push_topic_url($topic, &$app) {
  // If we've already seen the topic, assume it's valid and don't check it again
  if(!db\feed_from_url($topic)) {
    $topic_head = request\get_head($topic);
    if($topic_head && !request\response_is($topic_head['status'], 2)) {
      push_error($app, "The topic URL returned a " . $topic_head['status'] . " status code");
    } elseif(!$topic_head) {
      push_error($app, 'We tried to verify the topic URL exists but it didn\'t respond to a HEAD request.');
    }
  }
}


$app->post('/', function() use($app) {
  $params = $app->request()->params();

  switch($mode=push_param($params, 'mode')) {
    case 'subscribe':
    case 'unsubscribe':

      // Sanity check the request params
      $topic = push_param($params, 'url');
      // Support subscribing using either "url" or "topic" parameters
      if(!$topic) {
        $topic = push_param($params, 'topic');
      }
      $callback = push_param($params, 'callback');

      if(!$topic) {
        push_error($app, 'No topic URL was specified. Send the topic URL in a parameter named "topic"');
      }

      if(!$callback) {
        push_error($app, 'No callback URL was specified. Send the callback URL in a parameter named "callback"');
      }

      if(!is_valid_push_url($topic)) {
        push_error($app, 'Topic URL was invalid ('.$topic.')');
      }

      if(!is_valid_push_url($callback)) {
        push_error($app, 'Callback URL was invalid');
      }

      $namespaced = k($params, 'hub_mode') ? 1 : 0; // set namespaced=1 if they used hub_mode in the request

      if($mode == 'subscribe') {
        verify_push_topic_url($topic, $app);

        // Find or create the feed given the topic URL
        $feed = db\find_or_create('feeds', ['feed_url'=>$topic], [
          'hash' => db\random_hash(),
        ], true);

        // Find or create the subscription for this callback URL and feed
        $subscription = db\find_or_create('subscriptions', ['feed_id'=>$feed->id, 'callback_url'=>$callback], [
          'hash' => db\random_hash()
        ], true);
        // Always set a new requested date and challenge
        $subscription->date_requested = db\now();
        $subscription->challenge = db\random_hash();
        $subscription->namespaced = $namespaced;
        db\set_updated($subscription);
        $subscription->save();

        // Queue the worker to validate the subscription
        DeferredTask::queue('PushTask', 'verify_subscription', [$subscription->id, 'subscribe']);

      } else {
        $feed = db\feed_from_url($topic);
        if(!$feed) {
          push_error($app, 'The topic was not found, so there is no subscription active');
        }

        $subscription = db\find('subscriptions', ['feed_id'=>$feed->id, 'callback_url'=>$callback]);
        if(!$subscription) {
          push_error($app, 'There was no subscription found for this callback URL and topic');
        }

        // Queue the worker to validate the subscription
        DeferredTask::queue('PushTask', 'verify_subscription', [$subscription->id, 'unsubscribe']);
      }

      $app->response()->status(202);
      echo "The subscription request is being validated. Check the status here:\n";
      echo Config::$base_url . '/subscription/' . $subscription->hash . "\n";

      break;

    case 'publish':

      // Sanity check the request params
      $urls = push_param($params, 'url');
      // Allow publishers to use either "url" or "topic" to indicate the URL that changed
      if(!$urls) {
        $urls = push_param($params, 'topic');
      }

      if(!$urls) {
        push_error($app, 'No URL was specified. When publishing, send the topic URL in a parameter named "url"');
      }

      if(!is_array($urls)) {
        $urls = array($urls);
      }

      foreach($urls as $url) {
        if(!is_valid_push_url($url)) {
          push_error($app, 'URL was invalid');
        }

        verify_push_topic_url($url, $app);

        // Find or create the feed given the topic URL
        $feed = db\find_or_create('feeds', ['feed_url'=>$url], [
          'hash' => db\random_hash(),
        ], true);

        $num_subscribers = ORM::for_table('subscriptions')->where('feed_id', $feed->id)->where('active', 1)->count();

        $feed->push_last_ping_received = db\now();
        db\set_updated($feed);
        $feed->save();

        // Queue the worker to ping all the subscribers about the new content
        DeferredTask::queue('PushTask', 'publish', $feed->id);

        $app->response()->status(202);
        echo "$url\n";
        echo "There are currently $num_subscribers active subscriptions for this feed.\n";
        echo "The hub is checking the feed for new content and notifying the subscribers.\nCheck the status here:\n";
        echo Config::$base_url . '/feed/' . $feed->hash . "\n\n";
      }

      break;
  }

});
