<?php
	
function controller()
{
	$html = new HTML;	

	//if set, look for config file and store settings
	$settingslike = $_REQUEST['sameas'];

	if($settingslike && file_exists(ROOT.DS.'tmp'.DS.$settingslike.DS.'settings.json'))
	{
		$SETTINGS = json_decode(file_get_contents(ROOT.DS.'tmp'.DS.$settingslike.DS.'settings.json'),true);
		$SETTINGS['ignorefirstline']=$_POST['ignorefirstline'];
		$SETTINGS['encoding']=$_POST['encoding'];
		$SETTINGS['csv_aufbau']=$_POST['csv_aufbau'];
		$SETTINGS['trennzeichen']=$_POST['trennzeichen'];

		$_POST=$SETTINGS;
	}
	else $SETTINGS = $_POST;

	//array to check for double users
	$allusernames = array();
	
	// save csv
	$uploaddir = 'tmp/';
	$uploadfile = $uploaddir . basename($_FILES['csv']['name']);
	if(!move_uploaded_file($_FILES['csv']['tmp_name'], $uploadfile))
		return $html->error('CSV Konnte nicht gesichert werden');
		
	$hash = mb_substr(sha1(rand(0,100000)+time().time().basename($_FILES['csv']['name'])),0,8);
	
	$dlpath ='tmp/'.$hash.'/';
	if(!is_dir($dlpath)) 
		mkdir($dlpath);
	
	$t[] = array('Klasse','Name','Username','Email','Passwort');
	
	$lines = file($uploadfile);
	if($SETTINGS['ignorefirstline']=='1')
		unset($lines[0]);
	foreach($lines as $line)
	{
		// file data
		$line = str_replace("\0", "", $line);
		/*if($SETTINGS['encoding']=='1')
			$line = toUTF8(($line));
		else 
			$line = toISO(($line));
			*/
		$ic = $SETTINGS['trennzeichen'];
		$a = explode($ic,$line);
		
		switch($SETTINGS['csv_aufbau'])
		{
			case 1:
				$class = mb_trim($a[2]);
				$last= mb_trim(mb_convert_case(lower($a[1]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[0]), MB_CASE_TITLE, "UTF-8"));
			break;

			case 2:
				$class = mb_trim($a[2]);
				$last= mb_trim(mb_convert_case(lower($a[0]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[1]), MB_CASE_TITLE, "UTF-8"));
			break;

			case 3:
				$class = mb_trim($a[0]);
				$last= mb_trim(mb_convert_case(lower($a[1]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[2]), MB_CASE_TITLE, "UTF-8"));
			break;

			case 4:
				$class = mb_trim($a[0]);
				$uuid = mb_trim($a[1]);
				$last= mb_trim(mb_convert_case(lower($a[2]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[3]), MB_CASE_TITLE, "UTF-8"));
			break;

			case 5:
				$class = mb_trim($a[0]);
				$uuid = mb_trim($a[3]);
				$last= mb_trim(mb_convert_case(lower($a[1]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[2]), MB_CASE_TITLE, "UTF-8"));
			break;

			default:
				$class = mb_trim($a[0]);
				$last= mb_trim(mb_convert_case(lower($a[2]), MB_CASE_TITLE, "UTF-8"));
				$first = mb_trim(mb_convert_case(lower($a[1]), MB_CASE_TITLE, "UTF-8"));
		}

		if(!$class && !$last && !$first) continue;

		//calculated stuff
		$username = mb_substr(makeUsername($first,$last),0,20,'utf-8');
		$email = makeEmail($first,$last);
		$password = makePassword($last,$email);


		//check for double username
		if($allusernames[$username])
		{
			//ok so we have 2 users with the same name
			//try allowing of forbidding doublenames
			$origlast = $_POST['nodoublenames'];
			$origfirst = $_POST['nodoublenamesfirstname'];

			if($_POST['nodoublenames']) $_POST['nodoublenames'] = false;
			else $_POST['nodoublenames'] = true;

			if($_POST['nodoublenamesfirstname']) $_POST['nodoublenamesfirstname'] = false;
			else $_POST['nodoublenamesfirstname'] = true;

			$username = mb_substr(makeUsername($first,$last),0,20,'utf-8');
			$email = makeEmail($first,$last);

			if($allusernames[$username])
			{
				//hmm that didn't work. let's give them a number
				$username = mb_substr(makeUsername($first,$last).'1',0,20,'utf-8');
				$email = makeEmail($first,$last);
				if($allusernames[$username])
					exit("Cant fix the double name on $first $last. It's in the list twice");
			}

			//ok name change seems to have worked. let's reset the original rule and continue
			$_POST['nodoublenames'] = $origlast;
			$_POST['nodoublenamesfirstname'] = $origfirst;

		}
		else
			$allusernames[$username] = true;
		
		
		// form data
		$ou = utf8_decode($SETTINGS['ou']);
		$createhomes = $SETTINGS['homes'];
		$creategroups = $SETTINGS['groups'];
		$domainname = $SETTINGS['domainname'];
		$homedir_unc = str_replace("*user*", $username, $SETTINGS['uncpath']);
		$localpath = str_replace("*user*", $username, $SETTINGS['localpath']);
		$post = $SETTINGS['mailsuffix'];

		switch($SETTINGS['cnstyle'])
		{
			case '2':
				$cn = $first.' '.mb_convert_case(lower($last), MB_CASE_UPPER, "UTF-8");
			break;

			case '3':
				$cn = mb_convert_case(lower($last), MB_CASE_UPPER, "UTF-8").' '.$first;
			break;

			case '4':
				$cn = $username;
			break;

			default:
				$cn = makeEmailSafe($first,true).'.'.makeEmailSafe($last,true);
		}

		if($uuid)
		{
			$renamehome[] = '$oldhome = (get-aduser -Properties * -filter {employeeID -eq "'.$uuid.'"}).homedirectory';
			$renamehome[] = 'Rename-Item -Path $oldhome '.$username;
			$renamecn[] = 'rename-adobject -identity (get-aduser -filter {employeeID -eq "'.$uuid.'"}).distinguishedname -newname "'.$cn.'"';
			$renamecn[] = 'Set-ADUser "cn='.$cn.','.$ou.'" -Replace @{samaccountname="'.$username.'"} ';
		}
		else
			$renamecn[] = 'rename-adobject -identity (get-aduser -filter {SamAccountName -eq "'.$username.'"}).distinguishedname -newname "'.$cn.'"';
		
		//arrays
		$homerights[] = 'mkdir '.$localpath;
		$homerights[] = 'echo J|cacls '.$localpath.' /G '.$username.':f Domänen-Admins:f /T';
		
		
		if($SETTINGS['adduserstogroup'] && $class)
		{
			$addtogroups = explode(',',$SETTINGS['adduserstogroup']);
			foreach($addtogroups as $grp)
			{
				$grp = trim($grp);
				$usergroups[] = 'net group '.$grp.' '.$username.' /add /domain';
			}
		}

		$csvusers[] = implode(';',array($username,$password,$email,$first,$last,$class,$uuid));
		
		$mkuser[] = 'dsadd user "cn='.$cn.','.$ou.'" -samid '.$username.' -hmdrv H: -hmdir "'.$homedir_unc.'" -upn '.$email.' -fn "'.$first.'" -ln "'.$last.'" -email "'.$email.'" -display "'.upper($last).' '.$first.'" -pwd '.$password.' -mustchpwd '.(($SETTINGS['mustchangepw']==1)?'yes':'no').' -disabled no -canchpwd '.(($SETTINGS['cantchangepw']=='1')?'no':'yes').($uuid?' -empid '.$uuid:'');
		
		if($SETTINGS['forcepwallusers']=='1')
			$forcepw = ' -pwd '.$password.' -mustchpwd '.(($SETTINGS['mustchangepw']==1)?'yes':'no').' -canchpwd '.(($SETTINGS['cantchangepw']=='1')?'no':'yes');
		else $forcepw = '';
		
		if($uuid)
			$dsmod  = '$dsuser = dsquery * -filter "(employeeID='.$uuid.')" ; dsmod user $dsuser';
		else
			$dsmod = 'dsmod user "cn='.$cn.','.$ou.'"';
		$moduser[] = $dsmod.' -upn '.$email.' -display "'.upper($last).' '.$first.'" -disabled no -email "'.$email.'" -fn "'.$first.'" -ln "'.$last.'"'.$forcepw.($uuid?' -empid '.$uuid:'');
		
		//klogasse
		//$mkuser[] = 'dsadd user "cn='.$first.' '.upper($last).','.$ou.'" -samid '.$username.' -hmdrv H: -hmdir "'.$homedir_unc.'" -upn '.$username.'@'.$post.' -fn "'.$first.'" -ln "'.$last.'" -email "'.$email.'" -display "'.$first.' '.upper($last).'" -pwd '.$password.' -mustchpwd yes -disabled no';
		//$moduser[] = 'dsmod user "cn='.$username.','.$ou.'" -display "'.$first.' '.upper($last).'" -disabled no -email "'.$email.'" -fn "'.$first.'" -ln "'.$last.'"';
		//$moduser[] = 'dsmod user "cn='.$first.' '.upper($last).','.$ou.'" -display "'.$first.' '.upper($last).'" -disabled no -email "'.$email.'" -fn "'.$first.'" -ln "'.$last.'"';
		
		$t[] = array($class,$last.' | '.$first,($username),($email),$password);
		
		if($class)
			$classes[$class][] = array($class,$last,$first,$username,$email,$password);
	}
	
	
	if($creategroups && $SETTINGS['createclassgroups'] && is_array($classes))
	{
		$zip = new ZipArchive();
		$zipfilename = $dlpath."Klassenlisten.zip";
		
		if ($zip->open($zipfilename, ZIPARCHIVE::CREATE | ZipArchive::OVERWRITE)!==TRUE) {
			exit("cannot open $zipfilename");
		}
		
		foreach($classes as $class=>$users)
		{
			if($SETTINGS['deletegroups'])
				$groups[] = 'net group '.$class.' /delete';
			
			$groups[] = 'net group '.$class.' /add /domain';
			$groupsPS[] = 'Set-ADGroup "'.$class.'" -Replace @{mail="'.$class.'@'.$post.'"}';
			
			if($SETTINGS['addshare'] && $SETTINGS['classsharepath'])
			{
				$path = str_replace("*klasse*", $class, $SETTINGS['classsharepath']);
				$classshare[] = 'mkdir '.$path;
				if($SETTINGS['grouppermission'])
					$alsoallowed = $SETTINGS['grouppermission'].':f';
				$classshare[] = 'echo J|cacls '.$path.' /G '.$class.':f '.$alsoallowed.' Domänen-Admins:f';
			}
			
			$classcsv = array();
			$classcsv[] = implode(';',array('Nachname','Vorname','Benutzername','Email Adresse','Initialpasswort'));
			foreach($users as $u)
			{
				$classcsv[] = implode(';',array($u[1],$u[2],$u[3], $u[4],$u[5]));
				$usergroups[] = 'net group '.$class.' '.$u[3].' /add /domain';
			}
			
			saveFile($dlpath.$class.'.csv',$classcsv);
			$zip->addFile($dlpath. $class.'.csv',$class.'.csv');
					
		}
		
		$zip->close();
	}

	file_put_contents(ROOT.DS.'tmp'.DS.$hash.DS.'settings.json',json_encode($SETTINGS));
	
	saveFile($dlpath."domaincontroller.txt",$mkuser);
	saveFile($dlpath."domaincontroller.txt",$moduser,true);

	file_put_contents($dlpath."users.csv",implode("\n",$csvusers));
	
	saveFile($dlpath."domaincontroller.txt",$groups,true);
	saveFile($dlpath."domaincontroller.txt",$usergroups,true);
	
	if($SETTINGS['createclassgroups'])
		saveFile($dlpath."emails_for_groups.ps1",$groupsPS);

	if($SETTINGS['renamecn'])
		saveFile($dlpath."rename_old_cn.ps1",$renamecn);
	
	saveFile($dlpath."rename_homes.ps1",$renamehome);
	
	saveFile($dlpath."fileserver.txt",$homerights);
	saveFile($dlpath."fileserver.txt",$classshare,true);


	file_put_contents($dlpath.'table.json', (($SETTINGS['encoding']!='1')?"\xEF\xBB\xBF":''). json_encode($t)); 
	
	/*
	$downloadbuttons = $html->link('Download domaincontroller.txt',$dlpath."domaincontroller.txt").' ';
	if($_POST['createclassgroups'])
		$downloadbuttons.= $html->link('Download emails_for_groups.ps1',$dlpath."emails_for_groups.ps1").' ';
	$downloadbuttons.= $html->link('Download fileserver.txt',$dlpath."fileserver.txt").' ';
	$downloadbuttons.= $html->link('Download Klassenlisten.zip',$zipfilename);
	*/

	return renderResults($hash);
	//return $html->goToLocation('?h='.$hash);
}

function renderResults($hash)
{
	$basedir = ROOT.DS.'tmp'.DS.$hash;
	if(!is_dir($basedir))
		exit('Fehler');
	$html = new HTML;

	$downloadbuttons = '';

	$zipfilename = 'tmp/'.$hash.'/Klassenlisten.zip';
	if(file_exists($basedir.DS.'rename_old_cn.ps1'))
		$downloadbuttons.= $html->link('Download prepare_users.ps1','tmp/'.$hash."/rename_old_cn.ps1").' ';
	if(file_exists($basedir.DS.'rename_homes.ps1'))
		$downloadbuttons.= $html->link('Download rename_homes.ps1','tmp/'.$hash."/rename_homes.ps1").' ';
	if(file_exists($basedir.DS.'users.csv'))
		$downloadbuttons.= $html->link('Download users.csv','tmp/'.$hash."/users.csv").' ';
	$downloadbuttons.= $html->link('Download domaincontroller.txt','tmp/'.$hash."/domaincontroller.txt").' ';
	if(file_exists($basedir.DS.'emails_for_groups.ps1'))
		$downloadbuttons.= $html->link('Download emails_for_groups.ps1','tmp/'.$hash."/emails_for_groups.ps1").' ';
	$downloadbuttons.= $html->link('Download fileserver.txt','tmp/'.$hash."/fileserver.txt").' ';
	$downloadbuttons.= $html->link('Download Klassenlisten.zip',$zipfilename);


	$table = json_decode(implode(NULL,file($basedir.DS.'table.json')),true);

	if(!$_GET['h'])
		$permalink = '<br/><br/><div class="well"><strong>Permalink:</strong> <a href="?h='.$hash.'"><span id="domain"><script>window.onload = function() {$("#domain").text(window.location.href);}</script></span>?h='.$hash.'</a></div>';

	return $downloadbuttons.$permalink.
	'<h2> Empfohlene Vorgehensweise</h2>
	<h4><strong>Schritt 0:</strong> Ein Backup des Domaincontrollers machen (sicher ist sicher)!!</h4>
	<h4><strong>Schritt 1:</strong> Alle bestehenden Schülerkonten deaktivieren (im AD alle markieren -> rechte Maustaste-> Deaktivieren)</h4>
	<h4><strong>Schritt 2:</strong> Den Inhalt der domaincontroller.txt markieren und rechte Maustaste -> "Kopieren". Dann auf dem Domaincontroller eine Eingabeaufforderung (cmd) als Administrator aufmachen eingeben (rechte maustaste->einfügen) (nicht als .bat ausführen! Das wird Probleme mit Umlauten machen)</h4>
	<h4><strong>Schritt 2.1 (wenn Gruppen angelegt werden sollen):</strong> Auf dem Domaincontroller das Script emails_for_groups.ps1 mit Powershell ausführen</h4>
	<h4><strong>Schritt 3:</strong> Auf dem Dateiserver fileserver.txt mit einem Editor öffnen und in eine Eingabeaufforderung (cmd) eingeben</h4>
	<h4><strong>Schritt 4:</strong> In der Datei Klassenlisten.zip sind für jede Klasse die Zugangsdaten aufbereitet. Die Listen sind excel-freundlich und können in dieser Form an alle Lehrer versendet werden. Mit dem Hinweis, dass die Passwörter nur neu angelegt Schüler betreffen!</h4>
	<br/>
	<h3>Zu beachten:</h3>
	<ul>
		<li>Bestehende Benutzerkonten werden NICHT mit dem neuen Passwort versehen außer es wurde explizit ausgewählt</li>
		<li>Bestehende Klassenordner werden nicht geleert oder verschoben! Das sollte man am besten händisch vor dem Import machen</li>
	</ul>
	'.$html->table($table);
}

function upper($string)
{
	return mb_convert_case($string, MB_CASE_UPPER, "UTF-8");
}

function lower($string)
{
	return mb_convert_case($string, MB_CASE_LOWER, "UTF-8");
}

function mb_ucasefirst($str){ 
    $str[0] = mb_strtoupper($str[0]); 
    return $str; 
} 

function ensure_utf8($string)
{
	if (preg_match('%^(?:
		[\x09\x0A\x0D\x20-\x7E]            # ASCII
		| [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
		| \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
		| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
		| \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
		| \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
		| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
		| \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
	)*$%xs', $string))
		return $string;
	else
		return iconv('CP1252', 'UTF-8', $string);
}

function stackoverflow($input)
{
	$encoding = mb_detect_encoding( $input, array(
		"UTF-8",
		"UTF-32",
		"UTF-32BE",
		"UTF-32LE",
		"UTF-16",
		"UTF-16BE",
		"UTF-16LE"
	), TRUE );
	
	if( $encoding !== "UTF-8" ) {
		$input = mb_convert_encoding( $input, "UTF-8", $encoding );
	}
	return $input;
}

function makePassword($last,$email)
{
	switch($_POST['password'])
	{
		default:
		case 1: return generatePasswordAlphanum(8,$email);
		case 2: return generatePasswordAlpha(8);
		case 3: return makeEmailSafe(lower($last),false);
		case 4: return $_POST['custompassword'];
	}
}

function makeUsername($first,$last)
{
	$first = lower($first);
	$last = lower($last);
	
	if($_POST['noumlautinusername'])
	{
		$first = makeEmailSafe($first,$_POST['nodoublenames']);
		$last = makeEmailSafe($last,$_POST['nodoublenames']);
	}
	else //lets allow umlauts
	{
		$first = preg_replace('/[^\p{Latin}\d ]/u', '', $first);
		$last = preg_replace('/[^\p{Latin}\d ]/u', '', $last);

		$first = str_replace (" ", "", $first);
		$first = str_replace ("-", "", $first);
		
		$last = str_replace (" ", "", $last);
		$last = str_replace ("-", "", $last);
	}
	
	//var_dump(mb_substr($last,0,4,'utf-8'));
	
	
	switch($_POST['usernamestyle'])
	{
		default:
		case 1: return $first.'.'.$last;
		case 2: return $first.$last;
		case 3: return $last.'.'.$first;
		case 4: return $last.$first;
		case 5: return mb_substr($last,0,4,'utf-8').mb_substr($first,0,4,'utf-8');
		case 6: return $last;
	}
}

function getPartialNames()
{
	return array(
		'al',
		'el',
		'del',
		'van',
		'van den',
		'van der',
		'von',
		'de'
	);
}

function makeEmail($first,$last)
{
	$first = lower($first);
	$last = lower($last);
	
	$first = makeEmailSafe($first,$_POST['nodoublenamesfirstname']);
	$last = makeEmailSafe($last,$_POST['nodoublenames']);
	
	switch($_POST['emailstyle'])
	{
		default:
		case 1: return $first.'.'.$last.'@'.$_POST['mailsuffix'];
		case 2: return $first.$last.'@'.$_POST['mailsuffix'];
		case 3: return $last.'.'.$first.'@'.$_POST['mailsuffix'];
		case 4: return $last.$first.'@'.$_POST['mailsuffix'];
		case 5: return mb_substr($last,0,4,'utf-8').mb_substr($first,0,4,'utf-8').'@'.$_POST['mailsuffix'];
		case 6: return $last.'@'.$_POST['mailsuffix'];

	}
}

function generatePasswordAlphanum($length,$email)
{
	$pw = mb_substr(md5($email),0,($length-1));
	
	return 'p'.$pw;
}

function generatePasswordAlpha($length)
{
	$seed = str_split('0123456789'); // and any other characters
	shuffle($seed); // probably optional since array_is randomized; this may be redundant
	$rand = '';
	foreach (array_rand($seed, ($length-1)) as $k) $rand .= $seed[$k];
	
	return 'p'.$rand;
}


function makeEmailSafe($text,$trim=false,$nohyphen=false)
{
	$text = str_replace ("--", "-", $text);

	if($trim)
	{
		$partnames = getPartialNames();
		$testparts = str_replace('-',' ',$text);
		$parts = explode(' ',$testparts);
		$newtext = array();
		foreach($parts as $key => $p)
		{
            if(in_array(strtolower($p),$partnames) )
                $newtext[] = $p;
			else 
			{
                $newtext[] = $p;
                if(( $parts[($key+1)] && in_array(strtolower($parts[($key-1)].' '.$p),$partnames)))
                    $newtext[] = $parts[($key+1)];
				break;
			}
		}
		
		$text = implode('-',$newtext);
	}
	$text = trim($text);
	$text = lower($text);
	
	$text = str_replace (" ", "-", $text);
	
	
	
	if($nohyphen)
		$text = str_replace ("-", "", $text);
	//$text = preg_replace('~[^a-zA-Z0-9_-]*~i','',$text);

	$text = convertstrangeletters($text); 
	
	$text = preg_replace('@[^0-9a-zA-Z\.\-]+@i', '', $text);
	
	return $text;
}

function convertstrangeletters($text)
{
	$convert_to = array( 
		"a", "a", "a", "a", "ae", "a", "ae", "c", "c", "e", "e", "e", "e", "i", "i", "i", "i", 
		"o", "n", "o", "o", "o", "o", "oe", "o", "u", "u", "u", "ue", "y", "", "", "", "c", "c", "ss","s","z"
	); 
	$convert_from = array( 
		"à", "á", "â", "ã", "ä", "å", "æ", "ç", "ć", "è", "é", "ê", "ë", "ì", "í", "î", "ï", 
		"ð", "ñ", "ò", "ó", "ô", "õ", "ö", "ø", "ù", "ú", "û", "ü", "ý", "`", "´", "'", "č", "Č", "ß","š","ž"
	); 

	return str_replace($convert_from, $convert_to, $text); 
}

function deepLower($texto){ 
    $texto = strtr($texto, " 
    ACELNÓSZZABCDEFGHIJKLMNOPRSTUWYZQ 
    XV
    ÂÀÁÄÃÊÈÉËÎÍÌÏÔÕÒÓÖÛÙÚÜÇČ 
    ", " 
    acelnószzabcdefghijklmnoprstuwyzq 
    xv
    aaaäaeeeeiiiiooooöuuuücc 
    "); 
    return lower($texto); 
} 


function GetRandomHash($digits)
{
	$hash = md5(microtime()+time()+date("i")+rand(1,1000));

	while($digits > strlen($hash))
		$hash.=md5(microtime()+rand(1,99999));
	return mb_substr($hash,0,$digits);
}

function validateEmail($email)
{
	if(eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email))
	  return true;
	else
	  return false;
}

function translateUmlaute($text)
{
	$text = str_replace ("ä", "ae", $text);
	$text = str_replace ("Ä", "AE", $text);
	$text = str_replace ("ö", "oe", $text);
	$text = str_replace ("Ö", "OE", $text);
	$text = str_replace ("ü", "ue", $text);
	$text = str_replace ("Ü", "UE", $text);
	$text = str_replace ("ß", "ss", $text);
	
	return $text;
}

function checkclass($class)
{
	switch($class)
	{
		case '1a': return true;
		case '1b': return true;
		case '1c': return true;
		case '2a': return true;
		case '2b': return true;
		case '2c': return true;
		case '3a': return true;
		case '3b': return true;
		case '3c': return true;
		case '4a': return true;
		case '4b': return true;
		case '4c': return true;
		case '5a': return true;
		case '5b': return true;
		case '5c': return true;
		case '6a': return true;
		case '6b': return true;
		case '6c': return true;
		case '7a': return true;
		case '7b': return true;
		case '7c': return true;
		case '8a': return true;
		case '8b': return true;
		case '8c': return true;
		default: return false;
	}
}

function saveFile($filename,$data,$append=false)
{
	if(is_array($data))
		$data = implode("\r\n",$data);
		
	$mode = ($append?'a':'w');

	$fp = fopen(trim($filename),$mode);
	if($fp)
	{
		fwrite($fp,($data)."\r\n"); 
		fclose($fp);
	}
	else die('error opening '.$filename);
}

function mb_trim($string, $charlist='\\\\s', $ltrim=true, $rtrim=true) 
    { 
        $both_ends = $ltrim && $rtrim; 

        $char_class_inner = preg_replace( 
            array( '/[\^\-\]\\\]/S', '/\\\{4}/S' ), 
            array( '\\\\\\0', '\\' ), 
            $charlist 
        ); 

        $work_horse = '[' . $char_class_inner . ']+'; 
        $ltrim && $left_pattern = '^' . $work_horse; 
        $rtrim && $right_pattern = $work_horse . '$'; 

        if($both_ends) 
        { 
            $pattern_middle = $left_pattern . '|' . $right_pattern; 
        } 
        elseif($ltrim) 
        { 
            $pattern_middle = $left_pattern; 
        } 
        else 
        { 
            $pattern_middle = $right_pattern; 
        } 

        return preg_replace("/$pattern_middle/usSD", '', $string); 
    } 