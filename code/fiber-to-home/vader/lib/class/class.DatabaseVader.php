<?php
/*
 * class DatabaseVader
 * This class implements a singleton database connection.
 */
require_once dirname(__FILE__).'/../../include/config.php';

define("DATABASE_ERROR_CONNECT_ERROR",				1);
define("DATABASE_ERROR_SELECT_DB_ERROR",			2);
define("DATABASE_ERROR_QUERY_ERROR",				3);
define("DATABASE_ERROR_REFERENTIAL_INTEGRITY",		4);

/*
 * The calling script must have previously included config.php (which is standard).
 */
switch ($serverType) {
	case PRODUCTION:
		$hostnamePrmdata = "vdsltechsupp";
		$databasePrmdata = "PRMDATA";
		$usernamePrmdata = "rlorenz";
		$passwordPrmdata = "skywalk3";
		break;
	case INTEGRATION:
		$hostnamePrmdata = "vdsltechsupp";
		$databasePrmdata = "PRMDATA";
		$usernamePrmdata = "testvader";
		$passwordPrmdata = "iimitk";
		break;
	case TEST:
		$hostnamePrmdata = "vdsltechsupp";
		$databasePrmdata = "PRMDATA";
		$usernamePrmdata = "testvader";
		$passwordPrmdata = "iimitk";
		break;
}

class DatabaseVader {
private static $instance = NULL;
private $connection = NULL;

public static function getInstance( $dbHost = NULL, $dbUser = NULL, $dbPassword = NULL, $dbDatabase = NULL ) {
	if( ! self::$instance ) {
		self::$instance = new self( $dbHost, $dbUser, $dbPassword, $dbDatabase );
	}
		return self::$instance;
	}

private function __clone() {}	// Singleton

private function __construct( $dbHost = NULL, $dbUser = NULL, $dbPassword = NULL, $dbDatabase = NULL ) {
	global $hostnamePrmdata, $usernamePrmdata, $passwordPrmdata, $databasePrmdata;
	$dbHost = ($dbHost) ? $dbHost : $hostnamePrmdata;
	$dbUser = ($dbUser) ? $dbUser : $usernamePrmdata;
	$dbPassword = ($dbPassword) ? $dbPassword : $passwordPrmdata;
	$dbDatabase = ($dbDatabase) ? $dbDatbase : $databasePrmdata;
	$this->connection = mysql_connect( $dbHost, $dbUser, $dbPassword );	// NOTE: a resource will be returned (somehow) with all null parameters
	if( $this->connection ) {
		if( ! mysql_select_db( $dbDatabase, $this->connection ) ) {
			// NOTE: mysql error here is "No database selected" which is confusing (seems more like a query error)
			throw new Exception( mysql_error(), DATABASE_ERROR_SELECT_DB_ERROR );
		}
	} else {
		throw new Exception( mysql_error(), DATABASE_ERROR_CONNECT_ERROR );
	}
}

public function close() {
	mysql_close( $this->connection );
}

/*
 * function getOltArray( $oltIpAddress )
 * This function returns the OLT database entry for the single OLT by IP address.
 *
 * TODO: Merge this method with getOltDatabase()
 */
public function getOltArray( $oltIpAddress ) {
	$oltArray = array();
	$queryString = sprintf("select * from GPON_OLT_CHASSIS where GPON_OLT_CHASSIS.OLT_IP_ADDRESS = %s", $this->getSqlValueString( $oltIpAddress ) );
	$queryResult = mysql_query( $queryString, $this->connection );
	if( ! $queryResult ) {
		throw new Exception( mysql_error(), DATABASE_ERROR_QUERY_ERROR );
	}
	$rowCount = mysql_num_rows( $queryResult );
	if( $rowCount > 1 ) {
		throw new Exception( "More than one row returned", DATABASE_ERROR_REFERENTIAL_INTEGRITY );
	}
	if( $rowCount == 0 ) {
		return NULL;
	}
	$row = mysql_fetch_assoc( $queryResult );
	$oltArray['state'] = strtolower( $row['STATE'] );
	$oltArray['technology'] = strtolower( $row['TECHNOLOGY'] );
	$oltArray['vendor'] = strtolower( $row['VENDOR'] );
	$oltArray['centralOffice'] = $row['CENTRAL_OFFICE'];
	$oltArray['clli'] = $row['OLT_CLLI'];
	$oltArray['tid'] = $row['OLT_TID'];
	$oltArray['ipAddress'] = $row['OLT_IP_ADDRESS'];
	$oltArray['cotTid'] = $row['COT_TID'];
	$oltArray['ipAddressCot'] = $row['COT_IP_ADDRESS'];
	$oltArray['rfVideoLan'] = $row['RF_VIDEO_VLAN'];
	$oltArray['ipAddressEms'] = $row['EMS_IP_ADDRESS'];
	$oltArray['nconSourceDatabase'] = $row['NCON_SOURCE_DB'];
	$oltArray['status'] = strtolower( $row['STATUS'] );
	$oltArray['cuidLastUpdate'] = $row['CUID'];
	$oltArray['timeLastUpdate'] = $row['TIMESTMP'];
	return $oltArray;
}

/*
 * function getOltDatabase( $oltClass )
 * This function returns the entire Vader OLT database as an array of arrays.
 *
 * TODO: Merge this method with getOltArray()
 */
public function getOltDatabase( $oltClass = null ) {
	$oltArray = array();

	$queryString = "SELECT * FROM GPON_OLT_CHASSIS WHERE OLT_CLLI is not NULL AND OLT_IP_ADDRESS is not NULL AND STATUS != 'deleted'";
	switch( $oltClass ) {
		case DEVICE_VENDOR_ADTRAN:
			$queryString .= " AND GPON_OLT_CHASSIS.VENDOR = 'adtran'";
			break;
		case DEVICE_VENDOR_CALIX:
			$queryString .= " AND GPON_OLT_CHASSIS.VENDOR = 'calix'";
			break;
		default:
			break;
	}
	$queryString .= " order by STATE, CENTRAL_OFFICE";

	$queryResult = mysql_query( $queryString, $this->connection );
	if( ! $queryResult ) {
		throw new Exception( mysql_error(), DATABASE_ERROR_QUERY_ERROR );
	}

	while( $row = mysql_fetch_assoc( $queryResult ) ) {
		$oltRow['state'] = $row['STATE'];
		$oltRow['technology'] = $row['TECHNOLOGY'];
		$oltRow['vendor'] = $row['VENDOR'];
		$oltRow['centralOffice'] = $row['CENTRAL_OFFICE'];
		$oltRow['clli'] = $row['OLT_CLLI'];
		$oltRow['tid'] = $row['OLT_TID'];
		$oltRow['ipAddress'] = $row['OLT_IP_ADDRESS'];
		$oltRow['cotTid'] = $row['COT_TID'];
		$oltRow['ipAddressCot'] = $row['COT_IP_ADDRESS'];
		$oltRow['rfVideoLan'] = $row['RF_VIDEO_VLAN'];
		$oltRow['ipAddressEms'] = $row['EMS_IP_ADDRESS'];
		$oltRow['nconSourceDatabase'] = $row['NCON_SOURCE_DB'];
		$oltRow['status'] = $row['STATUS'];
		$oltRow['cuidLastUpdate'] = $row['CUID'];
		$oltRow['timeLastUpdate'] = $row['TIMESTMP'];
	    $oltArray[] = $oltRow;
	}

	mysql_free_result( $queryResult );
	return $oltArray;
}

public function getSqlValueString( $value, $type = "text", $definedValue = "", $notDefinedValue = "" ) {
	$value = get_magic_quotes_gpc() ? stripslashes($value) : $value;	// PHP version dependent; removes slashes from escaped quotes "\'"

	$value = function_exists("mysql_real_escape_string") ? mysql_real_escape_string($value) : mysql_escape_string($value);

	switch ($type) {
		case "text":
			$value = ($value != "") ? "'" . $value . "'" : "NULL";
			break;
		case "long":
		case "int":
			$value = ($value != "") ? intval($value) : "NULL";
			break;
		case "double":
			$value = ($value != "") ? "'" . doubleval($value) . "'" : "NULL";
			break;
		case "date":
			$value = ($value != "") ? "'" . $value . "'" : "NULL";
			break;
		case "defined":
			$value = ($value != "") ? $definedValue : $notDefinedValue;
			break;
	}
	return $value;
}

}
