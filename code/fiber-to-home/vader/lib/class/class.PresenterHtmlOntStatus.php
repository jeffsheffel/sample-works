<?php

require_once 'class.PresenterXml.php';

class PresenterHtmlOntStatus extends PresenterXml {

function getRequestProperties() {
	$html = '<div class="requestProperties"><ul class="oltItemHolder">' . "\n";
	$html .= '<li class="parameter">OLT IP Address</li>';
	$html .= '<li class="value">' . $this->xml->requestProperties->oltIpAddress . '</li>' . "\n";
	$html .= '<li class="parameter">AID</li>';
	$html .= '<li class="value">' . $this->xml->requestProperties->aid . '</li>' . "\n";
	$html .= '</ul></div>' . "\n";
	return $html;
}

function getResponseProperties() {
	$html = '<div class="responseProperties"><ul class="oltItemHolder">' . "\n";
	$html .= '<li class="parameter">Transaction ID</li>';
	$html .= '<li class="value">' . $this->xml->responseProperties->transactionId . '</li>' . "\n";
	$html .= '<li class="parameter">Status</li>';
	$html .= '<li class="value">' . $this->xml->responseProperties->status . '</li>' . "\n";

	$html .= '<div class="notices">' . "\n";
	foreach( $this->xml->responseProperties->notice as $notice ) {
		$html .= '<li class="parameter ' . $notice['type'] . '">' . $notice['type'] . '</li>';
		$html .= '<li class="value">' . $notice . '</li>' . "\n";
	}
	$html .= '</div>' . "\n";

	$html .= '</ul></div>' . "\n";
	return $html;
}

function getOlt() {
	$html = '<div class="olt"><ul class="oltItemHolder">' . "\n";
	$html .= '<li class="parameter">Vendor</li>';
	$html .= '<li class="value">' . $this->xml->olt->vendor . '</li>' . "\n";
	$html .= '<li class="parameter">Product ID</li>';
	$html .= '<li class="value">' . $this->xml->olt->productId . '</li>' . "\n";
	$html .= '<li class="parameter">TID</li>';
	$html .= '<li class="value">' . $this->xml->olt->tid . '</li>' . "\n";
	$html .= '<li class="parameter">Location</li>';
	$html .= '<li class="value">' . $this->xml->olt->location . '</li>' . "\n";
	$html .= '<li class="parameter">Uptime</li>';
	$html .= '<li class="value">' . $this->xml->olt->uptime . '</li>' . "\n";
	$html .= '</ul></div>' . "\n";
	return $html;
}

function getOnt() {
	$html = '<div class="ont"><ul class="oltItemHolder">' . "\n";
	$html .= '<li class="parameter">Model</li>';
	$html .= '<li class="value">' . $this->xml->ont->deviceConfig->model . '</li>' . "\n";
	$html .= '<li class="parameter">CLEI Code</li>';
	$html .= '<li class="value">' . $this->xml->ont->cleiCode . '</li>' . "\n";
	$html .= '<li class="parameter">AID</li>';
	$html .= '<li class="value">' . $this->xml->ont->aid . '</li>' . "\n";
	$html .= '<li class="parameter">Serial Number</li>';
	$html .= '<li class="value">' . $this->xml->ont->serialNumber . '</li>' . "\n";
	$html .= '<li class="parameter">Part Number</li>';
	$html .= '<li class="value">' . $this->xml->ont->partNumber . '</li>' . "\n";
	$html .= '<li class="parameter">Description</li>';
	$html .= '<li class="value">' . $this->xml->ont->description . '</li>' . "\n";
	$html .= '<li class="parameter">Hardware Version</li>';
	$html .= '<li class="value">' . $this->xml->ont->hardwareVersion . '</li>' . "\n";
	$html .= '<li class="parameter">Software Version</li>';
	$html .= '<li class="value">' . $this->xml->ont->softwareVersion . '</li>' . "\n";
	$html .= '<li class="parameter">Administrative State</li>';
	$html .= '<li class="value">' . $this->xml->ont->administrativeState . '</li>' . "\n";
	$html .= '<li class="parameter">Operational State</li>';
	$html .= '<li class="value">' . $this->xml->ont->operationalState . '</li>' . "\n";
	$html .= '<li class="parameter">Uptime</li>';
	$html .= '<li class="value">' . $this->xml->ont->uptime . '</li>' . "\n";
	$html .= '<li class="parameter">Battery Backup Flag</li>';
	$html .= '<li class="value">' . $this->xml->ont->batteryBackupFlag . '</li>' . "\n";
	$html .= '<li class="parameter">Subscriber ID</li>';
	$html .= '<li class="value">' . $this->xml->ont->subscriberId . '</li>' . "\n";
	$html .= '</ul></div>' . "\n";
	return $html;
}

}