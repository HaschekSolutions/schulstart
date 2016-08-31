<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of validator
 *
 * @author Christian
 */
class Validator
{
    function validateEmail($text)
	{
		//returnt true, wenns eine korrekte email ist, false wenn nicht
		if(!filter_var($text, FILTER_VALIDATE_EMAIL)) return false;
		$arr = explode('@',$text);
		if(!$arr[0] || !$arr[1])
			return false;
		$arr2 = explode('.',$arr[1]);
		if(!$arr2[0] || !$arr2[1])
			return false;
		
		switch($arr[1]) //auf gängige trashmailvarianten prüfen
		{
			case 'trashmail.com': return false;
			case '10minutemail.com': return false;
			case 'discardmail.com':	return false;
			case 'dontsendmespam.com': return false;
			case 'jetable.org':	return false;
			case 'slopsbox.com': return false;
			case 'mailinator.com': return false;
			case 'sofort-mail.de': return false;
			case 'spamgourmet.com': return false;
			case 'trash-mail.com': return false;
			case 'nospamfor.us': return false;
			case 'dontsendmespam.de': return false;
			case 'trashdevil.com': return false;
			case 'mailtrash.net': return false;
		}
		return true;
	}
	
	function validateDate($text)
	{
		$arr = explode(".",$text);
		if(!is_numeric($arr[0])||!is_numeric($arr[1])||!is_numeric($arr[2])) return false;
		else if( ($arr[0]<1 || $arr[0]>31) || ($arr[1]<1 || $arr[1]>12) || ($arr[2]<(date("Y")-200) || $arr[2]>(date("Y")+200)) ) return false;
		
		return true;
	}
        
        /**
         *
         * @param type $text 
         * Validates if string is time like 23:59
         */
        function validateTime($text)
        {
            $arr = explode(":",$text);
            if(!is_array($arr)) return false;
            if(is_numeric($arr[0]) && is_numeric($arr[1]))
                if($arr[0] >=0 && $arr[0]<=23)
                    if($arr[1] >=0 && $arr[1]<=59)
                        return true;
            else return false;
        }
}
