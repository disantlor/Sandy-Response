<?php
class OS_Aidee extends OS_Model_Db
{
	protected $_table = 'aidee_requests';
	
	public function setDataArray($data = array())
	{
		parent::setDataArray($data);
		
		// manually implement float type on lat/lng
		// database adapter only returns strings
		$this->set('lat', (float)$this->get('lat'));
		$this->set('lng', (float)$this->get('lng'));
		
		return $this;
	}
	
	public function getAddress()
	{
		return ucwords(strtolower($this->get('address')));
	}
	
	public function getPhone()
	{
		// format phone nicely here
		return $this->get('phone');
	}

	public function hasBeenHelped()
	{
		return (bool)$this->get('help_pledged_at');
	}
	
	public function helpIsExpired()
	{
		return (time() - strtotime($duplicate->get('help_pledged_at')) > 86400); // seconds in day
	}
	
	/**
	 * Set aidee request as helped ands ave
	 * @param int $aiderId
	 * @return boolean
	 */
	public function setHelped($aiderId)
	{
		$this->set('help_pledged_at', date('Y-m-d H:i:s'));
		$this->set('aider_id', (int)$aiderId);
		
		return $this->save();
	}	

	/**
	 * Unset helped status for aidee request and save
	 * @return boolean
	 */
	public function setNotHelped()
	{
		$this->set('help_pledged_at', new Zend_Db_Expr('NULL'));
		$this->set('aider_id', new Zend_Db_Expr('NULL'));
		return $this->save();
	}

	/*
	 * STATIC FUNCTIONS
	 */	
	public static function findOutstanding($need, $neighborhood, $offset = 0)
	{
		$aidee = new OS_Aidee();
		
		return $aidee->findOne(
			array(
				'need' => $need,
				'neighborhood' => $neighborhood,
				'help_pledged_at IS NULL'
			),
			$offset,
			'created_at ASC'
		);	
	}
	
	/**
	 * Main conduit to adding Aidee's.  Validates and cleans input data
	 * @param array $data
	 * @return int OS_Aidee_RequestResult code
	 */
	public static function createRequest($data)
	{
		// validate data
		if (! self::_validateRequestData($data)) {
			return OS_Aidee_RequestResult::FAIL;
		}
		
		// clean data
		self::_cleanseRequestData($data);

		// check if request already recorded
		$existingRequest = new OS_Aidee();
		$existingRequest = $existingRequest->findOne(array(
			'address' => $data['address'],
			'need' => $data['need'],
			'phone' => $data['phone']
		));
		
		// if not, insert into db
		if (! $existingRequest) {
			$aidee = new OS_Aidee($data);
			$result = $aidee->save();
			return ($result) ? $aidee : OS_Aidee_RequestResult::FAIL;
		}
		
		// if existing, but not yet helped, ask for patience
		if (! $existingRequest->hasBeenHelped()) {
			return OS_Aidee_RequestResult::HELP_PENDING;
		}

		// if existing request, and help has been recently pledged, let them know it's on the way
		if (! $existingRequest->helpIsExpired()) {
			return OS_Aidee_RequestResult::RECENTLY_HELPED;
		}
		
		// if existing request, but help has not shown up and help pledge has expired, reset help request
		$existingRequest->setNotHelped();
		return OS_Aidee_RequestResult::FLAG_NEW_REQUEST;
	}
	
	/**
	 * Validate Aidee request data
	 * @param array $data
	 * @return boolean
	 */
	private static function _validateRequestData($data)
	{
		if (! is_array($data)) {
			return false;
		}

		if (! isset($data['phone']) /* || ! $this->validatePhone($data['phone']) */) {
			return false;
		}

		if (! isset($data['address']) /* || ! $this->validateAddress($data['address']) */) {
			return false;
		}
		
		if (! isset($data['neighborhood']) /* || ! $this->validateNeighborhood($data['address']) */) {
			return false;
		}

		return true;
	}
	
	/**
	 * Trim and tidy up request data.  Standardize address and calculate geolocation. Standardize phone number
	 * @param array $data by reference
	 */
	private static function _cleanseRequestData(&$data)
	{
		/*
		 * address standardization
		 */
		$standardizer = new OS_AddressStandardization();
  		$data['address'] = $standardizer->AddressLineStandardization($data['address']);
		
		/* 
		 * get geolocation
		 * TODO: move this array to a class? NY-centricity (word?) is hardcoded into this thing
		 */ 
		$neighborhoodsForDisplay = array(
			'staten' => 'Staten Island',
			'coney' => 'Coney Island',
			'rockaway' => 'Rockaway'
		);
		$geolocation = OS_Geolocation::geolocate($data['address'] . ', ' . $neighborhoodsForDisplay[$data['neighborhood']] . ', New York');
		$data['lat'] = $geolocation['lat'];
		$data['lng'] = $geolocation['lng'];
  		
		/*
		 * standardize phone number display
		 */
		if ($data['phone'][0] === '1') {
		    $phone = substr($data['phone'], 1);
		}
		$data['phone'] = substr($data['phone'], 0, 3) . '-' . substr($data['phone'], 3, 3) . '-' . substr($data['phone'], 6);
		
		/*
		 * truncate details field
		 */	
		$data['details'] = substr($data['details'], 0, 140);
	}
}