<?php

require_once 'defs.php';

define('RATE_LIMIT', 5);
define('RATE_LIMIT_INTERVAL', '1 hour');

define('FLAG_NEW_REQUEST', 1);
define('FLAG_RECENTLY_HELPED', 2);
define('FLAG_HELP_PENDING', 3);

define('FLAG_NO_REQUESTS', 4);
define('FLAG_NO_MORE_REQUESTS', 5);
define('FLAG_SUCCESSFULLY_HELPING', 6);

define('FLAG_RATE_LIMIT', 7);
define('FLAG_NO_REQUEST_FOR_NUMBER', 8);

date_default_timezone_set('America/New_York');

// if we don't have a valid API key, go away!
if (!isset($_GET['api_key']) || $_GET['api_key'] != API_KEY) {
  echo 'Invalid API key.';
  exit;
}

require_once 'api.php';
require_once 'db.php';
require_once 'lang.php';

// Aider and aidee keyword mapping so we know whether a keyword is for an aider flow or aidee flow.
//
// Note: The two sub-arrays should match order. In other words, $keywords['aidee'][0] should be the
// aidee mapping of $keywords['aider'][0], in this case pump=>pumping.
$keywords = array(
  'aidee' => array(
    'pump',
    'cleanup',
    'supplies',
    'medical',
  ),
  'aider' => array(
    'pumping',
    'cleaning',
    'distro',
    'firstaid',
  ),
);

// if the texter is a potential aidee
if (in_array(get_keyword(), $keywords['aidee'])) {
  // create a new request object
  $request = new Request;

  $request->phone = $_GET['phone'];
  $request->type = get_keyword();

  $request->neighborhood = clean_neighborhood($_GET['profile_neighborhood']);

  // set the address to both fields if they're filled out
  $request->address = trim($_GET['profile_street1']);
  if (!empty($_GET['profile_street2'])) {
    $request->address .= ', ' . $_GET['profile_street2'];
  }

  // we store requests by lat/lon for accuracy instead of street address
  // Otherwise, we'd have to deal with things like (1 N Main St) vs (1 North Main Street)
  list($request->lat, $request->lon) = geocode($request->address, $request->neighborhood);

  // attempt to create the request in the DB, handling the response codes that get returned
  // from create_request(Request)
  switch(create_request($request)) {
    case FLAG_NEW_REQUEST:
      echo $responses['aidee_new_request'];
      if ($request->type == 'medical') {
        echo ' Please call 911 for emergencies.';
      }
      break;
    case FLAG_RECENTLY_HELPED:
      echo $responses['aidee_recently_helped'];
      break;
    case FLAG_HELP_PENDING:
      echo $responses['aidee_help_pending'];
      break;
  }
}
// if the texter is a potential aider
else if (in_array(get_keyword(), $keywords['aider'])) {
  global $type;
  // CHECK: if they say yes over an hour later, what do we do?
  $neighborhood = clean_neighborhood($_GET['profile_neighborhood']);
  $type = map_type(get_keyword());

  // make sure there's an entry in the DB conversation log for this phone number
  initialize_aider($_GET['phone']);

  // clean up the args
  $arg = strtolower(trim($_GET['args']));

  // If they text something that's not next
  if (!empty($arg) && substr($arg, 0, 4) != 'next') {

    // if it's YES
    if (substr($arg, 0, 3) == 'yes') {

      // flag the request as being helped by this aider if possible
      $result = set_helping($_GET['phone']);

      // send a message based on the response code from set_helping($phone)
      switch ($result) {
        case FLAG_SUCCESSFULLY_HELPING:
          echo $responses['aider_helping'];
          clear_log($_GET['phone']);
          break;
        case FLAG_RATE_LIMIT:
          echo strtr($responses['aider_rate_limited'], array(
            ':limit' => RATE_LIMIT,
            ':time' => RATE_LIMIT_INTERVAL,
          ));
          clear_log($_GET['phone']);
          break;
        case FLAG_NO_REQUEST_FOR_NUMBER:
          echo $responses['aider_try_again'];
          clear_log($_GET['phone']);
          break;
      }
    }
    else {
      // this is a bad arg. ignore it.
      echo $responses['aider_try_again'];
      clear_log($_GET['phone']);
    }
  }

  // if the text next or just the keyword
  else {
    // what page are we on?
    $offset = get_offset($_GET['phone']);

    // find the request for this page
    $response = find_request($neighborhood, $type, $offset);

    // if we get a response object
    if (!is_int($response)) {
      // log that we sent that response
      log_response_sent($_GET['phone'], $response->id);

      if ($type == 'supplies') {
        echo 'Supply needed: ' . get_supplies($response->phone) . '. ';
      }

      echo strtr($responses['aider_response'], array(
        ':phone' => format_phone($response->phone),
        ':address' => $response->address,
        ':type' => $type,
      ));
    }
    // something went wrong!
    else {
      // handle the error codes
      switch ($response) {
        case FLAG_NO_REQUESTS:
          echo $responses['aider_no_aidees'];
          break;
        case FLAG_NO_MORE_REQUESTS:
          echo $responses['aider_no_more_aidees'];
          clear_log($_GET['phone']);
          break;
      }
    }
  }
}
// miserable failure
else {
  if (is_aidee($_GET['phone'])) {
    echo $responses['unrecognized_keyword_aidee'];
  }
  else {
    echo $responses['unrecognized_keyword'];
  }
}

/**
 * Map an aider keyword to an aidee keyword.
 *
 * @param string $type
 *
 * @return string keyword mapping
 */
function map_type($type) {
  global $keywords;
  $pos = array_search($type, $keywords['aider']);
  return $keywords['aidee'][$pos];
}

/**
 * Map an aidee keyword to an aider keyword.
 *
 * @param string $type
 *
 * @return string keyword mapping
 */
function map_type_reverse($type) {
  global $keywords;
  $pos = array_search($type, $keywords['aidee']);
  return $keywords['aider'][$pos];
}

/**
 * Format a phone number to look pretty.
 *
 * @param string $phone
 *
 * @return string
 */
function format_phone($phone) {
  if ($phone[0] == '1') {
    $phone = substr($phone, 1);
  }
  return substr($phone, 0, 3) . '-' . substr($phone, 3, 3) . '-' . substr($phone, 6);
}
