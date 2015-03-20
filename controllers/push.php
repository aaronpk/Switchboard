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

$app->get('/callback-fail', function() use($app) {
  $params = $app->request()->params();
  $app->response()->status(404);
});
///////////////////////////////////////////////////////////////


$app->post('/', function() use($app) {
  $params = $app->request()->params();

  switch(k($params, 'hub_mode')) {
    case 'subscribe':

      // Sanity check the request params
      $topic = k($params, 'hub_topic');
      $callback = k($params, 'hub_callback');

      if(!is_valid_push_url($topic)) {
        push_error($app, 'Topic URL was invalid');
      }

      if(!is_valid_push_url($callback)) {
        push_error($app, 'Callback URL was invalid');
      }

      // If we've already seen the topic, assume it's valid and don't check it again
      if(!db\feed_from_url($topic)) {
        $topic_head = request\get_head($topic);
        if($topic_head && !request\response_is($topic_head['status'], 2)) {
          push_error($app, "The topic URL returned a " . $topic_head['status'] . " status code");
        } else {
          push_error($app, 'We tried to verify the topic URL exists but it didn\'t respond to a HEAD request.');
        }
      }

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
      $subscription->save();

      // Queue the worker to validate the subscription
      DeferredTask::queue('PushTask', 'verify_subscription', $subscription->id);

      $app->response()->status(202);
      echo "The subscription request is being validated. Check the status here:\n";
      echo Config::$base_url . '/subscription/' . $subscription->hash . "\n";

      break;

    case 'unsubscribe':

      break;
  }


});
