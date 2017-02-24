<?php
/*
 * class OltCalixE7
 *
 * Most of the Calix functionality is in the CalixManagementSystem class.
 */

require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-snmp.php';

class OltCalixE7 {

public $communityString;
public $ipAddress;
public $cms = NULL;

function __construct( $oltDatabaseAttributes ) {
	$this->ipAddress = $oltDatabaseAttributes['ipAddress'];
	$this->vendor = "calix";

	//$this->oidPartNumber = ?;
	//$this->oidCleiCode = "1.3.6.1.4.1.xxxx.5.13.2.4.1.2.254";	// No equivalent to Adtran
	$this->oidSystemDescription = "1.3.6.1.2.1.1.1.0";	// Eg. E7-2
	//$this->oidTid = "1.3.6.1.4.1.6321.1.2.2.2.1.7.1.0";	// Usually the TID, eg. OMAHNEFOOL101013804A
	$this->oidSystemName = "1.3.6.1.2.1.1.5.0";				// Usually the TID, eg. OMAHNEFOOL101013804A
	//$this->oidLocation = "1.3.6.1.2.1.1.6.0";	// Eg. 13211 FORT ST (not set correctly?)
	$this->oidLocation = "1.3.6.1.4.1.6321.1.2.2.2.1.7.2.0";	// Eg. 13211 FORT ST
	$this->oidUptime = "1.3.6.1.2.1.1.3.0";	// Eg. Timeticks: (3029170055) 350 days, 14:21:40.55
	//$this->oidSoftwareVersion = "1.3.6.1.4.1.xxxx.2.241.11.9.0";	// No equivalent to Adtran

	$this->ipAddressCms = "1.3.6.1.4.1.6321.1.2.2.2.1.8.1.1.4.1";	// CMS IP address; not used

	initializeSnmp();
	$this->communityString = getSnmpCommunityString(SNMP_DEVICE_CALIX);
	// TODO: Add product ident check

	//$this->partNumber = ?;
	//$this->cleiCode = ?;
	$this->productId = doSnmpGet($this->ipAddress, $this->communityString, $this->oidSystemDescription);
	$this->tid = doSnmpGet($this->ipAddress, $this->communityString, $this->oidSystemName);	// Use system name for TID (just cuz)
	$this->location = doSnmpGet($this->ipAddress, $this->communityString, $this->oidLocation);
	$this->uptime = doSnmpGet($this->ipAddress, $this->communityString, $this->oidUptime);
	//$this->softwareVersion = ?;
}

public function __toString() {
	return var_export( $this, TRUE );
}

function attachCms( $cms ) {
	$this->cms = $cms;
}

function getOltBasics( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOltBasics( $this->tid );	// Use this OLT's TID, not the CMS TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getOltStats( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getOltStats( $this->tid );	// Use this OLT's TID, not the CMS TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getPonCardBasics( $cms = NULL, $shelf = NULL, $card = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getPonCardBasics( $this->tid, $shelf, $card );	// Use this OLT's TID, not the CMS TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getPonCardSoftwareLevels( $cms = NULL, $shelf = NULL, $card = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getPonCardSoftwareLevels( $this->tid, $shelf, $card );	// Use this OLT's TID, not the CMS TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getPonCardAttributes( $cms = NULL, $shelf = NULL, $card = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getPonCardAttributes( $this->tid, $shelf, $card );	// Use this OLT's TID, not the CMS TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getPonPortBasics( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getPonPortBasics();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
}

function getPonPortState( $cms = NULL ) {
	if( ! $cms ) $cms = $this->cms;
	$properties = $cms->getPonPortState();	// Use CMS's default TID
	$this->setObjectPropertiesFromArray( $properties );
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
