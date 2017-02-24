<?php
/*
 * lib-web.php
 *
 * TODO: Set a transaction ID from the start (but must only call this module once then, hmm?)
 * TODO: Set other globals: $_SERVER['REMOTE_ADDR'], <vader-login-id>
 */
/*
 * Initialize a global script debug flag.
 */
$flagDebug = FALSE;
$requestDebug = "no";
$debug = "no";	// TODO: Remove this legacy flag
if ( isset($_GET['debug']) ) {
	$firstCharacter = strtolower( $_GET['debug'][0] );
	if ( $firstCharacter == "y" || $firstCharacter == "t" || $firstCharacter == "1" ) {
		$flagDebug = TRUE;
		$requestDebug = "yes";
		$debug = "yes";	// TODO: Remove this legacy flag
	}
}

/*
 * function startSession()
 *
 */
function startSession () {
	$sessionId = session_id();
	if( empty($sessionId) ) {
		session_start();
	}
}

/*
 * function endSession()
 * This function unsets each session variable used by Vader, thereby effectuating a logout.  And, a
 * session_destroy(), well, destroys the session (which is also required for the logout).
 *
 * NOTES:
 * ) Calling session_destroy() does not completely kill the session, as the browser still has
 *	the session cookie.  For details, see:
 *		http://www.php.net/manual/en/function.session-destroy.php
 *		http://www.php.net/manual/en/function.session-unset.php
 *	But, the Vader code still considers the user logged out, as the session was destroyed.
 */
function endSession () {
	$sessionId = session_id();
	if( empty($sessionId) ) {
		// Session wasn't properly started before calling endSession, so start one
		session_start();
	}
	$sessionId = session_id();
	if( !empty($sessionId) ) {
		//$_SESSION = array();	// Simply clear all session variables, or unset each individual variable ...
		//unset($_SESSION['valid_user']);
		//unset($_SESSION['prev_url']);
		//unset($_SESSION['user_full_name']);
		//unset($_SESSION['invalid_userpass']);
		//unset($_SESSION['security_level']);
		//unset($_SESSION['authPermBits']);
		session_unset();
		session_destroy();
		session_write_close();
		//setcookie(session_name(),'',0,'/');
		//session_regenerate_id(TRUE);
	}
}

/*
 * function isUserLoggedIn()
 *
 */
function isUserLoggedIn () {
	if( !empty($_SESSION['valid_user']) ) {
		return TRUE;
	} else {
		return FALSE;
	}
}

/*
 * function ensureLoggedInStatus()
 * If user is not considered to be logged in, then redirect to the login page.
 */
function ensureLoggedInStatus () {
global $urlLoginPage;
	if ( isUserLoggedIn() !== TRUE ) {
		$_SESSION['prev_url'] = $_SERVER['PHP_SELF'];
		header("Location: " . $urlLoginPage);
		exit;
	}
}

/*
 * function userAgentType()
 * Returns a constant that represents the user's agent type.
 * If wget or lwp-request isn't matched, then we assume the agent is a browser.
 */
function userAgentType() {
	if( preg_match( '/^wget/i', $_SERVER["HTTP_USER_AGENT"] ) ) {
		return AGENT_TYPE_WGET;
	} elseif( preg_match( '/^lwp-request/i', $_SERVER["HTTP_USER_AGENT"] ) ) {
		return AGENT_TYPE_LWP_REQUEST;
	} else {
		return AGENT_TYPE_BROWSER;
	}
}

/*
 * function getSessionVar()
 *
 */
function getSessionVar ( $key ) {
	if( isset( $_SESSION[$key] ) ) {
		return $_SESSION[$key];
	} else {
		return "";
	}
}

/*
 * function getUrlParameters()
 * This function reads standard URL request parameters and sets an array of all the standard Vader
 * parameters for use by the caller. If one of the standard parameters is not part of the URL, it is
 * set to a default value (FALSE for booleans, NULL for strings); that way its value can surely be tested.
 *
 * A typical HTTP request is:
 *	request.php?service=gpon&oltip=172.16.120.210&aid=ONT-1-1-1-3&debug=no&selection=retrieve
 */
function getUrlParameters () {
	unset( $urlParams );
	$urlBooleans = array('debug','gui','notCache');
	foreach( $urlBooleans as $urlBoolean ) {
		$urlParams[$urlBoolean] = FALSE;
		if( isset($_GET[$urlBoolean]) ){
			$firstCharacter = strtolower( $_GET[$urlBoolean][0] );
			if ( $firstCharacter == "y" || $firstCharacter == "t" || $firstCharacter == "1" ) {
				$urlParams[$urlBoolean] = TRUE;
			}
		}
	}
	$urlStrings = array('aid','central_office','debugLevel','oltip','selection','service','state');
	foreach( $urlStrings as $urlString ) {
		$urlParams[$urlString] = "";
		if( isset($_GET[$urlString]) ){ $urlParams[$urlString] = $_GET[$urlString]; }
	}
	return $urlParams;
}

/* function getTransactionId()
 * This function simply formats a Vader transaction ID and returns it to the caller.
 * The Vader transaction ID is a timestamp with a 3-digit random number appended.  This way the transaction time
 * can be easily determined, and two transaction (requests) that arrive at the exact same time will have a
 * one-in-a-thousand chance of colliding (ie. having the same ID).
 *
 * IDEA: This function may need to be a singleton class method (so many objects/modules can call it)
 */
function getTransactionId () {
global $timestampFormatTransactionIdDefault;
	$timestamp = @date($timestampFormatTransactionIdDefault);
	$randomId = str_pad(rand(0,999), 3, "0", STR_PAD_LEFT);
	return $timestamp . $randomId;
}

/*
 * function getVaderWebHeader()
 * This function returns an HTML header, which is the standard Vader webpage header.
 */
function getVaderWebHeader () {
	global $urlVader, $versionVaderInstance, $versionVaderRelease;
	$headerHtml = '
	<!-- Standard Vader logo header and user menu -->
	<table width="100%" border="0">
	<tr>
		<td width="20%" height="80">
			<img src="http://vdsltechsupp.uswc.uswest.com/Graphics/CTL_logo_Stronger_Connected.gif" alt="" width="180" height="48">
		</td>
		<td width="60%"><div align="center">
			<h1 style="color: #2F8A44;">CenturyLink <span class="headerBlack">VADER</span> Interface<br>
				<span class="headerBlack">V</span>ideo <span class="headerBlack">A</span>nd
				<span class="headerBlack">D</span>ata <span class="headerBlack">E</span>xt
				<span class="headerBlack">R</span>action Interface
			</h1>
			<span class="headerName">Vader Instance: </span><span class="headerValue">' .
				$versionVaderInstance .
			'</span> - <span class="headerName">Vader Release: </span><span class="headerValue">' .
				$versionVaderRelease.'</span></div>
		</td>';
	if( isUserLoggedIn() === TRUE ) {
		$headerHtml .= '
		<td width="20%">
			<div align="right"><span class="parameterSpecialName">Welcome:</span><span class="parameterValue">' .
				$_SESSION['user_full_name']	.
				'</span></div>
			<br/><div align="right"><a href="'.$urlVader.'/logout.php">Vader Logout</a></div>
		</td>';
	} else {
		$headerHtml .= '
		<td width="20%"></td>';
	}
	$headerHtml .= '
	</tr>
	</table>
';
	return $headerHtml;
}

/*
 * function convertTimeTicksToIntervals( $timeTicks, $ticksPerSecond = 100 )
 *
 * This function converts (SNMP) time ticks to time intervals (days, hours, minutes, seconds).
 * The standard (SNMP) ticks per second is 100, but this function can be used for other frequencies, with the use
 * of the $ticksPerSecond parameter.
 */
function convertTimeTicksToIntervals( $timeTicks, $ticksPerSecond = 100 ) {
	$ticksPerMinute = $ticksPerSecond * 60;
	$ticksPerHour = $ticksPerMinute * 60;
	$ticksPerDay = $ticksPerHour * 24;

	$seconds = (int)( $timeTicks / $ticksPerSecond ) % 60;
	$minutes = (int)( $timeTicks / $ticksPerMinute ) % 60;
	$hours = (int)( $timeTicks / $ticksPerHour ) % 24;
	$days = (int)( $timeTicks / $ticksPerDay );

	return array( $days, $hours, $minutes, $seconds );
}

/*
 * setOrNullify( $variable )
 * This function simply returns the variable if it is set, otherwise returns NULL.
 */
function setOrNullify( $variable ) {
	return ( isset( $variable ) ) ? $variable : NULL;
}
