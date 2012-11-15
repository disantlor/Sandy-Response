<?php
class OS_Aidee_Needs
{
	const PUMP = 'pump'; 
	const CLEANUP = 'cleanup'; 
	const REPAIR = 'repair';
	const SUPPLIES = 'supplies';
	
	/**
	 * Return array of class constants
	 * @return array
	 */
	public static function listAll()
	{
		$refl = new ReflectionClass(get_called_class());
		return $refl->getConstants();		
	}

}