<?php
/**
v2.0a 2015-08-10 (nearly) resurrected on Linux
v1.25 2010-08-16 mordernized (FileCopyMessage, error handling of Unicode filenames)
v1.24 2009-05-08 check for exclude archived or deleted files
v1.23 2008-07-02 sha1 hashes are calculated
*/
class BackupMain {
	var $srcDir;
	var $dstDir;
	var $archiveDir;
	var $delDir;

	var $excludePatterns;
	var $providentDeletion;

	var $numFilesBeforeWait;
	var $bytesBeforeWait;
	var $waitSecs;
	var $totalWaitedBytes;

	var $nextFileId;

	var $totalDirs;
	var $totalChecks;
	var $totalFiles;
	var $totalBytes;

	var $totalDelFiles;
	var $totalDelBytes;

	function BackupMain() {
		global $LNK;
		
		$this->srcDir = false;
		$this->dstDir = false;
		$this->archiveDir = false;
		$this->delDir = false;

		$this->excludePatterns = array();
		//wenn diese Anzahl an Dateien "bearbeitet" wurde, dann legt das Skript eine Pause ein
		$this->numFilesBeforeWait = 500;
		//wenn diese Anzahl an Bytes kopiert wurden, dann legt das Skript eine Pause ein
		$this->bytesBeforeWait = 10 * 1000 * 1000; // 10 MByte
		// wie lange soll eine Pause jeweils dauern?
		$this->waitSecs = 1;
		$this->totalWaitedBytes = $this->bytesBeforeWait;

		$this->nextFileId = 1;
		$sql = "SELECT MAX(fileid) AS maxfileid FROM files;";
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); }
		if ($row = mysqli_fetch_assoc($result)) $this->nextFileId = $row['maxfileid']+1;

		$this->totalDirs = 0;
		$this->totalChecks = 0;
		$this->totalFiles = 0;
		$this->totalBytes = (double) 0;
		$this->totalDelFiles = 0;
		$this->totalDelBytes = (double) 0;
	}

	function run() {
		if (!FX::endsWith('/', $this->srcDir)) $this->srcDir .= '/';
		if (!FX::endsWith('/', $this->dstDir)) $this->dstDir .= '/';
		if (!FX::endsWith('/', $this->archiveDir)) $this->archiveDir .= '/';
		if (!FX::endsWith('/', $this->delDir)) $this->delDir .= '/';

		if (!$this->checkFileSystems()) return FALSE;
		clearstatcache();

		// enter recursion:
		$this->backup_dir($this->srcDir, "copy");
		$totalCopyChecks = $this->totalChecks;

		// reverse check backup'ed files for deletion:
		$this->backup_dir($this->dstDir, "reverseCheck");
		
		// check DB entries, whether they are excluded now (archived and deleted files):
		$this->checkDatabase();


		$msg = "Insgesamt ".number_format($this->totalBytes,0,";",".")." Bytes in ".$this->totalFiles." Dateien gesichert, ".$totalCopyChecks." Dateien überprüft.";
		$e = new FileCopyMessage($msg, 'ADMIN'); echo $msg."\n";

		$msg = "Insgesamt ".number_format($this->totalDelBytes,0,";",".")." Bytes in ".$this->totalDelFiles." Dateien wurden gelöscht / verschoben.";
		$e = new FileCopyMessage($msg, 'ADMIN'); echo $msg."\n";

		return TRUE;
	}

	function checkFileSystems() {
		// check destination fs:
		$h = @fopen($this->dstDir.'PHPBAK.STAT','w');
		if (!$h) {
			$msg = "Kann nicht nach ".$this->dstDir." schreiben!";
			$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			return FALSE;
		}	else {
			fclose($h);
			unlink($this->dstDir.'PHPBAK.STAT');
		}

		// check archive fs:
		$h = @fopen($this->archiveDir.'PHPBAK.STAT','w');
		if (!$h) {
			$msg = "Kann nicht nach ".$this->archiveDir." schreiben!";
			$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			return FALSE;
		} else {
			fclose($h);
			unlink($this->archiveDir.'PHPBAK.STAT');
			
			for ($i=0; $i<100; $i++) {
				if (!file_exists($this->archiveDir."/".str_pad($i,2,'0',STR_PAD_LEFT))) {
					mkdir($this->archiveDir."/".str_pad($i,2,'0',STR_PAD_LEFT));
				}
			}
		}

		// check deleted fs:
		$h = @fopen($this->delDir.'PHPBAK.STAT','w');
		if (!$h) {
			$msg = "Kann nicht nach ".$this->delDir." schreiben!";
			$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			return FALSE;
		} else {
			fclose($h);
			unlink($this->delDir.'PHPBAK.STAT');

			for ($i=0; $i<100; $i++) {
				if (!file_exists($this->delDir."/".str_pad($i,2,'0',STR_PAD_LEFT))) {
					mkdir($this->delDir."/".str_pad($i,2,'0',STR_PAD_LEFT));
				}
			}
		}

		return TRUE;
	}

	function backup_dir($arg, $mode = "copy") {

		if ($mode == 'copy') {
			foreach ($this->excludePatterns as $exclude_dir) {
				if ( !(strpos($arg,$exclude_dir) === false) ) {
					$msg = "Überspringe ".$arg." durch Konfigurationseinstellung ($exclude_dir).";
					$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
					return;
				}
			}
		}

		// echo "Verzeichnis: $arg\n";
		$d = dir($arg);
		if (!$d) {
			$msg = "Kann Verzeichnis ".$arg." nicht lesen!";
			$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			return;
		}

		//echo "Handle: ".$d->handle."<br>\n";
		//echo "Path: ".$d->path."<br>\n";
		while($entry=$d->read()) {
			 if( is_dir($arg.$entry) ) {
				if ( ($entry != ".") && ($entry != "..") ) {
					//echo ( $arg.$entry."\\" )."<br>";
					$dstDir = str_replace($this->srcDir,$this->dstDir,$arg.$entry);
					$archiveDir = str_replace($this->srcDir,$this->archiveDir,$arg.$entry);
					$delDir = str_replace($this->srcDir,$this->delDir,$arg.$entry);

					// continue recursion:
					$this->backup_dir ( $arg.$entry."/", $mode );
				}
			 } else {
				if (!file_exists($arg.$entry)) {
					$msg = "Kann Datei ".$arg.$entry." nicht lesen! Wahrscheinlich ein Problem mit Unicode im Dateinamen.";
					$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
					
					continue;
				}

				if ($mode == "copy") $this->backup_file($arg.$entry);
				if ($mode == "reverseCheck") $this->reverse_check_file($arg.$entry);

			 }

		}
		$d->close();
	}

	function backup_file($fname) {
		global $LNK;

		// apply provident deletion:
		foreach ($this->providentDeletion as $delPattern)	{
			if ( !(strpos($fname,$delPattern) === FALSE) ) {
				$msg = $fname." wird aufgrund einer vordefinierten Regel gelöscht statt gesichert.";
				$e = new FileCopyMessage($msg, 'NOTICE');
				echo $msg."\n";
				if (@unlink($fname)) {
					; // nop
				}	else {
					$msg = $fname." konnte nicht gelöscht werden (vorsorgliche Löschung).";
					$e = new FileCopyMessage($msg, 'WARNING'); echo $msg."\n";
				}
				return;
			}
		}
		
		// apply exclude patterns:
		foreach ($this->excludePatterns as $exclude_dir)	{
			if ( !(strpos($fname,$exclude_dir) === FALSE) ) {
				$msg = "Überspringe ".$fname." durch Konfigurationseinstellung ($exclude_dir).";
				$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
				return;
			}
		}

		// echo "fname: ".$fname."<br/>";
		$values = @stat($fname);

		$fObj = new BackupFile();
		$fObj->fname = basename($fname);
		$fObj->path = dirname($fname);

		$fObj->fileid = BackupFile::findByName($fObj->path, $fObj->fname);
		if ( $fObj->fileid ) $fObj->fromDB();

			$extension = "";
			$dotParts = explode(".",$fObj->fname);
			if (count($dotParts) > 1) {
				$extension = $dotParts[count($dotParts)-1];
			}
		$fObj->status = 'C';
		$fObj->extension = $extension;
		$fObj->ctime = $values[10];
		$fObj->mtime = $values[9];
		$fObj->size = $values[7];


		$l = strlen($fname);
		if ( $l > 200) {
			$msg = $fname." hat eine FS-Adresse mit mehr als 200 Zeichen. ( $l )";
			$e = new FileCopyMessage($msg, 'WARNING');
		}

		if ($values[9] > time()) {
			$msg = $fname." hat ein zukünftiges Datum (Versuche zu korrigieren)!";
			$e = new FileCopyMessage($msg, 'WARNING'); echo $msg."\n";
			if(!@touch($fname)) {
				$msg = "Kann ".$fname." nicht TOUCHen!";
				$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			}
			$values = stat($fname);
		}


		$dst_fname = str_replace($this->srcDir,$this->dstDir,$fname); //echo $dst_fname;


		$flag = FALSE;


		// neu?
		if (! ($dst_values = @stat($dst_fname)) )	{
			//backup necessary...
			$flag = true;
			$msg = $fname." wurde neu erstellt seit dem letzten Backup!";
			$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";

			if (!$fObj->fileid) {
				// als neuen Datenbank-Record anlegen:
				$fObj->fileid = $this->nextFileId;
				$fObj->version = 0;
				$this->nextFileId ++;
			} else {
				// Datei existierte bereits einmal - neue Version anlegen, fileid beibehalten.
				$fObj->version ++;
			}
		}


		// geändert?
		if (!$flag) {
			if ( ($fObj->mtime > $dst_values[9]) or ($fObj->size != $dst_values[7]) ) {
				// echo $fObj->path."/".$fObj->fname.": ".makeDBDate($fObj->mtime)." ### ".makeDBDate($dst_values[9])."\n";
				$flag = true;
				$msg = $fObj->path."/".$fObj->fname." wurde geändert seit dem letzten Backup!";
				$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
				
				if ($fObj->version > 900) {
					$msg = $fObj->path."/".$fObj->fname." liegt bereits in Version ".$fObj->version." vor.\n";
					$e = new FileCopyMessage($msg, 'WARNING'); echo $msg."\n";
				}

				// Archiv-Backup von der gesicherten Datei erzeugen:
				$dstObj  = new BackupFile();
				$dstObj->fileid = BackupFile::findByName($fObj->path, $fObj->fname);
				if ( $dstObj->fileid and $dstObj->fromDB() ) {
					$archiveName = $dstObj->getStorageName();

					// Quellpfad der Datei: Die Sicherungskopie
					$dstObj->path = str_replace($this->srcDir, $this->dstDir, $dstObj->path);

					// Sicherungskopie ins Archiv-Verzeichnis verschieben
					if (!copy ($dstObj->path."/".$dstObj->fname, $this->archiveDir.$archiveName)) {
						$msg = $dstObj->path."/".$dstObj->fname." konnte nicht als ".$archiveName." ins Archiv-Verzeichnis kopiert werden!";
						$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
					} else {
						touch($this->archiveDir."/".$archiveName, $dst_values[9], $dst_values[9]);
					}

				} else {
					$msg = 'Kein Datenbank-Eintrag zum bestehenden Backup von '.$fObj->path."/".$fObj->fname.' gefunden!';
					$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
				}

				// Versionsnummer der aktuellen Datei um eins erhöhen:
				$fObj->status = 'C';
				$fObj->version ++;
			}
		}

		// migration insert 2009-05-07: compute missing SHA1s:
		if ((!$flag)
		 and 
		 ((mb_strlen($fObj->sha1) == 0) or ($fObj->sha1 == '0000000000000000000000000000000000000000'))
		) {
			// if not changed, but without valid SHA1:
			$msg = "Berechne nachträglich SHA1 für ".$fObj->path.'/'.$fObj->fname." .";
			$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
			$fObj->sha1 = sha1_file($fObj->path.'/'.$fObj->fname);
			$fObj->toDB();
		}

		if ($flag) {
			// Originaldatei ins Backup-Verzeichnis kopieren:
			//echo $dst_fname;
			$this->makePathDirs($dst_fname);

			if (!copy ($fname,$dst_fname)) {

				// kopieren fehlgeschlagen:
				$msg = $fname." konnte nicht ins Backup-Verzeichnis kopiert werden!";
				$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";

			}	else {
				if (!empty($fObj->fileid)) {
					// bisherigen Stand als "Archived" kennzeichnen, aber nur falls die bisherige Version als "Current" bezeichnet ist:
					$sql = "UPDATE files SET status ='A' WHERE (fileid=".$fObj->fileid.") AND (version=".($fObj->version - 1).") AND status LIKE 'C';";
					$result = @mysqli_query($LNK, $sql);
					if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")", 'ERROR'); }
	
					// v1.23 sha1 hash ermitteln:
					$fObj->sha1 = sha1_file($fname);
	
					// jetzigen Stand persistent machen:
					$fObj->toDB();
	
	
					//Neu in v1.22: Kopierte Datei bekommt das Datum des Originals
					$mtime = $fObj->mtime;
					if (date("I") == 1) $mtime += 3600;
	
					if(!@touch($dst_fname,$mtime,$mtime)) {
						$msg = "Kann ".$dst_fname." nicht TOUCHen!";
						$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
					}
					$this->totalBytes += $fObj->size;
					$this->totalFiles ++;
					//echo "( ".$fObj->size." Bytes kopiert )<br/>\n";
				} else {
					// $fObj->fileid is not set?
					$e = new FileCopyMessage(__FILE__."@".__LINE__.': $fObj->fileid is empty for: '.$fname.'', 'ERROR');
				}
			}
		}	else {
			//echo "Keine Änderung bei ".$fname."<br/>\n";
		}
		//usleep(10000);


		$this->totalChecks++;

		if ( ($this->totalChecks % $this->numFilesBeforeWait) == 0) {
			$msg = "sleeping a bit for ".$this->totalChecks."th file...";
			// $e = new FileCopyMessage($msg, 'INFO');
			echo $msg."\n";
			sleep($this->waitSecs);
		}
		if ($this->totalBytes > $this->totalWaitedBytes) {
			$msg = "sleeping a bit for ".number_format($this->totalWaitedBytes,"0",",",".")." bytes copied...";
			// $e = new FileCopyMessage($msg, 'INFO');
			echo $msg."\n";
			sleep($this->waitSecs);
			$this->totalWaitedBytes = (floor($this->totalBytes / $this->bytesBeforeWait) + 1) * $this->bytesBeforeWait;
		}
	}

	/**
	 * does the original of a copy still exist? Else, mark as deleted.
	 */
	function reverse_check_file($fname) {
		global $LNK;

		$values = @stat($fname);

		$bakObj = new BackupFile();
		$bakObj->fname = basename($fname);
		$bakObj->path = dirname($fname);

		// Pfad auf den Quellpfad anpassen
		$bakObj->path = str_replace($this->dstDir, $this->srcDir, $bakObj->path."/");
		$bakObj->path = substr($bakObj->path, 0, -1); // / am Ende entfernen.

		// echo $bakObj->path.$bakObj->fname."\n";
		$bakObj->fileid = BackupFile::findByName($bakObj->path, $bakObj->fname);
		if ( $bakObj->fileid ) {
			$bakObj->fromDB();
		} else {
			$msg = $fname." existiert im Dateisystem, aber nicht in der Backup-Datenbank!";
			$e = new FileCopyMessage($msg, 'WARNING'); echo $msg."\n";

			// Dateiinformationen neu generieren:
			$extension = "";
			$dotParts = explode(".",$bakObj->fname);
			if (count($dotParts) > 1) {
				$extension = $dotParts[count($dotParts)-1];
			}
			$bakObj->extension = $extension;
			$bakObj->ctime = $values[10];
			$bakObj->mtime = $values[9];
			$bakObj->size = $values[7];
			$bakObj->status = 'C';
			$bakObj->fileid = $this->nextFileId;
			$bakObj->version = 0;

			$bakObj->toDB();

			$this->nextFileId ++;
		}


		$orig_fname = str_replace($this->dstDir,$this->srcDir,$fname);
		// echo $orig_fname."\n";



		// existiert das Original noch?
		if ( ($dst_values = @stat($orig_fname)) )	{
			// Ja...
			;
			// keine weitere Aktion
		} else {
			// Nein - das Original wurde gelöscht.
			$flag = true;
			$msg = $bakObj->path."/".$bakObj->fname." wurde gelöscht.";
			$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";

			$this->totalDelFiles ++;
			$this->totalDelBytes += $bakObj->size;

			// Backup nach delDir verschieben:
			$bakFilename = str_replace($this->srcDir,$this->dstDir,$bakObj->path."/").$bakObj->fname;

			$delFilename = $this->delDir.$bakObj->getStorageName();

			if (!copy ($bakFilename, $delFilename)) {
				$msg = $bakFilename." konnte nicht als ".$delFilename." ins Deleted-Verzeichnis kopiert werden!";
				$e = new FileCopyMessage($msg, 'ERROR'); echo $msg."\n";
			} else {
				unlink($bakFilename);
				touch($delFilename, $bakObj->mtime, $bakObj->mtime);
			}
			
			// remove 'current' entry:
			$sql = 'DELETE FROM files WHERE (fileid='.$bakObj->fileid.') AND (version ='.$bakObj->version.');';
			$result = @mysqli_query($LNK, $sql);
			if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); return false; }

			// DB record entsprechend anpassen - überschreibt die vorher gehabte Version:
			$bakObj->status = 'D';
			$bakObj->toDB();
		}

		$this->totalChecks++;

		if ( ($this->totalChecks % $this->numFilesBeforeWait) == 0) {
			$msg = "sleeping a second for ".$this->totalChecks."th reverse check...";
			// $e = new FileCopyMessage($msg, 'INFO');
			echo $msg."\n";
			sleep(1);
		}

	}
	
	function checkDatabase() {
		global $LNK;
		
		$sql = "SELECT * FROM files;";
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); return false; }
		$cnt = 0;
		while ($row = mysqli_fetch_assoc($result)) {
			$f = new BackupFile($row['fileid']);
			$f->version = $row['version'];
			$f->fromDB();
			
			$origName = $f->getFullOrigPath();
			$storageName = $f->getFullBackupPath($this);

			// migrate archived and deleted files to their new position:
			if (FALSE) {
				if (($row["status"] == 'A') or ($row["status"] == 'D')) {
					$version = $row["version"];
					
					$newF = BackupFile::makeStorageName($row["fileid"], $row["fname"], $version);
					$oldF = explode("/", $newF);
					array_shift($oldF);
					$oldF = implode("/", $oldF);
	
					if ($row["status"] == 'A') $dirname = $this->archiveDir;
					if ($row["status"] == 'D') $dirname = $this->delDir;
					
					$oldF = $dirname.$oldF;
					$newF = $dirname.$newF;
	
					if (file_exists($oldF)) {
						
						if (copy($oldF, $newF)) {
							$msg = "moving: ".$oldF." TO ".$newF;
							$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
							
							if (!unlink($oldF)) {
								$msg = "Cannot delete old archive/deleted file ".$oldF;
								$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
							}
						} else {
							$msg = "Could not move ".$oldF." TO ".$newF;
							$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
						}
						
					} else {
						if (!file_exists($newF)) {
							if ($row["status"] == 'A') {
								$oldDelF = str_replace($this->archiveDir, $this->delDir, $oldF);
								if (file_exists($oldDelF)) {
									$msg = "Found ".$oldF." erroneously in delDir.";
									$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
									copy($oldDelF, $newF);
									unlink($oldDelF);
								} else {
									/*
									$msg = $oldF." does not exist at all (".$oldDelF.").";
									$this->dbreport('NOTICE', $msg); echo $msg."\n";
									*/
								}
							}
							if ($row["status"] == 'D') {
								$oldArchiveF = str_replace($this->delDir, $this->archiveDir, $oldF);
								if (file_exists($oldArchiveF)) {
		
									$msg = "Found ".$oldF." erroneously in archiveDir.";
									$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
									
									$sql2 = "UPDATE files SET status='A' WHERE uid=".$row["uid"].";"; // echo $sql2."\n";
									mysqli_query($LNK, $sql2);
								}
							}
						}
						
						if (!file_exists($newF)) {
	
							/*
							$msg = $newF." not found.";
							$this->dbreport('NOTICE', $msg);
							echo $msg."\n";
							*/
	
							if ($row["status"] == 'A') {
								$newDelF = str_replace($this->archiveDir, $this->delDir, $newF);
								if (file_exists($newDelF)) {
	
									$msg = "Found ".$newF." erroneously in delDir.";
									$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
									copy($newDelF, $newF);
									unlink($newDelF);
								} else {
									$cnt++; 
									$msg = $newF." does not exist at all (".$newDelF.").";
									$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
									
									$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";";
									mysqli_query($LNK, $sql2);
								}
							}
							if ($row["status"] == 'D') {
								$newArchiveF = str_replace($this->delDir, $this->archiveDir, $newF);
								if (file_exists($newArchiveF)) {
	
									$msg = "Found ".$newF." erroneously in archiveDir.";
									$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
									copy($newArchiveF, $newF);
									unlink($newArchiveF);
								} else {
									$found = false;
									if ($version > 0) {
										$previousF = $this->archiveDir.BackupFile::makeStorageName($row["fileid"], $row["fname"], $version-1);
										if (file_exists($previousF)) {
											$found = true;
											$msg = "Found previous version of missing del file: ".$previousF;
											$e = new FileCopyMessage($msg, 'NOTICE');
											echo $msg."\n";
											
											
											// find previous record:
											$sql2 = "SELECT uid FROM files WHERE fileid=".$row["fileid"]." AND version=".($version-1).";";
											// echo $sql2."\n";
											if ($result2 = mysqli_query($LNK, $sql2)) {
												if ($row2 = mysqli_fetch_assoc($result2)) {
													// ...and update it to 'D'
													$sql2 = "UPDATE files SET status='D' WHERE uid=".$row2["uid"].";";
													// echo $sql2."\n";
													mysqli_query($LNK, $sql2);
													
													// delete this 'D' record (now renegade)
													$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";";
													// echo $sql2."\n";
													mysqli_query($LNK, $sql2);
													
													// move the archive file to del file, maintain lower version!
													$delFile = str_replace($this->archiveDir, $this->delDir, $previousF);
													copy ($previousF, $delFile);
													unlink($previousF);
												}
											}
	
											$cnt++; 
	
										}
									}
									if (!$found) {
										$msg = $newF." does not exist at all.";
										$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
	
										$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";"; // echo $sql2."\n";
										mysqli_query($LNK, $sql2);
	
									}
								}
							}
	
						}
						
						
					}
						
				}
			}
			// if ($cnt > 0) break;
			// continue;


			// trifft ein Ausschluss-Muster auf diese Datei zu?
			foreach ($this->excludePatterns as $excludeDir)	{
				if ( !(strpos($origName,$excludeDir) === false) ) {
					$cnt++;
					
					
					$fileOkay = "";
					if (!file_exists($storageName)) $fileOkay = ", NOT FOUND";

					$msg = $origName." (".$row["status"].", ".$storageName.$fileOkay.") besitzt Backup(s), wird jetzt aber von einem Exclude-Pattern erfasst.";
					if (mb_strlen($fileOkay) > 0) {
						$e = new FileCopyMessage($msg, 'WARN');
					} else {
						$e = new FileCopyMessage($msg, 'NOTICE');
						
						// delete record...
						$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";";
						mysqli_query($LNK, $sql2);

						// and delete file
						unlink($storageName);
					}
					echo $msg."\n";

					// stop after first match.
					break;
				}
			}
			
			// SHA1?
			if (mb_strlen($row["sha1"]) == 0) {
				if (file_exists($storageName)) {
					$sha1 = sha1_file($storageName);
	
					$msg = "Aktualisiere SHA1-Hash von ".$storageName.": ".$sha1;
					$e = new FileCopyMessage($msg, 'NOTICE'); echo $msg."\n";
	
					$sql2 = "UPDATE files SET sha1='".$sha1."' WHERE uid=".$row["uid"].";";
					mysqli_query($LNK, $sql2);
				} else {
					// delete record...
					if (!file_exists($origName)) {
						$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";";
						mysqli_query($LNK, $sql2);
					}
				}
			}
			
			// maybe there is a record, but no file?
			if (!file_exists($storageName)) {
				// delete record...
				$sql2 = "DELETE FROM files WHERE uid=".$row["uid"].";";
				mysqli_query($LNK, $sql2);
				$msg = 'A backup record has been deleted for '.$origName.' / '.$storageName.': The stored copy of the file does not actually exist.';
				$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
			}
			
			// if ($cnt > 100) break;
		}
	}

	function makePathDirs($fname) {
		$dirs = dirname($fname);

		$dirs = explode("/", $dirs);

		$dir = '';
		for ($i = 1; $i < count($dirs); $i++) {
			$dir .= '/' . $dirs[$i];
			if (!file_exists($dir)) {
				$success = mkdir($dir);
				if (!$success) {
					$msg = 'Cannot makePathDirs: '.$dir;
					$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
				}
			}
		}
	}


	// static (if needed)
	/*
	function dbreport($cls = "INFO", $msg = "?") {
		$msg = mysqli_escape_string($LNK, $msg);
		$sql = "INSERT DELAYED INTO reports (klasse,meldung,datum) VALUES ('$cls','$msg',NOW());";
		$result = mysqli_query($LNK, $sql);
		if (!$result)	echo mysqli_error($LNK);
	} */

}

?>