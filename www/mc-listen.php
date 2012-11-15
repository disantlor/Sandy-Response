<?php
require 'init.php';

// validate request is from MC
if (! isset($_GET['api_key'])) {
	echo 'No API key given.'; 
	exit;
}

if (! OS_ApiUser::isValid($_GET['api_key'], $_SERVER['HTTP_HOST'])) {
	echo 'Invalid API key.'; 
	exit;
}

$keyword = OS_Transaction_Keywords::matchFromString($_GET['keyword']);

/*
 * HANDLE AIDEE FLOW
 */
if (in_array($keyword, OS_Aidee_Needs::listAll())) {
	
	// attempt to create request
	$result = OS_Aidee::createRequest(array(
		'phone' => $_GET['phone'],
		'need' => $keyword,
		'neighborhood' => $_GET['profile_neighborhood'],
		'address' => $_GET['profile_street_address'],
		'details' => ($keyword === OS_Aidee_Needs::SUPPLIES) ? $_GET['args'] : NULL
	));
	
	// success
	if ($result InstanceOf OS_Aidee) {
		
		echo OS_Transaction_Messages::fetch('aidee_new_request');
	
	// deal with error code
	} else {

		// deal with result code from creation attempt
		switch ($result) {

			// help pending, please be patient
			case OS_Aidee_RequestResult::HELP_PENDING:
				
				echo OS_Transaction_Messages::fetch('aidee_help_pending');
				break;
			
			// help is on the way
			case OS_Aidee_RequestResult::RECENTLY_HELPED:
				
				echo OS_Transaction_Messages::fetch('aidee_recently_helped');
				break;	
			
			// something went wrong
			case OS_Aidee_RequestResult::FAIL:
			default:
				
				echo OS_Transaction_Messages::fetch('unrecognized_keyword');
				break;
				
		}
	}
	
	exit;
}

/*
 * HANDLE AIDER FLOW
 */
if (in_array($_GET['keyword'], OS_Aider_Abilities::listAll())) {
	
	// log messagse in sms_users
	$transaction = OS_Transaction::initialize(array(
		'phone' => $_GET['phone'],
		'need' => $keyword
	));
	
	$args = isset($_GET['args']) ? strtolower(trim($_GET['args'])) : '';
	
	switch ($args) {
		
		/*
		 * Success!
		 */
		case OS_Transaction_Keywords::YES:
			
			// create OS_Aider or fetch existing
			$aider = new OS_Aider(array(
				'phone' => $_GET['phone'],
				'ability' => $keyword
			));
			
			// fetch OS_Aidee by offset and OS_Aider->makePledgeTo()
			$aidee = OS_Aidee::findOutstanding(OS_Aider_Abilities::matchToNeed($_GET['keyword']), $_GET['profile_neighborhood'], $transaction->getOffset());
			
			if (! $aidee) {	
				echo OS_Transaction_Messages::fetch('aider_aidee_already_helped');
				$transaction->resetOffset();
				break;				
			}
			
			$aider->makePledgeTo($aidee);
			
			// clear transaction from log
			$transaction->delete();
			
			break;
			
		/*
		 * Look for another Aidee
		 */
		case OS_Transaction_Keywords::NEXT:
			
			$transaction->incrementOffset();

			// get next aidee in line 
			$aidee = OS_Aidee::findOutstanding(OS_Aider_Abilities::matchToNeed($_GET['keyword']), $_GET['profile_neighborhood'], $transaction->getOffset());
			
			// if one found, print, otherwise send no aidees currently message
			if ($aidee) {
				
				echo OS_Transaction_Messages::translate('aider_response', array(
		        	':phone' => $aidee->getPhone(),
			    	':address' => $aidee->getAddress()
		        ));
		        
			} else {
				
				// if no aidees of this type at all
				if ($transaction->getOffset() === 0) {
					echo OS_Transaction_Messages::fetch('aider_no_aidees');
					
				// if we've run out of aidees to suggest
				} else {
					echo OS_Transaction_Messages::fetch('aider_no_more_aidees');
					$transaction->resetOffset();
				}
				
			}
			
			break;
			
		/*
		 * Fetch outstanding aidee and present option to aider
		 */
		default:
			
			if (in_array($_GET['keyword'], OS_Aider_Abilities::listAll())) {
				
				// fetch new aidee by params
				$aidee = OS_Aidee::findOutstanding(OS_Aider_Abilities::matchToNeed($_GET['keyword']), $_GET['profile_neighborhood']);
				
				// if one found, print, otherwise send no aidees currently message
				if ($aidee) {
					echo OS_Transaction_Messages::translate('aider_response', array(
			          ':phone' => $aidee->getPhone(),
			          ':address' => $aidee->getAddress()
			        ));
				} else {
					echo OS_Transaction_Messages::fetch('aider_no_aidees');
				}
				
			// else, send do not understand message
			} else {
				
				echo OS_Transaction_Messages::fetch('unrecognized_keyword');
				
			}
			
			break;
	}
	
	exit;
}

/*
 * If we're here, something went wrong
 */
echo OS_Transaction_Messages::fetch('unrecognized_keyword');
exit;