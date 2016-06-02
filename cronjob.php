<?php
/*
******************************************************
* This is the server side php script for PassLok Lock*
* Daily old lock checker                             *
* This page runs everyday                            *
******************************************************
* The first part is server configuration             *
* ****************************************************
* Version:0.5                                       *
* Author: Weicheng Huang ----whuang23@hawk.iit.edu   *
* edits by F. Ruiz									 *
*                                                    *
* If this pages violates any of terms and agreements *
* Sorry!                                             *
* Please let me know and I will fix it ASAP!         *
******************************************************
*/



//******uncomment this two row for debug mode******
//error_reporting(E_ALL|E_STRICT);
//ini_set('display_errors', 'on');

//****************************Server Conf************
	//uncomment the following define to turn on the server
	
	define("STATUS","ON");
	
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
	//stop execution if the server is closed	
	if(!defined("STATUS")){
		die("<h3>Currently Shut Down:  </h3><p>Sorry! The Server is under construction.</p>");	
	}
	
	//connect to mySQL database
	$con = mysqli_connect($db_addr,$db_uName,$db_passwd,$db_name);
//	mysqli_select_db($db_name,$con);


/* Unities*/
	mysqli_query($con,"DELETE FROM `AntiRobot`");//refresh the antiRobot list and unlock the ip
	
/* send email */

	
	//$log = fopen("log.txt","a+");
	
	$resultDelete=mysqli_query($con,"SELECT * FROM `Lock` WHERE `date`< now() - interval 187 day ");
	echo "<br>";
	echo "<h3>Old lock deleted!</h3>";
	while($row = mysqli_fetch_array($resultDelete)){
		$email = $row['email'];
		$lock = $row['lock'];
		$url = $row['url'];
		sendEmailOld($email,$url,$lock,"remove");
		mysqli_query($con,"DELETE FROM `Lock` WHERE `email`='$email'") or die(mysqli_error($con));
		echo $email." has been deleted!<br>";
	}
	
	$resultEmail=mysqli_query($con,"SELECT * FROM `Lock` WHERE `date`< now() - interval 180 day AND `is_activite` = 1");// 6 month
	echo "<br>";
	echo "<h3>Old lock needs to be confirmed</h3>";
	while($row = mysqli_fetch_array($resultEmail)){
		$email = $row['email'];
		$lock = $row['lock'];
		if($row['need_confirm']==0){
			$url = md5($email."=>".time());
			$url="REF-".$url;
			mysqli_query($con,"UPDATE `Lock` SET `url`='$url', `future_lock` = `lock`, `need_confirm`=1, `confirm_date`=CURRENT_TIMESTAMP WHERE `email`='$email'") or die(mysqli_error($con));
		}else{
			$url = $row['url'];
			echo $email." old url!<br>";
		}
		sendEmailOld($email,$url,$lock,"refresh");
		echo $email." needs to be refreshed! Email sent!<br>";	
	}
	$resultCancelChange=mysqli_query($con,"SELECT * FROM `Lock` WHERE `confirm_date` < now() - interval 10 day and `need_confirm` = 1");//cancel the update or register
	echo "<br>";
	echo "<h3>Lock will not change</h3>";
	while($row = mysqli_fetch_array($resultCancelChange)){
		$email = $row['email'];
		$lock = $row['lock'];
		$url = $row['url'];
		if($row['is_activite']==0){
			mysqli_query($con,"DELETE FROM `Lock` WHERE `email`='$email'");
		}else{
			mysqli_query($con,"UPDATE `Lock` SET `need_confirm` = 0 WHERE `email`='$email'");
		}
		sendEmailOld($email,$url,$lock,"cancelChange");
		echo $email." needs to be confirmed!</br>";
	}
	$resultNew=mysqli_query($con,"SELECT * FROM `Lock` WHERE `confirm_date` < now() - interval 2 day and `need_confirm` = 1");//confirm date before two days ago
	echo "<br>";
	echo "<h3>Lock needs to be confirmed</h3>";
	while($row = mysqli_fetch_array($resultNew)){
		$email = $row['email'];
		$lock = $row['lock'];
		$url = $row['url'];
		sendEmailOld($email,$url,$lock,"need_confirm");
		echo $email." needs to be confirmed!</br>";
	}
	
	
	echo "done! in ".time()."\n";


//function
function sendEmailOld($email,$url,$lock,$type){
	//echo "<hr>";
	$to = $email;
	
	// subject
	$subject = 'PassLok directory email confirmation';
	
	// message
	switch($type){
		case "refresh":
			$message = '
			<html>
			<head>
			<h5>Dear User:</h5>
			<p>For security reasons, we have to confirm periodically that your address is still in use.</p>
			<p>The following email is due to be confirmed: '.$email.' in the general PassLok directory. The associated Lock is:</p><br />
			<p>'.$lock.'</p>
			<br />
			<p>Please click <a href="http://passlok.com/lockdir/lockdir.php?link='.$url.'">this link</a> to <strong>CONFIRM</strong> your lock.</p><br />
			<p>If no confirmation is received, your Lock will be automatically deleted after one week.</p>
			</body>
			</html>
			';
			break;
		case "need_confirm":
			$message = '
			<html>
			<head>
			<h5>Dear User:</h5>
			<p>We have not yet received a confirmation for changes in your PassLok directory entry.</p>
			<p>The following has been submitted as the entry for '.$email.' in the general PassLok directory:</p><br />
			<p>'.$lock.'</p><br />
			<p>We need to confirm this email address so that no one else can register or update a Lock for it.</p>
			<p>Please click <a href="http://passlok.com/lockdir/lockdir.php?link='.$url.'">this link</a> to confirm your identity.</p><br />
			<p>If you did not expect this email, it is possible that someone else is trying to alter the posting of your Lock. Proceed with caution.</p><br />
			<p>Thank you, and enjoy PassLok.</p>
			<p>
			</body>
			</html>
			';
			break;
		case "remove":
			$message = '
			<html>
			<head>
			<h5>Dear User:</h5>
			
			<p>Your email and Lock have been removed from the general PassLok directory.</p><br />
			<p>Email:'.$email.'</p><br />
			<p>Lock:'.$lock.'</p>
			<br />
			
			</body>
			</html>
			';
			break;
		case "cancelChange":
			$message = '
			<html>
			<head>
			<h5>Dear User:</h5>
			
			<p>Since we have not received confirmation for changes in the general PassLok directory entry for your email address, we have canceled the requested changes. These were the proposed changes:</p><br />
			<p>Email:'.$email.'</p><br />
			<p>Lock:'.$lock.'</p><br />
			<p>If you did not expect this email, it is possible that someone else is trying to alter the posting of your Lock. Proceed with caution.</p>
			<br />
			
			</body>
			</html>
			';
			break;
		default:
			break;
	}
	// To send HTML mail, the Content-type header must be set
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
	$headers .= 'From: PassLok privacy<noreply@passlok.com>' . "\r\n";
	
	// Additional headers
	
	mail($to, $subject, $message, $headers) or die("Can not send email");
	//echo $message;
	//echo "<hr>";		
}
?>