<?php
/*
 * This module is common for all Calix 700 series ONTs, to make SOAP calls to the CMS, to obtain a particular
 * ONT state (ie. dynamic configuration as provisioned for a customer), and set global variables for
 * the calling module's use.
 */
require_once 'lib-debug.php';

/*
 * 130117 Sheffel Removed the need to conditionally define these empty arrays (by simple array[] assignment):
 *                These arrays will be subsequently used in (Calix): xml-response.php and gpon-web-provision.php
 *
$Prov_Ont_EthPort_Admin_State = array();
$Prov_Ont_EthPort_SubscrId = array();
$Prov_Ont_EthPort_Descr = array();
$Prov_Ont_EthPort_Speed = array();
$Prov_Ont_EthPort_Duplex = array();
$Prov_Ont_EthPort_DisableOnBatt = array();

$Ont_EthPort_Oper_State
$Ont_EthPort_DerivState
$Ont_EthPort_Rate
$Ont_EthPort_Status
$Ont_EthPort_ActDuplex
$Ont_EthPort_PwrStat

$ethServName[$GEPort]
$ethServAdmin[$GEPort]
$ethServTagAct[$GEPort]
$ethServBWProf[$GEPort]
$ethServOutTag[$GEPort]
$ethServInTag[$GEPort]
$ethServSvcType[$GEPort]
$ethServMulticastIndex[$GEPort]	- added in Vader 8.0
$ethServMulticastName[$GEPort]	- added in Vader 8.0
$ethServVlanId[$GEPort]			- added in Vader 8.0

$Prov_Ont_PotsPort_Admin_State
$Prov_Ont_PotsPort_SubscrId
$Prov_Ont_PotsPort_Descr
$Prov_Ont_PotsPort_Type
$Ont_PotsPort_TdmGWProf
$Ont_PotsPort_CRV
$Prov_Ont_PotsPort_SVCAdminState

$Ont_PotsPort_OpsStat
$Ont_PotsPort_DerivState
$Ont_PotsPort_HookState
$Ont_PotsPort_ConfigStat
$Ont_PotsPort_ServiceStat
$Ont_PotsPort_PwrStat

$Prov_Ont_RFPort_AdminStat
$Ont_RFPort_SubscrId
$Ont_RFPort_Descr
$Ont_RFPort_DisableOnBatt
$Ont_RFPort_OperState
$Ont_RFPort_DerivState
$Ont_RFPort_PwrStat

$Prov_OntRfAvo_RFReturnState
$Prov_OntRfAvo_AdminStat
$OntRfAvo_OperState
$OntRfAvo_DerivState
$OntRfAvo_RFReturnStatus
$OntRfAvo_OptRxPower

$Prov_Ont_VideoHotRf_Admin_State
$Prov_Ont_VideoHotRf_SubscrId
$Prov_Ont_VideoHotRf_Descr
$Prov_Ont_VideoHotRf_DisableOnBatt

$OntVideoHotRf_OperState
$OntVideoHotRf_DerivState
$OntVideoHotRf_PwrStat

$Prov_OntDS1_Admin_State
$OntDS1_SubscrId
$OntDS1_Descr
$OntDS1_Framing
$Prov_OntDS1_LineCode
$Prov_OntDS1_LineLength
$Prov_OntDS1_TimingMode
$Prov_OntDS1_Loopback
$Prov_OntDS1_GOSIndex
$Prov_OntDS1_InbdLoopbackEnable
$Prov_OntDS1_Impedance

$OntDS1_OperState
$OntDS1_DerivState
$OntDS1_PwrStat
*/

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve GE ethernet ports
 * ----------------------------------------------------------------------------------------------------------
 */
for ($GEPort=1; $GEPort <= $num_EthGE_ports; $GEPort++) {

	// Get configuration of provisioning of the specified ONT
	try {
		$rtrv_E7ONT_EthGE_port_response = $Cms->getOntGePortBasicsRaw( NULL, NULL, $GEPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_EthGE_port_response = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntGePortBasicsRaw for port $GEPort", $rtrv_E7ONT_EthGE_port_response, $urlParams['gui'] );
	}

	$Prov_Ont_EthPort_Admin_State[] = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['admin'];
	$Prov_Ont_EthPort_SubscrId[] = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['subscr-id'];
	$Prov_Ont_EthPort_Descr[] = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['descr'];

	if ($rtrv_E7ONT_EthGE_port_response['data']['top']['object']['speed'] == "auto") {
		$prov_espeed = "auto";
	} else {
		$prov_espeed = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['speed'] . " Mbps";
	}

	$Prov_Ont_EthPort_Speed[] = $prov_espeed;
	$Prov_Ont_EthPort_Duplex[] = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['duplex'];
	$Prov_Ont_EthPort_DisableOnBatt[] = $rtrv_E7ONT_EthGE_port_response['data']['top']['object']['disable-on-batt'];

	if ($flagDebug) {
		echo "Here is Provisioned Admin State: "; print_r($Prov_Ont_EthPort_Admin_State);
		echo "Here is SubscriberID: "; print_r($Prov_Ont_EthPort_SubscrId);
		echo "Here is Ethernet Port Description: "; print_r($Prov_Ont_EthPort_Descr);
		echo "Here is Ethernet Port Speed: "; print_r($Prov_Ont_EthPort_Speed);
		echo "Here is Ethernet Port Duplex setting: "; print_r($Prov_Ont_EthPort_Duplex);
		echo "Here is Ethernet Disable on Battery: "; print_r($Prov_Ont_EthPort_DisableOnBatt);
	}

	// Get the configuration of existing GE ethernet ports on the specified ONT
	try {
		$rtrv_E7ONT_EthGE_portA_response = $Cms->getOntGePortStateRaw( NULL, NULL, $GEPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_EthGE_portA_response = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntGePortStateRaw for port $GEPort", $rtrv_E7ONT_EthGE_portA_response, $urlParams['gui'] );
	}

	$Ont_EthPort_Oper_State[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['op-stat'];
	$Ont_EthPort_DerivState[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['derived-states'];
	$Ont_EthPort_Rate[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['rate'] . " Mbps";
	// This displays as Admin State in the dynamic section of GUI
	$Ont_EthPort_Status[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['status'];
	$Ont_EthPort_ActDuplex[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['actual-duplex'];
	$Ont_EthPort_PwrStat[] = $rtrv_E7ONT_EthGE_portA_response['data']['top']['object']['power-status'];

	if ($flagDebug) {
		echo "Here is Oper State: "; print_r($Ont_EthPort_Oper_State);
		echo "Here is Derived State: "; print_r($Ont_EthPort_DerivState);
		echo "Here is Ethernet Port Rate: "; print_r($Ont_EthPort_Rate);
		echo "Here is Ethernet Dynamic Admin Status: "; print_r($Ont_EthPort_Status);
		echo "Here is Ethernet Port Actual Duplex: "; print_r($Ont_EthPort_ActDuplex);
		echo "Here is Ethernet Power Status: "; print_r($Ont_EthPort_PwrStat);
	}

	// Get the configuration of services for existing GE ethernet ports on the specified ONT
	try {
		$rtrv_E7ONT_EthGE_portB_response = $Cms->getOntGePortServicesRaw( NULL, NULL, $GEPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_EthGE_portB_response = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntGePortServicesRaw for port $GEPort", $rtrv_E7ONT_EthGE_portB_response, $urlParams['gui'] );
	}

	/*
	 * Calix supports more than one service on an Ethernet port. So, determine how many services of what type are running.
	 * Also, the presence of a multicast profile indicates that the service being provided is PRISM-IPTV.
	 * Now it gets trickier; generally, an ONT will have its ports provisioned in some fashion, but they may not have services
	 * on the ports, so we have to test if a service exists, before processing it.
	 *
	 * The SOAP response returns an object, eg.:
	 *	['data']['top']['object']['children']['child'][$ethserv]['id']['ethsvc']['!name'];	// $ethServName[$GEPort][]
	 *	['data']['top']['object']['children']['child'][$ethserv]['admin'];	// $ethServAdmin[$GEPort][]
	 *
	 * But, if there is a single 'child', then the $ethserv array component is omitted in the response; ie.:
	 *	['data']['top']['object']['children']['child']['id']['ethsvc']['!name'];	// $ethServName[$GEPort][]
	 */

	$servicesArray = array();	// Default to no services
	if ( isset($rtrv_E7ONT_EthGE_portB_response['data']['top']['object']['children']['child']) ) {
		if ( array_key_exists('id', $rtrv_E7ONT_EthGE_portB_response['data']['top']['object']['children']['child']) ) {
			// Single service
			// Case when there is a single child; nusoap omits an array level, ie. not $response->[0]->['data'], but $response->['data']
			$servicesArray[0] = $rtrv_E7ONT_EthGE_portB_response['data']['top']['object']['children']['child'];
		} elseif ( array_key_exists('0', $rtrv_E7ONT_EthGE_portB_response['data']['top']['object']['children']['child']) ) {
			// Multiple services array
			// Case when there is are multiple children; nusoap loads an array, ie. $response->[n]->['data']
			$servicesArray = $rtrv_E7ONT_EthGE_portB_response['data']['top']['object']['children']['child'];
		} else {
			// Error: unexpected SOAP response (or perhaps zero services?)
			// TODO: Add exception handling for unexpected services SOAP response
		}
	}

	$num_of_services = count($servicesArray);
	for ($ethserv = 0; $ethserv < $num_of_services; $ethserv++) {
		$ethServName[$GEPort][] = $servicesArray[$ethserv]['id']['ethsvc']['!name'];
		$ethServAdmin[$GEPort][] = $servicesArray[$ethserv]['admin'];
		$ethServTagAct[$GEPort][] = $servicesArray[$ethserv]['tag-action']['id']['svctagaction']['!name'];
		$ethServBWProf[$GEPort][] = $servicesArray[$ethserv]['bw-prof']['id']['bwprof']['!name'];
		$ethServOutTag[$GEPort][] = $servicesArray[$ethserv]['out-tag'];
		$ethServInTag[$GEPort][] = $servicesArray[$ethserv]['in-tag'];

		// Determine service type: Video/PRISM or Data/HSI
		// So far, it seems to be the case that a multicast profile is only defined on the service if it's video; keep an eye on this, ok?
		//$ethServSvcType[$GEPort][] = !empty($servicesArray[$ethserv]['mcast-prof']) ? "Video - PRISM" : "Data - HSI";	// Original 1-line setting
		if( !empty($servicesArray[$ethserv]['mcast-prof']) ) {
			$ethServSvcType[$GEPort][] = "Video - PRISM";

			// Use element value for multicast ID, not attribute ['mcast-prof']['id']['mcastprof'][!localId] => 1 (which is probably the same?)
			$multicastIndex = isset( $servicesArray[$ethserv]['mcast-prof']['id']['mcastprof']['!'] )
				? $servicesArray[$ethserv]['mcast-prof']['id']['mcastprof']['!'] : NULL;
			$ethServMulticastIndex[$GEPort][] = $multicastIndex;

			// Get multicast profiles from OLT via CMS; use singleton pattern here
			if( isset( $multicastIndex ) && !isset( $multicastProfiles ) ) {
				$multicastProfiles = $Cms->getOltMulticastProfiles();
				if ($flagDebug) {
					echo "<h2>Chassis multicast profiles</h2>\n";
					echo "\n<pre>\n" . print_r($multicastProfiles, TRUE) . "\n</pre>\n\n";
				}
			}

			// Use multicast index into the multicast profile table, obtain the MVR profile table index, then obtain the VLAN ID from the MVR table
			if( isset( $multicastIndex ) && isset( $multicastProfiles[$multicastIndex] ) ) {
				$ethServMulticastName[$GEPort][] = $multicastProfiles[$multicastIndex]['profileName'];	// Not used
				$mvrProfileIndex = $multicastProfiles[$multicastIndex]['mvrProfileIndex'];

				// Get multicast profiles from OLT via CMS; use singleton pattern here
				if( isset( $mvrProfileIndex ) ) {
					$mvrProfile = $Cms->getOltMvrProfile( NULL, $mvrProfileIndex );
					if ($flagDebug) {
						echo "<h2>Chassis MVR profile for index: $mvrProfileIndex</h2>\n";
						echo "\n<pre>\n" . print_r($mvrProfile, TRUE) . "\n</pre>\n\n";
					}
				}
				$ethServVlanId[$GEPort][] = $mvrProfile['mvrVlan'];
			} else {
				$ethServMulticastName[$GEPort][] = NULL;
				$ethServVlanId[$GEPort][] = NULL;
			}
		} else {
			$ethServSvcType[$GEPort][] = "Data - HSI";
			$ethServMulticastIndex[$GEPort][] = NULL;
			$ethServMulticastName[$GEPort][] = NULL;
			$ethServVlanId[$GEPort][] = NULL;
		}
	}
	if ($flagDebug) {
		echo "DEBUG: \$ethServName[$GEPort]: ";
		if ( isset($ethServName[$GEPort]) ) {
			print_r($ethServName[$GEPort]);
		} else {
			print "No ethernet services found; possible Vader error (in get-ont-state-700-series.php)?";
		}
	}
}

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve FE ethernet ports
 * ----------------------------------------------------------------------------------------------------------
 *
 * NOTE: Currently, there are no ONTs (or requirements) that use Fast Ethernet ports.
 */

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve POTS ports
 * ----------------------------------------------------------------------------------------------------------
 */
for ($POTSPort=1; $POTSPort <= $num_OntPOTS_ports; $POTSPort++) {
	try {
		$rtrv_E7ONT_OntPots_port_response = $Cms->getOntPotsBasicsRaw( NULL, NULL, $POTSPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntPots_port_response = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntPotsBasicsRaw for port $POTSPort", $rtrv_E7ONT_OntPots_port_response, $urlParams['gui'] );
	}

	$Prov_Ont_PotsPort_Admin_State[] = $rtrv_E7ONT_OntPots_port_response['data']['top']['object']['admin'];
	$Prov_Ont_PotsPort_SubscrId[] = $rtrv_E7ONT_OntPots_port_response['data']['top']['object']['subscr-id'];
	$Prov_Ont_PotsPort_Descr[] = $rtrv_E7ONT_OntPots_port_response['data']['top']['object']['descr'];

	try {
		$rtrv_E7ONT_OntPots_port_responseA = $Cms->getOntPotsDetailsRaw( NULL, NULL, $POTSPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntPots_port_responseA = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntPotsDetailsRaw for port $POTSPort", $rtrv_E7ONT_OntPots_port_responseA, $urlParams['gui'] );
	}

	$Prov_Ont_PotsPort_Type[] = !empty($rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']) ?
		$rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']['child']['type'] : "";

	$Ont_PotsPort_TdmGWProf[] = !empty($rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']) ?
		$rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']['child']['tdmgw-prof']['id']['tdmgwprof']['!name']: "";

	$Ont_PotsPort_CRV[] = !empty($rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']) ?
		$rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']['child']['crv'] : "";

	$Prov_Ont_PotsPort_SVCAdminState[] = !empty($rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']) ?
		$rtrv_E7ONT_OntPots_port_responseA['data']['top']['object']['children']['child']['admin'] : "";

	try {
		$rtrv_E7ONT_OntPots_port_responseB = $Cms->getOntPotsStatsRaw( NULL, NULL, $POTSPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntPots_port_responseB = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntPotsStatsRaw for port $POTSPort", $rtrv_E7ONT_OntPots_port_responseB, $urlParams['gui'] );
	}

	// TODO: Implement better error handling
	// Example error return: $rtrv_E7ONT_OntPots_port_responseB['rpc-error']['error-message']['!'] = "unknown attribute pkt-rate in attr-list"
	if (isset($rtrv_E7ONT_OntPots_port_responseB['data'])) {
		$Ont_PotsPort_OpsStat[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['op-stat'];
		$Ont_PotsPort_DerivState[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['derived-states'];
		$Ont_PotsPort_HookState[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['hook-state'];
		$Ont_PotsPort_ConfigStat[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['config-status'];
		$Ont_PotsPort_ServiceStat[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['svc-status'];
		$Ont_PotsPort_CallStat[] = $rtrv_E7ONT_OntPots_port_responseB['data']['top']['object']['call-state'];
	} else {
		$Ont_PotsPort_OpsStat[] = "";
		$Ont_PotsPort_DerivState[] = "";
		$Ont_PotsPort_HookState[] = "";
		$Ont_PotsPort_ConfigStat[] = "";
		$Ont_PotsPort_ServiceStat[] = "";
		$Ont_PotsPort_CallStat[] = "";
	}

	try {
		$rtrv_E7ONT_OntPots_port_responseC = $Cms->getOntPotsPowerStatusRaw( NULL, NULL, $POTSPort );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntPots_port_responseC = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntPotsPowerStatusRaw for port $POTSPort", $rtrv_E7ONT_OntPots_port_responseC, $urlParams['gui'] );
	}

	$Ont_PotsPort_PwrStat[] = $rtrv_E7ONT_OntPots_port_responseC['data']['top']['object']['power-status'];
}

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve ONT RF video ports
 * ----------------------------------------------------------------------------------------------------------
 */
for ($RFport=1; $RFport <= $num_VideoRf_ports; $RFport++) {
	try {
		$rtrv_E7ONT_OntVideoRf_response = $Cms->getOntVideoRfBasicsRaw( NULL, NULL, $RFport );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntVideoRf_response = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntVideoRfBasicsRaw for port $RFport", $rtrv_E7ONT_OntVideoRf_response, $urlParams['gui'] );
	}

	$Prov_Ont_RFPort_AdminStat[] = $rtrv_E7ONT_OntVideoRf_response['data']['top']['object']['admin'];
	$Ont_RFPort_SubscrId[] = $rtrv_E7ONT_OntVideoRf_response['data']['top']['object']['subscr-id'];
	$Ont_RFPort_Descr[] = $rtrv_E7ONT_OntVideoRf_response['data']['top']['object']['descr'];
	$Ont_RFPort_DisableOnBatt[] = $rtrv_E7ONT_OntVideoRf_response['data']['top']['object']['disable-on-batt'];

	try {
		$rtrv_E7ONT_OntVideoRf_responseA = $Cms->getOntVideoRfStatsRaw( NULL, NULL, $RFport );
	}
	catch( Exception $e ) {
		$rtrv_E7ONT_OntVideoRf_responseA = NULL;
	}
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntVideoRfStatsRaw for port $RFport", $rtrv_E7ONT_OntVideoRf_responseA, $urlParams['gui'] );
	}

	$Ont_RFPort_OperState[] = $rtrv_E7ONT_OntVideoRf_responseA['data']['top']['object']['op-stat'];
	$Ont_RFPort_DerivState[] = $rtrv_E7ONT_OntVideoRf_responseA['data']['top']['object']['derived-states'];
	$Ont_RFPort_PwrStat[] = $rtrv_E7ONT_OntVideoRf_responseA['data']['top']['object']['power-status'];
}

/*
 * Retrieve ONT AVO port
 *
 * There is only one AVO port on an ONT; no looping required.
 * The number of AVO ports is independant of the number of RF ports.
 */
if ($num_OntRfAvo_ports > 0) {
	$rtrv_E7ONT_OntRfAvo_response = $Cms->getOntAvoRfBasicsRaw();
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntAvoRfBasicsRaw", $rtrv_E7ONT_OntRfAvo_response, $urlParams['gui'] );
	}

	$Prov_OntRfAvo_RFReturnState[] = $rtrv_E7ONT_OntRfAvo_response['data']['top']['object']['rf-return-state'];
	$Prov_OntRfAvo_AdminStat[] = $rtrv_E7ONT_OntRfAvo_response['data']['top']['object']['admin'];

	$rtrv_E7ONT_OntRfAvo_responseA = $Cms->getOntAvoRfStatsRaw();
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntAvoRfStatsRaw", $rtrv_E7ONT_OntRfAvo_responseA, $urlParams['gui'] );
	}

	$OntRfAvo_OperState[] = $rtrv_E7ONT_OntRfAvo_responseA['data']['top']['object']['op-stat'];
	$OntRfAvo_DerivState[] = $rtrv_E7ONT_OntRfAvo_responseA['data']['top']['object']['derived-states'];
	$OntRfAvo_RFReturnStatus[] = $rtrv_E7ONT_OntRfAvo_responseA['data']['top']['object']['rf-return-status'];
	$OntRfAvo_OptRxPower[] = $rtrv_E7ONT_OntRfAvo_responseA['data']['top']['object']['rx-power'];
}	// End RF AVO port

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve HOTRF port
 * ----------------------------------------------------------------------------------------------------------
 */
if ($num_VideoHotRf_ports > 0) {
	$rtrv_E7ONT_OntVideoHotRf_response = $Cms->getOntVideoHotRfBasicsRaw();
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntVideoHotRfBasicsRaw", $rtrv_E7ONT_OntVideoHotRf_response, $urlParams['gui'] );
	}

	$Prov_Ont_VideoHotRf_Admin_State[] = $rtrv_E7ONT_OntVideoHotRf_response['data']['top']['object']['admin'];
	$Prov_Ont_VideoHotRf_SubscrId[] = $rtrv_E7ONT_OntVideoHotRf_response['data']['top']['object']['subscr-id'];
	$Prov_Ont_VideoHotRf_Descr[] = $rtrv_E7ONT_OntVideoHotRf_response['data']['top']['object']['descr'];
	$Prov_Ont_VideoHotRf_DisableOnBatt[] = $rtrv_E7ONT_OntVideoHotRf_response['data']['top']['object']['disable-on-batt'];

	$rtrv_E7ONT_OntVideoHotRf_responseA = $Cms->getOntVideoHotRfStatsRaw();
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntVideoHotRfStatsRaw", $rtrv_E7ONT_OntVideoHotRf_responseA, $urlParams['gui'] );
	}

	$OntVideoHotRf_OperState[] = $rtrv_E7ONT_OntVideoHotRf_responseA['data']['top']['object']['op-stat'];
	$OntVideoHotRf_DerivState[] = $rtrv_E7ONT_OntVideoHotRf_responseA['data']['top']['object']['derived-states'];
	$OntVideoHotRf_PwrStat[] = $rtrv_E7ONT_OntVideoHotRf_responseA['data']['top']['object']['power-status'];
}

/*
 * ----------------------------------------------------------------------------------------------------------
 * Retrieve DS1 ports
 * ----------------------------------------------------------------------------------------------------------
 */
for ($DS1Port=1; $DS1Port <= $num_OntDS1_ports; $DS1Port++) {
	$rtrv_E7ONT_OntDS1_response = $Cms->getOntDs1BasicsRaw( NULL, NULL, $DS1Port );
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntDs1BasicsRaw for port $DS1Port", $rtrv_E7ONT_OntDS1_response, $urlParams['gui'] );
	}

	$Prov_OntDS1_Admin_State[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['admin'];
	$OntDS1_SubscrId[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['subscr-id'];
	$OntDS1_Descr[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['descr'];
	$OntDS1_Framing[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['framing'];
	$Prov_OntDS1_LineCode[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['line-code'];
	$Prov_OntDS1_LineLength[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['line-length'];
	$Prov_OntDS1_TimingMode[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['timing-mode'];
	$Prov_OntDS1_Loopback[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['loopback'];
	$Prov_OntDS1_GOSIndex[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['gos']['id']['ontds1portgos'];
	$Prov_OntDS1_InbdLoopbackEnable[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['inband-lpbk-enable'];
	$Prov_OntDS1_Impedance[] = $rtrv_E7ONT_OntDS1_response['data']['top']['object']['impedance'];

	$rtrv_E7ONT_OntDS1_responseA = $Cms->getOntDs1StatsRaw( NULL, NULL, $DS1Port );
	if ($flagDebug) {
		echo $Cms->getFormattedSoapDebugOutput( "getOntDs1StatsRaw for port $DS1Port", $rtrv_E7ONT_OntDS1_responseA, $urlParams['gui'] );
	}

	$OntDS1_OperState[] = $rtrv_E7ONT_OntDS1_responseA['data']['top']['object']['op-stat'];
	$OntDS1_DerivState[] = $rtrv_E7ONT_OntDS1_responseA['data']['top']['object']['derived-states'];
	$OntDS1_PwrStat[] = $rtrv_E7ONT_OntDS1_responseA['data']['top']['object']['power-status'];
}
