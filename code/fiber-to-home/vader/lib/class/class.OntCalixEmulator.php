<?php
/*
 * class OntCalixEmulator
 */
require_once dirname(__FILE__).'/../../include/config.php';
//require_once 'lib-text.php';
//require_once 'lib-database.php';

class OntCalixEmulator {
public $aidArray;
public $cmsDevice;
public $deviceEmulationDefinitionFile;
public $deviceEmulationXmlString;	// XML string in the emulation file
public $deviceEmulationXml;	// A SimpleXMLElement object created from the XML string in the emulation file
private $deviceVendor = DEVICE_VENDOR_CALIX;
public $ontModel;

	function __construct ($aidArray, $cmsDevice = NULL) {
		global $directoryTestDevice;

		$this->aidArray = $aidArray;
		$this->cmsDevice = $cmsDevice;
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

		// Read ONT model from device emulation definition XML file
		$this->ontModel = $this->deviceEmulationXml->{'ONT-STAT'}->{'ONT-HW-VERSION-NO'};
	}

	function getVendor () {
		return $this->deviceVendor;
	}

	function getXsdUrl () {
		global $urlXsdDirectory;
		return $urlXsdDirectory . "/calix/" . $this->ontModel . ".xsd";
	}

	function getDeviceEmulationXml () {
		return $this->deviceEmulationXml;	// SimpleXMLElement object
	}

	function getOntStatXmlString () {
		return $this->deviceEmulationXml->{'ONT-STAT'}->asXML();
	}

	function getPotsPortStatXmlString () {
		return $this->deviceEmulationXml->{'ONTPOTS-PORT-STAT'}->asXML();
	}

	function getEthernetPortStatXmlString () {
		return $this->deviceEmulationXml->{'ETHERNET-PORT-STAT'}->asXML();
	}

	function getEthernetPortServicesXmlString () {
		return $this->deviceEmulationXml->{'ETHERNET-PORT-SERVICES'}->asXML();
	}

	function getOntAlarmsXmlString () {
		return $this->deviceEmulationXml->{'ONT-ALARM'}->asXML();
	}
}

