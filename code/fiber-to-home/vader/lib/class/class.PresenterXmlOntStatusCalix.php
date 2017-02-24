<?php

require_once 'class.PresenterXmlOntStatus.php';

class PresenterXmlOntStatusCalix extends PresenterXmlOntStatus {

function setOntElement() {
	parent::setOntElement();
}

function setPotsElement() {
	parent::setPotsElement();
	for( $potsPortIndex=0; $potsPortIndex < $this->ont->deviceConfig['potsPortCount']; $potsPortIndex++ ) {
		$this->xml->pots->potsPort[$potsPortIndex]['id'] = $potsPortIndex + 1;
		$this->xml->pots->potsPort[$potsPortIndex]->administrativeState = $this->ont->potsPorts[$potsPortIndex]['basics']['administrativeState'];
		$this->xml->pots->potsPort[$potsPortIndex]->subscriberId = $this->ont->potsPorts[$potsPortIndex]['basics']['subscriberId'];
		$this->xml->pots->potsPort[$potsPortIndex]->description = $this->ont->potsPorts[$potsPortIndex]['basics']['description'];
		$this->xml->pots->potsPort[$potsPortIndex]->signalingMode = $this->ont->potsPorts[$potsPortIndex]['basics']['signalingMode'];
		$this->xml->pots->potsPort[$potsPortIndex]->type = $this->ont->potsPorts[$potsPortIndex]['details']['type'];
		$this->xml->pots->potsPort[$potsPortIndex]->crv = $this->ont->potsPorts[$potsPortIndex]['details']['crv'];
		$this->xml->pots->potsPort[$potsPortIndex]->operationalState = $this->ont->potsPorts[$potsPortIndex]['stats']['operationalState'];
		$this->xml->pots->potsPort[$potsPortIndex]->hookState = $this->ont->potsPorts[$potsPortIndex]['stats']['hookState'];
		$this->xml->pots->potsPort[$potsPortIndex]->configurationStatus = $this->ont->potsPorts[$potsPortIndex]['stats']['configurationStatus'];
		$this->xml->pots->potsPort[$potsPortIndex]->serviceStatus = $this->ont->potsPorts[$potsPortIndex]['stats']['serviceStatus'];
		$this->xml->pots->potsPort[$potsPortIndex]->callState = $this->ont->potsPorts[$potsPortIndex]['stats']['callState'];
		$this->xml->pots->potsPort[$potsPortIndex]->powerStatus = $this->ont->potsPorts[$potsPortIndex]['powerStatii']['powerStatus'];
	}
}

/*
 * function setEthernetElement()
 *
 * Additional unused parameters:
 *	'services' =>
 *      array (
 *        0 =>
 *        array (
 *          'outerTag' => '3135',
 *          'innerTag' => 'none',
 *          'multicastIndex' => NULL,
 *          'multicastName' => NULL,
 */
function setEthernetElement() {
	if( ! isset( $this->ont->gePorts ) ) return;
	// Check that (SNMP) parameter query response counts match expected port count
	if( count( $this->ont->gePorts ) != $this->ont->deviceConfig['ethernetPortCount'] ) {
		$this->addResponseNotice( NOTICE_TYPE_WARNING, "Parameter retrieval count mismatches port count: gePorts" );
	}
	for( $ethernetPortIndex=0; $ethernetPortIndex < $this->ont->deviceConfig['ethernetPortCount']; $ethernetPortIndex++ ) {
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]['id'] = $ethernetPortIndex + 1;
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->administrativeState = $this->ont->gePorts[$ethernetPortIndex+1]['basics']['administrativeState'];
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->subscriberId = $this->ont->gePorts[$ethernetPortIndex+1]['basics']['subscriberId'];
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->disableOnBatteryFlag = $this->ont->gePorts[$ethernetPortIndex+1]['basics']['disableOnBatteryFlag'];
		// TODO: More Ethernet basics parameters are available

		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->operationalState = $this->ont->gePorts[$ethernetPortIndex+1]['state']['operationalState'];
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->status = $this->ont->gePorts[$ethernetPortIndex+1]['state']['status'];
		$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->powerStatus = $this->ont->gePorts[$ethernetPortIndex+1]['state']['powerStatus'];
		// TODO: More Ethernet state parameters are available

		if( isset( $this->ont->gePorts[$ethernetPortIndex+1]['services'] ) ) {
			$serviceId = -1;
			foreach( $this->ont->gePorts[$ethernetPortIndex+1]['services'] as $serviceArray ) {
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[++$serviceId]['id'] = $serviceId + 1;
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->type = $serviceArray['type'];
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->bandwidthProfile = $serviceArray['bandwidthProfile'];
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->vlan = $serviceArray['vlanId'];
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->name = $serviceArray['name'];
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->administrativeState = $serviceArray['administrativeState'];
				$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->tagAction = $serviceArray['tagAction'];
			}
		}
	}
}

}
