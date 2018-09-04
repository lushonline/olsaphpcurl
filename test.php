<?php
require_once('config.php');
require_once('CURLStream.class.php');
require_once('WSSUserNameTokenSoapClient.class.php');
require_once('CURLWSSUserNameTokenSoapClient.class.php');

function getskillport8profilesoap($id, $value) {
  $txt = sprintf('<ns1:fieldValue id="%s"><ns1:value>%s</ns1:value></ns1:fieldValue>',$id,$value);
  return new SoapVar($txt,XSD_ANYXML);
}

function CURLSkillport8ExtendedSSO($username,$groupcode="skillsoft",$action="home",$assetid=null,$ctx=null) {
	global $CFG;

	//Specify the WSDL using the EndPoint
	$endpoint = $CFG->endpoint;

	//Specify the SOAP Client Options
	$options = array(
		"context" => $ctx,
		"trace"      => 1,
		"exceptions" => 1,
		"soap_version"   => SOAP_1_2,
		"cache_wsdl" => WSDL_CACHE_BOTH,
		"encoding"=> "UTF-8",
		"location"=> $endpoint,
		//"wsse_username"=>$CFG->customerid,
		//"wsse_password"=>$CFG->sharedsecret
		);

	 //Create a new instance of the OLSA Soap Client
	try {
		$client = new CURLWSSUserNameTokenSoapClient($endpoint."?WSDL",$options);
	}
	catch (Exception $ex)
	{
		//We have an exception creating the SOAP Client
		//This could be things like networking issues, badly typed url etc
		throw $ex;
	}

	//Create the USERNAMETOKEN
	$client->__setUsernameToken($CFG->customerid,$CFG->sharedsecret);

	//Create the basic GetMultiActionSignOnUrlRequest
	//Format is Case Sensitive and is:
	//	parameter => value
	$GetMultiActionSignOnUrlExtendedRequest =  array(
		"customerId" => $CFG->customerid
	);

	//Add additional elements
	//The Unique Username
	//Here we are using the PHP Session Variable set by the simulated login page
	//
	$GetMultiActionSignOnUrlExtendedRequest['username'] = $username;

    //Set the SkillPort password to welcome. This only affects
    //new account creation. If a user has already been created
    //and chosen a new password in SkillPort this will not
    //overwrite the user selected password.
    $GetMultiActionSignOnUrlExtendedRequest['password'] = 'welcome';

    //We ensure the user is created/assigned to just the Skillsoft group
    //This value is the ORGCODE specified for the group in SkillPort
    //This value overrides ALL existing group membership details for the
    //specified user

    //NOTE: See exception handling below for notes on how this can be used
    //in a production environment by NOT sending this unless the user
    //is a new user. That way existing users in SkillPort with existing
    //group memberships are not affected.
    $GetMultiActionSignOnUrlExtendedRequest['groupCode'] = $groupcode;

    //The actionType defines what type of URL to generate:
    //These following actions take the user into SkillPort UI, but to
    //particular page the user is 'free' to navigate away from the page.
    // catalog = SkillPort catalogue page
    // myplan = SkillPort MyPlan page
    // home = SkillPort Home page
    // summary = Course Summary Page for selected course
    //These actions limit the user to JUST the specified assetId.
    // launch = Opens courses
	$GetMultiActionSignOnUrlExtendedRequest['actionType'] = $action;

    //assetId is needed for launch and summary actions and is the
    //coursecode on SkillPort.
    //If the user does not have access to the course an error
    //will be thrown.
	$GetMultiActionSignOnUrlExtendedRequest['assetId'] = $assetid;

	//OPTIONAL
	//This determines whether the user should be active
	//
	//$GetMultiActionSignOnUrlExtendedRequest['active'] = true;

	//OPTIONAL
	//This fields controls the user role. Valid values are:
	//	Admin
	//	Manager
	//	End-User
	//If unspecified it defaults to End-User
	//$GetMultiActionSignOnUrlExtendedRequest['authType'] = "";

	//OPTIONAL
	//This specifies the users choice of Language for the SkillPort UI
	//Valid values are:
	//en_US - US English
	//en_GB - UK English
	//fr - French
	//es - Spanish
	//it - Italian
	//de - German
	//ja - Japanese
	//pl - Polish
	//ru - Russian
	//zh - Chinese Mandarin
	//If no language is supplied, the users language is set to the language
	//configured for the SkillPort site.
	//$GetMultiActionSignOnUrlExtendedRequest['siteLanguage'] = "";

	//OPTIONAL
	//This specifies the SkillPort UserName of this users manager
	//The manager username must already exist in SkillPort
	//$GetMultiActionSignOnUrlExtendedRequest['manager'] = "";

    //OPTIONAL
    //This determines whether the user has chosen that they require
    //Section508 compliant UI and courses
	//$GetMultiActionSignOnUrlExtendedRequest['enable508'] = false;

	//OPTIONAL
	//The additional custom profile fields in Skillport 8 are defined as key/value pairs and sent as an array
	//In the request
	/*
	 *  <!--Optional:-->
         <olsa:profileFieldValues>
            <!--Zero or more repetitions:-->
            <olsa:fieldValue id="?">
               <!--Zero or more repetitions:-->
               <olsa:value>?</olsa:value>
            </olsa:fieldValue>
         </olsa:profileFieldValues>
	 */
//	$profilefields = array();
//	$profilefields[] = getskillport8profilesoap('skillportprofilefield','valuetouse');
//	$profilefields[] = getskillport8profilesoap('skillportprofilefield2','valuetouse2');
//	
//	$GetMultiActionSignOnUrlExtendedRequest['profileFieldValues'] = $profilefields;
	 
	//Call the WebService and stored result in $result
	try {
		$result=$client->__soapCall('SO_GetMultiActionSignOnUrlExtended',array('parameters'=>$GetMultiActionSignOnUrlExtendedRequest));
	}
	catch (SoapFault $fault)
	{
		//These capture exceptions from the SOAP response
		 if (!stripos($fault->getmessage(), "the security token could not be authenticated or authorized") == false)
		{
			//The OLSA Credentials specified could not be authenticated
			//Check the values in the web.config are correct for OLSA.CustomerID and OLSA.SharedSecret - these are case sensitive
			//Check the time on the machine, the SOAP message is valid for 5 minutes. This means that if the time on the calling machine
			//is to slow OR to fast then the SOAP message will be invalid.
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "the property '_pathid_' or '_orgcode_' must be specified") == false)
		{
			//Captures if the USER does not exist and we have NOT SENT the _req.groupCode value.
			//This is a good methodology when the SSO process will not be aware of all groups a
			//user belongs to. This way capturing this exception means that we only need to send
			//an orgcode when we know we have to create the user.
			//This avoids the issue of overwriting existing group membership for user already in
			//SkillPort.
			//You would capture this exception and resubmit the request now including the "default"
			//orgcode.
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "invalid new username") == false)
		{
			//The username specified is not valid
			//Supported Characters: abcdefghijklmnopqrstuvwxyz0123456789@$_.~'-
			//Cannot start with apostrophe (') or dash (-)
			//Non-breaking white spaces (space, tab, new line) are not allowed in login names
			//No double-byte characters are allowed (e.g. Japanese or Chinese characters)
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "invalid password") == false)
		{
			//The password specified is not valid
			//All single-byte characters are allowed except back slash (\)
			//Non-breaking white spaces (space, tab, new line) are not allowed
			//No double-byte characters are allowed (e.g. Japanese or Chinese characters)
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "enter a valid email address") == false)
		{
			//The email address specified is not a valid SMTP email address
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "error: org code") == false)
		{
			//The single orgcode specified in the _req.groupCode is not valid
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "user group with orgcode") == false)
		{
			//One of the multiple orgcodes specified in the _req.groupCode is not valid
			throw $fault;
		}
		elseif (!stripos($fault->getmessage(), "field is too long") == false)
		{
			//One of the fields specified, see full faultstring for which, is too large
			//Generally text fields can be 255 characters in length
			throw $fault;
		}
		else
		{
			echo $fault->getmessage();
			//Any other SOAP exception not handled above
			throw $fault;
		}
    }
    if (isset($result->olsaURL)) {
	
		return $result->olsaURL;
    } else {
	
    	return null;
    }
}


function testCurlTLSConnection($ctx=null)
{
	global $CFG;
	
	print "\r\n"."++++++++++++++++++++++++++++++++++++++++"."\r\n";
	print "testCurlTLSConnection()"."\r\n";
	//Specify the WSDL using the EndPoint
	$wsdl = $CFG->endpoint."?WSDL";
	print "Attempting to download OLSA WSDL: ".$wsdl."\r\n";

	try {
		$content = file_get_contents($wsdl,false,$ctx);

		if ($content === false) {
			// Handle the error
			print "WSDL Download: Fail"."\r\n";
			print "\r\n"."----------------------------------------"."\r\n";
			return false;
		} else {
			print "WSDL Download: Success"."\r\n";
			print "\r\n"."----------------------------------------"."\r\n";
			//print_r($content);
			return true;
		}
	} catch (Exception $e) {
		// Handle exception
			print "An Exception Occured:".$e."\r\n";
			print "\r\n"."----------------------------------------"."\r\n";
			return false;
	}
}

function getCurlInfo()
{
	print "\r\n"."++++++++++++++++++++++++++++++++++++++++"."\r\n";
	print "getCurlInfo()"."\r\n";
	/* Get version information */
	$verinfo_php = phpversion();
	$verinfo_curl = curl_version();

	/* Show version details */
	print "FOR TLS 1.2 Support you need:"."\r\n";
	print "cURL version (minimum should be 7.34.0): " . $verinfo_curl['version'] . "\r\n";
	print "cURL OpenSSL version (minimum should be 1.0.1): " . $verinfo_curl['ssl_version'] . "\r\n";
	print "PHP version (minimum should be 5.5.19): " . $verinfo_php . "\r\n";
	print "\r\n"."----------------------------------------"."\r\n";
	if ((version_compare($verinfo_php, '5.5.19') >= 0) &&
		(version_compare( $verinfo_curl['version'], '7.34.0') >= 0)) {
			return true;
		} else {
			return false;
		}
}

function callOlsaSSO($username,$groupcode="skillsoft",$action="home",$assetid=null,$ctx=null)
{
	print "\r\n"."++++++++++++++++++++++++++++++++++++++++"."\r\n";
	print "callOlsaSSO()"."\r\n";

	print "Username: ".$username."\r\n";
	print "Group: ".$groupcode."\r\n";
	print "Action: ".$action."\r\n";
	print "Assetid: ".$assetid."\r\n";
	
	$result = CURLSkillport8ExtendedSSO($username, $groupcode, $action, $assetid,$ctx);
	
	if (isset($result)) {
		 print "OLSA URL: ".$result."\r\n";
    } else {
    	print "Invalid URL from OLSA"."\r\n";
    }
	print "\r\n"."----------------------------------------"."\r\n";
}

$proxy = null;
//$proxy = new stdclass();
//$proxy->proxyhost = '192.168.168.109';
//$proxy->proxyport = '8888';
//$proxy->proxyuser = '';
//$proxy->proxypassword = '';

print "\r\n"."++++++++++++++++++++++++++++++++++++++++"."\r\n";
print "Checking Proxy Support for cURL"."\r\n";
if (isset($proxy)) {
	print 'Proxy defined for cURL:'."\r\n";;
	print_r($proxy);
} else {
	print 'Proxy not defined for cURL.'."\r\n";;
}
print "\r\n"."----------------------------------------"."\r\n";

$opts = array( 
	  'CURLStream' =>array( 
		'proxy' => $proxy,
	  ),
	  'CURLWSSUserNameTokenSoapClient' =>array( 
		'proxy' => $proxy,
	  )
	);

$context = stream_context_create($opts);

$existinghttps = false;

print "\r\n"."++++++++++++++++++++++++++++++++++++++++"."\r\n";
print "Checking Support for HTTPS Stream Wrapper"."\r\n";
// we unregister the current HTTPS wrapper
if (in_array('https', stream_get_wrappers())) {
	$existinghttps = true;
    print 'https:// Stream support enabled in PHP, usually indicates php_openssl extension enabled.'."\r\n";;
	print 'Replacing stream_wrapper for https:// using CURLStream class'."\r\n";;
	stream_wrapper_unregister('https');
	stream_wrapper_register('https', 'CURLStream') or die("Failed to register protocol");
} else {
    print 'https:// support not enabled in PHP, usually indicates php_openssl extension not enabled.'."\r\n";;
	print 'Enabling stream_wrapper for https:// using CURLStream class'."\r\n";;
	stream_wrapper_register('https', 'CURLStream') or die("Failed to register protocol");
}
print "\r\n"."----------------------------------------"."\r\n";

if (getCurlInfo()) {
	if (testCurlTLSConnection($context)) {
		callOlsaSSO("olsatest", "skillsoft", "home", "", $context);
	} else {
		print "Could not connect to TLS v1.2 OLSA server"."\r\n";
	}
}
if ($existinghttps) {
	print 'Reestoring default stream_wrapper for https://'."\r\n";;
	stream_wrapper_restore('https');
}

?>