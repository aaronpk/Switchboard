<?php

function push_error(&$app, $msg) {
  $app->response()->status(400);
  echo $msg . "\n";
  die();
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

  switch($mode=k($params, 'hub_mode')) {
    case 'subscribe':
    case 'unsubscribe':

      // Sanity check the request params
      $topic = k($params, 'hub_topic');
      $callback = k($params, 'hub_callback');

      if(!is_valid_push_url($topic)) {
        push_error($app, 'Topic URL was invalid');
      }

      if(!is_valid_push_url($callback)) {
        push_error($app, 'Callback URL was invalid');
      }

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
      $url = k($params, 'hub_url');

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
      echo "There are currently $num_subscribers active subscriptions for this feed.\n";
      echo "The hub is checking the feed for new content and notifying the subscribers.\nCheck the status here:\n";
      echo Config::$base_url . '/feed/' . $feed->hash . "\n";

      break;
  }

});
