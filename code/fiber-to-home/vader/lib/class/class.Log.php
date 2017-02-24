<?php
/*
 * class.Log.php
 *
 * This class implements a logging system.
 *
 * This class is basically modeled after the Zend log class:
 *	http://framework.zend.com/manual/1.12/en/zend.log.html
 *
 * Only the eight RFC 5424 levels (debug, info, notice, warning, error, critical, alert, emergency) are present for basic
 * filtering purposes, but for sorting and other use cases that would require flexibility, you should add Processors to
 * the Logger that can add extra information (tags, user ip, ..) to the records before they are handled.
 *
 */
require_once dirname(__FILE__).'/../../include/config.php';

class Log {
public $writers = array();

/*
 * function __construct($writer)
 *
 * This object constructor simply assigns the log writer (a LogWriter subclass object).
 */
function __construct($writer = NULL) {
	if( isset( $writer ) ) {
		$this->writers[] = $writer;
	}
}

/*
 * function info($message)
 *
 */
function info( $message = NULL, $level = 0, $domain = "", $state = 0 ) {
	foreach( $this->writers as $writer ) {
		try {
			$writer->write( $message, $level, $domain, $state );
		}
		catch( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
}

/*
 * function warning($message)
 *
 */
function warning( $message = NULL, $level = 0, $domain = "", $state = 0 ) {
	foreach( $this->writers as $writer ) {
		try {
			$writer->write( $message, $level, $domain, $state );
		}
		catch( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
}

/*
 * function error($message)
 *
 */
function error( $message = NULL, $level = 0, $domain = "", $state = 0 ) {
	foreach( $this->writers as $writer ) {
		try {
			$writer->write( $message, $level, $domain, $state );
		}
		catch( Exception $e ) {
			throw new Exception( $e->getMessage() );
		}
	}
}

/*
 * function addWriter()
 *
 */
function addWriter( $writer ) {
	$this->writers[] = $writer;
}

}
