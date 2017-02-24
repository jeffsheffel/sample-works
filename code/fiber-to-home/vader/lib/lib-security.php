<?php
/*
 * This script library contains Vader security functions.
 */
require_once 'class.LogWriterFile.php';
require_once 'class.Log.php';

/*
 * function ldapAuthorize ()
 * This function checks for non-empty username and password, then makes a call to run the LDAP authorization.
 * The runLdapAuthorization() function does all of the authorizing, then redirects to the appropriate Vader webpage.
 */
function ldapAuthorize ($authenticateUserId, $authenticateUserPassword) {
	global $ldapUri, $ldapPort, $ldapUserIdLdap, $ldapPasswordLdap;	// Set in Vader ini file

	unset($_SESSION['invalid_userpass']);	// Clear possible previous invalidation

	if (empty($authenticateUserId) || empty($authenticateUserPassword)) {
		$_SESSION['invalid_userpass'] = "yes";
		header("Location: " . $urlLoginPage);
		exit;
	}

	runLdapAuthorization($ldapUri, $ldapPort, $ldapUserIdLdap, $ldapPasswordLdap, $authenticateUserId, $authenticateUserPassword);
}

/*
 * function localAuthorize ()
 * This function is the alternative to LDAP authorization, to implement a simplified authorization that is coded
 * in the (local) PHP. This is used when Vader is running as the test instance (since the test server may not have
 * access to the LDAP server).
 *
 * An entry to the web access log is made, logging success or failure.
 */
function localAuthorize ($authenticateUserId, $authenticateUserPassword) {
	global $securityUserIdTestAdmin, $securityPasswordTestAdmin, $securityUserIdTestDroid, $securityPasswordTestDroid;
	global $urlAdminPage, $urlLoginPage, $fileLogWebAccess;

	unset($_SESSION['invalid_userpass']);	// Clear possible previous invalidation

	$logWriter = new LogWriterFile($fileLogWebAccess);
	$logLogger = new Log($logWriter);

	// Check user input username and password against the one in the Vader ini file; droid is an unprivileged user
	if( ($authenticateUserId == $securityUserIdTestAdmin && $authenticateUserPassword == $securityPasswordTestAdmin) ||
		($authenticateUserId == $securityUserIdTestDroid && $authenticateUserPassword == $securityPasswordTestDroid) ) {

		// Session variable valid_user must be set to navigate the website
		$_SESSION['valid_user'] = $authenticateUserId;
		switch( $authenticateUserId ) {
			case $securityUserIdTestAdmin:
				$_SESSION['user_full_name'] = "Test Admin User";	// UserID displayed at the top of each page
				break;
			case $securityUserIdTestDroid:
				$_SESSION['user_full_name'] = "Test Droid User";	// UserID displayed at the top of each page
				break;
			default:
				$_SESSION['user_full_name'] = "Code Error";	// Should never reach this line of code
				break;
		}

		try {
			$logLogger->info("Local authorize successful for $authenticateUserId as {$_SESSION['user_full_name']}");
		}
		catch( Exception $e ) {
			trigger_error("Failed write to log", E_USER_WARNING);
		}

		if ( !empty($_SESSION['prev_url']) ) {
			header("Location: " . $_SESSION['prev_url']);
			exit;
		} else {
			header("Location: " . $urlAdminPage);
			exit;
		}
	} else {
		try {
			$logLogger->info("Local authorize failed for $authenticateUserId");
		}
		catch( Exception $e ) {
			trigger_error("Failed write to log", E_USER_WARNING);
		}

		$_SESSION['invalid_userpass'] = "yes";
		header("Location: " . $urlLoginPage);
		exit;
	}
}

/*
 * function runLdapAuthorization()
 * This function:
 *	- connects to the LDAP server
 *	- attempts to authorize the Vader user
 *	- on success, user is redirected to top-level admin webpage, or user's webpage of original request
 *	- on failure, user is redirected back to the login page
 *	- an entry to the web access log is made, logging success or failure
 */
function runLdapAuthorization($ldapUri, $ldapPort, $ldapUserId, $ldapUserPassword, $authUserId, $authUserPassword) {
	global $urlAdminPage, $urlLoginPage, $fileLogWebAccess, $fileLogError;

	$logWriter = new LogWriterFile($fileLogWebAccess);
	$logLogger = new Log($logWriter);
	$logWriterError = new LogWriterFile($fileLogError);
	$logLoggerError = new Log($logWriterError);

	// When OpenLDAP 2.x.x is used, ldap_connect() will always return a resource; see ldap_connect() manpage
	$domainService = ldap_connect($ldapUri, $ldapPort);	// Not sure port is used here since port is included in URI

	// These options may be depecrated
	ldap_set_option($domainService, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option(NULL, LDAP_OPT_DEBUG_LEVEL, 7);

	if ($domainService) {
		$bind_result = ldap_bind($domainService);
		if ($bind_result === FALSE) {
			try {
				$logLoggerError->error("ldap_bind() failed: URI=$ldapUri PORT=$ldapPort");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}
			throw new Exception("Anonymous bind to LDAP failed. LDAP is possibly down. Contact the VDOC server team.");
		}

		// Get application's Distinguished Name (DN)
		$distinguishedName = ldap_search($domainService, "dc=mnet,dc=qintra,dc=com", "uid=$ldapUserId");
		if ($distinguishedName === FALSE) {
			try {
				$logLoggerError->error("ldap_search() failed: UID=$ldapUserId");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}
			throw new Exception("ldap_search() failed. Contact the VDOC server team.");
		}
		$ldapEntries = ldap_get_entries($domainService, $distinguishedName);
		$appDn = $ldapEntries[0]["dn"];

		// Perform LDAP bind using application user and password
		// This will allow us to do lookups and authenticate users against LDAP
		$bind_result =  ldap_bind($domainService, $appDn, $ldapUserPassword);
		if ($bind_result === FALSE) {
			try {
				$logLoggerError->error("ldap_bind() failed: UID=$ldapUserId appDN=$appDn");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}
			throw new Exception("Unable to bind to the LDAP directory: appDN=$appDn Contact the VDOC server team.");
		}

		// Look for username DN (user to be authenticated)
		$distinguishedName = ldap_search($domainService, "dc=mnet,dc=qintra,dc=com", "uid=$authUserId");
		$ldapEntries = ldap_get_entries($domainService, $distinguishedName);
		$userDn = $ldapEntries[0]["dn"];
		if (empty($userDn)) {
			try {
				$logLogger->info("LDAP authorize failed for $authUserId - no such user");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}

			$_SESSION['invalid_userpass'] = "yes";
			header("Location: " . $urlLoginPage);
			ldap_close($domainService);
			exit;
		}

		//  Try to bind to LDAP/AD using user's username and password
		$bind_result = ldap_bind($domainService, $userDn, $authUserPassword);
		if ($bind_result) {
			// Session variable valid_user must be set to navigate the website
			// If user does not have this session variable set to a non-null value, then they are not authorized via LDAP
			$_SESSION['valid_user'] = $_POST['username'];
			$_SESSION['user_full_name'] = $ldapEntries[0][cn][0];	// User's name displayed at the top of each page

			try {
				$logLogger->info("LDAP authorize successful for $authUserId as {$_SESSION['user_full_name']}");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}

			if ( !empty($_SESSION['prev_url']) ) {
				header("Location: " . $_SESSION['prev_url']);
				ldap_close($domainService);
				exit;
			} else {
				header("Location: " . $urlAdminPage);
				ldap_close($domainService);
				exit;
			}
		} else {
			try {
				$logLogger->info("LDAP authorize failed for $authUserId - wrong password");
			}
			catch( Exception $e ) {
				trigger_error("Failed write to log", E_USER_WARNING);
			}

			$_SESSION['invalid_userpass'] = "yes";
			header("Location: " . $urlLoginPage);
			ldap_close($domainService);
			exit;
		}
	} else {
		// No domain service
		// When OpenLDAP 2.x.x is used, ldap_connect() will always return a resource; see ldap_connect() manpage
		try {
			$logLoggerError->error("ldap_connect() failed: URI=$ldapUri PORT=$ldapPort");
		}
		catch( Exception $e ) {
			trigger_error("Failed write to log", E_USER_WARNING);
		}
		throw new Exception("Fatal Vader error: LDAP connect failed. Contact the VDOC server team.");
	}
}
