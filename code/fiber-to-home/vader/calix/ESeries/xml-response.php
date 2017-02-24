<?php
/*
 * Calix XML Response Module
 * This script simply a Calix XML response.
 *
 * This script takes into account what kind of ONT was queried and only includes the ports that are supported by the
 * particular model of ONT queried.
 *
 * This script is called from:
 *	- ftth/calix/gpon-request.php
 *
 * NOTES:
 * ) This module depends on variables that are set in the caller (or callers, which is a code maintenance issue).
 * ) The GE ethernet port *service* array index starts at 1, while all other indices (eg. ethernet ports, POTS ports) start at 0 !!
 */

require_once dirname(__FILE__).'/../../../include/config.php';
require_once 'lib-debug.php';

/*
 * Assemble the XML response
 */
$xml_buf = xmlwriter_open_memory();
xmlwriter_set_indent($xml_buf, true);
xmlwriter_set_indent_string($xml_buf, "\t");
xmlwriter_start_document($xml_buf,'1.0','UTF-8');

xmlwriter_start_element($xml_buf,'RESULT');	// Start NDP-RESULT
xmlwriter_write_attribute($xml_buf, 'xmlns', "http://vdsltechsupp.uswc.uswest.com/vader/include/xsd/calix/" . $ontModel . ".xsd");
xmlwriter_write_attribute($xml_buf, 'xmlns:xs', "http://vdsltechsupp.uswc.uswest.com/vader/include/xsd/calix/" . $ontModel . ".xsd");
xmlwriter_write_attribute($xml_buf, 'xs', "http://vdsltechsupp.uswc.uswest.com/vader/include/xsd/calix/" . $ontModel . ".xsd");

xmlwriter_write_element($xml_buf,'TRANSNUM', "TRANSNUMSTART=" . $transnum); // Start TRANSNUM
xmlwriter_write_element($xml_buf,'DATETIME', date("m"."/"."d"."/"."Y" . " " . "H" . ":" . "i" . ":" . "s"));
xmlwriter_write_element($xml_buf,'REQUEST', "SERVICE=" . $urlParams['service'] . " OLTIP=" . $urlParams['oltip'] . " AID=" . $urlParams['aid'] . " DEBUG=" . $requestDebug . " SELECTION=" . $urlParams['selection'] . " CLIENTIP=" . $_SERVER["REMOTE_ADDR"] . " CUID=" . getSessionVar('valid_user'));
xmlwriter_start_element($xml_buf,'NETWORK-LOCATION');	// Start NETWORK-LOCATION
xmlwriter_write_element($xml_buf,'VENDOR', 'Calix');
xmlwriter_write_element($xml_buf,'CHASSIS-TID', $chassis_tid);
xmlwriter_write_element($xml_buf,'ONT-AID', $urlParams['aid']);
xmlwriter_write_element($xml_buf,'PRODUCT-ID', $product_id);
xmlwriter_write_element($xml_buf,'OLT-LOCATION', $chassis_location);
xmlwriter_write_element($xml_buf,'OLT-UPTIME', $chassis_uptime);
xmlwriter_end_element($xml_buf);	// Close NETWORK-LOCATION

xmlwriter_start_element($xml_buf,'LINECARD-PORT-STAT');	// Start LINECARD-PORT-STAT
xmlwriter_write_element($xml_buf,'CARD-TYPE', $card_type);
xmlwriter_write_element($xml_buf,'CARD-CLEI-CODE', $card_clei_code);
xmlwriter_write_element($xml_buf,'CARD-PART-NO', $card_part_no);
xmlwriter_write_element($xml_buf,'CARD-SERIAL-NO', $card_serial_no);
xmlwriter_write_element($xml_buf,'CARD-REVISION', 'Not Available');
xmlwriter_write_element($xml_buf,'CARD-SOFTWARE-VERSION-ALTERNATE', $card_software_alt);
xmlwriter_write_element($xml_buf,'CARD-SOFTWARE-VERSION', $card_active_software);
xmlwriter_write_element($xml_buf,'CARD-OPERATIONAL-STATE', $card_service_state);
xmlwriter_write_element($xml_buf,'CARD-UPTIME', 'Not Available');
xmlwriter_write_element($xml_buf,'PONPORT-SERVICE-STATE', $ponport_service_state);
xmlwriter_write_element($xml_buf,'PONPORT-DEPLOYMENT-RANGE', $ponport_deployment_range/1000 . ' KM');
xmlwriter_write_element($xml_buf,'PONPORT-SFP-STATE', $ponport_sfp_state);
xmlwriter_write_element($xml_buf,'PONPORT-SFP-ADDL-INFO', $ponport_sfp_addl_info);
xmlwriter_write_element($xml_buf,'PONPORT-SFP-TX-PWR', $ponport_sfp_tx_pwr . ' dB');
xmlwriter_end_element($xml_buf);	// Close LINECARED-PORT-STAT

xmlwriter_start_element($xml_buf,'ONT-STAT');	// Start ONT-STAT
xmlwriter_write_element($xml_buf,'ONT-SERIAL-NO', $ont_serial);
xmlwriter_write_element($xml_buf,'ONT-SUBSCRIBER-ID', $ont_description);
xmlwriter_write_element($xml_buf,'ONT-DESCRIPTION', $ont_description2);
xmlwriter_write_element($xml_buf,'ONT-CLEI-CODE', $ont_clei);
xmlwriter_write_element($xml_buf,'ONT-HW-VERSION-NO', $ont_model);
xmlwriter_write_element($xml_buf,'ONT-PART-NO', 'Not Available');
xmlwriter_write_element($xml_buf,'ONT-SW-VERSION-ALTERNATE', $ont_alt_sw_ver);
xmlwriter_write_element($xml_buf,'ONT-SW-VERSION-ACTIVE', $ont_sw_ver);
xmlwriter_write_element($xml_buf,'ONT-ADMIN-STATE', $ont_admin_state);
xmlwriter_write_element($xml_buf,'ONT-OPERATIONAL-STATE', $ont_oper_state);
xmlwriter_write_element($xml_buf,'ONT-UPTIME', 'Not Available');
xmlwriter_end_element($xml_buf);	// Close LINECARED-PORT-STAT

if ($num_OntPOTS_ports > 0) {
	xmlwriter_start_element($xml_buf,'ONTPOTS-PORT-STAT');	// Start ONTPOTS-PORT-STAT
	for ($pp = 0; $pp < $num_OntPOTS_ports; $pp++) {
		xmlwriter_start_element($xml_buf,'POTS-PORT-' . ($pp+1));	// Start POTS-PORT-x
		xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_Ont_PotsPort_Admin_State[$pp]);
		xmlwriter_write_element($xml_buf,'SUBSCRIBER-ID', $Prov_Ont_PotsPort_SubscrId[$pp]);
		xmlwriter_write_element($xml_buf,'DESCRIPTION', $Prov_Ont_PotsPort_Descr[$pp]);
		xmlwriter_write_element($xml_buf,'PORT-TYPE', $Prov_Ont_PotsPort_Type[$pp]);
		xmlwriter_write_element($xml_buf,'PROFILE-NAME', $Ont_PotsPort_TdmGWProf[$pp]);
		xmlwriter_write_element($xml_buf,'CRV', $Ont_PotsPort_CRV[$pp]);
		xmlwriter_write_element($xml_buf,'SERVICE-STATE', $Prov_Ont_PotsPort_SVCAdminState[$pp]);
		xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $Ont_PotsPort_OpsStat[$pp]);
		xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', $Ont_PotsPort_DerivState[$pp]);
		xmlwriter_write_element($xml_buf,'HOOK-STATE', $Ont_PotsPort_HookState[$pp]);
		xmlwriter_write_element($xml_buf,'CONFIGURATION-STATUS', $Ont_PotsPort_ConfigStat[$pp]);
		xmlwriter_write_element($xml_buf,'SERVICE-STATUS', $Ont_PotsPort_ServiceStat[$pp]);
		xmlwriter_write_element($xml_buf,'CALL-STATUS', $Ont_PotsPort_CallStat[$pp]);
		xmlwriter_write_element($xml_buf,'ACTUAL-PWR-STATUS', $Ont_PotsPort_PwrStat[$pp]);
		xmlwriter_end_element($xml_buf);	// Close POTS-PORT-x
	}
	xmlwriter_end_element($xml_buf);	// Close ONTPOTS-PORT-STAT
}

if ($num_OntDS1_ports > 0) {
	xmlwriter_start_element($xml_buf,'T1E1-PORT-STAT');	// Start T1E1-PORT-STAT
	for ($t1 = 0; $t1 < $num_OntDS1_ports; $t1++) {
		xmlwriter_start_element($xml_buf,'T1E1-PORT-' . ($t1+1));	// Start T1E1-PORT-x
		xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_OntDS1_Admin_State[$t1]);
		xmlwriter_write_element($xml_buf,'SUBSCRIBER-ID', $OntDS1_SubscrId[$t1]);
		xmlwriter_write_element($xml_buf,'DESCRIPTION', $OntDS1_Descr[$t1]);
		xmlwriter_write_element($xml_buf,'FRAMING', $OntDS1_Framing[$t1]);
		xmlwriter_write_element($xml_buf,'LINE-CODE', $Prov_OntDS1_LineCode[$t1]);
		xmlwriter_write_element($xml_buf,'LINE-LENGTH', $Prov_OntDS1_LineLength[$t1]);
		xmlwriter_write_element($xml_buf,'TIMING-MODE', $Prov_OntDS1_TimingMode[$t1]);
		xmlwriter_write_element($xml_buf,'LOOPBACK', $Prov_OntDS1_Loopback[$t1]);
		xmlwriter_write_element($xml_buf,'GOS-PROFILE', $Prov_OntDS1_GOSIndex[$t1]);
		xmlwriter_write_element($xml_buf,'LOOPBACK-ENABLE', $Prov_OntDS1_InbdLoopbackEnable[$t1]);
		xmlwriter_write_element($xml_buf,'IMPEDANCE', $Prov_OntDS1_Impedance[$t1]);
		xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $OntDS1_OperState[$t1]);
		xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', $OntDS1_DerivState[$t1]);
		xmlwriter_write_element($xml_buf,'ACTUAL-PWR-STATUS', $OntDS1_PwrStat[$t1]);
		xmlwriter_end_element($xml_buf);	// Close T1E1-PORT-x
	}
	xmlwriter_end_element($xml_buf);	// Close T1E1-PORT-STAT
}

if ($num_EthGE_ports > 0) {
	xmlwriter_start_element($xml_buf,'ETHERNET-PORT-STAT');	// Start ETHERNET-PORT-STAT
	for ($ep = 0; $ep < $num_EthGE_ports; $ep++) {
		xmlwriter_start_element($xml_buf,'GE-PORT-' . ($ep+1));	// Start GE-x-PORT
		xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_Ont_EthPort_Admin_State[$ep]);
		xmlwriter_write_element($xml_buf,'SUBSCRIBER-ID', $Prov_Ont_EthPort_SubscrId[$ep]);
		xmlwriter_write_element($xml_buf,'DESCRIPTION', $Prov_Ont_EthPort_Descr[$ep]);
		xmlwriter_write_element($xml_buf,'PROVISIONED-RATE', $Prov_Ont_EthPort_Speed[$ep]);
		xmlwriter_write_element($xml_buf,'PROVISIONED-DUPLEX', $Prov_Ont_EthPort_Duplex[$ep]);
		xmlwriter_write_element($xml_buf,'DISABLE-ON-BATT', $Prov_Ont_EthPort_DisableOnBatt[$ep]);
		xmlwriter_write_element($xml_buf,'ACTUAL-STATE', $Ont_EthPort_Status[$ep]);  // Admin State in CMS
		xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $Ont_EthPort_Oper_State[$ep]);
		xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', str_replace("suppr", "Alarming Suppressed On This Port", $Ont_EthPort_DerivState[$ep]));
		xmlwriter_write_element($xml_buf,'ACTUAL-RATE', $Ont_EthPort_Rate[$ep]);
		xmlwriter_write_element($xml_buf,'ACTUAL-DUPLEX', $Ont_EthPort_ActDuplex[$ep]);
		xmlwriter_write_element($xml_buf,'ACTUAL-PWR-STATUS', $Ont_EthPort_PwrStat[$ep]);
		xmlwriter_end_element($xml_buf);	// Close GE-x-PORT
	}
	xmlwriter_end_element($xml_buf);	// Close ETHERNET-PORT-STAT

	xmlwriter_start_element($xml_buf,'ETHERNET-PORT-SERVICES');	// Start ETHERNET-PORT-SERVICES
	for ($ep = 1; $ep <= $num_EthGE_ports; $ep++) {
		xmlwriter_start_element($xml_buf,'GE-PORT-' . ($ep));	// Start GE-x-PORT
		$serviceCount = isset($ethServName[$ep]) ? count($ethServName[$ep]) : 0; // Number of services on GE Port, possibly 0
		for ($serviceN = 0; $serviceN < $serviceCount; $serviceN++) {
			xmlwriter_start_element($xml_buf,'SERVICE-' . ($serviceN+1));	// Start Service Number on the port
			xmlwriter_write_element($xml_buf,'TYPE', $ethServSvcType[$ep][$serviceN]);
			xmlwriter_write_element($xml_buf,'NAME', $ethServName[$ep][$serviceN]);
			xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $ethServAdmin[$ep][$serviceN]);
			xmlwriter_write_element($xml_buf,'SVC-TAG-ACTION', $ethServTagAct[$ep][$serviceN]);
			xmlwriter_write_element($xml_buf,'BW-PROF', $ethServBWProf[$ep][$serviceN]);
			if( preg_match('/PRISM/', $ethServSvcType[$ep][$serviceN]) ) {
				// Video service
				xmlwriter_write_element($xml_buf,'VLAN', $ethServVlanId[$ep][$serviceN]);	// Parsed VLAN digits from OLT MVR profile name
			} else {
				// HSI data service
				xmlwriter_write_element($xml_buf,'VLAN', $ethServOutTag[$ep][$serviceN]);
			}
			xmlwriter_write_element($xml_buf,'CUST-VLAN', $ethServInTag[$ep][$serviceN]);
			xmlwriter_end_element($xml_buf);	// Close Service Number on the port
		}
		xmlwriter_end_element($xml_buf);	// Close GE-x-PORT
	}
	xmlwriter_end_element($xml_buf);	// Close ETHERNET-PORT-SERVICES
}

if ($num_VideoRf_ports > 0) {
	xmlwriter_start_element($xml_buf,'RFVIDEO-PORT-STAT');	// Start RFVIDEO-PORT-STAT
	for ($rfp = 0; $rfp < $num_VideoRf_ports; $rfp++) {
		xmlwriter_start_element($xml_buf,'RF-PORT-' . ($rfp+1));	// Start RF-PORT-x
		xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_Ont_RFPort_AdminStat[$rfp]);
		xmlwriter_write_element($xml_buf,'SUBSCRIBER-ID', $Ont_RFPort_SubscrId[$rfp]);
		xmlwriter_write_element($xml_buf,'DESCRIPTION', $Ont_RFPort_Descr[$rfp]);
		xmlwriter_write_element($xml_buf,'DISABLE-ON-BATT', $Ont_RFPort_DisableOnBatt[$rfp]);
		xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $Ont_RFPort_OperState[$rfp]);
		xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', $Ont_RFPort_DerivState[$rfp]);
		xmlwriter_write_element($xml_buf,'ACTUAL-PWR-STATUS', $Ont_RFPort_PwrStat[$rfp]);
		xmlwriter_end_element($xml_buf);	// Close RF-PORT-x
	}
	xmlwriter_end_element($xml_buf);	// Close RFVIDEO-PORT-STAT
}

if ($num_OntRfAvo_ports > 0) {
	xmlwriter_start_element($xml_buf,'AVO-PORT-STAT');	// Start AVO-PORT-STAT
	xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_OntRfAvo_AdminStat[0]);
	xmlwriter_write_element($xml_buf,'PROVISIONED-RF-RETURN-STATE', $Prov_OntRfAvo_RFReturnState[0]);
	xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $OntRfAvo_OperState[0]);
	xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', $OntRfAvo_DerivState[0]);
	xmlwriter_write_element($xml_buf,'RF-RETURN-STATUS', $OntRfAvo_RFReturnStatus[0]);
	xmlwriter_write_element($xml_buf,'OPTICAL-RX-PWR', $OntRfAvo_OptRxPower[0]);
	xmlwriter_end_element($xml_buf);	// Close AVO-PORT-STAT
}

if ($num_VideoHotRf_ports > 0) {
	xmlwriter_start_element($xml_buf,'HOTRF-PORT-STAT');	// Start HotRF-PORT-STAT
	xmlwriter_write_element($xml_buf,'PROVISIONED-STATE', $Prov_Ont_VideoHotRf_Admin_State[0]);
	xmlwriter_write_element($xml_buf,'SUBSCRIBER-ID', $Prov_Ont_VideoHotRf_SubscrId[0]);
	xmlwriter_write_element($xml_buf,'DESCRIPTION', $Prov_Ont_VideoHotRf_Descr[0]);
	xmlwriter_write_element($xml_buf,'DISABLE-ON-BATT', $Prov_Ont_VideoHotRf_DisableOnBatt[0]);
	xmlwriter_write_element($xml_buf,'OPERATIONAL-STATE', $OntVideoHotRf_OperState[0]);
	xmlwriter_write_element($xml_buf,'ADDITIONAL-STATE', $OntVideoHotRf_DerivState[0]);
	xmlwriter_write_element($xml_buf,'ACTUAL-PWR-STATUS', $OntVideoHotRf_PwrStat[0]);
	xmlwriter_end_element($xml_buf);	// Close HotRF-PORT-STAT
}

xmlwriter_start_element($xml_buf,'ONT-ALARM');	// Start ONT-ALARM
for ($i=0; $i < $number_of_ont_alarms -1; $i++) {
	xmlwriter_write_element($xml_buf,'ALARM', $ont_alarms_array[$i]);
}
xmlwriter_end_element($xml_buf);	// Close ONT-ALARM
xmlwriter_write_element($xml_buf,'TRANSNUM', "TRANSNUMEND=" . $transnum); // Start TRANSNUM End container

xmlwriter_end_element($xml_buf);	// Close NDP-RESULT
$responseXmlText = xmlwriter_output_memory( $xml_buf, TRUE );
