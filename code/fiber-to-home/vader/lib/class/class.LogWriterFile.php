<?php
/*
 * class.LogWriterFile.php
 *
 * This class implements a logfile writer (abstract) class, which is used by the Log class.
 * This class is basically modeled after the similar Zend class:
 *	http://framework.zend.com/manual/1.12/en/zend.log.writers.html
 *
 * There are no close() or destroy() methods, as we assume PHP objects are destroyed when PHP finishes,
 * and there are no open resources at that time.  Read more details in the write() method comments.
 */
require_once dirname(__FILE__).'/../../include/config.php';

class LogWriterFile {
public $logFilename = '/tmp/vader-logfile-default.txt';
public $timestampFormat;
private $logFilePointer = NULL;

/*
 * function __construct($filename)
 * This object constructor simply sets the logfile pathname.
 *
 * IDEA: The input could be a filename or resource, which would allow consumer to use its own resource:
 *	if( is_resource($nameOrResource) )...
 */
function __construct( $filename = NULL ) {
global $timestampFormatDefault;
	if( isset($filename) ) { $this->logFilename = $filename; }
	$this->timestampFormat = $timestampFormatDefault;
}

/*
 * function write($message)
 * This method opens the (object's) logfile, obtains an exclusive lock, writes a formatted message, releases
 * the lock, and closes the file.  This is done for every write, as it is assumed that all output will be
 * written at once, and other processes may be waiting on the lock release.
 */
public function write( $message = "No message" ) {
	if( !($this->logFilePointer = fopen( $this->logFilename, 'a' )) ){
		throw new Exception( "Can't open: $this->logFilename" );
	}
	if( !flock( $this->logFilePointer, LOCK_EX ) ) {
		throw new Exception( "Can't obtain exclusive lock on: $this->logFilename" );
	}
	$messageFormatted = $this->formatMessage( $message );
	fwrite( $this->logFilePointer, $messageFormatted );
	flock( $this->logFilePointer, LOCK_UN );
	fclose( $this->logFilePointer );
}

/*
 * function formatMessage($message)
 * This function wraps a simple text message with standard (log) message fields.
 *
 * TODO: Refactor this method to allow consumers to alter the message formatting.
 */
public function formatMessage( $message ) {
global $transactionId;
	$timestamp = @date($this->timestampFormat);
	$scriptName = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
	$clientIpAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : "0.0.0.0";
	$transId = isset($transactionId) ? $transactionId : "00000000000000000";
	$messageFormatted = $timestamp . " " . $transId . " " . $clientIpAddress . " " . $scriptName . " " . $message . PHP_EOL;	// IDEA: Call getOutputFormat or some such
	return $messageFormatted;
}

// public function setOutputFormat()
// public function getOutputFormat()

}
