<?php
class OS_Aider extends OS_Model_Db
{
	protected $_table = 'aider_pledges';
	
	public function makePledgeTo(OS_Aidee $aidee)
	{
		if (! $this->getId()) {
			$this->save();
		}
		
		// check rate limit
		
		$aidee->setHelped($this->getId());
		
		
		// send alert to $aidee
	}
	
}