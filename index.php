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

require_once 'api.php';
require_once 'db.php';
require_once 'lang.php';

if (!isset($_GET['api_key']) || $_GET['api_key'] != API_KEY) {
  echo 'Invalid API key.';
  exit;
}

// Note: The two sub-arrays should match order. In other words, $keywords['aidee'][0] should be the
// aidee mapping of $keywords['aider'][0], in this case pump=>pumping.
$keywords = array(
  'aidee' => array(
    'pump',
    'cleanup',
    'supplies',
  ),
  'aider' => array(
    'pumping',
    'cleaning',
    'distro',
  ),
);

if (in_array(get_keyword(), $keywords['aidee'])) {
  $request = new Request;

  $request->phone = $_GET['phone'];
  $request->type = get_keyword();

  $request->neighborhood = clean_neighborhood($_GET['profile_neighborhood']);
  $request->address = trim($_GET['profile_street1']);
  if (!empty($_GET['profile_street2'])) {
    $request->address .= ', ' . $_GET['profile_street2'];
  }

  list($request->lat, $request->lon) = geocode($request->address, $request->neighborhood);

  switch(create_request($request)) {
    case FLAG_NEW_REQUEST:
      echo $responses['aidee_new_request'];
      break;
    case FLAG_RECENTLY_HELPED:
      echo $responses['aidee_recently_helped'];
      break;
    case FLAG_HELP_PENDING:
      echo $responses['aidee_help_pending'];
      break;
  }
}
else if (in_array(get_keyword(), $keywords['aider'])) {
  global $type;
  // CHECK: if they say yes over an hour later, what do we do?
  $neighborhood = clean_neighborhood($_GET['profile_neighborhood']);
  $type = map_type(get_keyword());
  initialize_aider($_GET['phone']);

  $arg = strtolower(trim($_GET['args']));
  if (!empty($arg) && substr($arg, 0, 4) != 'next') {
    if (substr($arg, 0, 3) == 'yes') {
      $result = set_helping($_GET['phone']);
      if ($result == FLAG_SUCCESSFULLY_HELPING) {
        echo $responses['aider_helping'];
        clear_log($_GET['phone']);
      }
      else if ($result == FLAG_RATE_LIMIT) {
        echo strtr($responses['aider_rate_limited'], array(
          ':limit' => RATE_LIMIT,
          ':time' => RATE_LIMIT_INTERVAL,
        ));
        clear_log($_GET['phone']);
      }
      else if ($result == FLAG_NO_REQUEST_FOR_NUMBER) {
        echo $responses['aider_try_again'];
        clear_log($_GET['phone']);
      }
    }
    else {
      echo $responses['aider_try_again'];
      clear_log($_GET['phone']);
    }
  }
  else {
    $offset = get_offset($_GET['phone']);
    $response = find_request($neighborhood, $type, $offset);

    if (!is_int($response)) {
      log_response_sent($_GET['phone'], $response->id);
      // we got a response!
      echo strtr($responses['aider_response'], array(
        ':phone' => format_phone($response->phone),
        ':address' => $response->address,
        ':type' => $type,
      ));
    }
    else {
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
else {
  echo $responses['unrecognized_keyword'];
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
