<?php
class BackupReport {
	var $fname;

	function BackupReport() {
		global $_VER, $LNK;
	
		srand ((double)microtime()*1000000);
		$fname = dirname(__FILE__)."/../logs/backup_log_".strftime("%Y-%m-%d-%H-%M-%S").".html";
	
		$sql = "select * from reports order by uid asc;";
		$result = mysqli_query($LNK, $sql);
		
		$head = array(
			'<html>',
			'<head>',
			'  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">',
			'  <style type="text/css">',
			'  <!-- ',
			'    table,td { font-family: Arial,Helvetica,sans-serif; font-size:8pt }',
			'    body { font-family: Arial,Helvetica,sans-serif; font-size:8pt }',
			'  -->',
			'  </style>',
			'</head>',
			'<body>',
		);
		$html = implode("\n", $head);
	
		$html .= 'Backup-Report generiert von filecopy.php (Version '.$_VER." )\n";
	
		$html .= '<table width="100%" cellspacing="0" bgcolor="#000000">'."\n";
		$html .= '<tr><th bgcolor="ffffff">Ereignis-Klasse</th><th bgcolor="ffffff">Ereignistext</th><th bgcolor="ffffff">Datum</th></tr>'."\n";
		while ($row = mysqli_fetch_assoc($result))	{
			$fontopen = ""; $fontclose = "";
			if ($row["klasse"] == "ADMIN") { $fontopen = '<font size="+1" color="0000ff">'; $fontclose = '</font>'; }
			if ($row["klasse"] == "WARNING") { $fontopen = '<font color="ccaa00">'; $fontclose = '</font>'; }
			if ($row["klasse"] == "ERROR") { $fontopen = '<font color="ff0000">'; $fontclose = '</font>'; }
			$html .= '<tr><td bgcolor="ffffff"><nobr>'.$fontopen;
			// $html .= BackupReport::html_encode($row["klasse"]);
			$html .= $row["klasse"];
			$html .= $fontclose.'</nobr></td><td bgcolor="ffffff"><nobr>'.$fontopen;
			// $html .= BackupReport::html_encode($row["meldung"]);
			$html .= $row["meldung"];
			$html .= $fontclose.'</nobr></td><td bgcolor="ffffff"><nobr>'.$fontopen;
			$html .= $row["datum"];
			$html .= $fontclose.'</nobr></td></tr>'."\n";
		}
		$html .= '</table></body></html>'."\n";
	
		$outfile = fopen ($fname,'w'); fputs($outfile,$html); fclose ($outfile);
	
		echo "Report-Datei: ".$fname."\n";
	
		$this->fname = $fname;
		return $fname;
	}
	
	// static:
	function send_report_mail($recipient) {
		global $PATH, $MAIL_CFG, $LNK;
	
		$fullfilename = $this->fname;
	
		$msg = "Ein Backup vom Server zum Backup-Server wurde erstellt. Dabei wurde folgendes Protokoll erstellt:\n\n";
		$msg .= $fullfilename."\n\n";
	
		$msg .= "Es gab folgende FEHLER (nur solche, die automatisch erfasst werden konnten):\n\n";
	
		$sql = "select * from reports where klasse like 'ERROR' order by uid asc;";
		$result = mysqli_query($LNK, $sql);
	
		$txt = "";
		while ($row = mysqli_fetch_assoc($result)) {
			$txt .= $row["datum"]."\t";
			$txt .= $row["meldung"]."\n";
		}
	
		$msg .= $txt;
	
		if ($txt == "") $msg .= "< KEINE. >";
	
	
	
		$msg .= "\n\n\nEs gab folgende WARNUNGEN:\n\n";
	
		$sql = "select * from reports where klasse like 'WARNING' order by uid asc;";
		$result = mysqli_query($LNK, $sql);
	
		$txt = "";
		while ($row = mysqli_fetch_assoc($result))	{
			$txt .= $row["datum"]."\t";
			$txt .= $row["meldung"]."\n";
		}
	
		$msg .= $txt;
	
		if ($txt == "") $msg .= "< KEINE. >";
	
	
		$mail = new htmlMimeMail5();
	
		$mail->setFrom($MAIL_CFG["from"]);
		$mail->setSubject('Backup-Durchlauf '.strftime('%d.%m.%y %H:%M:%S',time()));
		$mail->setHeader('Date', date('D, d M y H:i:s O'));

	
		$mail->setTextCharset('UTF-8');
		$mail->setText($msg);
		
		$mail->setHtmlCharset('UTF-8');
		$mail->setHTML(file_get_contents($this->fname));
	
		// $mail->addEmbeddedImage(new fileEmbeddedImage($PATH."img/APPrototype.gif"));
		// $mail->addAttachment(new fileAttachment('example.zip'));
	
		$mail->setSMTPParams($MAIL_CFG["host"], $MAIL_CFG["port"], $MAIL_CFG["helo"], $MAIL_CFG["auth"], $MAIL_CFG["user"], $MAIL_CFG["pass"]);
	
		$mail->send(array($recipient), 'smtp'); // first param is an array!
	
		if (isset($mail->errors)) {
			echo "\n\n\nFehler beim Versand der Report-Mail an $recipient !\n\n\n";
			if (is_array($mail->errors)) {
				echo(implode("; ", $mail->errors)) ."\n"; // non-fatal;
			} else {
				errlog($mail->errors) ."\n"; // non-fatal;
			}
		}
	}

	function html_encode($var) {
		return htmlentities($var, ENT_QUOTES, 'UTF-8') ;
	}
}
?>