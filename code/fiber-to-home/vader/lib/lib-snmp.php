<?php


/*
 * function initializeSnmp()
 */
function initializeSnmp() {
/*
 * Set snmp quick print, so only values of the OIDs are printed, not including MIB variable names.
 * Yet, returned string values still include the double-quotes.
 */
	snmp_set_quick_print(1);
}

/*
 * function getSnmpCommunityString()
 */
function getSnmpCommunityString($deviceType = NULL) {
	switch ($deviceType) {
		case SNMP_DEVICE_ADTRAN:
			return "public";
			break;
		case SNMP_DEVICE_CALIX:
			return "vader";
			break;
		default:
			return "public";
			break;
	}
}

/*
 * doSnmpGet($host, $communityString, $mibSpec)
 * This function simplifies calls to snmpget(); the $mibSpec can be a single MIB or an array of MIBs.
 *
 * If the MIB specification is NULL, then a FALSE is returned.  In that case, the snmpget() call will
 * throw a warning (Invalid object identifier: NULL), unless suppressed with @.  The Vader code does make calls
 * to this function with NULL MIB specifications.
 *
 * NOTES:
 *	- Any returned values of FALSE indicates an snmpget failure.
 *	- Attempting to get a MIB array (ie. a MIB appropriate for an snmpwalk) will return a FALSE value (ie. failure).
 *	- An object return type of SNMP_OCTET_STR applies to returned strings (eg. "Off") and hexadecimal encodings (eg. 04 1D).
 *		Hence, knowledge of the MIB data (type) is required for interpretting the values.
 */
function doSnmpGet( $host, $communityString, $mibSpec, $timeout = 500000, $retries = 1 ) {
	$valueRetrievalBefore = snmp_get_valueretrieval();
	//$quickPrintBefore = snmp_get_quick_print();

	snmp_set_valueretrieval(SNMP_VALUE_OBJECT);
	//snmp_set_valueretrieval(SNMP_VALUE_OBJECT | SNMP_VALUE_PLAIN);	// Wrong; >= PHP 5.4?
	//snmp_set_quick_print(1);	// Only applies for SNMP_VALUE_PLAIN retrieval, not SNMP_VALUE_OBJECT

	if( is_array( $mibSpec ) ) {
		$singleValueRequested = FALSE;
		$mibArray = $mibSpec;
	} else {
		$singleValueRequested = TRUE;
		$mibArray[0] = $mibSpec;
	}

	$arrayIndex = 0;
	$valuesArray = array();
	foreach( $mibArray as $mib ) {
		$response = @snmpget($host, $communityString, $mib, $timeout, $retries );
		// stdClass Object
		//   [type] => 4        <-- SNMP_OCTET_STR, see constants
		//   [value] => lo
		if( $response === FALSE ) {
			$valuesArray[$arrayIndex] = FALSE;
		} else {
			switch( $response->type ) {
				case SNMP_BIT_STR:
				case SNMP_OCTET_STR:
				case SNMP_IPADDRESS:
					$valuesArray[$arrayIndex] = $response->value;
					break;
				case SNMP_COUNTER:
				case SNMP_UNSIGNED:
				case SNMP_UINTEGER:
				case SNMP_INTEGER:
				case SNMP_COUNTER64:
				case SNMP_TIMETICKS:
					$valuesArray[$arrayIndex] = 0 + $response->value;	// Coerce to number
					break;
				case SNMP_OBJECT_ID:
				case SNMP_OPAQUE:
				case SNMP_NULL:
					$valuesArray[$arrayIndex] = $response->value;
					break;
				default:
					$valuesArray[$arrayIndex] = $response->value;
					break;
			}
		}
		$arrayIndex += 1;
	}

	// Restore original SNMP settings
	snmp_set_valueretrieval( $valueRetrievalBefore );
	//snmp_set_quick_print( $quickPrintBefore );
	return $singleValueRequested ? $valuesArray[0] : $valuesArray;
}

/*
 * doSnmpWalk($host, $communityString, $mibSpec, $timeout, $retries)
 *
 * This function simplifies calls to snmprealwalk().
 */
function doSnmpWalk( $host, $communityString, $mibSpec, $timeout = 500000, $retries = 1 ) {
	$valueRetrievalBefore = snmp_get_valueretrieval();
	//$quickPrintBefore = snmp_get_quick_print();

	snmp_set_valueretrieval( SNMP_VALUE_OBJECT );
	//snmp_set_valueretrieval( SNMP_VALUE_OBJECT | SNMP_VALUE_PLAIN );	// Wrong; >= PHP 5.4?
	//snmp_set_quick_print( TRUE );	// Only applies for SNMP_VALUE_PLAIN retrieval, not SNMP_VALUE_OBJECT

	return snmprealwalk( $host, $communityString, $mibSpec, $timeout, $retries );

	// Restore original SNMP settings
	snmp_set_valueretrieval( $valueRetrievalBefore );
	//snmp_set_quick_print( $quickPrintBefore );
}
