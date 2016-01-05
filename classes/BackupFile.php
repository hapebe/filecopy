<?php
class BackupFile {
	
	var $fileid;
	var $version;
	var $status;
	var $path;
	var $fname;
	var $extension;
	var $size;
	var $ctime;
	var $mtime;
	var $sha1;
	
	function BackupFile($fileid = -1) {
		$this->fileid = $fileid;
		$this->version = -1;
		$this->path = "";
		$this->fname = "";
		$this->extension = "";
		$this->size = -1;
		$this->ctime = 0;
		$this->mtime = 0;
		$this->status = 'C';
		$this->sha1 = str_repeat("00", 20);
		
		if ($fileid > 0) $this->fromDB();
	}
	
	function fromDB() {
		global $LNK;
		
		if ( (!isset($this->fileid)) or ($this->fileid < 0) ) {
			$e = new FileCopyMessage(__FILE__.'@'.__LINE__.': fileid is not valid in fromDB()', 'WARN');
			return false;
		}
		
		if ($this->version == -1) {
			// neueste Version finden:
			$sql = "SELECT * FROM files WHERE fileid = ".$this->fileid." ORDER BY version DESC;";
		} else {
			// spezielle Version finden:
			$sql = "SELECT * FROM files WHERE fileid = ".$this->fileid." AND version = ".$this->version.";";
		}
			
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")", 'WARN'); }
		if ($row = mysqli_fetch_assoc($result)) {
			$this->version = $row["version"];
			$this->path = $row["path"];
			$this->fname = $row["fname"];
			$this->extension = $row["extension"];
			$this->size = $row["size"];
			$this->ctime = FX::parseDBDate($row["ctime"]);
			$this->mtime = FX::parseDBDate($row["mtime"]);
			$this->status = $row["status"];
			$this->sha1 = $row["sha1"];
		} else {
			return false;
		}
			
		return true;
	}
	
	/**
	 * 
	 */
	function toDB() {
		global $LNK;
		
		if ( (!isset($this->fileid)) or ($this->fileid < 0) or (!$this->fileid) ) {
			if ( ((!isset($this->fname)) or ($this->fname == ""))
			 	or ((!isset($this->path )) or ($this->path  == ""))
			) {
				$e = new FileCopyMessage(__FILE__.'@'.__LINE__.': neither fileid nor fname/path are valid in toDB() - cannot store this!'); return false;
			} else {
				// fileid not set, but fname / path - find new fileid:
				$sql = "SELECT MAX(fileid) AS maxfid FROM files;";
				$result = @mysqli_query($LNK, $sql);
				if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")", 'WARN'); return false; }
				if ($row = mysqli_fetch_assoc($result)) {
					$this->fileid = $row['maxfid'] + 1;
				}
			}
		}
		
		if ($this->version == -1) {
			// determine latest version:
			$this->version = 0; // default: very first version
			
			// or are there previous versions?
			$sql = "SELECT MAX(version) AS maxversion FROM files WHERE fileid = ".$this->fileid.";";
			$result = @mysqli_query($LNK, $sql);
			if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")", 'WARN'); return false; }
			if ($row = mysqli_fetch_assoc($result)) {
				$this->version = $row['maxversion'] + 1;
			}
		} else {
			// delete any possibly existing entry for the same fileid AND version:
			$result = @mysqli_query($LNK, "DELETE FROM files WHERE fileid = ".$this->fileid." AND version = ".$this->version.";");
			if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")", 'WARN'); return false; }
		}
		
		// neuen Eintrag in die DB
		$sql = "INSERT DELAYED INTO files (".
		       "fileid,".
		       "version,".
		       "status,".
		       "path,".
		       "fname,".
		       "extension,".
		       "size,".
		       "ctime,".
		       "mtime,".
		       "sha1".
		       ") VALUES (".
		       $this->fileid.",".
		       $this->version.",".
		       "'".mysqli_escape_string($LNK, $this->status)."',".
		       "'".mysqli_escape_string($LNK, $this->path)."',".
		       "'".mysqli_escape_string($LNK, $this->fname)."',".
		       "'".mysqli_escape_string($LNK, $this->extension)."',".
		       $this->size.",".
		       "'".FX::makeDBDate($this->ctime)."',".
		       "'".FX::makeDBDate($this->mtime)."',".
		       "'".$this->sha1."'". // no ','
		       ");";
		// echo $sql . "\n";
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { 
			$msg = __FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")";
			$e = new FileCopyMessage($msg, 'WARN'); echo $msg."\n";
			return FALSE; 
		}

		return TRUE;
	}
	
	function findByName($fpath, $fname) {
		global $LNK;
		
		$fileid = FALSE;
		if (FX::endsWith('/', $fpath)) $fpath = substr($fpath, 0, -1);
		
		$sql = "SELECT fileid FROM files WHERE path LIKE '".mysqli_escape_string($LNK, $fpath)."' AND fname LIKE '".mysqli_escape_string($LNK, $fname)."';";
		$result = @mysqli_query($LNK, $sql);
		if (!$result) { $e = new FileCopyMessage(__FILE__."@".__LINE__.": ".mysqli_error($LNK)." ( SQL = ".$sql.")"); }
		if ($row = mysqli_fetch_assoc($result)) $fileid = $row["fileid"];

		return $fileid;
	}
	
	/**
	 * @return the full filesystem path and name of the original File
	 */
	function getFullOrigPath() {
		return $this->path.'/'.$this->fname;
	}
	
	/**
	 * @arg $backupMain instance of BackupMain (for reference to the storage configuration settings)
	 * @return full file system path to the stored / backed up copy of the original file
	 */
	function getFullBackupPath(&$backupMain) {
		if (!is_object($backupMain)) {
			$e = new FileCopyMessage(__FILE__."@".__LINE__.": invalid backupMain reference given.", 'ERROR');
			return FALSE;
		}
		
		if ($this->status == 'C') {
			return str_replace($backupMain->srcDir, $backupMain->dstDir, $this->path.'/'.$this->fname);
		} else {
			$fname = $this->getStorageName();
			if ($this->status == 'A') {
				return $backupMain->archiveDir.$fname;
			} else if ($this->status == 'D') {
				return $backupMain->delDir.$fname;
			} else {
 				$e = new FileCopyMessage(__FILE__."@".__LINE__.": file ".$this->fileid." v".$this->version." has an unknown status (".$this->status.").", 'WARN');				
 				return FALSE;
			}
		}
		
		return FALSE; // this should never happen
	}
	
	/**
	 * returns file name for storage in the ARCHIVED of DELETED storage areas
	 */
	function getStorageName() {
		return BackupFile::makeStorageName($this->fileid, $this->fname, $this->version);
	}
	
	static function makeStorageName($fileid, $fname, $version) {
		return
			str_pad($fileid % 100,2,'0',STR_PAD_LEFT).
			"/".
			str_pad($fileid,8,'0',STR_PAD_LEFT).
			"-v".
			str_pad($version,3,'0',STR_PAD_LEFT).
			"-".
			$fname;
	}
}
?>