<?php
class OS_Geolocation
{	
	/**
	 * Consult Google Geolocate API
	 * @param string $address
	 * @return mixed array or coordinates or boolean 
	 */
	public static function geolocate($address)
	{
		$url = "http://maps.google.com/maps/api/geocode/json?address=" . urlencode($address) . "&sensor=false";
		$apiResponse = json_decode(OS_Geolocation::_makeCURLRequest($url));
		
		$lat = $apiResponse->results[0]->geometry->location->lat;
		$lng = $apiResponse->results[0]->geometry->location->lng;
		
		
		if (is_null($lat) || is_null($lng)) {
			return false;
		}
		
		return array(
			'lat' => $lat,
			'lng' => $lng
		);
	}
	
	// TODO: use Zend_Http_Client instead
	private static function _makeCURLRequest($url)
	{
		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_URL, $url); 
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$data = curl_exec($ch); 
		curl_close($ch);
	
		return $data;
	}
}