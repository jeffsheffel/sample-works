<?php
/*
 * This script emulates the return of an XML response for a bounce test to a GPON device.
 * REWRITE: The device type, configuration, and state, is determined by device emulation definition XML files, which are indexed
 * by ONT numbers (eg. emulated-ont-1-1-1-1.xml).
 *
 * This script is called by:
 *	- ftth/calix/gpon-bounce.php	(as an include)
 *
 * Request variables:
 * service = 	(str gpon)
 * oltip = 	(str xxx.xxx.xxx.xxx) - IP Address of E7 or C7 Node
 * aid = (str ONT-AID)
 *		aid must have the following format:
 *		ONT	-	<SHELF> - <SLOT> - <PORT> -	<ONT #>
 *				 1-10      1-2      1-4      1-64
 *
 * debug = 	(str yes|no)
 *   			use debug=no for straight xml responses to PollDSLAM and NDP
 *   			Debug mode will return html and xml, and will be used for troubleshooting purposes via a web browser...
 * selection =	(str bounce-pots-port-(1-8)) bounces selected pots port
 *				(str bounce-ethernet-port-(1-8)) bounces selected ethernet port
 *				(str bounce-rf-port-(1-4)) bounces the selected RF port
 *				(str bounce-hotrf) bounces the hotrf port
 *				(str reset-ont) Resets the ONT
 *				(str reboot-ont) Reboots the ONT
 *
 * TODO: Implement bounce-rf-port-(1-4) and bounce-hotrf
 */

require_once 'lib-database.php';
require_once 'lib-text.php';
require_once 'class.OltCalixEmulator.php';
require_once 'class.OntCalixEmulator.php';
require_once 'class.LogWriterFile.php';
require_once 'class.Log.php';
//require_once 'lib-snmp.php';

$urlParams = getUrlParameters();
$transactionTimestamp = date("Y-m-d H:i:s");	// TODO: Change timestamp format to match legacy Vader format
$transactionId = getTransactionId();

$transactionWriter = new LogWriterFile($fileLogTransaction);
$transactionLogger = new Log($transactionWriter);
try {
	$transactionLogger->info("Test ".$urlParams['oltip']." ".$urlParams['aid']);
}
catch( Exception $e ) {
	trigger_error("Failed write to log", E_USER_WARNING);
}

$aidArray = parseAidCalix($urlParams['aid']);
if ( $aidArray ) {
	list($shelf, $card, $port, $ontNumber, $servicePort, $ontId) = $aidArray;
} else {
	// ERROR: Invalid AID in request
	print "ERROR: Failed to parse AID";
	exit;
}

$cmsDevice = NULL;	// For test devices don't need: new CalixManagementSystem()

// TODO: Use a factory pattern to create devices (either Calix or Adtran) dependent upon device emulation XML file contents
try {
	$oltDevice = new OltCalixEmulator( $urlParams['oltip'], $aidArray );	// Emulator needs ONT ID for ONT XML file lookup
	$ontDevice = new OntCalixEmulator( $aidArray, $cmsDevice );
}
catch (Exception $e) {
	switch( $e->getCode() ) {
		case EMULATION_ERROR_PARSE_FAILURE:
			// TODO: Respond with Vader error response
			// TODO: Send admin notification of Vader parse failure error
			print $e->getMessage();
			break;
		case EMULATION_ERROR_MISSING_DEVICE_DEFINITION_FILE:
		default:
			// TODO: Respond with No Such OLT error response
			print $e->getMessage();
			break;
	}
	exit;
}

switch ( $oltDevice->getVendor() ) {
	case DEVICE_VENDOR_CALIX:
		$rootElementName = "RESULT";
		break;
	case DEVICE_VENDOR_CALIX:
		$rootElementName = "NDP-RESULT";
		break;
	default:
		$rootElementName = "VADER-CODE-ERROR";
		break;
}

// Construct XML response
$xmlResponse  = '<?xml version="1.0" encoding="UTF-8"?>';
$xmlResponse .= '<' . $rootElementName . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' .
	' xsi:noNamespaceSchemaLocation="' . $ontDevice->getXsdUrl() . '">';

// Add transaction elements
$xmlResponse .= '<TRANSNUM>' . $transactionId . '</TRANSNUM>';
$xmlResponse .= '<DATETIME>' . $transactionTimestamp . '</DATETIME>';
$xmlResponse .= '<REQUEST>SERVICE=gpon OLTIP=' . $oltDevice->oltIp . ' AID=' . $urlParams['aid'] . ' DEBUG=' . $requestDebug .
	' SELECTION=' . $urlParams['selection'] . ' CLIENTIP=' . $_SERVER['REMOTE_ADDR'] . " CUID=" . getSessionVar('valid_user') . '</REQUEST>';

// Add ONT device bounce response XML
$ontXml = $ontDevice->getDeviceEmulationXml();	// SimpleXMLElement object
$bounceResultXml = $ontXml->{'emulationAttributes'}->{'behavior'}->{'bounce'}->{$urlParams['selection']}->{'BOUNCE-STATUS'};
if( isset($bounceResultXml) ) {
	/*
	 * For bounces, the OLT stats element is not like a usual Vader response:
	 *	- the AID includes the service port number, and
	 *	- only the following 3 child elements are included:
	 *		<NETWORK-LOCATION>
	 *			<VENDOR>Calix</VENDOR>
	 *			<CHASSIS-TID>LTTNCOMLH0113CAB01A</CHASSIS-TID>
	 *			<ONT-AID>ONT-1-1-2-34-1</ONT-AID>
	 */
	$xmlResponse .= $oltDevice->getOltStatXmlString();	// TODO: Remove child elements that aren't in a bounce response
	$xmlResponse .= $bounceResultXml->asXML();
} else {
	/*
	 * <DESC> will be one of: "VOICE Port N" | "Ethernet Port GE-N" | "bounce-something-port-5" does not exist ...
	 * For now, simply return the generic error description (with the selection parameter inserted).
	 * TODO: Pattern match the selection request variable to properly set the (overly-complicated) error description
	 */
	$xmlResponse .= "<ERROR>";
	$xmlResponse .= "<DESC>".$urlParams['selection']." does not exist on this ONT.  Please check that the ONT is signed on and that this model supports this type and port number.</DESC>";
	$xmlResponse .= "</ERROR>";
}
$xmlResponse .= '</' . $rootElementName . '>';

// Format (pretty-print) the response XML
try {
	$xmlResponse = formatXmlString($xmlResponse, FALSE, 1, "\t");
}
catch (Exception $e) {
	print $e->getMessage();
	exit;
}

if( $urlParams['gui'] ) { header('Content-type: text/xml; charset=UTF-8'); }

print $xmlResponse;
