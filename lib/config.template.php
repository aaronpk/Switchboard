<?php
class Config {
  public static $base_url = 'https://example.com';
  public static $hostname = 'example.com';
  public static $ssl = false;

  public static $dbHost = '127.0.0.1';
  public static $dbName = 'switchboard';
  public static $dbUsername = 'switchboard';
  public static $dbPassword = '';

  public static $beanstalkServer = '127.0.0.1';
  public static $beanstalkPort = 11300;

  public static $defaultAuthorizationEndpoint = 'https://indieauth.com/auth';
}
