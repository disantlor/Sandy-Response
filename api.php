<?php

/**
 * This file contains methods for accessing Mobile Commons and Google Maps APIs.
 *
 * Yeah, we're breaking the GMaps EULA. Don't really care.
 */

/**
 * Gets the lat/long of an address (assuming the first result for now).
 *
 * @param string $address street address entered by the user
 * @param string $neighborhood neighborhood the user selected (rockaway, coney, or staten)
 *
 * @return array list($lat, $lon)
 */
function geocode($address, $neighborhood) {
  $url = 'http://maps.googleapis.com/maps/api/geocode/json?sensor=false&address=';
  $url.= urlencode("$address, $neighborhood");

  $response = json_decode(file_get_contents($url))->results[0];

  return array(
    $response->geometry->location->lat,
    $response->geometry->location->lng,
  );
}

/**
 * Gets a profile value for neighborhood and maps it to the proper value, since
 * users can text in a variety of responses. Returns one of three named constants.
 *
 * Keep in sync with:
 * https://secure.mcommons.com/campaigns/102851/opt_in_paths/133621
 *
 * @param string $response the plain text response from the user to parse
 *
 * @return string|bool cleaned-up neighborhood string, FALSE for failure
 */
function clean_neighborhood($response) {
  // arrays! Channeling the Drupal mentality!
  $neighborhoods = array(
    'rockaway' => array(
      'rockaway',
      'rockaways',
      'far rockaway',
      'the rockaways',
      'a.',
    ),
    'coney' => array(
      'coney',
      'b.',
    ),
    'staten' => array(
      'staten',
      'si',
      'c.',
    ),
  );
  
  // some responses are wrapped with massive whitespace for some reason
  $response = trim($response);

  // figure out which neighborhood we're in
  foreach ($neighborhoods as $key => $neighborhood_responses) {
    if (in_array($response, $neighborhood_responses)) {
      return $key;
    }
  }

  // whoops, we haven't found one. We should probably handle this error somewhere!
  return FALSE;
}

/**
 * Gets a keyword mapped to the original keyword based on GET parameters.
 */
function get_keyword() {
  static $keyword;
  if (isset($keyword)) {
    return $keyword;
  }

  $mapping = array(
    'pump' => array(
      'pump',
    ),
    'cleanup' => array(
      'cleanup',
    ),
    'supplies' => array(
      'supplies',
    ),
    'pumping' => array(
      'pumping',
    ),
    'cleaning' => array(
      'cleaning',
    ),
    'distro' => array(
      'distro',
      'distribution',
      'distr',
      'distri',
      'dist',
    ),
  );

  $keyword = trim($_GET['keyword']);
  if (empty($keyword)) {
    $keyword = get_recent_keyword($_GET['phone']);
    return $keyword;
  }
  foreach ($mapping as $key => $values) {
    if (in_array($keyword, $values)) {
      $keyword = $key;
      return $keyword;
    }
  }
}

/**
 * Send a POST request to Mobile Commons REST API.
 *
 * See: http://www.mobilecommons.com/mobile-commons-api/rest
 *
 * @param string $url a Mobile Commons API URL to POST to
 * @param array $fields an array of key=>value mappings to send with the POST request
 *
 * @return SimpleXMLElement parsed XML if the request
 */
function mc_post($url, $fields) {
  $ch = curl_init();
  curl_setopt($ch,CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
  curl_setopt($ch,CURLOPT_USERPWD, MC_AUTH);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch,CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS, $fields);

  $result = curl_exec($ch);
  curl_close($ch);

  return simplexml_load_string($result);
}
