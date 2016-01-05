<?php
class FileCopyMessage {
	function __construct($msg, $severity = '!', $filename = FALSE) {
		global $LNK;
		if (($severity == '!') && ($severity != 'ERROR')) FX::errlog($msg, $severity);
		
		$meldung = mysql_escape_string($msg);
		
		$klasse = 'INFO';
		if ($severity == '!') $klasse = 'ERROR';
		if ($severity == 'ERROR') $klasse = 'ERROR';
		if ($severity == 'NOTICE') $klasse = 'NOTICE';
		if ($severity == 'WARN') $klasse = 'WARNING';
		if ($severity == 'WARNING') $klasse = 'WARNING';
		if ($severity == 'ADMIN') $klasse = 'ADMIN';
		
		$datei = '';
		if ($filename !== FALSE) $datei = mysqli_escape_string($LNK, $filename);
		
		$sql = "INSERT DELAYED INTO reports (klasse, meldung, datei, datum)"
			." VALUES ('".$klasse."', '".$meldung."', '".$datei."', NOW() )";
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { FX::errlog(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); return FALSE; }
	}
}

?>