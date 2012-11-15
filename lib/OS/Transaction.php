<?php
class OS_Transaction extends OS_Model_Db
{
	protected $_table = 'transactions';
		
	/**
	 * Find existing transaction or start a new session
	 * @param unknown_type $data
	 * @return boolean|OS_Transaction
	 */
	public static function initialize($data)
	{
		$transaction = new self();
		
		$existingTransaction = $transaction->findOne(array(
			'phone' => $data['phone'],
			'need' => $data['need']
		));
		
		if ($existingTransaction) {
			return $existingTransaction;
		}
		
		$transaction
			->setDataArray(array(
				'phone' => $data['phone'],
				'need' => $data['need'],
				'offset' => 0			
			))
			->save();
				
		return $transaction;
	}
	
	public function getOffset() 
	{
		return $this->get('offset');
	}
	
	public function incrementOffset()
	{
		$this->set('offset', $this->getOffset() + 1);
		$this->save();
	}
	
	public function resetOffset()
	{
		$this->set('offset', 0);
		$this->save();
	}
}