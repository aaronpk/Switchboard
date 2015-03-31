<?php
declare(ticks=1);

chdir(__DIR__.'/..');

$mode = 'run';
if(array_key_exists(1, $argv) && $argv[1] == 'once')
  $mode = 'once';

if($mode == 'run') {
  if(function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function($sig){
      global $pcntl_continue;
      $pcntl_continue = FALSE;
    });
  }
}
$pcntl_continue = TRUE;

define('PDO_SUPPORT_DELAYED', TRUE);

// TODO: add support for forking and running many workers in parallel
// e.g. `php run.php 10`

require 'vendor/autoload.php';

if($mode == 'once') {
  DeferredTask::run_once();
} else {
  DeferredTask::run();
}
