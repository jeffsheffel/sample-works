<?php
/*
 * class OltCalixEmulator
 */
require_once dirname(__FILE__).'/../../include/config.php';
//require_once 'lib-text.php';
//require_once 'lib-database.php';

class OltCalixEmulator {
public $oltIp;
public $aidArray;
public $deviceEmulationDefinitionFile;
public $deviceEmulationXmlString;
public $deviceEmulationXml;
//public $deviceTid;	// Use TID in emulation definition file
private $deviceVendor = DEVICE_VENDOR_CALIX;

	function __construct ($oltIp, $aidArray) {
		global $directoryTestDevice;

		$this->oltIp = $oltIp;
		$this->aidArray = $aidArray;
		list($shelf, $card, $port, $ontNumber, $ontPort, $ontId) = $aidArray;
		$this->deviceEmulationDefinitionFile = $directoryTestDevice."/emulated-ont-".$shelf."-".$card."-".$port."-".$ontNumber.".xml";
		if ( !file_exists($this->deviceEmulationDefinitionFile) ) {
			throw new Exception("Non-existent device emulation file: $this->deviceEmulationDefinitionFile", EMULATION_ERROR_MISSING_DEVICE_DEFINITION_FILE);
		}
		$this->deviceEmulationXmlString = file_get_contents( $this->deviceEmulationDefinitionFile );
		try {
			$this->deviceEmulationXml = new SimpleXMLElement( $this->deviceEmulationXmlString );
		}
		catch (Exception $e) {
			throw new Exception("Failed to parse device emulation file: $this->deviceEmulationDefinitionFile", EMULATION_ERROR_PARSE_FAILURE);
		}
		/* Calculate OLT uptime and update XML
		 * <OLT-UPTIME> in the emulated device definition XML is a timestamp representing device startup time.
		 * The uptime is calculated by subtracting the startup time from the current time.
		 */
		$deviceStartTimestamp = $this->deviceEmulationXml->{'NETWORK-LOCATION'}->{'OLT-UPTIME'};
		$deviceStartTime = strtotime( $deviceStartTimestamp );
		$deviceUptime = time() - $deviceStartTime;	// Seconds
		$deviceUptimeDays = (int)($deviceUptime / (24 * 60 * 60));
		$deviceUptimeHours = (int)(($deviceUptime % (24 * 60 * 60)) / (60 * 60));
		$deviceUptimeMinutes = (int)(($deviceUptime % (60 * 60)) / 60);
		$deviceUptimeSeconds = (int)($deviceUptime % 60);
		$deviceUptimeString = $deviceUptimeDays . ":" . $deviceUptimeHours . ":" . $deviceUptimeMinutes . ":" . $deviceUptimeSeconds;
		$this->deviceEmulationXml->{'NETWORK-LOCATION'}->{'OLT-UPTIME'} = $deviceUptimeString;
	}

	function getVendor () {
		return $this->deviceVendor;
	}

	function getOltStatXmlString () {
		return $this->deviceEmulationXml->{'NETWORK-LOCATION'}->asXML();
	}

	function getCardStatXmlString () {
		return $this->deviceEmulationXml->{'LINECARD-PORT-STAT'}->asXML();
	}
}
