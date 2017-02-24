<?php

require_once 'class.PresenterXml.php';

class PresenterXmlOntStatus extends PresenterXml {

public $olt = NULL;
public $ont = NULL;

function setOntElement() {
	// Set <deviceConfig> element
	if( isset( $this->ont->deviceConfig['model'] ) ) 				$this->xml->ont->deviceConfig->model = $this->ont->deviceConfig['model'];
	if( isset( $this->ont->deviceConfig['potsPortCount'] ) )		$this->xml->ont->deviceConfig->potsPortCount = $this->ont->deviceConfig['potsPortCount'];
	if( isset( $this->ont->deviceConfig['ethernetPortCount'] ) )	$this->xml->ont->deviceConfig->ethernetPortCount = $this->ont->deviceConfig['ethernetPortCount'];
	if( isset( $this->ont->deviceConfig['rfVideoPortCount'] ) )		$this->xml->ont->deviceConfig->rfVideoPortCount = $this->ont->deviceConfig['rfVideoPortCount'];
	if( isset( $this->ont->deviceConfig['ds1PortCount'] ) )			$this->xml->ont->deviceConfig->ds1PortCount = $this->ont->deviceConfig['ds1PortCount'];
	if( isset( $this->ont->deviceConfig['rfAvoPortCount'] ) )		$this->xml->ont->deviceConfig->rfAvoPortCount = $this->ont->deviceConfig['rfAvoPortCount'];
	if( isset( $this->ont->deviceConfig['rfHotPortCount'] ) )		$this->xml->ont->deviceConfig->rfHotPortCount = $this->ont->deviceConfig['rfHotPortCount'];

	if( isset( $this->ont->aid ) )					$this->xml->ont->aid = $this->ont->aid;
	if( isset( $this->ont->cleiCode ) )				$this->xml->ont->cleiCode = $this->ont->cleiCode;
	if( isset( $this->ont->serialNumber ) )			$this->xml->ont->serialNumber = $this->ont->serialNumber;
	if( isset( $this->ont->partNumber ) )			$this->xml->ont->partNumber = $this->ont->partNumber;
	if( isset( $this->ont->description ) )			$this->xml->ont->description = $this->ont->description;
	if( isset( $this->ont->hardwareVersion ) )		$this->xml->ont->hardwareVersion = $this->ont->hardwareVersion;
	if( isset( $this->ont->softwareVersion ) )		$this->xml->ont->softwareVersion = $this->ont->softwareVersion;
	if( isset( $this->ont->administrativeState ) )	$this->xml->ont->administrativeState = $this->ont->administrativeState;
	if( isset( $this->ont->operationalState ) )		$this->xml->ont->operationalState = $this->ont->operationalState;
	if( isset( $this->ont->uptime ) )				$this->xml->ont->uptime = $this->ont->uptime;
	if( isset( $this->ont->batteryBackupFlag ) )	$this->xml->ont->batteryBackupFlag = $this->ont->batteryBackupFlag;
	if( isset( $this->ont->subscriberId ) )			$this->xml->ont->subscriberId = $this->ont->subscriberId;
}

function setPotsElement() {
	if( isset( $this->pots->potsServiceMode ) ) 	$this->xml->ont->pots->serviceMode = $this->ont->potsServiceMode;	// TODO: Translate from code
}

}
