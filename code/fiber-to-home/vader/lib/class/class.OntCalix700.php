<?php
/*
 * class OntCalix700
 *
 * Most of the Calix functionality is in the CalixManagementSystem class.
 */

require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-snmp.php';

class OntCalix700 {

public $communityString;
public $ipAddress;
public $cms = NULL;
public $deviceConfig = NULL;

function __construct( $oltDatabaseAttributes ) {
	$this->ipAddress = $oltDatabaseAttributes['ipAddress'];
	$this->vendor = "calix";

}

public function __toString() {
	return var_export( $this, TRUE );
}

function attachCms( $cms ) {
	$this->cms = $cms;
}

function getOntBasics( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntBasics();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntState( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntState();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntDetails( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntDetails();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntPotsBasics( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntPotsBasics();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntPotsDetails( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntPotsDetails();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntPotsStats( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntPotsStats();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntPotsPowerStatus( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntPotsPowerStatus();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function buildOntPotsPortProperties( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	for( $potsPortIndex=0; $potsPortIndex < $this->deviceConfig['potsPortCount']; $potsPortIndex++ ) {
		$this->potsPorts[$potsPortIndex]['basics'] = NULL;
		$this->potsPorts[$potsPortIndex]['details'] = NULL;
		$this->potsPorts[$potsPortIndex]['stats'] = NULL;
		$this->potsPorts[$potsPortIndex]['powerStatii'] = NULL;
		try {
			$arrayBasics = $cms->getOntPotsBasics( NULL, NULL, $potsPortIndex );
			$this->potsPorts[$potsPortIndex]['basics'] = $arrayBasics;
			$arrayDetails = $cms->getOntPotsDetails( NULL, NULL, $potsPortIndex );
			$this->potsPorts[$potsPortIndex]['details'] = $arrayDetails;
			$arrayStats = $cms->getOntPotsStats( NULL, NULL, $potsPortIndex );
			$this->potsPorts[$potsPortIndex]['stats'] = $arrayStats;
			$arrayPowerStatii = $cms->getOntPotsPowerStatus( NULL, NULL, $potsPortIndex );
			$this->potsPorts[$potsPortIndex]['powerStatii'] = $arrayPowerStatii;
		}
		catch( Exception $e ) {}
	}
}

function getOntGePortBasics( $cms = NULL, $ontPort ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntGePortBasics( NULL, NULL, $ontPort );
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntGePortState( $cms = NULL, $ontPort ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntGePortState( NULL, NULL, $ontPort );
	$this->setObjectPropertiesFromArray( $properties );
}

function getOntGePortServices( $cms = NULL, $ontPort ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOntGePortServices( NULL, NULL, $ontPort );
	$this->setObjectPropertiesFromArray( $properties );
}

function buildOntGePortProperties( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	for( $port=1; $port <= $this->deviceConfig['ethernetPortCount']; $port++ ) {
		$this->gePorts[$port]['basics'] = NULL;
		$this->gePorts[$port]['state'] = NULL;
		$this->gePorts[$port]['services'] = NULL;
		try {
			$arrayBasics = $cms->getOntGePortBasics( NULL, NULL, $port );
			$this->gePorts[$port]['basics'] = $arrayBasics;
			$arrayState = $cms->getOntGePortState( NULL, NULL, $port );
			$this->gePorts[$port]['state'] = $arrayState;
			$arrayServices = $cms->getOntGePortServices( NULL, NULL, $port );
			$this->gePorts[$port]['services'] = $arrayServices;
		}
		catch( Exception $e ) {}
	}
}

/*
 * function setDeviceConfig()
 *
 * This function queries the ONT's CLEI code to set the ONT's capabilities.
 *
 * Refer to the Vader-ONT-accounting.docx document.
 * Also refer to duplicate code in the adtranCleiCodeToModel() function of the lib-adtran.php module.
 * TODO: Throw an exception if the CLEI code isn't recognized
 */
function setDeviceConfig() {
	$this->deviceConfig['ethernetPortCount'] = 2;	// Default to 2 Ethernet ports; unset below if an ONT doesn't have Ethernet
	$this->deviceConfig['potsPortCount'] = 2;		// Default to 2 POTS ports; unset below if an ONT doesn't have POTS
	//$this->deviceConfig['rfVideoPortCount'] = 0;
	//$this->deviceConfig['ds1PortCount'] = 0;

	switch( $this->cleiCode ) {
		case "":
			$this->deviceConfig['model'] = "N/A";
			// NOTE: still assume there are a default number of ports, as set above
			break;
		case "BVM8700CR":
		case "BVM9P00AR":
			$this->deviceConfig['model'] = "711GE";
			break;
		case "BVM9S00AR":
			$this->deviceConfig['model'] = "717GE";
			$this->deviceConfig['ethernetPortCount'] = 4;
			$this->deviceConfig['potsPortCount'] = 4;
			break;
		case "BVL3ANPFA":
			$this->deviceConfig['model'] = "762GX";
			$this->deviceConfig['ethernetPortCount'] = 8;
			$this->deviceConfig['potsPortCount'] = 8;
		case "BVM8W00CR":
			$this->deviceConfig['model'] = "766GX-R";
			$this->deviceConfig['ethernetPortCount'] = 4;
			$this->deviceConfig['potsPortCount'] = 8;
			$this->deviceConfig['rfVideoPortCount'] = 1;
			$this->deviceConfig['ds1PortCount'] = 8;
			$this->deviceConfig['rfAvoPortCount'] = 1;	// Unique to this model
			$this->deviceConfig['rfHotPortCount'] = 1;	// Unique to this model
			break;
		default:
			$this->deviceConfig['model'] = "Unknown";
			// NOTE: still assume there are a default number of ports, as set above
			break;
	}
}

/*
 * function setObjectPropertiesFromArray( $propertyArray )
 * This method simply sets object properties from an associative array.
 *
 * NOTE: Numeric values are converted to strings, even if they're numeric in $propertyArray; not sure why
 */
function setObjectPropertiesFromArray( $propertyArray ) {
	foreach( $propertyArray as $property => $value ) {
		$this->$property = $value;
	}
}
}
