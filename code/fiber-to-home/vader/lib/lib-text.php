<?php

/*
 * function GetSQLValueString()
 * Define function GetSQLValueString() if it doesn't exist.
 * The function conditions values for SQL inserts and wraps the values in single quotes (for SQL).
 *
 * NOTES:
 *	) A MySQL database connection must be established before calling this function; otherwise the mysql_real_escape_string() function
 *		does exist, but it can't be called (until there is a database connection).
 */
if (!function_exists("GetSQLValueString")) {
	function GetSQLValueString($theValue, $theType = "text", $theDefinedValue = "", $theNotDefinedValue = "")
	{
		$theValue = get_magic_quotes_gpc() ? stripslashes($theValue) : $theValue;	// PHP version dependent; removes slashes from escaped quotes "\'"

		$theValue = function_exists("mysql_real_escape_string") ? mysql_real_escape_string($theValue) : mysql_escape_string($theValue);

		switch ($theType) {
			case "text":
				$theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
				break;
			case "long":
			case "int":
				$theValue = ($theValue != "") ? intval($theValue) : "NULL";
				break;
			case "double":
				$theValue = ($theValue != "") ? "'" . doubleval($theValue) . "'" : "NULL";
				break;
			case "date":
				$theValue = ($theValue != "") ? "'" . $theValue . "'" : "NULL";
				break;
			case "defined":
				$theValue = ($theValue != "") ? $theDefinedValue : $theNotDefinedValue;
				break;
		}
		return $theValue;
	}
}


/*
 * function parseAidCalix( $aid )
 * This function parses a Calix AID string, and returns an array of parsed values.
 *
 * The input AID format should be one of the following:
 *	Calix without servicePort:	ONT-12-2-4-64
 *	Calix with servicePort:		ONT-12-2-4-64-1
 */
function parseAidCalix($aid) {
	$aidArray = preg_split('/-/', $aid, -1);
	switch($aidArray[0]) {
		case "ONT":
			$shelf			= !empty($aidArray[1]) ? $aidArray[1] : ""; // Shelf (1-10)
			$card			= !empty($aidArray[2]) ? $aidArray[2] : "";	// PON card (1-2)
			$port			= !empty($aidArray[3]) ? $aidArray[3] : "";	// PON port (1-4)
			$ont			= !empty($aidArray[4]) ? $aidArray[4] : "";	// ONT # (1-64)
			$servicePort	= !empty($aidArray[5]) ? $aidArray[5] : "";	// Service port (1-?)
			$ontId	= $shelf . $card . $port . $ont;
			return array($shelf, $card, $port, $ont, $servicePort, $ontId);
			break;
		default:
			return NULL;
			break;
	}
}

/*
 * function parseAidAdtran( $aid )
 * This function parses a Adtran AID string, and returns an array of parsed values.
 * Adtran differs from Calix by having the additional rack number as the first value.
 *
 * The input AID format should be one of the following:
 *	Adtran without servicePort:	ONT-1-1-4-4-64
 *	Adtran with servicePort:	ONT-1-1-4-4-64-1
 */
function parseAidAdtran($aid) {
	$aidArray = preg_split('/-/', $aid, -1);
	switch($aidArray[0]) {
		case "ONT":
			$rack			= !empty($aidArray[1]) ? $aidArray[1] : ""; // Rack (1)
			$shelf			= !empty($aidArray[2]) ? $aidArray[2] : ""; // Shelf (1)
			$card			= !empty($aidArray[3]) ? $aidArray[3] : "";	// PON card (1-22)
			$port			= !empty($aidArray[4]) ? $aidArray[4] : "";	// PON port (1-4)
			$ont			= !empty($aidArray[5]) ? $aidArray[5] : "";	// ONT # (1-64)
			$servicePort	= !empty($aidArray[6]) ? $aidArray[6] : "";	// Service port (1-?)
			$ontId	= $rack . $shelf . $card . $port . $ont;
			return array($rack, $shelf, $card, $port, $ont, $servicePort, $ontId);
			break;
		default:
			return NULL;
			break;
	}
}

/*
 * function formatXmlString()
 * Formats an XML string into a human-readable and an indented work of art.
 *  @param string $xml Input XML
 *  @param boolean $outputHtml Set to TRUE if XML should run through htmlentities before return
 *  @param integer $indentCount Number of characters for each indentation
 *  @param string $indentCharacters String to use for indentation; defaults to single space, could be "\t"
 */
function formatXmlString($xmlInput, $outputHtml = FALSE, $indentCount = 4, $indentCharacters = ' ') {
	$xmlObject = new SimpleXMLElement($xmlInput);
	$indentLevel = 0;	// Indentation level counter
	$xmlFormattedArray = array();

	// Get an array containing each XML element
	$xmlArray = explode("\n", preg_replace('/>\s*</', ">\n<", $xmlObject->asXML()));

	// Shift off opening XML tag if present
	if ( count($xmlArray) && preg_match('/^<\?\s*xml/', $xmlArray[0]) ) {
		$xmlFormattedArray[] = array_shift($xmlArray);
	}

	// Process each element (as returned by asXML() method above) updating indentation level accordingly
	foreach( $xmlArray as $element ) {
		if( preg_match('/^<\/.+>$/', $element) ) {
			// Found closing tag, decrease indent
			$indentLevel -= $indentCount;
			if ($indentLevel < 0) { $indentLevel = 0; }
			$xmlFormattedArray[] = str_repeat($indentCharacters, $indentLevel) . $element;
		} elseif( preg_match('/<\//', $element) || preg_match('/\/>/', $element) ) {
			// Complete element or empty element, so no indent changes
			$xmlFormattedArray[] = str_repeat($indentCharacters, $indentLevel) . $element;
		} else {
			// Otherwise, element is an opening tag, increase indent
			$xmlFormattedArray[] = str_repeat($indentCharacters, $indentLevel) . $element;
			$indentLevel += $indentCount;
		}
	}
	$xmlFormatted = implode("\n", $xmlFormattedArray);
	return ($outputHtml) ? htmlentities($xmlFormatted) : $xmlFormatted;
}

/*
 * twoByteHexPosition( $binaryString, $twoByteHexString, [$offset] )
 * This function returns the position of a 2-byte hexadecimal string (eg. "0F") within a binary string
 * (which can be a PHP string).  If not found, FALSE is returned (much like strnpos()).
 */
function twoByteHexPosition( $binaryString, $twoByteHexString, $offset = 0 ) {
	$hexString = bin2hex( $binaryString );
	$hexString = substr( $hexString, $offset );
	for( $iii=0; $iii<strlen($hexString); $iii++ ) {
		if( strncmp( substr($hexString, $iii), $twoByteHexString, 2 ) == 0 ) {
			return $iii + $offset;
		}
	}
	return FALSE;	// Two-byte hex string not found
}

/*
 * function mapKeyFromArray( $index, $arrayOrHash, $default )
 * This function returns the value associated with the array value associated with the input index.
 * Curiously, this function works with simple arrays or hashes!
 *
 * Example:
 *	$value = mapKeyFromArray( $index, array( "value0", "value1" ), "no_match_default" );
 *		or
 *	$hashValue = mapKeyFromArray( $key, array( 0 => "value0", "one" => "value1" ), "no_match_default" );
 *
 * Also refer to the built-in PHP array_map() function.
 */
function mapKeyFromArray( $index, $arrayOrHash, $default = NULL ) {
	if( array_key_exists( $index, $arrayOrHash ) ) return $arrayOrHash[$index];
	return $default;
}
