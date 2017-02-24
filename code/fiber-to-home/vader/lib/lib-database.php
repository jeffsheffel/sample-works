<?php
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

/*
 * databaseConnect()
 *
 */
function databaseConnect( $hostname = NULL, $username = NULL, $password = NULL ) {
	global $hostnamePrmdata, $usernamePrmdata, $passwordPrmdata;

	// Use Vader instance defaults if caller didn't specify connection parameters
	if( empty( $hostname ) ) { $hostname = $hostnamePrmdata; }
	if( empty( $username ) ) { $username = $usernamePrmdata; }
	if( empty( $password ) ) { $password = $passwordPrmdata; }

	$databaseResource = mysql_pconnect($hostname, $username, $password) or trigger_error(mysql_error(),E_USER_ERROR);
	if( empty( $databaseResource ) ) {
		throw new Exception( mysql_error() );
	} else {
		return $databaseResource;
	}
}

/*
 * databaseConnectPDO()
 * http://www.php.net/manual/en/pdo.construct.php
 *
 * NOTES:
 * ) This method not supported since the PDO MySQL driver is not available?: http://www.php.net/manual/en/pdo.installation.php
 */
function databaseConnectPDO( $database = NULL, $hostname = NULL, $username = NULL, $password = NULL ) {
	global $databasePrmdata, $hostnamePrmdata, $usernamePrmdata, $passwordPrmdata;

	// Use Vader instance defaults if caller didn't specify connection parameters
	if( empty( $database ) ) { $database = $databasePrmdata; }
	if( empty( $hostname ) ) { $hostname = $hostnamePrmdata; }
	if( empty( $username ) ) { $username = $usernamePrmdata; }
	if( empty( $password ) ) { $password = $passwordPrmdata; }

	$dsn = "mysql:dbname=$database;host=$hostname";	// Optionally add: ;port=nnnn

	try {
		$dbh = new PDO($dsn, $username, $password);
	} catch (PDOException $e) {
		throw new Exception( $e->getMessage() );
	}
}

/*
 * getVaderOltArray( $oltClass )
 *
 */
function getVaderOltArray( $oltClass = null ) {
	global $databasePrmdata, $hostnamePrmdata, $usernamePrmdata, $passwordPrmdata;
	$oltArray = array();

	$databaseResource = databaseConnect($hostnamePrmdata, $usernamePrmdata, $passwordPrmdata);
	mysql_select_db($databasePrmdata, $databaseResource);
	$queryString = "SELECT OLT_CLLI,OLT_IP_ADDRESS,STATUS,VENDOR FROM GPON_OLT_CHASSIS WHERE OLT_CLLI is not NULL AND OLT_IP_ADDRESS is not NULL AND STATUS != 'deleted'";
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

	$queryResult = mysql_query($queryString, $databaseResource);
	if( !$queryResult ) {
		throw new Exception( "Query error: " . mysql_error() );
	}

	if( mysql_num_rows($queryResult) == 0 ) {
		// No rows found
		return NULL;
	}

	while ($row = mysql_fetch_assoc($queryResult)) {
	    $oltArray[] = $row;
	}

	mysql_free_result($queryResult);
	return $oltArray;
}
