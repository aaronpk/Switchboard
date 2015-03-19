<?php

function push_error(&$app, $msg) {
  $app->response()->status(400);
  echo $msg . "\n";
  die();
}

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

      // Make a HEAD request to the topic URL to check if it exists
      $topic_head = request\get_head($topic);
      if($topic_head) {
        if(request\response_is($topic_head['status'], 2)) {
          $app->response()->status(202);

          // Find or create the feed given the topic URL
          $feed = db\find_or_create('feeds', ['feed_url'=>$topic], [
            'hash' => db\random_hash(),
          ], true);

          print_r($feed);

          // Find or create the subscription for this callback URL and feed
          $subscription = db\find_or_create('subscriptions', ['feed_id'=>$feed->id, 'callback_url'=>$callback], [
            'hash' => db\random_hash(),
          ]);
          $subscription->date_requested = db\now();
          $subscription->challenge = db\random_hash();
          $subscription->save();





          echo "The subscription request is being validated. Check the status here:\n";
          echo Config::$base_url . '/subscription/' . $subscription->hash . "\n";
        } else {
          $app->response()->status(400);
          echo "The topic URL returned a " . $topic_head['status'] . " status code\n";
        }
      } else {
        push_error($app, 'There was a problem trying to verify the topic URL');
      }

      break;

    case 'unsubscribe':

      break;
  }


});
