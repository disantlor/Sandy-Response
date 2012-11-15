<?php
class OS_Transaction_Keywords
{
	const YES = 'yes';
	const NEXT = 'next';
	
	/**
	 * Try to match valid Need from the input string 
	 * @param unknown_type $input
	 */
	public static function matchFromString($input)
	{
		$mapping = array(
			OS_Aidee_Needs::PUMP => array(
				'pump',
		    ),
			OS_Aidee_Needs::CLEANUP => array(
				'cleanup',
		    ),
			OS_Aidee_Needs::SUPPLIES => array(
				'supplies',
				'need',
				'i need',
		    ),
			OS_Aidee_Needs::REPAIR => array(
				'repair',
		    ),
			OS_Aider_Abilities::PUMPING => array(
				'pumping',
		    ),
			OS_Aider_Abilities::CLEANING => array(
				'cleaning',
		    ),
			OS_Aider_Abilities::DISTRO => array(
				'distro',
				'distribution',
				'distr',
				'distri',
				'dist',
			),
			OS_Aider_Abilities::BUILDING => array(
				'builder',
				'building',
			)
		);
	
	 	$input = trim($input);
		foreach ($mapping as $key => $values) {
			if (in_array($input, $values)) {
				$keyword = $key;
				return $keyword;
			}
		}
	  	
		return false;	
	}
}