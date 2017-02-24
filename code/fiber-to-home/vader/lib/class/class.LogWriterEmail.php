<?php
/*
 * class.LogWriterEmail.php
 *
 * This class implements a logging interface to email addresses.  The write() method adds a throttling of email
 * messages by checking (database) log entry timestamps.  For example, if there is already a database log entry that was
 * written more recently than 24 hours, then an email is assumed to have already been sent.  Hence, for this class
 * to work properly, this class must be used in conjunction with the LogWriterDatabase class.
 *
 * There are no close() or destroy() methods, as we assume PHP objects are destroyed when PHP finishes,
 * and there are no open resources at that time.  Read more details in the write() method comments.
 */
require_once dirname(__FILE__).'/../../include/config.php';
require_once 'lib-database.php';
require_once 'lib-text.php';

class LogWriterEmail {
public $emailAddress;
public $notifyPeriod;
private $databaseHandle = NULL;
public $databaseName;
private $logTable;		// Sets to default if client doesn't specify in object constructor

	/*
	 * function __construct($tableName)
	 * This object constructor establishes a database connection.
	 * It is expected that the (Vader instance default) user has table write permissions.
	 */
	function __construct( $emailAddress, $notifyPeriod = DURATION_24_HOURS_IN_SECONDS, $tableName = NULL, $databaseName = NULL ) {
		global $databasePrmdata;
		$this->emailAddress = $emailAddress;
		$this->notifyPeriod = $notifyPeriod;
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
	 * This function reads entries from the database log, filters by log domain (eg. OLT-LTTNCOMLH0), and sends an email
	 * if there are zero or one entries within the last notification period.
	 * There is no filtering by log level or state, which could be added.
	 */
	function write( $message = "", $level = 0, $domain = "", $state = 0 ) {
		global $urlParams, $transactionId;
		global $logLevelToTextArray, $logStateToTextArray, $emailAddrWarnings;

		$clientIpAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "0.0.0.0";
		$scriptName = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
		$transId = isset($transactionId) ? $transactionId : "00000000000000000";

		// Query all database log entries of the same (log) domain which have occurred within this log writer object's notification period
		// Table columns: timestamp, domain, state, level, message, clientip, script, transid
		$statementSelect = "select * from $this->logTable where domain = " .
			GetSQLValueString( $domain ) . " and timestampdiff(second, timestamp, now()) < $this->notifyPeriod";

		mysql_select_db( $this->databaseName, $this->databaseHandle );
		$resultSelect = mysql_query( $statementSelect, $this->databaseHandle );

		if( !$resultSelect ) {
			throw new Exception( "Failed database log read from $this->databaseName : " . mysql_error() );
		}

		$rowCount = mysql_num_rows($resultSelect);
		//$row = mysql_fetch_assoc($resultSelect);

		// Assume that maybe the first database log message has been written already
		if( $rowCount <= 1 ) {
			$emailSubject = "Vader " . $logLevelToTextArray[$level] . " on " . $logStateToTextArray[$state];
			$message .= sprintf( "\n\nTransaction ID: %s", $transId );
			$message .= sprintf( "\n\nThere have been %d logged messages within the last (notify period) %d seconds.", $rowCount, $this->notifyPeriod );
			mail($emailAddrWarnings, $emailSubject, $message);
		}
	}
}
