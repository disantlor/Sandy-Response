<?php
class OS_Aider_Abilities
{
	const PUMPING = 'pumping'; 
	const CLEANING = 'cleaning'; 
	const BUILDING = 'building';
	const DISTRO = 'distro';
	
	/**
	 * Return array of class constants
	 * @return array
	 */
	public static function listAll()
	{
		$refl = new ReflectionClass(get_called_class());
		return $refl->getConstants();		
	}
	
	/**
	 * Return the need keyword that matches the given ability keyword
	 */
	public static function matchToNeed($ability)
	{
		switch ($ability) {
			case OS_Aider_Abilities::CLEANING:
				return OS_Aidee_Needs::CLEANUP;
				
			case OS_Aider_Abilities::PUMPING:
				return OS_Aidee_Needs::PUMP;
				
			case OS_Aider_Abilities::BUILDING:
				return OS_Aidee_Needs::REPAIR;
				
			case OS_Aider_Abilities::DISTRO:
				return OS_Aidee_Needs::SUPPLIES;
				
			default:
				return false;
		}		
	}
}