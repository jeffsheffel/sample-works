<?php
/*
 * This script returns an XML response for a test GPON device.
 * The device type, configuration, and state, is determined by device emulation definition XML files, which are indexed
 * by ONT numbers (eg. emulated-ont-1-1-1-1.xml).
 *
 * Request variables:
 * service		= (str "gpon")
 * oltip		= (str "xxx.xxx.xxx.xxx") - IP Address of OLT
 * aid = (str "ONT-<AID>")
 *		aid must have the following format:
 *
 *		- Adtran ONT AID format
 *		ONT	-	<RACK> - <SHELF> - <PONCARD> - <PORT> -	<ONT #>
 *				   1        1        1-22       1-2      1–32
 *
 *		- Calix ONT AID format
 *		ONT	-	<SHELF> - <SLOT> - <PORT> -	<ONT #>
 *				 1-10      1-2      1-4      1–64
 *
 * debug		= (str "yes"|"no")
 * selection	= (str "retrieve")
 *
 * Example:
 * http://<webserverIP>/vader/ftth/request.php?service=gpon&oltip=<testIP>&aid=ONT-1-1-1-1&selection=retrieve
 *
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
	case DEVICE_VENDOR_ADTRAN:
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

// Add OLT device response XML
$xmlResponse .= $oltDevice->getOltStatXmlString();
$xmlResponse .= $oltDevice->getCardStatXmlString();

// Add ONT device response XML
$xmlResponse .= $ontDevice->getOntStatXmlString();
$xmlResponse .= $ontDevice->getPotsPortStatXmlString();
$xmlResponse .= $ontDevice->getEthernetPortStatXmlString();
$xmlResponse .= $ontDevice->getEthernetPortServicesXmlString();
$xmlResponse .= $ontDevice->getOntAlarmsXmlString();
$xmlResponse .= '</' . $rootElementName . '>';

// Format (pretty-print) the response XML
try {
	$xmlResponse = formatXmlString($xmlResponse, FALSE, 1, "\t");
}
catch (Exception $e) {
	// TODO: Respond with Vader error response
	// TODO: Send admin notification of Vader parse failure error
	print $e->getMessage();
	exit;
}

if( $urlParams['gui'] ) { header('Content-type: text/xml; charset=UTF-8'); }

print $xmlResponse;
