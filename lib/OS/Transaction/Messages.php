<?php
class OS_Transaction_Messages
{	
	public static function fetch($key)
	{
		$responses = array(
		  'aider_no_aidees' => 'There aren\'t unattended needs of this nature reported in your area currently. Pls spread the word about this resource! Have people text SANDY to 69866 w/ needs.',
		
		  'aider_helping' => 'Thank you for taking responsibility for this task. It will be taken off the need list.',
		
		  'aider_try_again' => 'Sorry, we couldn\'t understand your response. Please start over by texting MUTUALAID to 69866.',
		
		  'aider_rate_limited' => 'At this time, our system only allows you to volunteer to help :limit people per :time',
		
		  'aider_response' => 'Location: :address (:phone). Reply YES to take responsibility for this address. It will be removed from the need list; only reply if you will definitely help within 24 hrs. Reply NEXT for another address instead.',
		
		  'aider_response_supplies' => 'Location: :address (:phone) needs :supplies. If these supplies are available at your distribution center, text YES to accept responsibility for this address or NEXT for the next need.',
		
		  'aider_no_more_aidees' => 'You\'ve reached the end of this list. Reply NEXT to start over. Pls spread the word by having people in need text SANDY to 69866.',
		
		  'aider_aidee_already_helped' => 'Someone else has just pledged help for this address. Reply NEXT for another address.',
		
		  'aidee_new_request' => 'Your request has been recorded. We will try to send the next available volunteer to help you.',
		
		  'aidee_recently_helped' => 'A volunteer has said they would come help you. If no one shows up within 24 hrs, please text SANDY to 69866 again.',
		
		  'aidee_help_pending' => 'We already have this request in the system. No one has replied to help you yet, but please be patient!',
		
		  'unrecognized_keyword' => 'We don\'t recognize that keyword. Please try again.',
		
		  'unrecognized_keyword_aidee' => 'Your request has been recorded already. Please be patient! We\'ll let you know when someone is on their way. Have others in need text SANDY to 69866.',
		);
		
		return isset($responses[$key]) ? $responses[$key] : '';
	}
	
	public static function translate($key, $replace = array()) 
	{
		return strtr(self::fetch($key), $replace);	
	}
}