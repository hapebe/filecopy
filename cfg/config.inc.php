<?php

set_time_limit(24*60*60);
ignore_user_abort(TRUE);

$backup_sets = array(
	// '/home/hostmaster/backup-experiment/source/|/home/hostmaster/backup-experiment/target/|/home/hostmaster/backup-experiment/archive/|/home/hostmaster/backup-experiment/deleted/',
	'/mnt/dc01-e/Daten/|/mnt/bak/backup-experiment/target/|/mnt/bak/backup-experiment/archive/|/mnt/bak/backup-experiment/deleted/',
	// '/mnt/dc01-e/Daten/ina/|/mnt/bak/backup-experiment/target/|/mnt/bak/backup-experiment/archive/|/mnt/bak/backup-experiment/deleted/',
);

$report_mails = array(
	'hpb@erp-berlin.com',
);

//Wenn Teile des SRC-Dateinamens diesen Patterns entsprechen, so findet kein Backup statt
// TODO: ausserdem werden existierende Backups getilgt.
$exclude_dirs = array(
	'/System Volume Information/',
	'/RECYCLER/',
	'/htdocs/man/',
	'/htdocs/admin/stats/',
	'/htdocs/temp/',
	'/cartrends2/log/',
	'/cartrends2/img/hist/',
	'/cartrends2b/log/',
	'/cartrends2b/img/hist/',
	'/cartrends2b/img/previews/',
	'/cartrends2b/img/thumbnails/',
	'/deutschebildungsbank.de/admin/stats/',
	'/_ClipArt/1/',
	'/_ClipArt/2/',
	'/res/ct/hist/',
	'/res/ct/tn/',
	'/res/ct/prv/',
	// pir ESI/MSI Viewer and Reports, frequently updated (generated!) files...
	'/pir2008/test/',
	'/pir2008/ESI-reports/',
	'/04i ESI 2010/ESI Viewers/',
	'/04i ESI 2010/Individual Dealership Reports/',
	'/04j MSI 2010/MSI Viewers/',
	'/pir2012/MSI Action Plan reports/',
	'/pir2012/test/',
	'/04m ESI 2012/20 ESI Viewers/',
	'/04m ESI 2012/21 Individual Dealership Reports/',
	'/04n MSI 2012/20 MSI Viewers/',
	// Windows image cache files
	'/Thumbs.db',
);

// vorsorgliches Löschen: Bekannte Temporäre Dateien werden ohne Backup gelöscht.
$providentDeletion = array(
	// '/Thumbs.db',
);

// mail:
$MAIL_CFG = array(
	"from" => 'Backup (filecopy.php) <php@erp-berlin.com>',
	"host" => 'mail.erp-berlin.com',
	"port" => 25,
	"helo" => 'chronos.erp.local',
	"auth" => true,
	"user" => '?',
	"pass" => '?'
);


function filecopy_connect_db() {
	$_DB_HOST = "localhost";
	$_DB_USER = "filecopy";
	$_DB_PASSWD = "?";
	$_DB_NAME = "filecopy";

	$GLOBALS['LNK'] = mysqli_connect( $_DB_HOST, $_DB_USER, $_DB_PASSWD, $_DB_NAME ); //host, user, password, default database
	if (mysqli_connect_error()) {
		die('Connect Error (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
	}
	
	return $GLOBALS['LNK'];
}

?>