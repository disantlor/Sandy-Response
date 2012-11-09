<?php

/**
 * This file contains methods for accessing Mobile Commons and Google Maps APIs.
 *
 * Yeah, we're breaking the GMaps EULA. Don't really care.
 */

/**
 * Normalize an address to USPS format.
 * 
 * @param string $address
 *
 * @return string formatted address
 */
function standardAddress($address) {
  require_once('AddressStandardizationSolution.php');
  $standardizer = new AddressStandardizationSolution;
  $address = $standardizer->AddressLineStandardization($address);
  return $address;
}

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
  $url.= urlencode("$address, $neighborhood, NY");

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

  if (is_supply_request()) {
    $keyword = 'supplies';
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
      'need',
      'i need',
    ),
    'medical' => array(
      'medical',
    ),
    'repair' => array(
      'repair',
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
    'firstaid' => array(
      'firstaid',
      'first aid',
    ),
    'building' => array(
      'building',
    ),
  );

  $keyword = trim($_GET['keyword']);
  if (empty($keyword) && !is_aidee($_GET['phone'])) {
    $keyword = get_recent_keyword($_GET['phone']);
    return $keyword;
  }
  foreach ($mapping as $key => $values) {
    if (in_array($keyword, $values)) {
      $keyword = $key;
      return $keyword;
    }
  }
  return FALSE;
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

/**
 * Get the supplies needed by an aidee.
 *
 * @param string $phone
 * 
 * return string|bool needed supplies, FALSE if empty
 */
function get_supplies($phone) {
  $url = 'https://secure.mcommons.com/api/profile';
  $params = array(
    'phone_number' => $phone,
  );

  $supplies = '';
  $data = mc_post($url, $params);

  foreach ($data->profile->custom_columns->custom_column as $column) {
    $attributes = $column->attributes();
    if ($attributes['name'] == 'Supplies needed') {
      $supplies = trim((string) $column);
    }
  }

  if (!empty($supplies)) {
    return $supplies;
  }
  else {
    return FALSE;
  }
}

/**
 * Map a user-inputted supply to a more peasant version
 *
 * @param string $supply
 *
 * @return string supply
 */
function map_supplies($supply) {
  $supply = strtolower($supply);

  $mapping = supply_map();
  foreach ($mapping as $key => $type) {
    if (in_array($supply, $type)) {
      return $key;
    }
  }
  return FALSE;
}

/**
 * @return array supply map
 */
function supply_map() {
  return array(
    'food' => array(
      'a',
      'a.',
      'food',
    ),
    'warm clothing' => array(
      'b',
      'b.',
      'warm',
      'clothing',
      'warm clothing',
    ),
    'baby' => array(
      'c',
      'c.',
      'baby',
    ),
  );

}

/**
 * @return bool
 */
function is_supply_request() {
  $arg = strtolower(trim($_GET['args']));
  foreach (supply_map() as $supply) {
    foreach ($supply as $response) {
      if (substr($arg, 0, strlen($response)) == $response) {
        return TRUE;
      }
    }
  }
  return false;
}
