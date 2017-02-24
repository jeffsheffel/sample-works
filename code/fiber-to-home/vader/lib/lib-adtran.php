<?php

/*
 * function hasAdtranPonCardHexFeature( $hexFeatureString, $featureAsTwoDigitHex )
 * This function checks if the 2-digit hexadecimal feature is part of a feature string.
 * The features begin at byte six.  If the feature exists, then the offset of the feature in the string
 * is returned.  If the feature is not included, then FALSE is returned.
 *
 * The Adtran PON card features are found at MIB: 1.3.6.1.4.1.664.5.70.1.1.1.3.<ponCardSlotNumber>
 *
 * Refer to the Vader SNMP Guide for more details.
 */
function hasAdtranPonCardHexFeature( $hexFeatureString, $featureAsTwoDigitHex ) {
	return twoByteHexPosition( $hexFeatureString, $featureAsTwoDigitHex, 6 );
}

/*
 * function computeInterfaceIndexes()
 *
 * NOTE: This function is borrowed from a forthcoming object class (class.OntAdtranTA300.php) to implement its functionality
 * in this current (non-OO) version of Vader (which will eventually be replaced).
 *
 * Refer to the Adtran Carrier Network SNMP ifIndex Application Guide document (Document Number: 65KIFAPP-49C December 2012),
 * section "Remote Terminal Proxy Extended ifIndex section".
 * Note that, the documentation can be confusing, in that the least significant bit (LSB) is named Bit 0.
 *
 * Example ifIndex bit encoding for ONT-1-1-4-2-15:
 *	1075985408 = 1000000001000100011110000000000	(P/N 1187500E1, 1187501G1)	Bit30=1, Bit29=0
 *	1678261248 = 1100100000010000011110000000000	(P/N 1187502F1)				Bit30=1, Bit29=1
 *
 * Also refer to the Vader Programming Guide for details on ifIndex bit encoding.
 */
function computeInterfaceIndexes( $ipAddress, $communityString, $card, $port, $ontNumber ) {

	$oidPonCardHexFeatures = "1.3.6.1.4.1.664.5.70.1.1.1.3." . $card;
	$ponCardHexFeaturesString = doSnmpGet( $ipAddress, $communityString, $oidPonCardHexFeatures );

	$shelfBase = PON_CARD_MULTI_SHELF_BASED;	// Supports up to 32 ONTs per PON port
	$singleShelfFeatureHex = "67";
	if( hasAdtranPonCardHexFeature( $ponCardHexFeaturesString, $singleShelfFeatureHex ) ) {
		$shelfBase = PON_CARD_SINGLE_SHELF_BASED;	// Supports up to 64 ONTs per PON port
	}

	switch( $shelfBase ) {
		case PON_CARD_MULTI_SHELF_BASED:
			/*
			 * Note that this is the original Vader code, which doesn't exactly follow the Adtran documentation convention,
			 * and doesn't consider a variable shelf number (Vader always assumes shelf 1); see code for next card in this switch statement.
			 */
			$extendedIfIndex = "10";	// Bit30=1, Bit29=0

			// TA5K or TA5006 slot number (10 bit zero-padded binary of slot number)
			// NOTE: this doesn't not correctly factor in a variable shelf number (per the Adtran documentation)
			$ponCardBits = str_pad( decbin($card), 10, "0", STR_PAD_LEFT );

			// PON Port (1 - 4) (3 bits)
			$ponPortBits = str_pad( decbin($port), 3, "0", STR_PAD_LEFT );

			// ONT # (1 - 32) (6 bits)
			$ontNumberBits = str_pad( decbin($ontNumber), 6, "0", STR_PAD_LEFT );
			break;

		case PON_CARD_SINGLE_SHELF_BASED:
			$extendedIfIndex = "11";	// Bit30=1, Bit29=1

			// TA5K or TA5006 slot number (5 bit zero-padded binary of slot number); only one shelf allowed (per documentation)
			$ponCardBits = str_pad( decbin($card), 5, "0", STR_PAD_LEFT );

			// PON Port (1 - 4) (6 bits)
			$ponPortBits = str_pad( decbin($port), 6, "0", STR_PAD_LEFT );

			// ONT # (1 - 32) (8 bits)
			$ontNumberBits = str_pad( decbin($ontNumber), 8, "0", STR_PAD_LEFT );
			break;
	}

	// Technology/Service type we're polling (4 bits)
	$portTypeBitEncodeTable = array(
			"ethernet" => str_pad(decbin(1), 4, "0", STR_PAD_LEFT),
			"pots" => str_pad(decbin(2), 4, "0", STR_PAD_LEFT),
			"rfvideo" => str_pad(decbin(3), 4, "0", STR_PAD_LEFT),
			"voip" => str_pad(decbin(4), 4, "0", STR_PAD_LEFT),
			"ds3" => str_pad(decbin(5), 4, "0", STR_PAD_LEFT),
			"ds1" => str_pad(decbin(6), 4, "0", STR_PAD_LEFT) );

	// ONT Technology/Service port number (0 - 63); currently only 2 ports
	// TODO: Add AdTran physical ports
	$portNumberBitEncodeTable = array(
			"port1" => str_pad(decbin(1), 6, "0", STR_PAD_LEFT),
			"port2" => str_pad(decbin(2), 6, "0", STR_PAD_LEFT) );

	// NOTE: If technology/service type and ONT port bits are set to 0, then the interfaceIndex refers to the ONT itself
	$interfaceIndexOnt = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . "0000" . "000000");
	$interfaceIndexPots1 = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . $portTypeBitEncodeTable['pots'] . $portNumberBitEncodeTable['port1']);
	$interfaceIndexPots2 = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . $portTypeBitEncodeTable['pots'] . $portNumberBitEncodeTable['port2']);
	$interfaceIndexEthernet1 = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . $portTypeBitEncodeTable['ethernet'] . $portNumberBitEncodeTable['port1']);
	$interfaceIndexEthernet2 = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . $portTypeBitEncodeTable['ethernet'] . $portNumberBitEncodeTable['port2']);
	$interfaceIndexRfVideo = bindec($extendedIfIndex . $ponCardBits . $ponPortBits . $ontNumberBits . $portTypeBitEncodeTable['rfvideo'] . $portNumberBitEncodeTable['port1']);

	return( array( $interfaceIndexOnt, $interfaceIndexPots1, $interfaceIndexPots2, $interfaceIndexEthernet1, $interfaceIndexEthernet2, $interfaceIndexRfVideo ) );
}

/*
 * function convertMibIndexToOntAid( $mibIndex )
 *
 * Refer to the Adtran Carrier Network SNMP ifIndex Application Guide document (Document Number: 65KIFAPP-49C December 2012),
 * section "Remote Terminal Proxy Extended ifIndex section".
 * Note that, the documentation can be confusing, in that the least significant bit (LSB) is named Bit 0.
 *
 * Example ifIndex bit encoding for ONT-1-1-4-2-15:
 *	1075985408 = 1000000001000100011110000000000	(P/N 1187500E1, 1187501G1)	Bit30=1, Bit29=0
 *	1678261248 = 1100100000010000011110000000000	(P/N 1187502F1)				Bit30=1, Bit29=1
 *
 * Also refer to the Vader Programming Guide for details on ifIndex bit encoding.
 *	1075985408 = 10 0000000100 010 001111 0000000000	for Multi-shelf base interface indexing scheme
 *	1678261248 = 11 00100 000010 00001111 0000000000	for Single-shelf base interface indexing scheme
 */
function convertMibIndexToOntAid( $mibIndex ) {
	$binary = decbin( $mibIndex );
	if( strlen($binary) < 31 ) return FALSE;
	if( strlen($binary) > 31 ) return FALSE;

	$shelfBaseBit = substr( $binary, 1, 1 );

	if( $shelfBaseBit ) {
		// Parse out binary strings for Single-shelf base interface indexing scheme
		$cardBin = substr( $binary, 2, 5 );
		$portBin = substr( $binary, 7, 6 );
		$ontBin = substr( $binary, 13, 8 );
	} else {
		// Parse out binary strings for Multi-shelf base interface indexing scheme
		$cardBin = substr( $binary, 2, 10 );
		$portBin = substr( $binary, 12, 3 );
		$ontBin = substr( $binary, 15, 6 );
	}

	# Convert binary strings to decimal
	$card = bindec( $cardBin );
	$port = bindec( $portBin );
	$ontNumber = bindec( $ontBin );

	return "1-1-".$card."-".$port."-".$ontNumber;
}

/*
 * function potsSignalingModeToText( $ontPotsSignalingMode )
 * The POTs service mode has the following values 0 = none, 1 = GR-303, 2 = SIP, 3 = MGCP
 * The signaling mode on the fxs port conveys if is TR08 or not. Use adTA5kPotsPortMode OID.
 *	adTA5kPotsPortMode OBJECT-TYPE
 *		SYNTAX  INTEGER { loopStart(1), groundStart(2), tr08SingleParty(3), tr08Uvg(4) }
 *		DESCRIPTION "POTS port operational Mode - 1 = Loopstart 2 = Groundstart 3 = TR08 Single Party 4 = TR08 UVG" ::= { adTA5kPotsPortProvEntry 1 }
 */
function potsSignalingModeToText( $ontPotsSignalingMode ) {
	switch( $ontPotsSignalingMode ) {
		case 1:	return "Loopstart"; break;
		case 2:	return "Groundstart"; break;
		case 3:	return "TR08_single_party"; break;
		case 4:	return "TR08_UVG"; break;
		default:	return "unknown"; break;
	}
}

/*
 * function embedVideoPipeRateInProfileNames( $ethProfileNamesArray, $cardType )
 * This function receives an array of Ethernet profile names and converts a standard video profile name to a
 * profile name that has a standard "pipe rate" embedded in the name.
 *
 *	"video_traffic_GPON" becomes "video_traffic_2500000x2500000_GPON"
 *
 * The reason for this function is, that the QwestRx client application parses out a pipe rate that was once a standard
 * naming convention in the (DSL) profile names.  But, as services are now provided via GPON technology, profile names no
 * longer contain the pipe rate.  In essence, the fact that QwestRx parses a rate from a name is silly, or maybe that the
 * company standard was once to embed the rate in the name... maybe that's the only way it could be implemented?
 *
 * There are two Adtran GPON cards (1179 and 1189), which are both 2.5GB cards, so the $cardType is not (yet) used.
 */
function embedVideoPipeRateInProfileNames( $ethProfileNamesArray, $cardType ) {
	foreach( $ethProfileNamesArray as $profileName ) {
		$ethProfileNamesArrayNew[] = ($profileName == "video_traffic_GPON") ? "video_traffic_2500000x2500000_GPON" : $profileName;
	}
	return $ethProfileNamesArrayNew;
}

/*
 * function adtranCleiCodeToModel( $cleiCode )
 *
 * Refer to the Vader-ONT-accounting.docx document.
 * Also refer to duplicate code in the OntAdtranTA300::setDeviceConfig() method.
 */
function adtranCleiCodeToModel( $cleiCode ) {
	switch( $cleiCode ) {
		case "":
			return "N/A";
			break;
		case "BVMB600FRB":
			return "TA324";
			break;
		case "BVMBM10FRA":
			return "TA324RG";
			break;
		case "BVMBB10FRA":
			return "TA334";
			break;
		case "BVL3AFTDTA":
			return "TA351";
			break;
		case "BVL3AP8DTA":
			return "TA351 2nd Gen";
			break;
		case "BVL3AFUDTA":
			return "TA352";
			break;
		case "BVL3AP9DTA":
		case "BVL3ATUDTA":
			return "TA352 2nd Gen";
			break;
		case "BVMB900FRA":
			return "TA362";
			break;
		case "BVM8K00ERA":
			return "TA362S";
			break;
		case "BVM9N00BRA":
			return "TA372";
			break;
		case "BVMB400FRA":
			return "TA372R 2nd Gen";
			break;
		case "BVM9Z00BRA":
			return "TA374";
			break;
		default:
			return "Unknown";
			break;
	}
}
