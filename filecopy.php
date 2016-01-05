<?php /*
filecopy.php
kopiert Dateien in ein Backup-Verzeichnis, falls sie geändert wurden. Nach dem Löschen
von gesicherten Dateien werden diese in einen dritten Ordner verschoben.

v2.0a 2015-08-10 re-activated, trying to get it to run on Linux / drupal environment
v1.24 2010-08-16 modernized, utf8 filename error handling.
v1.21 2003-01-27 remove_del_dirs complete, report_mail...
v1.2 2003-01-06
					multiple src/dst/del Sätze
					del_path cleanup
v1.1 2002-06-10 Error-Tracking, wenn keine Schreibzugriffe möglich, detailfixes
*/
$_VER = 'v2.0a 2015-08-10';

header("Content-Type: text/plain");
//error_reporting(E_ERROR);

require (dirname(__FILE__).'/path.inc.php');
require ($CONFIG_FILE);
// require ($PATH.'functions.lib.php');
require ($PATH.'classes/FX.php');
require ($PATH.'classes/BackupMain.php');
require ($PATH.'classes/BackupFile.php');
require ($PATH.'classes/BackupReport.php');
require ($PATH.'classes/FileCopyMessage.php');
require ($PATH.'classes/htmlMimeMail5/htmlMimeMail5.php');

filecopy_connect_db();

$msg = "Starting... (".strftime("%H:%M:%S",time()).")";
$e = new FileCopyMessage($msg, 'ADMIN'); echo $msg."\n"; // exit;


foreach ($backup_sets as $set) {
	$backupMain = new BackupMain();
	list($src_path,$dst_path,$archive_path,$del_path) = explode("|",$set);
	$backupMain->srcDir = $src_path;
	$backupMain->dstDir = $dst_path;
	$backupMain->archiveDir = $archive_path;
	$backupMain->delDir = $del_path;

	$backupMain->excludePatterns = $exclude_dirs;
	$backupMain->providentDeletion = $providentDeletion; // config value

	$backupMain->run();


	$msg = "Fertig mit dem Backup von $src_path . (".strftime("%H:%M:%S",time()).")";
	$e = new FileCopyMessage($msg, 'ADMIN'); echo $msg."\n";

	$backupReport = new BackupReport();

	//Mail an:
	foreach ($report_mails as $adr) {
		$backupReport->send_report_mail($adr);
	}

	$sql = "DELETE FROM reports;";
	if (!mysqli_query($LNK, $sql)) die(mysqli_error($LNK));
}

?>