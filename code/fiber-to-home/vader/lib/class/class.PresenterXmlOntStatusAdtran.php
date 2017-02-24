<?php

require_once 'class.PresenterXmlOntStatus.php';

class PresenterXmlOntStatusAdtran extends PresenterXmlOntStatus {

function setOntElement() {
	parent::setOntElement();
	if( isset( $this->ont->softwareVersionActive ) ) $this->xml->ont->softwareVersion = $this->ont->softwareVersionActive;
}

function setPotsElement() {
	parent::setPotsElement();
	foreach( array( 'potsAdministrativeStates' => 'administrativeState' ,
					'potsOperationalStates' => 'operationalState',
					'potsHookStates' => 'hookState',
					'potsSignalingModes' => 'signalingMode' ) as $parameterType => $elementName ) {
		// Check that (SNMP) parameter query response counts match expected port count
		if( count( $this->ont->$parameterType ) != $this->ont->deviceConfig['potsPortCount'] ) {
			$this->addResponseNotice( NOTICE_TYPE_WARNING, "Parameter retrieval count mismatches port count: " . $parameterType );
		}
		for( $potsPortIndex=0; $potsPortIndex < $this->ont->deviceConfig['potsPortCount']; $potsPortIndex++ ) {
			$this->xml->pots->potsPort[$potsPortIndex]['id'] = $potsPortIndex + 1;
			$this->xml->pots->potsPort[$potsPortIndex]->$elementName = $this->ont->{$parameterType}[$potsPortIndex];
		}
	}
}

function setEthernetElement() {
	foreach( array( 'ethernetAdministrativeStates' => 'administrativeState' ,
					'ethernetOperationalStates' => 'operationalState' ) as $parameterType => $elementName ) {
		// Check that (SNMP) parameter query response counts match expected port count
		if( count( $this->ont->$parameterType ) != $this->ont->deviceConfig['ethernetPortCount'] ) {
			$this->addResponseNotice( NOTICE_TYPE_WARNING, "Parameter retrieval count mismatches port count: " . $parameterType );
		}
		for( $ethernetPortIndex=0; $ethernetPortIndex < $this->ont->deviceConfig['ethernetPortCount']; $ethernetPortIndex++ ) {
			$this->xml->ethernet->ethernetPort[$ethernetPortIndex]['id'] = $ethernetPortIndex + 1;
			$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->$elementName = $this->ont->{$parameterType}[$ethernetPortIndex];

			foreach( array( 'ethVlanAssignmentsByService' => 'vlan' ,
							'ethProfileNamesByService' => 'bandwidthProfile',
							'ethEncapModesByService' => 'encapsulationMode',
							'ethFlowIgmpProcessingsByService' => 'type' ) as $parameterTypeService => $elementNameService ) {
				// Check that (SNMP) parameter query response counts match expected port count
				if( count( $this->ont->$parameterTypeService ) != $this->ont->deviceConfig['ethernetPortCount'] ) {
					$this->addResponseNotice( NOTICE_TYPE_WARNING, "Parameter retrieval count mismatches port count: " . $parameterTypeService );
				}
				$serviceId = -1;
				$serviceArray = $this->ont->{$parameterTypeService}[$ethernetPortIndex];
				foreach( $serviceArray as $serviceIndex => $value ) {
					$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[++$serviceId]['id'] = $serviceId + 1;
					$this->xml->ethernet->ethernetPort[$ethernetPortIndex]->ethernetPortServices->service[$serviceId]->{$elementNameService} = $value;
				}
			}
		}
	}
}

}
