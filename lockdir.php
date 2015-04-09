<?php
/*
******************************************************
* This is the server side php script for PassLok Lock*
* storage and retrieval                              *
* We have only one php page that handles everything  *
******************************************************
* The first part is server configuration             *
* ****************************************************
* Version:0.9                                       *
* Author: Weicheng Huang ----whuang23@hawk.iit.edu   *
* edits by F. Ruiz									 *
*                                                    *
* If this pages violates any of terms and agreements *
* Sorry!                                             *
* Please let me know and I will fix it ASAP!         *
******************************************************
*/

//******uncomment these two rows for debug mode******
//error_reporting(E_ALL|E_STRICT);
//ini_set('display_errors', 'on');

//****************************Server Conf************
	//uncomment the following define to turn on the server
	
	define("STATUS","ON");
	define("ANTIBOT_MAX_ERROR_COUNT",15);
	define("ANTIBOT_TEST_INTERVAL",5);
	//Database conf. ACTUAL DATA HAS BEEN REDACTED
	$db_addr="REDACTED";
	$db_name="REDACTED";
	$db_uName="REDACTED";
	$db_passwd="REDACTED";
	$db = array(
			'host' => $db_addr,
			'user' => $db_uName,
			'pass' => $db_passwd,
			'db' => $db_name
	);
	define("DATABASE_TABLE_NAME",'Lock');	
	//email conf
	define("EMAIL_NAME","");
	define("EMAIL_PASS","");
	define("EMAIL_SERVER","");
	
	
	//stop execution if the server is closed	
	if(!defined("STATUS")){
		die("<h3>Currently Shut Down:  </h3><p>Sorry! The Server is under construction.</p>");	
	}
	
	//connect to mySQL database
	$con = mysql_connect($db_addr,$db_uName,$db_passwd);
	mysql_select_db($db_name,$con);
	antiRobotCheck($con);
	//judge which action we need to handle
	if(isset($_GET['request_email']))
		define("ACTION","REQ");	
	else if(isset($_POST['update_email']))
		define("ACTION","UPD");	
	else if(isset($_POST['register_email']))
		define("ACTION","RIG");
	else if(isset($_GET['link']))
		define("ACTION","CFM");
	else
		define("ACTION","DEB");
	
	
	//main action direction
	switch(ACTION){
		case "REQ":
			handleRequest($con);
			break;
		case "UPD":
			handleUpdate($con);
			break;
		case "RIG":
			handleRegister($con);
			break;
		case "CFM":
			handleConfirm($con);
			break;
		case "DEB";
			if(STATUS=="DEB")
				displayForm($con);
			else
				echo("<h3>PassLok Server is ON</h3>");
			break;		
	}
	
//build in functions

function handleRequest($con){
	$email=$_GET['request_email'];
	if(!is_exist($email,$con)){
		antiRobotError($con);
		return;
	}	
	$email = xss_clean($email);
	$result = mysql_query("SELECT * FROM  `Lock` WHERE `email`='$email'",$con) or die(mysql_error($con));
	$row=mysql_fetch_array($result);
	$isgood = ($row['is_activite']==1);	
//	mysql_time($email,$con);
	if($isgood)
		echo $row['lock'];
	else
		return;	
}

function handleUpdate($con){
	$email = $_POST['update_email'];
	$lock = $_POST['update_lock'];
	if(empty($email)||empty($lock)){antiRobotError($con);return;}	
	
	//xss filter
	$email = xss_clean($email);
	$lock  = xss_clean($lock);	
	
	if(is_exist($email,$con)){
		if($lock=="Remove"){
			$url = md5($email."=>".time());
			$url = "DEL-".$url;
			mysql_query("UPDATE `Lock` SET `url`='$url',`need_confirm`=1, `confirm_date`=CURRENT_TIMESTAMP WHERE `email`='$email'") or die(mysql_error($con));
			sendEmail($email,$url,$lock);
			say("Email confirmation sent!");
			say("You should be getting an email from PassLok privacy shortly. The email contains a link that you must click in order to confirm the removal of your Lock");
		}else{
			mysql_update($email,"after",$con,$lock);
			$url = md5($email."=>".time());
			$url = "UPD-".$url;
			mysql_query("UPDATE `Lock` SET `url`='$url',`is_activite`=1, `need_confirm`=1, `confirm_date`=CURRENT_TIMESTAMP WHERE `email`='$email'") or die(mysql_error($con));
			mysql_time($email,$con);
			sendEmail($email,$url,$lock);
			say("Email confirmation sent!");
			say("You should be getting an email from PassLok privacy shortly. The email contains a link that you must click in order to finish updating the directory entry for your Lock.");
		}
		say("Sometimes emails like this get treated as spam, so please check your spam folder before you retry.");
		say("To return to the PassLok directory, click the link below.");
		back();
	}else{
		handleRegister($con);
	}	
}

function handleRegister($con){
	$email=$_POST['update_email'];
	$lock=$_POST['update_lock'];
	if(empty($email)||empty($lock))return;
	
	//xss filter
	$email = xss_clean($email);
	$lock  = xss_clean($lock);
	
	$url = md5($email."=>".time());
	$url = "REG-".$url;
	
	mysql_query("DELETE FROM `Lock` WHERE `email`='$email' and `is_activite`=0",$con);
	
	mysql_query("
		INSERT INTO `Lock`(
		`email`,
		`future_lock`,
		`date`,
		`url`,
		`is_activite`,
		`need_confirm`,
		`confirm_date`
		)
		VALUES(
		'$email','$lock',CURRENT_TIMESTAMP,'$url',0,1,CURRENT_TIMESTAMP
		);
	",$con) or die(mysql_error($con));
	sendEmail($email,$url,$lock);
	say("Email confirmation sent!");
	say("You should be getting an email from PassLok privacy shortly. The email contains a link that you must click in order to finish registering your Lock.");
	say("Sometimes emails like this get treated as spam, so please check your spam folder before you retry.");
	say("To return to the PassLok directory, click the link below.");
	back();
}

function handleConfirm($con){
	echo "<h3>PassLok directory confirmation</h3>";
	$url=$_GET['link'];
	if($url==""){
		antiRobotError($con);
		return;
	}
	$url = xss_clean($url);
	$result = mysql_query("SELECT * FROM  `Lock` WHERE `url`='$url'",$con) or die(mysql_error($con));
	if(mysql_num_rows($result)>0){
		$row = mysql_fetch_array($result);
		$email = $row['email'];	
		$prefix = substr($row['url'],0,3);	//use prefix to decide what needs to be confirmed
		if($row['need_confirm']==1){
			switch($prefix){
				case "UPD"://update
					mysql_update($email,"do",$con);
					mysql_time($email,$con);
					say("Lock update successful!");
					say("Please close this tab now.");
					break;
				case "REG"://register
					mysql_update($email,"do",$con);
					mysql_time($email,$con);
					say("Lock posting successful!");
					say("Please close this tab now.");
					break;
				case "DEL"://delete
					mysql_query("DELETE FROM `Lock` WHERE `email`='$email'",$con) or die(mysql_error($con));
					say("Lock removal successful!");
					say("Please close this tab now.");
					break;					
				case "REF"://refresh old lock
					mysql_time($email,$con);
					say("Lock refresh successful!");
					say("Please close this tab now.");
					break;
				default:
					say("Wrong confirm type!");
					antiRobotError($con);
					break;
			}
		mysql_query("UPDATE `Lock` SET `need_confirm` = 0 WHERE `email`='$email'",$con) or die(mysql_error($con));			
		}else{
			say("You do not need to confirm!");
			antiRobotError($con);	
		}
	}else{
		antiRobotError($con);
		say("404: Cannot find the link....URL=".$url);
		say("If you clicked a link to get here, please contact the site manager.");
		say("If you are playing with me, stop it!");
	}	
}	
	
//sub function
function sendEmail($email,$url,$lock){
	$to = $email;
	
	// subject
	$subject = 'PassLok directory email confirmation';
	
	// message
	$message = '
	<html>
	<head>
	<h5>Dear User:</h5>
	<p>Welcome to PassLok privacy.</p>
	<p>The following has been submitted as the entry for '.$email.' in the general PassLok directory:</p>
	<p>'.$lock.'</p>
	<p>We need to confirm this email address so that no one else can register a Lock for it.</p>
	<p>If you did not expect this email, it is possible that someone else is trying to alter the posting of your Lock. Proceed with caution.</p><br />
	<p>Please click <a href="https://passlok.com/lockdir/lockdir.php?link='.$url.'">this link</a> to confirm your address.</p><br />
	<p>Thank you, and enjoy PassLok.</p>
	<p>
	</body>
	</html>
	';
	
	// To send HTML mail, the Content-type header must be set
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
	$headers .= 'From: PassLok privacy<noreply@passlok.com>' . "\r\n";
	
	// Additional headers
	
	mail($to, $subject, $message, $headers) or die("Can not send email");		
}

function is_exist($email,$con){
	$result = mysql_query("SELECT * FROM  `Lock` WHERE `email`='$email'",$con) or die(mysql_error($con));
	if(mysql_num_rows($result)>0){
		$row=mysql_fetch_array($result);
		if($row['is_activite']==0){
			return false;	
		}else{
			return true;	
		}
	}else{
		return false; 
	}
}

function back(){
	echo "<a href='index.html'>Return</a>";	
}

function mysql_update($email,$type,$con,$lock=''){
	if($type=="now")
		mysql_query("UPDATE `Lock` SET `lock`='$lock',`is_activite`=1, `date`=CURRENT_TIMESTAMP WHERE `email`='$email'",$con) or die(mysql_error($con));
	else if($type=="after")
		mysql_query("UPDATE `Lock` SET `future_lock`='$lock',`is_activite`=1 WHERE `email`='$email'",$con) or die(mysql_error($con));
	else if($type=="do")
		mysql_query("UPDATE `Lock` SET `lock`=`future_lock`, `is_activite`=1, `date`=CURRENT_TIMESTAMP WHERE`email`='$email'",$con) or die(mysql_error($con));
	else
		die("Wrong uupdate type=".$type);
}

function mysql_time($email,$con){
	mysql_query("UPDATE `Lock` SET `date`=CURRENT_TIMESTAMP WHERE `email`='$email'") or die(mysql_error($con));	
}

function say($something){
	echo "<p>".$something."</p>";	
}
function antiRobotCheck($con){
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

	$result = mysql_query("SELECT * FROM `AntiRobot` WHERE `ip`='$ip'",$con);
	
	$row = mysql_fetch_array($result);
	if($row)
		if($row['isBanned']==1){
			die($ip." was Banned due to suspicious behavior on ".date(time()).",<br>");	
		}
}
function antiRobotError($con){
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

	
	$result = mysql_query("SELECT * FROM `AntiRobot` WHERE `ip`='$ip'",$con);
	
	$row = mysql_fetch_array($result);
	
	if($row){
		$time = time();
		$interval = $time-$row['date'];
		mysql_query("UPDATE `AntiRobot` SET `errorCount` = `errorCount` + 1 WHERE `ip`='$ip'",$con);
		if($interval>ANTIBOT_TEST_INTERVAL){
			mysql_query("UPDATE `AntiRobot` SET `errorCount` = 1 , `date`= '$time' WHERE `ip`='$ip'",$con);
			
		}else{
			$errorPerInterval = $row['errorCount']+1;
			if($errorPerInterval>ANTIBOT_MAX_ERROR_COUNT){
				mysql_query("UPDATE `AntiRobot` SET `isBanned`=1 WHERE `ip`='$ip' ");
				die($ip." was Banned due to suspicious behavior at ".time().",<br>");	
			}
		}
	}else{
		$time = time();
		mysql_query("
		INSERT INTO `AntiRobot`(
		`ip`,
		`errorCount`,
		`date`,
		`isBanned`		
		)
		VALUES(
		'$ip',1,'$time',0
		);
		",$con) or die(mysql_error($con));
	}
	
	
	
}
/*
 * XSS filter 
 *
 * This was built from numerous sources
 * (thanks all, sorry I didn't track to credit you)
 * 
 * It was tested against *most* exploits here: http://ha.ckers.org/xss.html
 * WARNING: Some weren't tested!!!
 * Those include the Actionscript and SSI samples, or any newer than Jan 2011
 *
 *
 * TO-DO: compare to SymphonyCMS filter:
 * https://github.com/symphonycms/xssfilter/blob/master/extension.driver.php
 * (Symphony's is probably faster than my hack)
 */
 
function xss_clean($data)
{
        // Fix &entity\n;
        $data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
        $data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
        $data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
        $data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');
 
        // Remove any attribute starting with "on" or xmlns
        $data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);
 
        // Remove javascript: and vbscript: protocols
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
        $data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);
 
        // Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
        $data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);
 
        // Remove namespaced elements (we do not need them)
        $data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);
 
        do
        {
                // Remove really unwanted tags
                $old_data = $data;
                $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
        }
        while ($old_data !== $data);
 
        // we are done... but I added a SQL injection filter here.
        return mysql_real_escape_string($data);
}
?>

