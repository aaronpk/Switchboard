<?php

$app->get('/', function() use($app) {
  $res = $app->response();
  ob_start();
  render('index', array(
    'title'        => 'Switchboard',
    'meta'         => ''
  ));
  $html = ob_get_clean();
  $res->body($html);
});

$app->get('/subscription/:hash', function($hash) use($app) {
  $res = $app->response();

  $subscription = db\get_by_col('subscriptions', 'hash', $hash);
  $feed = db\get_by_id('feeds', $subscription->feed_id);

  if(!$subscription) {
    $app->response()->status(404);
  } else {
    ob_start();
    render('subscription-status', array(
      'title'        => 'Switchboard',
      'meta'         => '',
      'subscription' => $subscription,
      'feed'         => $feed
    ));
    $html = ob_get_clean();
    $res->body($html);
  }
});

