<?php

/*
 * function getOntStatusPresenterXml( $urlParams, $transactionId )
 * This function does a much work to:
 *	- query the Vader OLT database to determine the OLT properties (eg. vendor, TID, EMS/CMS IP address)
 *	- instantiate the OLT and ONT objects
 *	- run the OLT and ONT object methods to determine the OLT status
 *	- build the (vendor specific) XML presenter object
 *	- return the XML presenter object
 */
function getOntStatusPresenterXml( $urlParams = NULL, $transactionId = TRANSACTION_ID_NULL ) {
	global $versionVaderInstance, $versionVaderRelease, $flagDebug;

	$debugTextPrepend = ( userAgentType() == AGENT_TYPE_BROWSER ) ? "<pre>\n" : "";
	$debugTextAppend = ( userAgentType() == AGENT_TYPE_BROWSER ) ? "</pre>\n" : "";

	$presenter = new PresenterXmlOntStatus( "<vaderOntStatus/>" );
	$presenter->setRequest( $urlParams );
	$presenter->setTransactionId( $transactionId );
	$presenter->setVaderVersion( $versionVaderInstance, $versionVaderRelease );
	$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_OK );

	try {
		$database = DatabaseVader::getInstance();
		$oltDatabaseAttributes = $database->getOltArray( $urlParams['oltip'] );
	}
	catch (Exception $e) {
		// TODO: Respond with Vader error response
		// TODO: Send admin notification of Vader parse failure error
		switch( $e->getCode() ) {
			case DATABASE_ERROR_CONNECT_ERROR:
			case DATABASE_ERROR_SELECT_DB_ERROR:
			case DATABASE_ERROR_QUERY_ERROR:
			case DATABASE_ERROR_REFERENTIAL_INTEGRITY:
			default:
				$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
				$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "Vader OLT database error: " . $e->getMessage() );
				break;
		}
		return $presenter;
	}

	if( ! $oltDatabaseAttributes ) {
		$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
		$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "No such OLT IP address in the Vader OLT database" );
		return $presenter;
	}

	// Branch to vendor-specific module
	switch( $oltDatabaseAttributes['vendor'] ) {
		case "adtran":
			require_once 'class.PresenterXmlOntStatusAdtran.php';
			$presenter = new PresenterXmlOntStatusAdtran( $presenter );	// New presenter object by copying existing presenter properties

			$aidArray = parseAidAdtran( $urlParams['aid'] );
			list( $rack, $shelf, $card, $port, $ontNumber, $servicePort, $ontId ) = $aidArray;

			// Objectify the OLT (and PON card)
			try {
				$olt = new OltAdtranTA5000( $oltDatabaseAttributes, $rack, $shelf );
				$olt->getOltPonCardProperties( $card );	// Must call before getting PON port properties (or first set $olt->slot = $card)
				$olt->getOltPonPortProperties( $port );
			}
			catch( Exception $e ) {
				switch( $e->getCode() ) {
					case OLT_ERROR_CARD_NOT_GPON:
					case OLT_ERROR_CARD_PRODUCT_CODE_NOT_GPON:
					default:
						$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
						$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "OLT error: " . $e->getMessage() );
						return $presenter;
						break;
				}
			}
			if( $flagDebug ) {
				print $debugTextPrepend . $olt . $debugTextAppend;
			}

			// Objectify the ONT
			try {
				$ont = new OntAdtranTA300( $olt->ipAddress, $card, $port, $ontNumber );
			}
			catch( Exception $e ) {
				switch( $e->getCode() ) {
					case ONT_ERROR_ONT_NONEXISTENT:
						$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
						$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "ONT non-existent" );
						return $presenter;
						break;
				}
			}
			if( $flagDebug ) {
				print $debugTextPrepend . $ont . $debugTextAppend;
			}
			break;
		case "calix":
			require_once 'class.PresenterXmlOntStatusCalix.php';
			$presenter = new PresenterXmlOntStatusCalix( $presenter );	// New presenter object by copying existing presenter properties

			$cms = new CalixManagementSystem( NULL, $urlParams['oltip'] );

			try {
				$cms->login();
			}
			catch( Exception $e ) {
				// TODO: Notify admin of failed CMS login
				$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
				$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "Failed CMS login to: " . $Cms->ipAddress . " : " . $e->getMessage() );
				return $presenter;
			}
			$cms->setAid( $urlParams['aid'] );	// Set object's default AID properties

			$olt = new OltCalixE7( $oltDatabaseAttributes );
			//$olt->attachCms( $cms );	// Optional to not require CMS object to subsequent method calls
			$olt->getOltBasics( $cms );
			$olt->getOltStats( $cms );
			$olt->getPonCardBasics( $cms );
			$olt->getPonCardSoftwareLevels( $cms );
			$olt->getPonCardAttributes( $cms );
			$olt->getPonPortBasics( $cms );
			$olt->getPonPortState( $cms );

			if( $flagDebug ) {
				print $debugTextPrepend . $olt . $debugTextAppend;
			}

			$ont = new OntCalix700( $oltDatabaseAttributes );
			//$ont->attachCms( $cms );	// Optional to not require CMS object to subsequent method calls
			$ont->getOntBasics( $cms );
			$ont->getOntState( $cms );
			$ont->getOntDetails( $cms );
			$ont->setDeviceConfig();	// Must call after getOntState() (which sets the cleiCode)
			$ont->buildOntPotsPortProperties( $cms );
			$ont->buildOntGePortProperties( $cms );

			if( $flagDebug ) {
				print $debugTextPrepend . $ont . $debugTextAppend;
			}
			break;
		case "test":
			return NULL;	// TODO: Implement presenter for test ont-status module (which doesn't yet exist)
			//include('ftth/test/gpon-request-test.php');
			break;
		default:
			$presenter->setResponseStatus( RESPONSE_STATUS_TEXT_ERROR );
			$presenter->addResponseNotice( NOTICE_TYPE_TEXT_ERROR, "OLT vendor type is missing from the Vader database" );
			return $presenter;
			break;
	}

	// Construct XML response
	$presenter->olt = $olt;
	$presenter->ont = $ont;
	$presenter->setOntElement();
	$presenter->setPotsElement();
	$presenter->setEthernetElement();

	return $presenter;
}
