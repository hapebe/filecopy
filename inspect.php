<?php /*
inspect.php
Zuständig für alle Betrachtungsaktionen: Operationen, die keine Auswirkungen auf DB und Dateisystem haben.

v2.0a 2015-08-11 Linux port, testing
v1.00 2007-07-18 *new*
*/
$_VER = "v2.0a 2015-08-11";

header("Content-Type: text/plain; charset=utf-8");
// error_reporting(E_ERROR);

require (dirname(__FILE__).'/path.inc.php');
require ($CONFIG_FILE);
require ($PATH.'classes/FX.php');
require ($PATH.'classes/BackupMain.php');
require ($PATH.'classes/BackupFile.php');

filecopy_connect_db();

// globals holen: evtl. durch etwas anderes ersetzen...
$params = array_merge($_GET, $_POST);
// keine XSS Injection Safety!!! - nicht extern zugänglich machen.
extract($params);

if (isset($d0)) {
	// erwartet einen Parameter in der Form: dateFrom=2007-12-12+08:00:00 , dateTo=2007-12-19+20:59:59
	$timeStampFrom = FX::parseDBDate(str_replace("+"," ",$d0));
	$timeStampTo = FX::parseDBDate(str_replace("+"," ",$d1));
	
	echo "Dateien, auf die zwischen den Daten ".FX::makeDBDate($timeStampFrom)." und ".FX::makeDBDate($timeStampTo)." schreibend zugegriffen wurde.\n";

	$sql = "SELECT path, fname, mtime FROM files WHERE ".
			   "(mtime > '".FX::makeDBDate($timeStampFrom)."') ".
			   " AND (mtime < '".FX::makeDBDate($timeStampTo)."') ".
				 " AND (status='C')".
				 " ORDER BY mtime DESC;";
	$result = @mysqli_query($LNK, $sql);
	if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); }
	while ($row = mysqli_fetch_assoc($result)) {
		extract($row);
		echo $path."/".$fname." [".$mtime."]"."\n";
	}
}

if (isset($sameName)) {
	echo "Dateien mit dem Namen ".FX::html_encode($sameName)." existieren in den Verzeichnissen:\n";
	$sql = "SELECT path, size FROM files WHERE fname LIKE '".mysqli_escape_string($LNK, $sameName)."' AND status='C';";
	$result = @mysqli_query($LNK, $sql);
	if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); }
	while ($row = mysqli_fetch_assoc($result)) {
		echo $row["path"]." [".$row["size"]." bytes]"."\n";		
	}
}

if (isset($largeFiles)) {
	$sql = "SELECT path, fname, size FROM files WHERE size > (100 * 1048000) AND status='C'";
	if (isset($ext)) $sql .= " AND extension LIKE '".mysqli_escape_string($LNK, $ext)."'";
	$sql .= " ORDER BY size DESC;";
	
	$result = @mysqli_query($LNK, $sql);
	if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysqli_error()." ( SQL = ".$sql.")"); }
	
	$cnt = mysqli_num_rows($result);
	echo $cnt . " große Dateien:\n";	
	
	while ($row = mysqli_fetch_assoc($result)) {
		extract($row);
		echo str_pad($path."/".$fname, 120, "  .", STR_PAD_RIGHT)."[".number_format($size)." bytes]\n";
	}
	mysqli_free_result($result);
}

if (isset($nameStats)) {
	echo "Häufigkeiten gleicher Dateinamen:\n";
	
	$filenameFreqs = array();
	$maxLength = -1;
	
	$sql = "SELECT DISTINCT fname FROM files WHERE status='C';";
	$result = @mysqli_query($LNK, $sql);
	if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); }
	while ($row = mysqli_fetch_assoc($result)) {
		extract($row);
		if (strlen($fname) > $maxLength) $maxLength = mb_strlen($fname);
		
		$sql2 = "SELECT COUNT(uid) AS cnt FROM files WHERE fname LIKE '".mysqli_escape_string($LNK, $fname)."' AND status='C';";
		$result2 = @mysqli_query($LNK, $sql2);
		if (!$result2) { errlog(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql2.")"); }
		if ($row2 = mysqli_fetch_assoc($result2))	$filenameFreqs[$fname] = $row2['cnt'];
		
	}

	arsort($filenameFreqs);
	
	foreach ($filenameFreqs as $fname => $freq) {
		if ($freq < 2) break;
		echo str_pad($fname, $maxLength, " .", STR_PAD_RIGHT).$freq."\n";
	}
}

?>