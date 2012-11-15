<?php
class OS_ApiUser extends OS_Model_Db
{
	protected $_table = 'api_users';
	
	/**
	 * Validates API Key and requesting domain
	 * @param string $apiKey
	 * @param string $host
	 * @return boolean
	 */
	public static function isValid($apiKey, $host)
	{
		$apiUser = new OS_ApiUser();

		return (bool)$apiUser->findOne(array(
			'api_key' => $apiKey,
			'domain' => $host
		));
	}
	
}