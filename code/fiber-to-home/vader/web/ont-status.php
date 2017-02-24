<?php
/*
 * ont-status.php
* GPON ONT status GUI
*
*/

require_once '../../include/config.php';
require_once 'class.DatabaseVader.php';
require_once 'class.OltAdtranTA5000.php';
require_once 'class.OntAdtranTA300.php';
require_once 'class.CalixManagementSystem.php';
require_once 'class.OltCalixE7.php';
require_once 'class.OntCalix700.php';
require_once 'lib-text.php';
require_once 'class.PresenterXmlOntStatus.php';
require_once 'class.LogWriterFile.php';
//require_once 'class.LogWriterDatabase.php';
//require_once 'class.LogWriterEmail.php';
require_once 'class.Log.php';
require_once 'lib-presenter.php';
require_once 'class.PresenterHtmlOntStatus.php';

$urlParams = getUrlParameters();
$transactionId = getTransactionId();

startSession();
ensureLoggedInStatus();

$logWriter = new LogWriterFile($fileLogWebAccess);
$logLogger = new Log($logWriter);
try {
	$logLogger->info("Page access by {$_SESSION['valid_user']} as {$_SESSION['user_full_name']}");
}
catch( Exception $e ) {
	trigger_error("Failed write to log", E_USER_WARNING);
}

$transactionWriter = new LogWriterFile( $fileLogTransaction );
$transactionLogger = new Log( $transactionWriter );
try {
	$transactionLogger->info( $urlParams['oltip'] . " " . $urlParams['aid'] );
}
catch( Exception $e ) {
	trigger_error("Failed write to log", E_USER_WARNING);
}

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
}

list( $oltDatabaseState, $oltDatabaseCentralOffice, $oltDatabaseEms, $oltDatabaseStatus) =
	array("Missing State", "Missing CentralOffice", "Missing EMS/CMS", "Missing Production Status" );
if( $oltDatabaseAttributes ) {
	$oltDatabaseState = $oltDatabaseAttributes['state'];
	$oltDatabaseCentralOffice = $oltDatabaseAttributes['centralOffice'];
	$oltDatabaseEms = $oltDatabaseAttributes['ipAddressEms'];
	$oltDatabaseStatus = $oltDatabaseAttributes['status'];
}
$ontDescriptionTitle = $oltDatabaseState . " - " . $oltDatabaseCentralOffice . " - " . $urlParams['oltip']  . " - " . $urlParams['aid'];

$presenterXml = getOntStatusPresenterXml( $urlParams, $transactionId );

$presenterHtml = new PresenterHtmlOntStatus( $presenterXml );
//$presenterHtml->removeEmptyElements();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>ONT Status <?php print "- " . $ontDescriptionTitle?></title>
<link rel="shortcut icon" type="image/x-icon" href="../../favicon.ico"/>
<link rel="stylesheet" type="text/css" href="../../include/css/vader.css"/>
<link rel="stylesheet" type="text/css" href="../../include/css/device-presenter.css"/>
<!-- <link rel="stylesheet" type="text/css" href="../../../../include/css/vroc-app.css"/> -->
</head>

<body>
<?php echo getVaderWebHeader(); ?>

	<p class="pageNavigationMenu">Site Navigation</p>
	<ul class="pageNavigationMenu">
		<li class="navigationMenuLink"><a href="../../admin.php">VADER Administration Homepage</a></li>
		<li class="navigationMenuLink"><a href="../gpon-admin.php">FTTH GPON Administration</a></li>
		<li class="navigationMenuLink"><a href="US-Map.php">VADER United States Map</a></li>
		<!-- ================================================================================================================== -->
		<!-- Display Vader database table edit link (only to privileged users) -->
		<!-- ================================================================================================================== -->
		<?php if( validate_user_as_advanced( $_SESSION['valid_user'] ) ) { ?>
		<li class="navigationMenuLink"><a href="../gpon-admin-edit.php">GPON Chassis Table Edit
			<img src="../../../Graphics/Icons/classy-icons-set/png/24x24/application_edit.png" width="24" height="24" align="absbottom"></a>
		- Privileged Admin Function
		</li>
		 <?php } ?>
	</ul>

	<p class="pageFunctionTitle">OLT Status <?php print "- " . $ontDescriptionTitle?></p>

<?php
	print '<div class="ontStatus">' . "\n";

	print '<div class="oltDatabase"><ul class="oltItemHolder">' . "\n";
	print '<li class="parameter">US State</li>';
	print '<li class="value">' . $oltDatabaseState . '</li>' . "\n";
	print '<li class="parameter">Central Office</li>';
	print '<li class="value">' . $oltDatabaseCentralOffice . '</li>' . "\n";
	print '<li class="parameter">EMS/CMS IP Address</li>';
	print '<li class="value">' . $oltDatabaseEms . '</li>' . "\n";
	print '<li class="parameter">OLT Production Status</li>';
	print '<li class="value">' . $oltDatabaseStatus . '</li>' . "\n";
	print '</ul></div>';

	print $presenterHtml->getRequestProperties();
	print $presenterHtml->getResponseProperties();
	print $presenterHtml->getOlt();
	print $presenterHtml->getOnt();
	print '</div>' . "\n";
?>
<?php require_once('../../include/html-trailer-secure.php'); ?>
</body>
</html>
