<?php
namespace request;

function get_url($url, $include_headers=false) {
  $ch = curl_init($url);
  set_user_agent($ch);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  if($include_headers) {
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [
      'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
      'headers' => trim(substr($response, 0, $header_size)),
      'body' => substr($response, $header_size)
    ];
  } else {
    return curl_exec($ch);
  }
}

function get_head($url) {
  $ch = curl_init($url);
  set_user_agent($ch);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HEADER, true);
  curl_setopt($ch, CURLOPT_NOBODY, true);
  $headers = curl_exec($ch);
  return [
    'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'headers' => trim($headers)
  ];
}

function post($url, $params, $format='form', $headers=[]) {
  $ch = curl_init($url);
  set_user_agent($ch);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  if($format == 'json') {
    $body = json_encode($params);
    $headers[] = 'Content-type: application/json';
  } elseif($format == 'form') {
    $body = http_build_query($params);
  } else {
    $body = $params;
  }
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
  curl_setopt($ch, CURLOPT_HEADER, true);
  $response = curl_exec($ch);
  $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
  return [
    'status' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
    'headers' => trim(substr($response, 0, $header_size)),
    'body' => substr($response, $header_size)
  ];
}

function parse_headers($headers) {
  $retVal = array();
  $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $headers));
  foreach($fields as $field) {
    if(preg_match('/([^:]+): (.+)/m', $field, $match)) {
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      // If there's already a value set for the header name being returned, turn it into an array and add the new value
      $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function($m) {
        return strtoupper($m[0]);
      }, strtolower(trim($match[1])));
      if(isset($retVal[$match[1]])) {
        if(!is_array($retVal[$match[1]]))
          $retVal[$match[1]] = array($retVal[$match[1]]);
        $retVal[$match[1]][] = $match[2];
      } else {
        $retVal[$match[1]] = trim($match[2]);
      }
    }
  }
  return $retVal;
}

function set_user_agent(&$ch) {
  // Unfortunately I've seen a bunch of websites return different content when the user agent is set to something like curl or other server-side libraries, so we have to pretend to be a browser to successfully get the real HTML
  curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) p3k/Switchboard/0.1.1 AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.57 Safari/537.36');
}

function response_is($status, $prefix) {
  if($status) {
    $status_str = (string)$status;
    return $status_str[0] == (string)$prefix;
  } else {
    return false;
  }
}
