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

$app->get('/feed/:hash', function($hash) use($app) {
  $res = $app->response();

  $feed = db\get_by_col('feeds', 'hash', $hash);
  $subscribers = ORM::for_table('subscriptions')->where('feed_id', $feed->id)->where('active', 1)->find_many();
  $num_subscribers = ORM::for_table('subscriptions')->where('feed_id', $feed->id)->where('active', 1)->count();

  if(!$feed) {
    $app->response()->status(404);
  } else {
    ob_start();
    render('feed-status', array(
      'title'        => 'Switchboard',
      'meta'         => '',
      'feed'         => $feed,
      'subscribers'  => $subscribers,
      'num_subscribers' => $num_subscribers
    ));
    $html = ob_get_clean();
    $res->body($html);
  }
});
