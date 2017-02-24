<?php
/*
 * USED ONLY FOR Calix E-7 Series ONTs
 *
 * This script is a collection of common functions, including:
 *	- assembling XML for SOAP calls to a Calix CMS (to obtain device configurations)
 *	- utility functions
 */

// Test parameters
//$nodename = "LTTNCOMLE7103CAB01A";
//$shelf = "1";
//$card = "1";
//$port = "1";
//$ontid = "11131";
//$svc_type = "EthSvc";

/*
 * function find_ONTModel()
 * This function uses a Calix ONT SOAP response to translate Calix model name to Vader profile model name.  The Vader profile
 * name is simply used to apply the correct ONT model methods (eg. 727GE.php).
 *
 * Return values (string):
 *	- <vader-ont-profile-name>	(eg. "727GE")
 *	- "missing"					= SOAP call didn't contain the Calix model (due to missing ONT?, ONT rebooting, error)
 *	- "ONT Is Not On ONT List"	= Vader doesn't know about the Calix model
 *
 * Refer to the rtrv_E7ONT3() function (above) to see the SOAP request (for which the response is used by this function).
 *
 * TODO: Refactor function with logical return values
 */
function find_ONTModel($rtrv_E7ONT_response3) {
	global $directoryCalix;

	include($directoryCalix . '/ont_list.php');	// Define $E7OntList, which is a Calix model to Vader profile model translation

	// Translate Calix model name to Vader profile model name
	if (!empty($rtrv_E7ONT_response3['data']['top']['object']['children']['child']['model'])) {
		// SOAP call was successful and contains the Calix model name
		$theActualModel = $rtrv_E7ONT_response3['data']['top']['object']['children']['child']['model'];
		if( array_key_exists($theActualModel, $E7OntList) ) {
			$ontProfileModel = $E7OntList[$theActualModel];
		} else {
			$ontProfileModel = "ONT Is Not On ONT List";
		}
	} else {
		$ontProfileModel = "missing";
	}

	return $ontProfileModel;
}

/*
 * function convert_seconds($seconds)
 * Changes the number of seconds to the number of days, hours, minutes, and seconds
 */
function convert_seconds($seconds) {
	$days = floor($seconds/60/60/24);
	$hours = str_pad($seconds/60/60%24, 2, '0', STR_PAD_LEFT);
	$mins = str_pad($seconds/60%60, 2, '0', STR_PAD_LEFT);
	$secs = str_pad($seconds%60,  2, '0', STR_PAD_LEFT);
	$duration='';
	if($days>0) $duration .= "$days days, ";
	$duration .= "$hours:";
	$duration .= "$mins:";
	$duration .= "$secs";

	return $duration;
}


/*
 * SOAP functions
 *
 */

function login($user, $pass) {
$login_xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
	<soapenv:Body>
		<auth message-id="VADER-001">
			<login>
				<UserName>' . $user . '</UserName>
				<Password>' . $pass . '</Password>
			</login>
		</auth>
	</soapenv:Body>
</soapenv:Envelope>';

return $login_xml;
}

function logout($user, $calix_sessid) {
$logout_xml = '<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
	<soapenv:Body>
		<auth message-id="VADER-002">
			<logout>
				<UserName>' . $user . '</UserName>
				<SessionId>' . $calix_sessid . '</SessionId>
			</logout>
		</auth>
	</soapenv:Body>
</soapenv:Envelope>';

return $logout_xml;
}

/*
 * function rtrv_ALARMS()
 * This function constructs a SOAP request to retrieve alarms on a chassis.
 *
 * $nodename = name/TID of node
 * $shelf = shelf ONT is on
 * $card = card number of shelf (1 or 2)
 * $user = user authenticated into XML GW
 * $calix_sessid = session id of authenticated user
 *
 * NOTE: $ontid is not used, even though it's a calling parameter
 */
function rtrv_ALARMS($nodename, $shelf, $card, $ontid, $user, $calix_sessid) {
	// Need to generate a random number to use as our message-id
	$msg_id = rand(1, 10000);

	$rtrv_ALARMS = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <action>
	    <action-type>show-alarms</action-type>
		<action-args/>
	  </action>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

	return $rtrv_ALARMS;
}

/*
 * rtrv_E7Chassis()
 *
 * SOAP query for: sys-id, sys-location
 *
 * The SOAP function differs from rtrv_E7Chassis2() in that it has the element <get-config> with child <source><running/>.
 */
function rtrv_E7Chassis($nodename, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_E7Chassis_xml = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
      <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>System</type>
			  <id/>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_E7Chassis_xml;
}

/*
 * rtrv_E7Chassis2()
 *
 * SOAP query for: chassis child-prov, chassis uptime, chassis op-stat
 *
 * The SOAP function differs from rtrv_E7Chassis() in that it has the element <get>.
 */
function rtrv_E7Chassis2($nodename, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_E7Chassis_xml2 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get>
	    <filter type="subtree">
		  <top>
		    <object>
			  <type>System</type>
			  <id/>
			</object>
		  </top>
		</filter>
	  </get>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_E7Chassis_xml2;
}

/*
 * rtrv_PONCard()
 *
 * SOAP query for: PON card name, PON card admin state
 */
function rtrv_PONCard($nodename, $shelf, $card, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_PONCard_xml = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>Card</type>
			  <id>
			    <shelf>' . $shelf . '</shelf>
				<card>' . $card . '</card>
			  </id>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_PONCard_xml;
}

/*
 * rtrv_PONCard2()
 *
 * SOAP query for: PON card software levels
 */
function rtrv_PONCard2($nodename, $shelf, $card, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_PONCard_xml2 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <action>
	    <action-type>show-software-card</action-type>
		<action-args>
		  <object>
		    <type>Card</type>
			<id>
			  <shelf>' . $shelf . '</shelf>
			  <card>' . $card . '</card>
			</id>
		  </object>
	  </action-args>
	  </action>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_PONCard_xml2;
}

/*
 * rtrv_PONCard4()
 *
 * SOAP query for: PON card op-state, child-prov, card type (actual), clei code, serial no, part no
 */
function rtrv_PONCard4($nodename, $shelf, $card, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_PONCard_xml4 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get>
	    <filter type="subtree">
		  <top>
		    <object>
			  <type>Card</type>
			  <id>
			    <shelf>' . $shelf . '</shelf>
				<card>' . $card . '</card>
			  </id>
			</object>
		  </top>
		</filter>
	  </get>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_PONCard_xml4;
}

/*
 * rtrv_PONPort()
 *
 * SOAP query for: PON Port info, admin state
 */
function rtrv_PONPort($nodename, $shelf, $card, $port, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_GPONPort_xml = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>GponPort</type>
			  <id>
			    <shelf>' . $shelf . '</shelf>
				<card>' . $card . '</card>
				<gponport>' . $port . '</gponport>
			  </id>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_GPONPort_xml;
}

/*
 * rtrv_PONPort2()
 *
 * SOAP query for: PON Port info like op-state, derived stated, sft status, line length, tx power
 */
function rtrv_PONPort2($nodename, $shelf, $card, $port, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_GPONPort_xml2 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get>
	    <filter type="subtree">
		  <top>
		    <object>
			  <type>GponPort</type>
			  <id>
			    <shelf>' . $shelf . '</shelf>
			    <card>' . $card . '</card>
			    <gponport>' . $port . '</gponport>
			  </id>
			  <attr-list>op-stat info derived-states status sfp-status sfp-type sfp-conn sfp-encoding sfp-bitrate sfp-bitratemax sfp-vendname sfp-vendpartno sfp-vendrev sfp-vendserno v2-joins-sent v2-joins-rec leaves-sent leaves-rec gsq-sent gsq-rec inval-msg query-solicits-sent query-solicits-rec general-querys-sent general-querys-rec sfp-temp sfp-tx-bias sfp-tx-power sfp-rx-power sfp-voltage sfp-line-length sfp-wavelength clei </attr-list>
			</object>
		  </top>
		</filter>
	  </get>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_GPONPort_xml2;
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////
//  Begin GPON ONT Common Info (what is provisioned - not necessarily what is actually there)
//  - Use this to determine what ONT it is 'supposed' to be - which will drive what ONT include will be used
//////////////////////////////////////////////////////////////////////////////////////////////////////////////

/*
 * rtrv_E7ONT()
 *
 * SOAP query for: what is physically provisioned on the chassis like admin, ont profile (prov. model), serial num, reg-id,
 *	subscr-id, descr, what pon it's connected to
 */
function rtrv_E7ONT($nodename, $ontid, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_E7ONT_xml = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>Ont</type>
			  <id>
				<ont>' . $ontid . '</ont>
			  </id>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_E7ONT_xml;
}

/*
 * rtrv_E7ONT2()
 *
 * SOAP query for: actual op-state, serial num, derived-states, model, vendor and clei
 *
 * Somewhat worthless, since the function rtrvE7ONT3() is much more verbose.
 */
function rtrv_E7ONT2($nodename, $ontid, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_E7ONT_xml2 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get>
	    <filter type="subtree">
		  <top>
		    <object>
			  <type>Ont</type>
			  <id>
			    <ont>' . $ontid . '</ont>
			  </id>
			</object>
		  </top>
		</filter>
	  </get>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_E7ONT_xml2;
}

/*
 * rtrv_E7ONT3()
 *
 * SOAP query for: actual op-state, serial num, derived-states, model, vendor, clei, subscr-id, descr, ont software
 *
 * Much more verbose response than rtrv_E7ONT2() for the ONT.
 */
function rtrv_E7ONT3($nodename, $ontid, $user, $calix_sessid) {
$msg_id = rand(1, 10000);

$rtrv_E7ONT_xml3 = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $nodename . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
	  <get>
	    <filter type="subtree">
		  <top>
		    <object>
			  <type>System</type>
			  <id/>
			  <children>
			    <type>DiscOnt</type>
				<attr-list>op-stat crit maj min warn info derived-states reg-id prov-reg-id pon model vendor clei ont ontprof subscr-id descr curr-sw-vers alt-sw-vers curr-committed</attr-list>
				<attr-filter>
				  <ont>' . $ontid . '</ont>
				</attr-filter>
			  </children>
			</object>
		  </top>
		</filter>
	  </get>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $rtrv_E7ONT_xml3;
}

/*
 * getOltMulticastProfilesSoapRequestString()
 * This SOAP request is to obtain a list of multicast profile names and associated MVR profile names.  A video service's VLAN ID
 * (by CTL convention) is parsed off of the MVR profile name; eg. "@PRISM_3301".
 *
 * SOAP query attributes: name mvr-prof
 *
 * NOTES:
 * ) Additional attributes can be requested: max-strms mcast-filter query-interval convert-mcast
 *	Refer to the Vader Coding Details document.
 */
function getOltMulticastProfilesSoapRequestString( $oltTid, $user, $calix_sessid ) {
$msg_id = rand(1, 10000);

$soapRequestString = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $oltTid . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
      <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>System</type>
			  <id/>
			  <children>
				<type>McastProf</type>
				<attr-list>name mvr-prof</attr-list>
			  </children>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $soapRequestString;
}

/*
 * getOltMvrProfileSoapRequestString($oltTid, $user, $calix_sessid, $mvrProfileIndex)
 * This SOAP request is to obtain a specific MVR profile.  A video service's VLAN ID is contained MVR profile.
 *
 * SOAP query attributes: NULL
 *
 * NOTES:
 * ) Additional attributes can be requested: start-addr1 end-addr1 start-addr2 end-addr2 start-addr3 end-addr3 start-addr4 end-addr4
 * Refer to the Vader Coding Details document.
 */
function getOltMvrProfileSoapRequestString( $oltTid, $user, $calix_sessid, $mvrProfileIndex ) {
$msg_id = rand(1, 10000);

$soapRequestString = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $oltTid . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
      <get-config>
	    <source>
		  <running/>
		</source>
		<filter type="subtree">
		  <top>
		    <object>
			  <type>MvrProf</type>
			  <id>
    			<mvrprof>' . $mvrProfileIndex . '</mvrprof>
    		  </id>
			  <children>
				<type>MvrVlan</type>
				<attr-list></attr-list>
			  </children>
			</object>
		  </top>
		</filter>
	  </get-config>
	</rpc>
  </soapenv:Body>
</soapenv:Envelope>';

return $soapRequestString;
}

/*
 * getOltOntsSoapRequestString()
 * This SOAP request is to query for the ONTs configured in an OLT, by sending multiple SOAP requests.  Multiple
 * requests are needed so that the responses are small.
 * If the response contains a <more/> element, then take the last return ONT object, and specify it in $afterOnt
 * to continue with the rest of the ONTs.
 */
function getOltOntsSoapRequestString( $oltTid, $user, $calix_sessid, $afterOnt = null ) {
	$msg_id = rand(1, 10000);

	$soapRequestString = '<soapenv:Envelope xmlns:soapenv="http://www.w3.org/2003/05/soap-envelope">
  <soapenv:Body>
    <rpc message-id="' . $msg_id . '" nodename="NTWK-' . $oltTid . '" timeout="35000" username="' . $user . '" sessionid="' . $calix_sessid . '">
      <action>
	    <action-type>show-ont</action-type>';
	if( !empty( $afterOnt ) ) {
		$soapRequestString .= '
        <action-args>
          <after>
            <type>Ont</type>
            <id><ont>' . $afterOnt . '</ont></id>
          </after>
        </action-args>
		';
	} else {
		$soapRequestString .= '
        <action-args/>';
	}
		$soapRequestString .= '
      </action>
    </rpc>
  </soapenv:Body>
  </soapenv:Envelope>';

	return $soapRequestString;
}
