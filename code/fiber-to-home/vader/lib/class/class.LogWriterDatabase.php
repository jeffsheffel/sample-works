<?php
/*
 * class.LogWriterDatabase.php
 *
 * This class implements a logging interface to a database.
 *
 * There are no close() or destroy() methods, as we assume PHP objects are destroyed when PHP finishes,
 * and there are no open resources at that time.  Read more details in the write() method comments.
 */
require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-database.php';
require_once 'lib-text.php';

class LogWriterDatabase {
private $databaseHandle = NULL;
public $databaseName;
private $logTable;		// Sets to default if client doesn't specify in object constructor
public $logEntryState = LOG_STATE_NONE;
public $logEntryStateText = LOG_STATE_TEXT_NONE;
public $logEntryDomain = "No-domain";
public $logEntryLevel = LOG_LEVEL_NONE;
public $logEntryMessage = "No message";

	/*
	 * function __construct($tableName)
	 * This object constructor establishes a PDO database connection.
	 * It is expected that the (Vader instance default) user has table write permissions.
	 */
	function __construct( $tableName = NULL, $databaseName = NULL ) {
		global $databasePrmdata;
		!empty( $tableName ) ? $this->logTable = $tableName : $this->logTable = $dbTableMessageLog;
		if( empty( $databaseName ) ) { $this->databaseName = $databasePrmdata; }

		try {
			//$this->databaseHandle = databaseConnectPDO( $databaseName );	// Use Vader instance connection defaults
			$this->databaseHandle = databaseConnect();	// Use Vader instance connection defaults
		}
		catch( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}

	/*
	 * function write($message, $level, $domain, $state)
	 *
	 */
	function write( $message = "", $level = 0, $domain = "", $state = 0 ) {
		global $urlParams, $transactionId;
		if( empty( $level ) ) { $level = $this->logEntryLevel; }
		if( empty( $domain ) ) { $domain = $this->logEntryDomain; }
		if( empty( $state ) ) { $state = $this->logEntryState; }
		$clientIpAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "0.0.0.0";
		$scriptName = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
		$transId = isset($transactionId) ? $transactionId : "00000000000000000";

		$statementInsert = "insert into $this->logTable (timestamp, domain, state, level, message, clientip, script, transid) values (now(), ".
			GetSQLValueString( $domain ) . ",".
			GetSQLValueString( $state, SQL_VALUE_INT ) . ",".
			GetSQLValueString( $level, SQL_VALUE_INT ) . ",".
			GetSQLValueString( $message ) . ",".
			GetSQLValueString( $clientIpAddress ) . ",".
			GetSQLValueString( $scriptName ) . ",".
			GetSQLValueString( $transId ) . ")";
		// Alternate PDO method (which isn't supported by Vader)
		//$statementInsert = "insert into $this->logTable (timestamp, domain, state, level, message, clientip, script, transid) values (now(),?,?,?,?,?,?,?)";

		mysql_select_db( $this->databaseName, $this->databaseHandle );
		$resultInsert = mysql_query( $statementInsert, $this->databaseHandle );
		// Alternate PDO method (which isn't supported by Vader)
		//$sth = $this->databaseHandle->prepare( $statementInsert );
		//$sth->execute( array( $domain, $state, $level, $message, $clientIpAddress, $scriptName, $transId ) );

		if( !$resultInsert ) {
			throw new Exception( "Failed database log write to $this->databaseName : " . mysql_error() );
		}
	}
}
