<?php
/*
 * class OntAdtranTA300
 *
 * This class models an Adtran ONT of the TA300 product line.
 *
 * Because some properties of the ONT are dependent upon the PON card slot number ($this->card), this class
 * doesn't cleanly separate the ONT object from the PON card object (which isn't modeled as a separate class anyhow).
 */
require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-snmp.php';

define("ONT_ERROR_ONT_NONEXISTENT",				1);

class OntAdtranTA300 {
public $communityString;
public $ipAddressOlt;
public $deviceConfig = NULL;

function __construct( $ipAddressOlt, $card, $port, $ontNumber ) {
	$this->ipAddressOlt = $ipAddressOlt;
	$this->card = $card;
	$this->port = $port;
	$this->ontNumber = $ontNumber;

	initializeSnmp();
	$this->communityString = getSnmpCommunityString(SNMP_DEVICE_ADTRAN);

	$this->getShelfBase();	// Call first to set ONT's interface index (encoded bits)

	// Use query of ONT's serial number to decide if the ONT exists
	$this->oidSerialNumber = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.1." . $this->interfaceIndexOnt;
	$this->serialNumber = doSnmpGet($this->ipAddressOlt, $this->communityString, $this->oidSerialNumber);
	if( empty( $this->serialNumber ) ) {
		throw new Exception( "ONT not found", ONT_ERROR_ONT_NONEXISTENT );
	}

	$this->setDeviceConfig();
	$this->computeInterfaceIndexes();
	$this->setOntOids();
	$this->getOntProperties();
}

public function __toString() {
	return var_export( $this, TRUE );
}

/*
 * function getShelfBase()
 *
 * This function queries the PON card's shelf base (single or multi), which determines the method used by the
 * card to encode the interface index bits (and whether 32 or 64 ONTs can be addressed).
 *
 * Importantly, this function also sets the interface index (bits) for the ONT itself (which is required upfront to make
 * queries about the ONT).
 *
 * Refer to the Adtran Carrier Network SNMP ifIndex Application Guide document (Document Number: 65KIFAPP-49C December 2012),
 * section "Remote Terminal Proxy Extended ifIndex section".
 * Note that, the documentation can be confusing, in that the least significant bit (LSB) is named Bit 0.
 *
 * Example ifIndex bit encoding for ONT-1-1-4-2-15:
 *	1075985408 = 1000000001000100011110000000000	(P/N 1187500E1, 1187501G1)	Bit30=1, Bit29=0
 *	1678261248 = 1100100000010000011110000000000	(P/N 1187502F1)				Bit30=1, Bit29=1
 *
 * Also refer to the Vader Programming Guide for details on ifIndex bit encoding.
 */
private function getShelfBase() {

	$this->oidPonCardHexFeatures = "1.3.6.1.4.1.664.5.70.1.1.1.3." . $this->card;
	$this->ponCardHexFeaturesString = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPonCardHexFeatures );

	$this->shelfBase = PON_CARD_MULTI_SHELF_BASED;	// Supports up to 32 ONTs per PON port
	$singleShelfFeatureHex = "67";
	if( hasAdtranPonCardHexFeature( $this->ponCardHexFeaturesString, $singleShelfFeatureHex ) ) {
		$this->shelfBase = PON_CARD_SINGLE_SHELF_BASED;	// Supports up to 64 ONTs per PON port
	}

	switch( $this->shelfBase ) {
		case PON_CARD_MULTI_SHELF_BASED:
			/*
			 * Note that this is the original Vader code, which doesn't exactly follow the Adtran documentation convention,
			 * and doesn't consider a variable shelf number (Vader always assumes shelf 1); see code for next card in this switch statement.
			 */
			$this->extendedIfIndexBits = "10";	// Bit30=1, Bit29=0

			// TA5K or TA5006 slot number (10 bit zero-padded binary of slot number)
			// NOTE: this doesn't not correctly factor in a variable shelf number (per the Adtran documentation)
			$this->ponCardBits = str_pad( decbin($this->card), 10, "0", STR_PAD_LEFT );

			// PON Port (1 - 4) (3 bits)
			$this->ponPortBits = str_pad( decbin($this->port), 3, "0", STR_PAD_LEFT );

			// ONT # (1 - 32) (6 bits)
			$this->ontNumberBits = str_pad( decbin($this->ontNumber), 6, "0", STR_PAD_LEFT );
			break;

		case PON_CARD_SINGLE_SHELF_BASED:
			$this->extendedIfIndexBits = "11";	// Bit30=1, Bit29=1

			// TA5K or TA5006 slot number (5 bit zero-padded binary of slot number); only one shelf allowed (per documentation)
			$this->ponCardBits = str_pad( decbin($this->card), 5, "0", STR_PAD_LEFT );

			// PON Port (1 - 4) (6 bits)
			$this->ponPortBits = str_pad( decbin($this->port), 6, "0", STR_PAD_LEFT );

			// ONT # (1 - 32) (8 bits)
			$this->ontNumberBits = str_pad( decbin($this->ontNumber), 8, "0", STR_PAD_LEFT );
			break;
	}

	// NOTE: If technology/service type and ONT port bits are set to 0, then the interfaceIndex refers to the ONT itself
	$this->interfaceIndexOnt = bindec($this->extendedIfIndexBits . $this->ponCardBits . $this->ponPortBits . $this->ontNumberBits . "0000" . "000000");
	$this->interfaceIndexOntBinary = decbin( $this->interfaceIndexOnt );
}

/*
 * function computeInterfaceIndexes()
 */
private function computeInterfaceIndexes() {

	// Technology/Service type (4 bits)
	$portTypeBitEncodeTable = array(
		"ethernet" => str_pad(decbin(1), 4, "0", STR_PAD_LEFT),
		"pots" => str_pad(decbin(2), 4, "0", STR_PAD_LEFT),
		"rfvideo" => str_pad(decbin(3), 4, "0", STR_PAD_LEFT),
		"voip" => str_pad(decbin(4), 4, "0", STR_PAD_LEFT),
		"ds3" => str_pad(decbin(5), 4, "0", STR_PAD_LEFT),
		"ds1" => str_pad(decbin(6), 4, "0", STR_PAD_LEFT) );

	// ONT Technology/Service port number (0 - 63)
	// TODO: Add AdTran physical ports
	$portNumberBitEncodeTable = array(
		"port1" => str_pad(decbin(1), 6, "0", STR_PAD_LEFT),
		"port2" => str_pad(decbin(2), 6, "0", STR_PAD_LEFT) );

	for( $iii=0; $iii<$this->deviceConfig['potsPortCount']; $iii++ ) {
		$this->interfaceIndexPots[$iii] = bindec($this->extendedIfIndexBits . $this->ponCardBits . $this->ponPortBits . $this->ontNumberBits .
				$portTypeBitEncodeTable['pots'] . str_pad(decbin($iii+1), 6, "0", STR_PAD_LEFT));
		$this->interfaceIndexPotsBinary[$iii] = decbin( $this->interfaceIndexPots[$iii] );
	}
	for( $iii=0; $iii<$this->deviceConfig['ethernetPortCount']; $iii++ ) {
		$this->interfaceIndexEthernet[$iii] = bindec($this->extendedIfIndexBits . $this->ponCardBits . $this->ponPortBits . $this->ontNumberBits .
				$portTypeBitEncodeTable['ethernet'] . str_pad(decbin($iii+1), 6, "0", STR_PAD_LEFT));
		$this->interfaceIndexEthernetBinary[$iii] = decbin( $this->interfaceIndexEthernet[$iii] );
	}
	for( $iii=0; $iii<$this->deviceConfig['rfVideoPortCount']; $iii++ ) {
		$this->interfaceIndexRfVideo[$iii] = bindec($this->extendedIfIndexBits . $this->ponCardBits . $this->ponPortBits . $this->ontNumberBits .
				$portTypeBitEncodeTable['rfvideo'] . str_pad(decbin($iii+1), 6, "0", STR_PAD_LEFT));
		$this->interfaceIndexRfVideoBinary[$iii] = decbin( $this->interfaceIndexRfVideo[$iii] );
	}
}

/*
 * function setOntOids()
 */
private function setOntOids() {
	$this->oidDescription = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.2." . $this->interfaceIndexOnt;
	$this->oidHardwareVersion = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.13." . $this->interfaceIndexOnt;
	$this->oidPartNumber = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.12." . $this->interfaceIndexOnt;
	$this->oidSoftwareVersion = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.7." . $this->interfaceIndexOnt;
	$this->oidSoftwareVersionActive = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.10." . $this->interfaceIndexOnt;
	$this->oidBatteryBackupFlag = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.3." . $this->interfaceIndexOnt;	// 0 or )
	$this->oidAdministrativeState = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.4." . $this->interfaceIndexOnt;	// 1 = InService, 2 = Out Of Service UAS, 3 = Out of Service MA
	$this->oidOperationalState = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.7." . $this->interfaceIndexOnt;	// 0 = Init, 1 = Discovering, 2 = Disocvered, 3 = Rejected, 4 = Up, 5 = Down
	$this->oidUptime = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.6." . $this->interfaceIndexOnt;	// Hundredths of a second

	$this->oidProvisionedSpeedUpstream = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.9." . $this->interfaceIndexOnt;	// Integer, kbps - multiples of 64k)
	$this->oidProvisionedSpeedDownstream = "1.3.6.1.4.1.664.5.70.2.6.1.3." . $this->interfaceIndexOnt;	// Integer, kbps

	$this->oidPotsServiceMode = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.13." . $this->interfaceIndexOnt;	// 0 = none, 1 = GR-303, 2 = SIP, 3 = MGCP
	$this->oidPotsMacAddress = "1.3.6.1.4.1.664.6.10000.76.1.1.2.3.1.8." . $this->interfaceIndexOnt;	// GR303 voice MAC address

	for( $iii=0; $iii<$this->deviceConfig['potsPortCount']; $iii++ ) {
		$this->oidPotsAdministrativeStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.1." . $this->interfaceIndexPots[$iii];	// 1 = In Service, 2 = Out Of Service UAS, 3 = Out Of Service MA
		$this->oidPotsOperationalStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.10." . $this->interfaceIndexPots[$iii];	// 1 = Up, 2 = Down
		$this->oidPotsHookStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.9." . $this->interfaceIndexPots[$iii];
		$this->oidPotsIpAddresses[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.5.1.10." . $this->interfaceIndexPots[$iii];
		//$this->oidPots303CrvPorts[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.12." . $this->interfaceIndexPots[$iii];	// Not used, refer to Vader Interface Specification
		$this->oidPotsSignalingModes[$iii] = "1.3.6.1.4.1.664.2.753.1.2.1.1.1." . $this->interfaceIndexPots[$iii];
	}

	for( $iii=0; $iii<$this->deviceConfig['ethernetPortCount']; $iii++ ) {
		$this->oidEthernetAdministrativeStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.1." . $this->interfaceIndexEthernet[$iii]; // 1 = In Service, 2 = Out Of Service UAS, 3 = Out Of Service MA
		$this->oidEthernetOperationalStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.10." . $this->interfaceIndexEthernet[$iii];	// 1 = Up, 2 = Down
		$this->oidEthernetAutoDetectStates[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.2." . $this->interfaceIndexEthernet[$iii]; // 0 = Auto/Auto, 1 = Ten/Full, 2 = Hundred/Full, 3 = Thousand/Full, 4 = Auto/Full
		$this->oidEthernetPortConfiguration[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.7." . $this->interfaceIndexEthernet[$iii];  // 0 = Unknown, 1 = 10BT/FullDuplex, 2 = 100BT/FullDuplex, 3 = Gig-E/FullDuplex, 17 = 10BT/HalfDuplex, 18 = 100BT/HalfDuplex, 19 = Gig-E/HalfDuplex
		$this->oidEthFlowIndexStrings[$iii] = "1.3.6.1.4.1.664.5.70.2.1.1.3." . $this->interfaceIndexEthernet[$iii] . ".0";	// Ethernet flow indexes; ie. MIB variables contain indexes into other MIB arrays
	}

	for( $iii=0; $iii<$this->deviceConfig['rfVideoPortCount']; $iii++ ) {
		$this->oidRfVideoAdministrativeState[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.1." . $this->interfaceIndexRfVideo[$iii]; // 1 = In Service, 2 = Out Of Service UAS, 3 = Out Of Service MA
		$this->oidRfVideoOperationalState[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.3.2.1.10." . $this->interfaceIndexRfVideo[$iii]; // 1 = Up, 2 = Down
		$this->oidEthernetPowerControlState[$iii] = "1.3.6.1.4.1.664.6.10000.76.1.1.2.4.1.9." . $this->interfaceIndexRfVideo[$iii]; // 0 = Disabled, 1 = Enabled
	}
}

/*
 * function getOntProperties()
 */
private function getOntProperties() {
	$this->description = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidDescription );
	$this->hardwareVersion = trim( doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidHardwareVersion ) );
	$this->partNumber = trim( doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPartNumber ) );
	$this->softwareVersion = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidSoftwareVersion );
	$this->softwareVersionActive = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidSoftwareVersionActive );
	$this->batteryBackupFlag = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidBatteryBackupFlag );
	$this->administrativeState = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidAdministrativeState );
	$this->operationalState = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidOperationalState );
	$this->uptime = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidUptime );

	$this->provisionedSpeedUpstream = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidProvisionedSpeedUpstream );
	$this->provisionedSpeedDownstream = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidProvisionedSpeedDownstream );

	$this->potsServiceMode = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsServiceMode );
	$this->potsMacAddress = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsMacAddress );
	$this->potsAdministrativeStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsAdministrativeStates );
	$this->potsOperationalStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsOperationalStates );
	$this->potsHookStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsHookStates );
	$this->potsIpAddresses = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsIpAddresses );
	//$this->pots303CrvPorts = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPots303CrvPorts );
	$this->potsSignalingModes = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidPotsSignalingModes );

	$this->ethernetAdministrativeStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidEthernetAdministrativeStates );
	$this->ethernetOperationalStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidEthernetOperationalStates );
	$this->ethernetAutoDetectStates = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidEthernetAutoDetectStates );
	$this->ethernetPortConfiguration = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidEthernetPortConfiguration );

	if( ! empty( $this->deviceConfig['rfVideoPortCount'] ) ) {
		$this->rfVideoAdministrativeState = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidRfVideoAdministrativeState );
		$this->rfVideoOperationalState = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidRfVideoOperationalState );
		$this->ethernetPowerControlState = doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidEthernetPowerControlState );
	}

	/*
	 * Handle multiple services per Ethernet port
	 */
	$this->ethFlowIndexStrings = doSnmpGet($this->ipAddressOlt, $this->communityString, $this->oidEthFlowIndexStrings);
	$ethernetPortIndex = 0;
	foreach( $this->ethFlowIndexStrings as $ethFlowIndexString ) {
		$ethFlowIndex = strtok($ethFlowIndexString, ",");
		do {
			$ethFlowIndex = trim($ethFlowIndex);
			if( is_numeric( $ethFlowIndex ) ) {
				$this->oidEthVlanAssignmentsByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.4." . $this->card . "." . $ethFlowIndex;  // VLAN assignment for the port
				$this->oidEthProfileNamesByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.55." . $this->card . "." . $ethFlowIndex;  //  Ethernet profile name for the port
				$this->oidEthEncapModesByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.30." . $this->card . "." . $ethFlowIndex;  //  Eth Encapsulation Mode: 1 = IPoE, 2 = PPPoE, 3 = PPPoA, 4 = Not Applicable, 5 = ATMoE, 6 = PPPoA VcMux, 7 = Auto Detect
				$this->oidEthFlowIgmpProcessingsByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.36." . $this->card . "." . $ethFlowIndex;	// Refer to Vader SNMP Guide
				//$oid_dslam_flow_name_port1 = "1.3.6.1.4.1.664.5.70.2.2.1.2." . $slot . "." . $ethindex_ethflow_port1;  // Ethernet Flow Name for port; only used in gpon-web-provision.php
				//$this->oidEthFlowPPPoEProcessingByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.62." . $this->card . "." . $ethFlowIndex;	// Refer to Vader SNMP Guide
				//$this->oidEthFlowDhcpRelayByService[$ethernetPortIndex][] = "1.3.6.1.4.1.664.5.70.2.2.1.33." . $this->card . "." . $ethFlowIndex;	// Refer to Vader SNMP Guide
			} else {
				$this->oidEthVlanAssignmentsByService[$ethernetPortIndex][] = NULL;
				$this->oidEthProfileNamesByService[$ethernetPortIndex][] = NULL;
				$this->oidEthEncapModesByService[$ethernetPortIndex][] = NULL;
				$this->oidEthFlowIgmpProcessingsByService[$ethernetPortIndex][] = NULL;
			}
			$ethFlowIndex = strtok(",");
		} while( $ethFlowIndex !== FALSE );
		$ethernetPortIndex++;
	}
	foreach( $this->oidEthVlanAssignmentsByService as $oidEthVlanAssignmentsByPort ) {
		$this->ethVlanAssignmentsByService[] = doSnmpGet($this->ipAddressOlt, $this->communityString, $oidEthVlanAssignmentsByPort);
	}
	foreach( $this->oidEthProfileNamesByService as $oidEthProfileNamesByPort ) {
		$this->ethProfileNamesByService[] = doSnmpGet($this->ipAddressOlt, $this->communityString, $oidEthProfileNamesByPort);
	}
	foreach( $this->oidEthEncapModesByService as $oidEthEncapModesByPort ) {
		$this->ethEncapModesByService[] = doSnmpGet($this->ipAddressOlt, $this->communityString, $oidEthEncapModesByPort);
	}
	foreach( $this->oidEthFlowIgmpProcessingsByService as $oidEthFlowIgmpProcessingsByPort ) {
		$this->ethFlowIgmpProcessingsByService[] = doSnmpGet($this->ipAddressOlt, $this->communityString, $oidEthFlowIgmpProcessingsByPort);
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

	$this->oidCleiCode = "1.3.6.1.4.1.664.6.10000.76.1.1.3.1.1.5." . $this->interfaceIndexOnt;
	$this->cleiCode = trim( doSnmpGet( $this->ipAddressOlt, $this->communityString, $this->oidCleiCode ) );

	switch( $this->cleiCode ) {
		case "":
			$this->deviceConfig['model'] = "N/A";
			// NOTE: still assume there are a default number of ports, as set above
			break;
		case "BVMB600FRB":
			$this->deviceConfig['model'] = "TA324";
			$this->deviceConfig['ethernetPortCount'] = 4;
			break;
		case "BVMBM10FRA":
			$this->deviceConfig['model'] = "TA324RG";
			$this->deviceConfig['ethernetPortCount'] = 4;
			break;
		case "BVMBB10FRA":
			$this->deviceConfig['model'] = "TA334";
			$this->deviceConfig['ethernetPortCount'] = 4;
			$this->deviceConfig['rfVideoPortCount'] = 1;
			break;
		case "BVL3AFTDTA":
			$this->deviceConfig['model'] = "TA351";
			$this->deviceConfig['ethernetPortCount'] = 1;
			break;
		case "BVL3AP8DTA":
			$this->deviceConfig['model'] = "TA351 2nd Gen";
			$this->deviceConfig['ethernetPortCount'] = 1;
			break;
		case "BVL3AFUDTA":
			$this->deviceConfig['model'] = "TA352";
			break;
		case "BVL3AP9DTA":
		case "BVL3ATUDTA":
			$this->deviceConfig['model'] = "TA352 2nd Gen";
			break;
		case "BVMB900FRA":
			$this->deviceConfig['model'] = "TA362";
			$this->deviceConfig['rfVideoPortCount'] = 1;
			break;
		case "BVM8K00ERA":
			$this->deviceConfig['model'] = "TA362S";
			$this->deviceConfig['rfVideoPortCount'] = 1;
			break;
		case "BVM9N00BRA":
			$this->deviceConfig['model'] = "TA372";
			$this->deviceConfig['potsPortCount'] = 8;
			$this->deviceConfig['ds1PortCount'] = 4;
			break;
		case "BVMB400FRA":
			$this->deviceConfig['model'] = "TA372R 2nd Gen";
			$this->deviceConfig['potsPortCount'] = 8;
			$this->deviceConfig['ds1PortCount'] = 4;
			$this->deviceConfig['rfVideoPortCount'] = 1;
			break;
		case "BVM9Z00BRA":
			$this->deviceConfig['model'] = "TA374";
			$this->deviceConfig['ethernetPortCount'] = 4;
			$this->deviceConfig['potsPortCount'] = 4;
			break;
		default:
			$this->deviceConfig['model'] = "Unknown";
			// NOTE: still assume there are a default number of ports, as set above
			break;
	}
}

public function translateBatteryBackupFlagToText( $ontBatteryBackupFlag ) {
	switch( $ontBatteryBackupFlag ) {
		case "0":
			return "False";
			break;
		case "1":
			return "True";
			break;
		default:
			return "Missing";
	}
}
public function tranlateOntAdministrativeStateToText( $ontAdministrativeState ) {
	switch( $ontAdministrativeState ) {
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
			return "Unknown";
	}
}
public function translateOntOperationalStateToText( $ontOperationalState ) {
	switch( $ontOperationalState ) {
		case "0":
			return "Initializing";
			break;
		case "1":
			return "Discovering";
			break;
		case "2":
			return "Discovered";
			break;
		case "3":
			return "Rejected";
			break;
		case "4":
			return "Up";
			break;
		case "5":
			return "Down";
			break;
		default:
			return "Unknown";
	}
}

}
