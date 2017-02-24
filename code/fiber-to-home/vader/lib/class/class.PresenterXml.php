<?php

define( "RESPONSE_STATUS_TEXT_OK",			"ok" );
define( "RESPONSE_STATUS_TEXT_ERROR",		"error" );

define( "NOTICE_TYPE_TEXT_INFO",			"info" );
define( "NOTICE_TYPE_TEXT_WARNING",			"warning" );
define( "NOTICE_TYPE_TEXT_ERROR",			"error" );

class PresenterXml {
public $xml = NULL;
public $noticeCount = 0;

function __construct( $root ) {
	if( is_string( $root ) ) $this->xml = new SimpleXMLElement( $root );
	if( is_object( $root ) && ( get_class( $root ) == "PresenterXml" || is_subclass_of( $root, "PresenterXml" ) ) ) {
		// Copy object properties
		foreach( get_object_vars( $root ) as $property => $value ) {
			$this->$property = $value;
		}
	}
	if( ! isset( $this->xml ) ) $this->xml = new SimpleXMLElement( "<vaderRoot/>" );	// Default
}

/*
 * __toString()
 * This method is used to get a debug string.  It creates a string representation of an array that is formed of
 * certain attributes of the object:
 *	- all object properties *except* the SimpleXMLElement object (which could be large so we exclude it)
 *	- the text representation of the SimpleXMLElement object (which would be smaller)
 */
public function __toString() {
	$outputs = array();
	// Exclude large properties, like $xml and $root
	foreach( get_object_vars( $this ) as $property => $value ) {
		if( $property != "xml" ) {
			$outputs[$property] = $this->$property;
		}
	}
	// Format the XML
	$outputs['xml'] = $this->xml->asXml();
	return var_export( $outputs, TRUE );
}

/*
 * function getXml( $removeEmptyElements )
 *
 * This function returns the XML string of the presenter object.
 * The default is to remove empty (no children) and blank (no text) XML elements, unless the $removeEmptyElements parameter
 * is set to FALSE.  An empty root element will not be removed (as that would break the asXml() call).
 */
public function getXml( $removeEmptyElements = TRUE ) {
	global $noticeTypeToTextArray;

	if( $removeEmptyElements ) {
		$this->removeEmptyElements();
	}
	return formatXmlString( $this->xml->asXml(), FALSE, 4 );
}

/*
 * function removeEmptyElements()
 * Remove empty (no children) and blank (no text) XML elements, but not an empty root element (/child::*).
 * This does not work recursively; meaning after empty child elements are removed, parents are not reexamined.
 */
function removeEmptyElements() {
	foreach( $this->xml->xpath('/child::*//*[not(*) and not(text()[normalize-space()])]') as $emptyElement ) {
		unset( $emptyElement[0] );
	}
}

function setRequest( $urlParams = NULL ) {
	if( ! empty( $urlParams['oltip'] ) ) $this->xml->requestProperties->oltIpAddress = $urlParams['oltip'];
	if( ! empty( $urlParams['aid'] ) ) $this->xml->requestProperties->aid = $urlParams['aid'];
	$this->xml->requestProperties->clientIpAddress = $_SERVER['REMOTE_ADDR'];
	$sessionUser = getSessionVar('valid_user');	// Must variablize for empty() call
	if( ! empty( $sessionUser ) ) $this->xml->requestProperties->username = $sessionUser;
	if( ! empty( $urlParams['debugLevel'] ) ) $this->xml->requestProperties->debugLevel = $urlParams['debugLevel'];
	$this->xml->requestProperties->rawRequest = $_SERVER["REQUEST_URI"];
}

function setTransactionId( $transactionId = TRANSACTION_ID_NULL ) {
	$this->xml->responseProperties->transactionId = $transactionId;
}

function setVaderVersion( $versionVaderInstance = "Unknown", $versionVaderRelease = "Unknown" ) {
	$this->xml->responseProperties->vaderVersion = $versionVaderInstance . "/" . $versionVaderRelease;
}

function setResponseStatus( $status = RESPONSE_STATUS_TEXT_OK ) {
	$this->xml->responseProperties->status = $status;
}

/*
 * function addResponseNotice( $type, $text )
 * This is a method adds notices to the XML.
 */
function addResponseNotice( $type, $text ) {
	$this->xml->responseProperties->notice[$noticeCount] = $text;
	$this->xml->responseProperties->notice[$noticeCount]['type'] = $noticeTypeToTextArray[ $type ];
	$noticeCount++;
}

}
