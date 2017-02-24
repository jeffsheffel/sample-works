<?php
/*
 * class OltAdtranTA5000
 *	- only implements a single PON card and PON port (of a single OLT in a rack and shelf)
 *	- $slot is synonymous with $card, ie. an OLT slot number is the same as the PON card number
 */

require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-snmp.php';
require_once 'lib-adtran.php';
require_once 'lib-text.php';

define("OLT_ERROR_CARD_NOT_GPON",				1);
define("OLT_ERROR_CARD_PRODUCT_CODE_NOT_GPON",	2);

class OltAdtranTA5000 {
public $communityString;
public $ipAddress;
public $ontArray = NULL;

function __construct( $oltDatabaseAttributes, $rack, $shelf ) {
	$this->ipAddress = $oltDatabaseAttributes['ipAddress'];
	$this->rack = $rack;
	$this->shelf = $shelf;
	$this->card = NULL;
	$this->port = NULL;
	$this->vendor = "adtran";
	$this->oidPartNumber = "1.3.6.1.4.1.664.3.1.2.0";	// Eg. 1187001L1
	$this->oidCleiCode = "1.3.6.1.4.1.664.5.13.2.4.1.2.254";	// Eg. 1187011G1Q
	$this->oidProductId = "1.3.6.1.4.1.664.3.1.1.0";	// Eg. TA5000 23 inch shelf
	$this->oidSystemDescription = "1.3.6.1.2.1.1.1.0";	// Eg. TA5000 23 inch shelf
	$this->oidTid = "1.3.6.1.4.1.664.5.17.3.1.1.9.254";	// Usually the TID, eg. PTLDOR69OL105053804A
	$this->oidSystemName = "1.3.6.1.2.1.1.5.0";			// Usually the TID, eg. PTLDOR69OL105053804A
	$this->oidLocation = "1.3.6.1.2.1.1.6.0";	// Eg. PORTLAND-CAPITOL_5TH_FLR
	$this->oidUptime = "1.3.6.1.2.1.1.3.0";	// Eg. Timeticks: (292303610) 33 days, 19:57:16.10
	$this->oidSoftwareVersion = "1.3.6.1.4.1.664.2.241.11.9.0";	// Eg. 06.00.04.03 Auto

	initializeSnmp();
	$this->communityString = getSnmpCommunityString(SNMP_DEVICE_ADTRAN);
	// TODO: Add product ident check

	$this->partNumber = doSnmpGet($this->ipAddress, $this->communityString, $this->oidPartNumber);
	$this->cleiCode = doSnmpGet($this->ipAddress, $this->communityString, $this->oidCleiCode);
	$this->productId = doSnmpGet($this->ipAddress, $this->communityString, $this->oidProductId);
	$this->systemDescription = doSnmpGet($this->ipAddress, $this->communityString, $this->oidSystemDescription);
	$this->tid = doSnmpGet($this->ipAddress, $this->communityString, $this->oidTid);
	$this->systemName = doSnmpGet($this->ipAddress, $this->communityString, $this->oidSystemName);
	$this->location = doSnmpGet($this->ipAddress, $this->communityString, $this->oidLocation);
	$this->uptime = doSnmpGet($this->ipAddress, $this->communityString, $this->oidUptime);
	$this->softwareVersion = doSnmpGet($this->ipAddress, $this->communityString, $this->oidSoftwareVersion);

	// TODO: Add OLT card part numbers array (1.3.6.1.4.1.664.5.13.2.4.1.2); see show-olt-config-adtran.sh
	// TODO: Add OLT VLANs array (SNMPv2-SMI::enterprises.664.6.10000.62.2.4.1.1); see show-olt-config-adtran.sh
}

public function __toString() {
	return var_export( $this, TRUE );
}

public function getOltPonCardProperties( $slot ) {
	$this->card = $slot;

	// Determine shelf base, which determines maximum number of ONTs (32 or 64)
	$this->oidPonCardHexFeatures = "1.3.6.1.4.1.664.5.70.1.1.1.3." . $this->card;
	$this->ponCardHexFeaturesString = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardHexFeatures );

	$this->shelfBase = PON_CARD_MULTI_SHELF_BASED;	// Supports up to 32 ONTs per PON port
	$this->maxOntCount = 32;
	$singleShelfFeatureHex = "67";
	if( hasAdtranPonCardHexFeature( $this->ponCardHexFeaturesString, $singleShelfFeatureHex ) ) {
		$this->shelfBase = PON_CARD_SINGLE_SHELF_BASED;	// Supports up to 64 ONTs per PON port
		$this->maxOntCount = 64;
	}

	$this->oidPonCardName = "1.3.6.1.4.1.664.5.13.2.4.1.1."  . $slot;  // Should be "GPON OLT"
	$this->oidPonCardProductCode = "1.3.6.1.4.1.664.5.13.2.3.1.4."  . $slot;
	$this->oidPonCardCleiCode = "1.3.6.1.4.1.664.5.13.2.4.1.3."  . $slot;
	$this->oidPonCardPartNumber = "1.3.6.1.4.1.664.5.13.2.4.1.2."  . $slot;
	$this->oidPonCardSerialNumber = "1.3.6.1.4.1.664.5.13.2.4.1.4."  . $slot;
	$this->oidPonCardRevision = "1.3.6.1.4.1.664.5.13.2.4.1.5."  . $slot;
	$this->oidPonCardSoftwareVersion = "1.3.6.1.4.1.664.5.13.2.4.1.6."  . $slot;
	$this->oidPonCardServiceState = "1.3.6.1.4.1.664.5.13.2.3.1.8." . $slot;  // 1 = In Service, 2 = Out Of Service UAS, 3 = Out Of Service MA, 5 = Fault, 8 = InService StandByHOT, 9 = InService ActLock, 10 InService StandByLock
	$this->oidPonCardUptime = "1.3.6.1.4.1.664.5.13.2.3.1.17." . $slot; // Uptime in hundredths of a second

	$this->ponCardName = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardName );
	$this->ponCardProductCode = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardProductCode );

	if( ! preg_match( '/GPON/', $this->ponCardName ) ) {
		// PON card type isn't GPON
		throw new Exception( "OLT card type isn't GPON", OLT_ERROR_CARD_NOT_GPON );
	}

	if( ! preg_match( '/GPON/', strtoupper( $this->translateProductCodeToText( $this->ponCardProductCode ) ) ) ) {
		// PON card product code isn't GPON
		throw new Exception( "OLT card product code isn't GPON", OLT_ERROR_CARD_PRODUCT_CODE_NOT_GPON );
	}

	$this->ponCardCleiCode = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardCleiCode );
	$this->ponCardPartNumber = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardPartNumber );
	$this->ponCardSerialNumber = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardSerialNumber );
	$this->ponCardRevision = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardRevision );
	$this->ponCardSoftwareVersion = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardSoftwareVersion );
	$this->ponCardServiceState = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardServiceState );
	$this->ponCardUptime = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonCardUptime );

	$this->ponCardPortCount = 0;
	switch( $this->ponCardProductCode ) {
		case 1179:
			$this->ponCardPortCount = 2;
			break;
		case 1189:
			$this->ponCardPortCount = 4;
			break;
		default:
			break;
	}
}

/*
 * function getOltPonPortProperties( $port )
 *
 * Must either:
 *	1) First call getOltPonCardProperties() to set the PON slot, or
 *	2) Set the OLT object's $slot property
 */
public function getOltPonPortProperties( $port ) {
	$this->port = $port;
	if( empty( $this->card ) ) {
		throw new Exception( "OLT object's PON slot not specified" );
	}
	$this->oidPonPortServiceState = "1.3.6.1.2.1.2.2.1.7." . $this->card . "0000" . $port;
	$this->oidPonPortDeploymentRange = "1.3.6.1.4.1.664.6.10000.76.1.1.2.2.1.7." . $this->card . "0000" . $port;  // 1 = standard (20 Km/Max Rate 1.2 Gbps), 2 = Extended (37.5 Km/Max Rate 890 Mbps), 3 = Maximum (37.5 Km/Max Rate 1.2 Gbps)
	$this->oidPonPortAutoActivateMode = "1.3.6.1.4.1.664.6.10000.76.1.1.2.2.1.3." . $this->card . "0000" . $port;  // 1 = Enable, 2 = Disable
	$this->oidPonPortAutoDiscoverMode = "1.3.6.1.4.1.664.6.10000.76.1.1.2.2.1.2." . $this->card . "0000" . $port;  // 1 = Enable, 2 = Disable

	$this->ponPortServiceState = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonPortServiceState );
	$this->ponPortDeploymentRange = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonPortDeploymentRange );
	$this->ponPortAutoActivateMode = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonPortAutoActivateMode );
	$this->ponPortAutoDiscoverMode = doSnmpGet( $this->ipAddress, $this->communityString, $this->oidPonPortAutoDiscoverMode );
}

/*
 * function getOltOnts()
 * This function returns an array of ONT attributes indexed by ONT AIDs.
 * Each ONT array entry gets:
 *	- serial number
 *	- CLEI code
 *	- administrative status
 *	- operational status
 * Each SNMP walk could return a different subset of ONTs, but generally does not.
 *
 * A serial number array object entry example:
 *	[.1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.1.1074339840] => stdClass Object
 *		( [type] => 4 [value] => ADTN13020318 )
 *
 * NOTES:
 * ) Calls to doSnmpWalk() are tuned (longer timeouts, more retries) for slow responding OLTs.
 */
public function getOltOnts() {
	$ontArray = array();

	// Walk MIB for all ONT serial numbers
	$oidOltSerialNumbers = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.1";
	$objectArray = doSnmpWalk($this->ipAddress, $this->communityString, $oidOltSerialNumbers, 2000000, 2 );
	if( ! empty( $objectArray ) ) {
		foreach( $objectArray as $mibTableIndex => $objectSerialNumber ) {
			if( preg_match('/\d+$/', $mibTableIndex, $match) ) {
				$ifIndex = $match[0];
				$ontAid = convertMibIndexToOntAid($ifIndex);
				$ontArray[$ontAid]['serialNumber'] = trim( $objectSerialNumber->value );
			}
		}
	}

	// Walk MIB for all ONT CLEI codes
	$oidOltCleiCodes = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.5";
	$objectArray = doSnmpWalk($this->ipAddress, $this->communityString, $oidOltCleiCodes, 2000000, 2 );
	if( ! empty( $objectArray ) ) {
		foreach( $objectArray as $mibTableIndex => $objectCleiCode ) {
			if( preg_match('/\d+$/', $mibTableIndex, $match) ) {
				$ifIndex = $match[0];
				$ontAid = convertMibIndexToOntAid($ifIndex);
				$ontArray[$ontAid]['cleiCode'] = trim( $objectCleiCode->value );
				$ontArray[$ontAid]['model'] = adtranCleiCodeToModel( $ontArray[$ontAid]['cleiCode'] );
			}
		}
	}

	// Walk administrative states MIB for all port types (POTS, Ethernet, ...)
	$oidOltAdminStates = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.1";
	$objectArray = doSnmpWalk($this->ipAddress, $this->communityString, $oidOltAdminStates, 2000000, 2 );
	if( ! empty( $objectArray ) ) {
		foreach( $objectArray as $mibTableIndex => $objectAdminState ) {
			if( preg_match('/\d+$/', $mibTableIndex, $match) ) {
				$ifIndex = $match[0];
				$ontAid = convertMibIndexToOntAid($ifIndex);
				$portIndex = $ifIndex & 0x3F;	// Decode six lower-order bits (0-5)
				$portType = ($ifIndex & 0x3C0) >> 6;	// Decode bits 6-9
				$portTypeText = mapKeyFromArray( $portType,
					array(1 => "ethernet", 2 => "pots", 3 => "rfVideo", 4 => "voip", 5 => "ds3", 6 => "ds1"), "unknownPortAdminState" );
				$ontArray[$ontAid][$portTypeText][$portIndex]["adminState"] = $objectAdminState->value;
			}
		}
	}

	// Walk operational states MIB for all port types (POTS, Ethernet, ...)
	$oidOltOperationalStates = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.10";
	$objectArray = doSnmpWalk($this->ipAddress, $this->communityString, $oidOltOperationalStates, 2000000, 2 );
	if( ! empty( $objectArray ) ) {
		foreach( $objectArray as $mibTableIndex => $objectOperationalState ) {
			if( preg_match('/\d+$/', $mibTableIndex, $match) ) {
				$ifIndex = $match[0];
				$ontAid = convertMibIndexToOntAid($ifIndex);
				$portIndex = $ifIndex & 0x3F;	// Decode six lower-order bits (0-5)
				$portType = ($ifIndex & 0x3C0) >> 6;	// Decode bits 6-9
				$portTypeText = mapKeyFromArray( $portType,
					array(1 => "ethernet", 2 => "pots", 3 => "rfVideo", 4 => "voip", 5 => "ds3", 6 => "ds1"), "unknownPortOperState" );
				$ontArray[$ontAid][$portTypeText][$portIndex]["operState"] = $objectOperationalState->value;
			}
		}
	}

	$this->ontArray = $ontArray;
	return $ontArray;
}

/*
 * function translateProductCodeToText( $productCode )
 */
public function translateProductCodeToText( $productCode ) {
	$productCodeTable = array(
		1179 => "adTA5k2pSfp25GigGPON",	// (1187501G1) TA5000 2.5G 2-PORT SFP Based GPON OLT
		1189 => "adTA5k4pSfp25GigGPON"	// (1187502F1) TA5000 2.5G 4-PORT SFP Based GPON OLT
	);
	return $productCodeTable[ $productCode ];
}

/*
 *
 */
public function translateCardServiceStateToText( $cardServiceState ) {
	switch( $cardServiceState ) {
		case "1":
			return "In Service";
			break;
		case "2":
			return "Out Of Service UAS";
			break;
		case "3":
			return "Out Of Service MA";
			break;
		default:
			return "Missing";
	}
}

/*
 *
 */
public function translatePonPortDeploymentRangeToText( $ponPortDeploymentRange ) {
	switch( $ponPortDeploymentRange ) {
		case "1":
			return "Standard 20 Km/1.2 Gbps";
			break;
		case "2":
			return "Extended 37.5 Km/890 Mbps";
			break;
		case "3":
			return "Maximum 37.5 Km/890 Mbps";
			break;
		default:
			return "Missing";
	}
}

/*
 *
 */
public function translatePonPortAutoActivateModeToText( $ponPortAutoActivateMode ) {
	switch( $ponPortAutoActivateMode ) {
		case "1":
			return "Enabled";
			break;
		case "2":
			return "Disabled";
			break;
		default:
			return "Missing";
	}
}

/*
 *
 */
public function translatePonPortAutoDiscoverModeToText( $ponPortAutoDiscoverMode ) {
	switch( $ponPortAutoDiscoverMode ) {
		case "1":
			return "Enabled";
			break;
		case "2":
			return "Disabled";
			break;
		default:
			return "Missing";
	}
}

}
