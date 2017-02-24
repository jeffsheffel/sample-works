<?php
/*
 * class.CalixManagementSystem.php
 *
 * This class follows a facade OOP pattern, to wrap original SOAP calls (which are included early on).
 *
 * Ideally, most of the Get methods should return an (array) object that contains the CMS (OLT) data in a well-formed
 * associative array.  Then, if the client (ie. method caller) needs access to the raw SOAP object, a call to
 * getRawSoapResponse() can be made.
 *
 * NOTES:
 * 1) There could be some confusion with this class, as it is named CalixManagementSystem (CMS), but the methods
 *	apply to an OLT.  For example, the constructor requires an OLT IP address, which is then used to lookup the
 *	OLT's associated CMS, to which all of the SOAP calls are sent. UPDATE: There now is an OltCalixE7 class.
 *
 */
require_once dirname(__FILE__).'/../../include/config.php';
require_once 'class.DeviceManagementSystem.php';
require_once 'class.DatabaseVader.php';
require_once 'lib-snmp.php';
require_once 'lib-debug.php';

// Pull in the common functions file, dependent on Calix device series: C7 or E7
include_once($directoryCalix . '/ESeries/onts/common-functions.php');

// Discover ONT ports and quantity, in order to poll each individual port
include_once($directoryCalix . '/ESeries/onts/discover-ont-ports.php');

class CalixManagementSystem extends DeviceManagementSystem {

private $soapPath;
private $soapUrl;
private $soapClient;
private $soapSessionId;
private $oltServiceType = "EthSvc";  // Used for getOltServices() method
private $aid = "ONT-1-1-1-1-1";	// Default if client doesn't set: shelf, card, ponPort, ONT [,ontPort]
public $shelf = "1";		// Default if client doesn't set
public $card = "1";			// Default if client doesn't set
public $ponPort = "1";		// Default if client doesn't set
public $ontNumber = "1";	// Default if client doesn't set
public $ontPort = "1";	// Default port number of a specific service (POTS, GE, video, DS1)
public $ontId = "1111";		// Default if client doesn't set; a Calix identifier, a concatenation of: shelf, card, ponPort, ONT
public $oltTid;
public $timeoutConnection = 15;
public $timeoutResponse = 15;
public $ontArray = NULL;

/*
 * function __construct( $ipAddressCms, $ipAddressOlt )
 *
 * This object constructor:
 *	1) optionally takes one of:
 *		A) the IP address of the CMS
 *			- the calling client must subsequently specify an OLT TID (for method SOAP calls)
 *		B) the IP address of and OLT
 *			- then a Vader database CMS IP address lookup is done, and the associated OLT TID is set
 *	2) sets object properties
 *	3) creates a (nusoap) SOAP client
 *
 * See NOTE 1 in top comment header (regarding CMS versus OLT confusion).
 * TODO: Add ping and SNMP checks to object constructor
 */
function __construct( $ipAddressCms = NULL, $ipAddressOlt = NULL ) {
global $hostnamePrmdata, $usernamePrmdata, $passwordPrmdata, $databasePrmdata;
global $flagDebug, $directorySoap;

	$this->username = "vader";		// All CMS have pre-configured Vader administrative login account
	$this->password	= "skywalk3";
	initializeSnmp();
	$this->communityString = getSnmpCommunityString(SNMP_DEVICE_CALIX);

	if( ! isset( $ipAddressCms ) ) {
		// Get CMS host IP address from the Vader database
		try {
			$database = DatabaseVader::getInstance();
			$oltDatabaseAttributes = $database->getOltArray( $ipAddressOlt );
		}
		catch( Exception $e ) {
			// TODO: Respond with Vader error response
			// TODO: Send admin notification of Vader parse failure error
			switch( $e->getCode() ) {
				case DATABASE_ERROR_CONNECT_ERROR:
				case DATABASE_ERROR_SELECT_DB_ERROR:
				case DATABASE_ERROR_QUERY_ERROR:
				case DATABASE_ERROR_REFERENTIAL_INTEGRITY:
				default:
					print $e->getMessage();
					break;
			}
			exit;
		}

		if( ! $oltDatabaseAttributes ) {
			// Return error: no such OLT (IP address)
			throw new Exception( "ERROR: No such OLT IP address in the Vader database" );
		}

		if( $flagDebug ) { print "<p>DEBUG: TIMER in class.CalixManagementSystem.php: Obtained OLT attributes from database: ".get_elapsed_debug_time()."</p>\n"; }

		if( $oltDatabaseAttributes['vendor'] != DEVICE_VENDOR_STRING_CALIX ) {
			throw new Exception( "OLT vendor is not Calix" );
		}

		$this->ipAddress = $oltDatabaseAttributes['ipAddressEms'];
		$this->oltTid = $oltDatabaseAttributes['tid'];	// Simply use database value
	} else {
		// Otherwise use passed in CMS IP address; client must also specify an OLT TID for method's SOAP calls
		$this->ipAddress = $ipAddressCms;
	}

	// Ping device for basic connectivity test

	// Query device type (via SNMP), for subsequent comparison and validation against the configuration in the database

	// Query device system name (via SNMP), for subsequent comparison and validation against the configuration in the database

	// Set up (CTL) CMS (SOAP) communication parameters
	$this->port = "18080";
	$this->soapPath = "/cmsexc/ex/netconf";
	$this->soapUrl = "http://$this->ipAddress:$this->port/$this->soapPath";

	if( $flagDebug ) { print "<p>DEBUG: TIMER in class.CalixManagementSystem.php: Starting nusoap client creation: ".get_elapsed_debug_time()."</p>\n"; }

	require_once($directorySoap . '/lib/nusoap.php');
	try {
		$this->soapClient = new nusoap_client( $this->soapUrl );
		$this->soapClient->soap_defencoding = 'utf-8';
		$this->soapClient->useHTTPPersistentConnection();	// Uses http 1.1 instead of 1.0
	}
	catch( Exception $e ) {
		throw new Exception( "SOAP client creation failed: " . $e->getMessage() );
	}
	//$soapaction = "https://service.somesite.com/GetFoods";	// shouldn't need $soapaction
	$this->soapClient->operation = '';	// Suppress PHP Notice (nusoap_parser constructor bug): Undefined property: nusoap_client::$operation

	if( $flagDebug ) { print "<p>DEBUG: TIMER in class.CalixManagementSystem.php: Finished nusoap client creation: ".get_elapsed_debug_time()."</p>\n"; }
}

/*
 * Parse an AID and set object properties.  Object defaults are used if parsing is incomplete.
 *
 * TODO: Throw exceptions on AID parse problems
 */
function setAid ($aid) {
	$aid = str_replace('ont-', '', strtolower($aid));	// Remove optional leading "ONT-" or "ont-"
	$aidArray = preg_split('/-/', $aid, NULL);	// NULL means unlimited split
	//list($shelf, $card, $ponPort, $ontNumber, $ontPort) = preg_split('/-/', $aid, NULL);	// Wrong! Undefined offset if $aid is short, ugg!
	if( isset($aidArray[0]) ) { $this->shelf = $aidArray[0]; }
	if( isset($aidArray[1]) ) { $this->card = $aidArray[1]; }
	if( isset($aidArray[2]) ) { $this->ponPort = $aidArray[2]; }
	if( isset($aidArray[3]) ) { $this->ontNumber = $aidArray[3]; }
	if( isset($aidArray[4]) ) { $this->ontPort = $aidArray[4]; }
	$this->ontId = $this->shelf.$this->card.$this->ponPort.$this->ontNumber;
}

/*
 * function login()
 *
 * Logs in to the (object's) CMS using SOAP.
 */
function login() {
	global $flagDebug;
	$response = $this->soapClient->send(login($this->username, $this->password), '', $this->timeoutConnection, $this->timeoutResponse);   //  This produces an array with the xml response elements in it
	$this->validateSoapResponse( $response );
	$loginResultCode = $response['ResultCode'];
	$this->soapSessionId = $response['SessionId'];
	if( $flagDebug ) {
		print "<p>DEBUG: TIMER in class.CalixManagementSystem.php: Logged in to soap server: ".get_elapsed_debug_time()."</p>\n";
		echo $this->getFormattedSoapDebugOutput( "login", $response );
	}

	if ($loginResultCode != '0') {
		// TODO: Notify admin of failed CMS login
		// Failed login attempt; look for error message and print
		$login_error_message = isset($response['ResultMessage']) ? $response['ResultMessage'] : "Unknown";
		throw new Exception( "ERROR: Failed CMS login to: " . $this->ipAddressCms . " with code: " . $loginResultCode . " and message: " . $login_error_message );
	}
}

/*
 * function logout()
 *
 * Logs out of the (object's) CMS using SOAP.  The object properties, like $this->soapClient are kept, so at least the response
 * is still accessible (for debugging).
 *
 * TODO: CMS object logout() method does not currently throw an exception
 */
function logout() {
	global $flagDebug;
	$response = $this->soapClient->send( logout($this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	if( $flagDebug ) {
		print "<p>DEBUG: TIMER in class.CalixManagementSystem.php: Logged out of soap server: ".get_elapsed_debug_time()."</p>\n";
		echo $this->getFormattedSoapDebugOutput( "logout", $response );
	}
	return $response;
}

/*
 * function validateSoapResponse()
 *
 * When not (previously) logged in to a CMS and making a SOAP call, the response is like:
 *	array(3) {
 *		["rpc-error"]=> array(4)
 *			{ ["error-type"]=> string(11) "application"
 *			["error-tag"]=> string(16) "operation failed"
 *			["error-severity"]=> string(5) "error"
 *			["error-message"]=> string(39) "session not exist, SESSION ID REQUIRED." }
 *		["!message-id"]=> string(4) "5912"
 *		["!nodename"]=> string(25) "NTWK-EUGNORBMOL101010206A" }
 */
function validateSoapResponse( $response = NULL ) {
	if( ! isset( $response ) )
		throw new Exception( "SOAP response is NULL" );
	if( isset( $response['rpc-error'] ) )
		throw new Exception( "SOAP error: " . $response['rpc-error']['error-message'] );

	// TODO: Add SOAP response content validations
	return TRUE;
}

/*
 * function getRawSoapRequest()
 * Returns a (SOAP) string, not a PHP array (untested!).
 */
function getRawSoapRequest() {
	return $this->soapClient->request;
}

/*
 * function getRawSoapResponse()
 * Returns a (SOAP) string, not a PHP array.
 */
function getRawSoapResponse() {
	return $this->soapClient->response;	// A (SOAP) string, not a PHP array
}

function makeCustomSoapRequest ( $rawSoapRequest = NULL ) {
	/*
	 * Substitute the login SOAP session ID into user's custom SOAP request
	 *	<soapenv:Body>
	 *	<rpc message-id="9553" nodename="NTWK-LTTNCOMLH0103CAB01A" timeout="35000" username="vader" sessionid="580">
	 * NOTE: message-id is a random number?  Just use whatever the user input.
	 */
	$rawSoapRequest = preg_replace( '/sessionid=\"\d*\"/', "sessionid=\"".$this->soapSessionId."\"", $rawSoapRequest, 1 );
	$response = $this->soapClient->send( $rawSoapRequest );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getFormattedSoapDebugOutput($headerText, $soapResponseArray, $asHtml)
 *
 * NOTES:
 *	- This method only returns HTML, while the $asHtml flag controls the formatting of the raw SOAP data.
 *	- It is expected that this method will be called after the method call that created the $soapResponseArray,
 *		as the associated raw SOAP request/response is obtained from the (CMS) object (and hence only pertains
 *		to the most recent SOAP call, ie. the call that created the $soapResponseArray).
 */
function getFormattedSoapDebugOutput( $headerText = "SOAP Debug", $soapResponseArray = NULL, $asHtml = FALSE ) {
	$debugOutput = "<h2>$headerText Request</h2>\n<pre>\n";
	$debugOutput .= $asHtml ? htmlspecialchars($this->getRawSoapRequest(), ENT_QUOTES) : $this->getRawSoapRequest();
	$debugOutput .= "\n</pre>\n";
	$debugOutput .= "<h2>$headerText Response</h2>\n<pre>\n";
	$debugOutput .= $asHtml ? htmlspecialchars($this->getRawSoapResponse(), ENT_QUOTES) : $this->getRawSoapResponse();
	$debugOutput .= "\n</pre>\n";
	// print "<h2>Debug</h2>\n<pre>\n" . htmlspecialchars($this->soapClient->getDebug(), ENT_QUOTES) . "\n</pre>\n";
	$debugOutput .= "<h2>$headerText Response PHP Array</h2>\n<pre>\n";
	$debugOutput .= sprintf( "%s", print_r($soapResponseArray, TRUE) );
	$debugOutput .= "\n</pre>\n\n";
	return $debugOutput;
}

/*
 * common-functions.php
 * 	$ontid = $shelf . $card . $ponPort . $ont;
 */
function getOltBasicsRaw( $oltTid = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	$response = $this->soapClient->send( rtrv_E7Chassis( $oltTid, $this->username, $this->soapSessionId ) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOltBasics( $oltTid )
 *
 * The following additional PON port parameters are available:
 *	[type] => System *	[id] => *	[auto-upgr] => true *	[cli-telnet] => true *	[gui-http] => true *	[passwd-expiry] => 30
 *	[pri-dns-srv] => 0.0.0.0 *	[sec-dns-srv] => 0.0.0.0 *	[startup-run] => true *	[first-res-vlan] => 1002 *	[bar-trans] => ftp-active
 *	[upgr-trans] => ftp-active *	[bar-port] => 21 *	[upgr-port] => 21 *	[user-auth-order] => local *	[mvr-enabled] => true
 *	[conc-upgr-limit] => 2 *	[card-reset-seq] => one-at-a-time *	[modular-chassis] => true
 */
function getOltBasics( $oltTid = NULL ) {
	$response = $this->getOltBasicsRaw( $oltTid );
	$result['tid'] = setOrNullify( $response['data']['top']['object']['sys-id'] );	// LTTNCOMLH0113CAB01A
	$result['location'] = setOrNullify( $response['data']['top']['object']['sys-loc'] );
	$result['administrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );	// enabled
	$result['ipGateway'] = setOrNullify( $response['data']['top']['object']['ip-gw'] );
	$result['hasOntDownstreamShapingFlag'] = setOrNullify( $response['data']['top']['object']['ont-dwnstrm-shaping'] );
	$result['timezone'] = setOrNullify( $response['data']['top']['object']['timezone'] );	// US/Mountain
	return $result;
}

function getOltStatsRaw( $oltTid = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	$response = $this->soapClient->send( rtrv_E7Chassis2( $oltTid, $this->username, $this->soapSessionId ) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOltStats( $oltTid )
 *
 *	[type] => System *	[id] => *	[op-stat] => enable *	[crit] => 0 *	[maj] => 0 *	[min] => 0 *	[warn] => 0 *	[info] => 0
 *	[derived-states] => child-prov *	[create-time] => 1314116285 *	[create-time-nsec] => 47835000 *	[num-res-vlan] => 4
 *	[shelf-type] => e7-2slotchassis-1ru *	[current-time] => 1378395555 *	[current-time-str] => Thu Sep  5 09:39:15 2013
 *	[uptime] => 149885 *	[active-card] => Array *	[standby-card] => Array *	[update-time] => 1378339336 *	[master-shelf] => 1
 */
function getOltStats( $oltTid = NULL ) {
	$response = $this->getOltStatsRaw( $oltTid );
	$result['uptime'] = setOrNullify( $response['data']['top']['object']['uptime'] );
	return $result;
}

/*
 * function getOltMulticastProfiles()
 *
 * This method returns an associative array (and not a raw SOAP response).
 *
 * NOTES:
 * ) The SOAP request only returns the "name" and MVR profile attributes, yet additional attributes can be requested.
 *	Refer to the Vader Coding Details document.
 */
function getOltMulticastProfiles( $oltTid = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	$response = $this->soapClient->send( getOltMulticastProfilesSoapRequestString( $oltTid, $this->username, $this->soapSessionId ) );
	$this->validateSoapResponse( $response );

	$profileArray = array();
	if( isset($response['data']['top']['object']['children']['child']['type']) ) {
		// Case when there is a single child; nusoap omits an array level, ie. not $response->[0]->['data'], but $response->['data']
		$childrenArray[0] = $response['data']['top']['object']['children']['child'];
	} else {
		// Case when there is are multiple children; nusoap loads an array, ie. $response->[n]->['data']
		$childrenArray = isset($response['data']['top']['object']['children']['child']) ? $response['data']['top']['object']['children']['child'] : array();
	}
	// TODO: Add error notification when multicast profiles are not returned
	foreach( $childrenArray as $child ) {
		if( !empty( $child['type'] ) && $child['type'] == 'McastProf' ) {
			$profileIndex = $child['id']['mcastprof']['!'];
			$profileArray[$profileIndex]['profileName'] = $child['name'];
			$profileArray[$profileIndex]['mvrProfileIndex'] = NULL;
			$profileArray[$profileIndex]['mvrProfileName'] = NULL;
			if( !empty( $child['mvr-prof'] ) && $child['mvr-prof']['type'] == 'MvrProf' && !empty( $child['mvr-prof']['id']['mvrprof']['!'] ) ) {
				$profileArray[$profileIndex]['mvrProfileIndex'] = $child['mvr-prof']['id']['mvrprof']['!'];
				$profileArray[$profileIndex]['mvrProfileName'] = $child['mvr-prof']['id']['mvrprof']['!name'];
			}
		}
	}
	return $profileArray;
}

/*
 * function getOltMvrProfile( $oltTid, $mvrProfileIndex )
 *
 * This method returns an associative array (and not a raw SOAP response).
 *
 */
function getOltMvrProfile( $oltTid = NULL, $mvrProfileIndex ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	$response = $this->soapClient->send( getOltMvrProfileSoapRequestString( $oltTid, $this->username, $this->soapSessionId, $mvrProfileIndex ) );
	$this->validateSoapResponse( $response );

	$profile['mvrVlan'] = NULL;
	if( isset($response['data']['top']['object']['children']['child']['type']) && $response['data']['top']['object']['children']['child']['type'] == 'MvrVlan' ) {
		// Correct object returned
		if( !empty( $response['data']['top']['object']['children']['child']['id']['mvrvlan'] ) ) {
			$profile['mvrVlan'] = $response['data']['top']['object']['children']['child']['id']['mvrvlan'];
		}
	}
	// TODO: Add error notification when MVR profile is not returned
	return $profile;
}

/*
 * function getOltAlarms( $oltTid, $shelf, $card )
 *
 * This method ...
 *
 */
function getOltAlarmsRaw( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	// NOTE: $ontId is not used by the rtrv_ALARMS() function
	$ontId = "dummy_parameter_not_used";
	$response = $this->soapClient->send( rtrv_ALARMS( $oltTid, $shelf, $card, $ontId, $this->username, $this->soapSessionId ) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * getOltOnts( $oltTid )
 * This method makes a series of calls to an OLT chassis to build an array of all ONTs.
 *
 * This method returns an associative array (and not a raw SOAP response).
 * Refer to the test-getOltOnts-method.php script for sample usage.
 *
 * WARNING: With a fully loaded OLT, this method can take minutes (say 1 or 2) to return!
 */
function getOltOnts( $oltTid = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	$ontArray = array();
	$lastOnt = NULL;
	do {
		$response = $this->soapClient->send( getOltOntsSoapRequestString( $oltTid, $this->username, $this->soapSessionId, $lastOnt ) );
		$this->validateSoapResponse( $response );
		$ontId = NULL;	// In case SOAP fails
		if( isset($response['action-reply']['match']['get-config']) ) {
			// Case when there is a single child; nusoap omits an array level, ie. not $response->[0]->['data'], but $response->['data']
			$ontMatchArray[0] = $response['action-reply']['match'];
		} else {
			// Case when there is are multiple children; nusoap loads an array, ie. $response->[n]->['data']
			$ontMatchArray = isset($response['action-reply']['match']) ? $response['action-reply']['match'] : array();
		}
		foreach( $ontMatchArray as $ontMatch ) {
			// <action-reply><match><get-config> or <action-reply><match><get>
			if( ! empty( $ontMatch['get-config'] ) && $ontMatch['get-config']['object']['type'] == 'Ont' ) {
				$ontId = $ontMatch['get-config']['object']['id']['ont'];
				if( strlen($ontId) == 4 || strlen($ontId) == 5 ) {
					// Expect ONT ID to be either NNNN or NNNNN (ie. <shelf><card><port><ontId>); sometimes CMS has wrong configurations
					$ontArray[$ontId]['adminState'] = $ontMatch['get-config']['object']['admin'];
					$ontArray[$ontId]['profileId'] = $ontMatch['get-config']['object']['ontprof']['id']['ontprof']['!'];	// Element value
					$ontArray[$ontId]['profileName'] = $ontMatch['get-config']['object']['ontprof']['id']['ontprof']['!name'];	// Element attribute "name"
					$ontArray[$ontId]['serialNumber'] = $ontMatch['get-config']['object']['serno'];
					$ontArray[$ontId]['regId'] = $ontMatch['get-config']['object']['reg-id'];
					$ontArray[$ontId]['subscriberId'] = $ontMatch['get-config']['object']['subscr-id'];
					$ontArray[$ontId]['description'] = $ontMatch['get-config']['object']['descr'];
					if( ! empty( $ontMatch['get-config']['object']['linked-pon'] ) ) {
						$ontArray[$ontId]['ponType'] = $ontMatch['get-config']['object']['linked-pon']['type'];
						$ontArray[$ontId]['ponShelf'] = $ontMatch['get-config']['object']['linked-pon']['id']['shelf'];
						$ontArray[$ontId]['ponCard'] = $ontMatch['get-config']['object']['linked-pon']['id']['card'];
						$ontArray[$ontId]['ponPort'] = $ontMatch['get-config']['object']['linked-pon']['id']['gponport'];
					}
					// pwe3prof not implemented here since it doesn't seem interesting at this time
					//$ontArray[$ontId]['pwe3profSomething'] = $ontMatch['get-config']['object']['pwe3prof']['something'];
					if( ! empty( $ontMatch['get'] ) && $ontMatch['get']['object']['type'] == 'Ont' ) {
						$ontId = $ontMatch['get']['object']['id']['ont'];
						$ontArray[$ontId]['operationalState'] = $ontMatch['get']['object']['op-stat'];
						$ontArray[$ontId]['alarmCritical'] = $ontMatch['get']['object']['crit'];
						$ontArray[$ontId]['alarmMajor'] = $ontMatch['get']['object']['maj'];
						$ontArray[$ontId]['alarmMinor'] = $ontMatch['get']['object']['min'];
						$ontArray[$ontId]['alarmWarning'] = $ontMatch['get']['object']['warn'];
						$ontArray[$ontId]['alarmInfo'] = $ontMatch['get']['object']['info'];
						$ontArray[$ontId]['derivedStates'] = $ontMatch['get']['object']['derived-states'];
						$ontArray[$ontId]['model'] = $ontMatch['get']['object']['model'];
						$ontArray[$ontId]['vendor'] = $ontMatch['get']['object']['vendor'];
						$ontArray[$ontId]['clei'] = $ontMatch['get']['object']['clei'];
					}
				}
			}
		}
		$lastOnt = isset( $response['action-reply']['more'] ) ? $ontId : NULL;	// Set last ONT found in this SOAP request, to specify continuation past this ONT on next request
	} while ( ! empty($lastOnt) );
	$this->ontArray = $ontArray;
	return $ontArray;
}

function getPonCardBasicsRaw( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	$response = $this->soapClient->send( rtrv_PONCard( $oltTid, $shelf, $card, $this->username, $this->soapSessionId ) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getPonCardBasics( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	$response = $this->getPonCardBasicsRaw( $oltTid, $shelf, $card );
	//$result['baseType'] = setOrNullify( $response['data']['top']['object']['type'] );	// Card
	//$result['cardType'] = setOrNullify( $response['data']['top']['object']['equip-type'] );	// gpon-4; use getPonCardAttributes() instead
	$result['ponCardAdministrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );	// enabled
	//$result['controlCandidateFlag'] = setOrNullify( $response['data']['top']['object']['ctrl-candidate'] );	// true
	return $result;
}

function getPonCardSoftwareLevelsRaw( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	$response = $this->soapClient->send( rtrv_PONCard2( $oltTid, $shelf, $card, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getPonCardSoftwareLevels( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	$response = $this->getPonCardSoftwareLevelsRaw( $oltTid, $shelf, $card );
	//$result['cardSoftwarePresentFlag'] = setOrNullify( $response['action-reply']['present'] );	// true
	$result['ponCardSoftwareVersion'] = setOrNullify( $response['action-reply']['running'] );	// 2.1.41.3
	//$result['cardSoftwareVersionCommitted'] = setOrNullify( $response['action-reply']['committed'] );	// 2.1.41.3
	$result['ponCardSoftwareVersionAlternate'] = setOrNullify( $response['action-reply']['alternate'] );	// 2.1.40.3
	return $result;
}

function getPonCardAttributesRaw( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	$response = $this->soapClient->send( rtrv_PONCard4( $oltTid, $shelf, $card, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getPonCardAttributes( $oltTid = NULL, $shelf = NULL, $card = NULL ) {
	$response = $this->getPonCardAttributesRaw( $oltTid, $shelf, $card );
	$result['ponCardType'] = setOrNullify( $response['data']['top']['object']['actual-type'] );	// gpon-4
	$result['ponCardCleiCode'] = setOrNullify( $response['data']['top']['object']['clei'] );
	$result['ponCardPartNumber'] = setOrNullify( $response['data']['top']['object']['partno'] );
	$result['ponCardSerialNumber'] = setOrNullify( $response['data']['top']['object']['serno'] );
	$result['ponCardServiceState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );
	return $result;
}

function getPonPortBasicsRaw( $oltTid = NULL, $shelf = NULL, $card = NULL, $ponPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	if( ! isset($ponPort) ) { $ponPort = $this->ponPort; }
	$response = $this->soapClient->send( rtrv_PONPort( $oltTid, $shelf, $card, $ponPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getPonPortBasics( $oltTid = NULL, $shelf = NULL, $card = NULL, $ponPort = NULL ) {
	$response = $this->getPonPortBasicsRaw( $oltTid, $shelf, $card, $ponPort );
	$result['ponPortAdministrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );	// enabled
	$result['ponPortRateLimit'] = setOrNullify( $response['data']['top']['object']['rate-limit'] );	// 2500
	$result['ponPortMaxStreams'] = setOrNullify( $response['data']['top']['object']['max-streams'] );	// 500
	$result['ponPortStreamAlarmLevel'] = setOrNullify( $response['data']['top']['object']['stream-alarm-lvl'] );	// 500
	$result['ponPortMulticastBandwidth'] = setOrNullify( $response['data']['top']['object']['mcast-bw'] );	// 2500
	$result['ponPortMulticastBandwidthAlarmLevel'] = setOrNullify( $response['data']['top']['object']['mcast-bw-alarm-lvl'] );	// 2500
	$result['ponPortSplitHorFlag'] = setOrNullify( $response['data']['top']['object']['split-hor'] );	// false
	$result['ponPortDynamicBandwidthAllocationFlag'] = setOrNullify( $response['data']['top']['object']['dyn-bw-alloc'] );	// true
	$result['ponPortDescription'] = setOrNullify( $response['data']['top']['object']['descr'] );
	return $result;
}

function getPonPortStateRaw( $oltTid = NULL, $shelf = NULL, $card = NULL, $ponPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($shelf) ) $shelf = $this->shelf;
	if( ! isset($card) ) $card = $this->card;
	if( ! isset($ponPort) ) { $ponPort = $this->ponPort; }
	$response = $this->soapClient->send( rtrv_PONPort2( $oltTid, $shelf, $card, $ponPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getPonPortState( $oltTid, $shelf, $card, $ponPort )
 *
 * Additional parameters available by raw return:
 *	[info] => 0 *	[derived-states] => *	[sfp-type] => gpon *	[sfp-conn] => sc *	[sfp-encoding] => unknown
 *	[sfp-bitrate] => 24 *	[sfp-bitratemax] => 0 *	[sfp-vendname] => Calix *	[sfp-vendpartno] => 100-01782
 *	[sfp-vendrev] =>  *	[sfp-vendserno] => 01679  KA9214 *	[v2-joins-sent] => 0 *	[v2-joins-rec] => 4787
 *	[leaves-sent] => 0 *	[leaves-rec] => 6 *	[gsq-sent] => 11 *	[gsq-rec] => 0 *	[inval-msg] => 0
 *	[query-solicits-sent] => 12 *	[query-solicits-rec] => 0 *	[general-querys-sent] => 35922 *	[general-querys-rec] => 0
 *	[sfp-temp] => 50.11 *	[sfp-tx-bias] => 34.326 *	[sfp-tx-power] => 2.5014 *	[sfp-rx-power] => 0.0000
 *	[sfp-voltage] => 3325.00 *	[sfp-line-length] => 20000 *	[sfp-wavelength] => 1490.00 *	[clei] =>
 */
function getPonPortState( $oltTid = NULL, $shelf = NULL, $card = NULL, $ponPort = NULL ) {
	$response = $this->getPonPortStateRaw( $oltTid, $shelf, $card, $ponPort );
	$result['ponPortOperationalState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );
	$result['ponPortStatus'] = setOrNullify( $response['data']['top']['object']['status'] );
	$result['ponPortSfpStatus'] = setOrNullify( $response['data']['top']['object']['sfp-status'] );
	return $result;
}

/*
 * function getOntBasicsRaw( $oltTid, $ontId )
 * The SOAP response has a slightly nested array of values.  Use test-soap-call-cms.php for details.
 */
function getOntBasicsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($ontId) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntBasics( $oltTid, $ontId )
 *
 */
function getOntBasics( $oltTid = NULL, $ontId = NULL ) {
	$response = $this->getOntBasicsRaw( $oltTid, $ontId );
	$result['administrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );
	$result['subscriberId'] = setOrNullify( $response['data']['top']['object']['subscr-id'] );
	$result['description'] = setOrNullify( $response['data']['top']['object']['descr'] );
	$result['serialNumber'] = setOrNullify( $response['data']['top']['object']['serno'] );
	return $result;
}

function getOntStateRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($ontId) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT2( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntState( $oltTid, $ontId )
 *
 * Additional parameters available by raw return:
 *	[type] => Ont
 *	[id] => Array
 *	    (
 *	        [ont] => 1113
 *	    )
 *	[derived-states] => child-prov present
 *	[vendor] => CXNK
 */
function getOntState( $oltTid = NULL, $ontId = NULL ) {
	$response = $this->getOntStateRaw( $oltTid, $ontId );
	$result['cleiCode'] = setOrNullify( $response['data']['top']['object']['clei'] );
	$result['model'] = setOrNullify( $response['data']['top']['object']['model'] );
	$result['operationalState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );
	$result['alarmCriticalFlag'] = setOrNullify( $response['data']['top']['object']['crit'] );
	$result['alarmMajorFlag'] = setOrNullify( $response['data']['top']['object']['maj'] );
	$result['alarmMinorFlag'] = setOrNullify( $response['data']['top']['object']['min'] );
	$result['alarmWarningFlag'] = setOrNullify( $response['data']['top']['object']['warn'] );
	$result['alarmInfoFlag'] = setOrNullify( $response['data']['top']['object']['info'] );
	return $result;
}

/*
 * function getOntDetailsRaw( $oltTid, $ontId )
 * This SOAP call duplicates most of the parameters returned by getOntBasicsRaw() and getOntStateRaw().
 */
function getOntDetailsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset($ontId) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT3( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntDetails( $oltTid, $ontId )
 *
 * Some ONT parameters are obtained by other SOAP calls and so are commented out in this method.
 */
function getOntDetails( $oltTid = NULL, $ontId = NULL ) {
	$response = $this->getOntDetailsRaw( $oltTid, $ontId );
	if( ! empty( $response['data']['top']['object']['children'] ) ) {
		//$result['serialNumber'] = setOrNullify( $response['data']['top']['object']['children']['child']['id']['discont'] );
		//$result['subscriberId'] = setOrNullify( $response['data']['top']['object']['children']['child']['subscr-id'] );
		//$result['description'] = setOrNullify( $response['data']['top']['object']['children']['child']['descr'] );
		//$result['cleiCode'] = setOrNullify( $response['data']['top']['object']['children']['child']['clei'] );
		//$result['model'] = setOrNullify( $response['data']['top']['object']['children']['child']['model'] );
		$result['softwareVersion'] = setOrNullify( $response['data']['top']['object']['children']['child']['curr-sw-vers'] );
		$result['softwareVersionAlternate'] = setOrNullify( $response['data']['top']['object']['children']['child']['alt-sw-vers'] );
		//$result['operationalState'] = setOrNullify( $response['data']['top']['object']['children']['child']['op-stat'] );
	}
	return $result;
}

/*
 * Obsolete function?
 *
function getOntHpnaPorts() {
	$response = $this->soapClient->send( rtrv_E7ONT_EthHPNA_exist( $oltTid, $ontId, $this->username, $this->soapSessionId ) );
}
*/

function getOntPotsBasicsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntPOTS( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntPotsBasics( $oltTid, $ontId, $ontPort )
 *
 * Additional parameters available by raw return:
 *	[impedance] => 600-ohm, [system-tx-loss] => gr909, [system-rx-loss] => gr909
 */

function getOntPotsBasics( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntPotsBasicsRaw( $oltTid, $ontId, $ontPort );
	$result['administrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );	// Eg. enabled
	$result['subscriberId'] = setOrNullify( $response['data']['top']['object']['subscr-id'] );
	$result['description'] = setOrNullify( $response['data']['top']['object']['descr'] );
	$result['signalingMode'] = setOrNullify( $response['data']['top']['object']['signal-type'] );	// Eg. auto
	return $result;
}

function getOntPotsDetailsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntPOTSA( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntPotsDetails( $oltTid, $ontId, $ontPort )
 *
 * Additional parameters available by raw return:
 *	[children] => Array (
 *		[child] => Array (
 *				[type] => TdmGwSvc
 *				[id] => Array (
 *						[ont] => 1119
 *						[ontslot] => 6
 *						[ontpots] => 1
 *						[tdmgwsvc] => 1
 *				[tdmgw-prof] => Array (
 *						[type] => TdmGwProf
 *						[id] => Array (
 *								[tdmgwprof] => Array (
 *										[!name] => @OMAHNEFOG0201013803A&IG1
 *										[!localId] => 7
 *										[!] => 7
 *				[crv] => N1-1-IG1-45
 *				[admin] => enabled
 *
 */
function getOntPotsDetails( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntPotsDetailsRaw( $oltTid, $ontId, $ontPort );
	$result = NULL;
	if( ! empty( $response['data']['top']['object']['children'] ) ) {
		$result['type'] = setOrNullify( $response['data']['top']['object']['children']['child']['type'] );	// Eg. TdmGwSvc
		$result['crv'] = setOrNullify( $response['data']['top']['object']['children']['child']['crv'] );	// Eg. N1-1-IG1-45
	}
	return $result;
}

function getOntPotsStatsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntPOTSB( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntPotsStats( $oltTid, $ontId, $ontPort )
 *
 * There are many parameters available.  Use test-soap-call-cms.php for details.
 */
function getOntPotsStats( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntPotsStatsRaw( $oltTid, $ontId, $ontPort );
	$result['operationalState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );	// Eg. enable
	$result['hookState'] = setOrNullify( $response['data']['top']['object']['hook-state'] );	// Eg. on-hook
	$result['configurationStatus'] = setOrNullify( $response['data']['top']['object']['config-status'] );	// Eg. active
	$result['serviceStatus'] = setOrNullify( $response['data']['top']['object']['svc-status'] );	// Eg. registered
	$result['callState'] = setOrNullify( $response['data']['top']['object']['call-state'] );	// Eg. idle
	return $result;
}

function getOntPotsPowerStatusRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntPOTSC( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntPotsPowerStatus( $oltTid, $ontId, $ontPort )
 *
 * Additional parameters available by raw return:
 *	[op-stat] => enable *	[crit] => 0 *	[maj] => 0 *	[min] => 0 *	[warn] => 0 *	[info] => 0
 *	[derived-states] => child-prov default-prov
 */
function getOntPotsPowerStatus( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntPotsPowerStatusRaw( $oltTid, $ontId, $ontPort );
	//$result['operationalState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );	// Eg. enable
	$result['powerStatus'] = setOrNullify( $response['data']['top']['object']['power-status'] );	// Eg. ac-up
	return $result;
}

function getOntGePortBasicsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) { $ontPort = $this->ontPort; }
	$response = $this->soapClient->send( rtrv_E7ONT_EthGE_port( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntGePortBasics( $oltTid, $ontId, $ontPort )
 *
 * Additional parameters available by raw return:
 *	[type] => OntEthGe
 *	[gos] => Array (
 *	        [type] => OntEthPortGos
 *	        [id] => Array (
 *	                [ontethportgos] => 1
 *	[sec] => Array (
 *	        [type] => EthSecProf
 *	        [id] => Array (
 *	                [ethsecprof] => Array (
 *	                        [!name] => system-default
 *	                        [!] => 1
 *	[link-oam-events] => false
 *	[accept-link-oam-loopbacks] => false
 */
function getOntGePortBasics( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntGePortBasicsRaw( $oltTid, $ontId, $ontPort );
	$result['administrativeState'] = setOrNullify( $response['data']['top']['object']['admin'] );	// Eg. enabled-no-alarms
	$result['subscriberId'] = setOrNullify( $response['data']['top']['object']['subscr-id'] );
	$result['description'] = setOrNullify( $response['data']['top']['object']['descr'] );
	$result['speed'] = setOrNullify( $response['data']['top']['object']['speed'] );	// Eg. auto
	$result['duplex'] = setOrNullify( $response['data']['top']['object']['duplex'] );	// Eg. full
	$result['disableOnBatteryFlag'] = setOrNullify( $response['data']['top']['object']['disable-on-batt'] );
	return $result;
}

function getOntGePortStateRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) { $ontPort = $this->ontPort; }
	$response = $this->soapClient->send( rtrv_E7ONT_EthGE_portA( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntGePortState( $oltTid, $ontId, $ontPort )
 *
 * There are many parameters available.  Use test-soap-call-cms.php for details.
 */
function getOntGePortState( $oltTid, $ontId, $ontPort ) {
	$response = $this->getOntGePortStateRaw( $oltTid, $ontId, $ontPort );
	$result['operationalState'] = setOrNullify( $response['data']['top']['object']['op-stat'] );	// Eg. enabled-no-alarms or sys-disable
	$result['derivedStates'] = setOrNullify( $response['data']['top']['object']['derived-states'] );	// Eg. default-prov suppr TODO Is this used?
	$result['rate'] = setOrNullify( $response['data']['top']['object']['rate'] );	// Eg. 10
	$result['status'] = setOrNullify( $response['data']['top']['object']['status'] );	// Eg. down
	$result['duplexActual'] = setOrNullify( $response['data']['top']['object']['actual-duplex'] );	// Eg. half
	$result['powerStatus'] = setOrNullify( $response['data']['top']['object']['power-status'] );	// Eg. ac-up
	return $result;
}

function getOntGePortServicesRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_EthGE_portB( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

/*
 * function getOntGePortServices( $oltTid, $ontId, $ontPort )
 */
function getOntGePortServices( $oltTid, $ontId, $ontPort ) {
	global $flagDebug;
	$result = NULL;
	$response = $this->getOntGePortServicesRaw( $oltTid, $ontId, $ontPort );

	/*
	 * Calix supports more than one service on an Ethernet port. So, determine how many services of what type are running.
	 * Also, the presence of a multicast profile indicates that the service being provided is PRISM-IPTV.
	 * Now it gets trickier; generally, an ONT will have its ports provisioned in some fashion, but they may not have services
	 * on the ports, so we have to test if a service exists, before processing it.
	 *
	 * The SOAP response returns an object, eg.:
	 *	['data']['top']['object']['children']['child'][$serviceIndex]['id']['ethsvc']['!name'];	// $ethServName[$GEPort][]
	 *	['data']['top']['object']['children']['child'][$serviceIndex]['admin'];	// $ethServAdmin[$GEPort][]
	 *
	 * But, if there is a single 'child', then the $serviceIndex array component is omitted in the response; ie.:
	 *	['data']['top']['object']['children']['child']['id']['ethsvc']['!name'];	// $ethServName[$GEPort][]
	 */
	$servicesArray = array();	// Default to no services
	if ( isset($response['data']['top']['object']['children']['child']) ) {
		if ( array_key_exists('id', $response['data']['top']['object']['children']['child']) ) {
			// Single service
			// Case when there is a single child; nusoap omits an array level, ie. not $response->[0]->['data'], but $response->['data']
			$servicesArray[0] = $response['data']['top']['object']['children']['child'];
		} elseif ( array_key_exists('0', $response['data']['top']['object']['children']['child']) ) {
			// Multiple services array
			// Case when there is are multiple children; nusoap loads an array, ie. $response->[n]->['data']
			$servicesArray = $response['data']['top']['object']['children']['child'];
		} else {
			// Error: unexpected SOAP response (or perhaps zero services?)
			// TODO: Add exception handling for unexpected services SOAP response
		}
	}

	$serviceCount = count( $servicesArray );
	for( $serviceIndex = 0; $serviceIndex < $serviceCount; $serviceIndex++ ) {
		$result[$serviceIndex]['name'] = $servicesArray[$serviceIndex]['id']['ethsvc']['!name'];
		$result[$serviceIndex]['administrativeState'] = $servicesArray[$serviceIndex]['admin'];
		$result[$serviceIndex]['tagAction'] = $servicesArray[$serviceIndex]['tag-action']['id']['svctagaction']['!name'];
		$result[$serviceIndex]['bandwidthProfile'] = $servicesArray[$serviceIndex]['bw-prof']['id']['bwprof']['!name'];
		$result[$serviceIndex]['outerTag'] = $servicesArray[$serviceIndex]['out-tag'];
		$result[$serviceIndex]['innerTag'] = $servicesArray[$serviceIndex]['in-tag'];

		// Determine service type: Video/PRISM or Data/HSI
		// So far, it seems to be the case that a multicast profile is only defined on the service if it's video; keep an eye on this, ok?
		//$ethServSvcType[$GEPort][] = !empty($servicesArray[$serviceIndex]['mcast-prof']) ? "Video - PRISM" : "Data - HSI";	// Original 1-line setting
		if( ! empty( $servicesArray[$serviceIndex]['mcast-prof'] ) ) {
			$result[$serviceIndex]['type'] = "Video - PRISM";

			// Use element value for multicast ID, not attribute ['mcast-prof']['id']['mcastprof'][!localId] => 1 (which is probably the same?)
			$multicastIndex = isset( $servicesArray[$serviceIndex]['mcast-prof']['id']['mcastprof']['!'] )
				? $servicesArray[$serviceIndex]['mcast-prof']['id']['mcastprof']['!'] : NULL;
			$result[$serviceIndex]['multicastIndex'] = $multicastIndex;

			// Get multicast profiles from OLT via CMS; use singleton pattern here
			if( isset( $multicastIndex ) && !isset( $multicastProfiles ) ) {
				$multicastProfiles = $this->getOltMulticastProfiles( $oltTid );
				if( $flagDebug ) {
					echo "<h2>Chassis multicast profiles</h2>\n";
					echo "\n<pre>\n" . print_r($multicastProfiles, TRUE) . "\n</pre>\n\n";
				}
			}

			// Use multicast index into the multicast profile table, obtain the MVR profile table index, then obtain the VLAN ID from the MVR table
			if( isset( $multicastIndex ) && isset( $multicastProfiles[$multicastIndex] ) ) {
				$result[$serviceIndex]['multicastName'] = $multicastProfiles[$multicastIndex]['profileName'];	// Not used
				$mvrProfileIndex = $multicastProfiles[$multicastIndex]['mvrProfileIndex'];
				$result[$serviceIndex]['vlanId'] = NULL;
				if( isset( $mvrProfileIndex ) ) {
					try {
						$mvrProfile = $this->getOltMvrProfile( $oltTid, $mvrProfileIndex );
					}
					catch( Exception $e ) {
						$mvrProfile = NULL;
					}
					if( $flagDebug ) {
						echo "<h2>Chassis MVR profile for index: $mvrProfileIndex</h2>\n";
						echo "\n<pre>\n" . print_r($mvrProfile, TRUE) . "\n</pre>\n\n";
					}
					if( isset( $mvrProfile ) ) $result[$serviceIndex]['vlanId'] = $mvrProfile['mvrVlan'];
				}
			} else {
				$result[$serviceIndex]['multicastName'] = NULL;
				$result[$serviceIndex]['vlanId'] = NULL;
			}
		} else {
			$result[$serviceIndex]['type'] = "Data - HSI";
			$result[$serviceIndex]['multicastIndex'] = NULL;
			$result[$serviceIndex]['multicastName'] = NULL;
			$result[$serviceIndex]['vlanId'] = NULL;
		}
	}
	return $result;
}

function getOntVideoRfBasicsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntVideoRf( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntVideoRfStatsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_OntVideoRfA( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntAvoRfBasicsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT_OntRfAvo( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntAvoRfStatsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT_OntRfAvoA( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntVideoHotRfBasicsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT_OntVideoHotRf( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntVideoHotRfStatsRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT_OntVideoHotRfA( $oltTid, $ontId, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntDs1BasicsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) $ontPort = $this->ontPort;
	$response = $this->soapClient->send( rtrv_E7ONT_Ont_DS1( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntDs1StatsRaw( $oltTid = NULL, $ontId = NULL, $ontPort = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	if( ! isset( $ontPort ) ) { $ontPort = $this->ontPort; }
	$response = $this->soapClient->send( rtrv_E7ONT_Ont_DS1A( $oltTid, $ontId, $ontPort, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}

function getOntServicesRaw( $oltTid = NULL, $ontId = NULL ) {
	if( ! isset( $oltTid ) ) $oltTid = $this->oltTid;
	if( ! isset( $ontId ) ) $ontId = $this->ontId;
	$response = $this->soapClient->send( rtrv_E7ONT_svcs( $oltTid, $ontId, $this->oltServiceType, $this->username, $this->soapSessionId) );
	$this->validateSoapResponse( $response );
	return $response;
}
}
