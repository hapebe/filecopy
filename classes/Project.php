<?php
class Project {
	
	var $uid;
	var $name;
	var $path;

	static function getAll() {
		$retval = array();
		
		$sql = "SELECT uid FROM projects;";
		$result = @mysql_query($sql);
		if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysql_error()." ( SQL = ".$sql.")"); }
		while ($row = mysql_fetch_array($result)) {
			$uid = $row["uid"];
			
			$p = new Project($uid);
			$p->fromDB();
			
			$retval[$uid] = $p;
		}
		
		return $retval;
	}
	
	function Project($uid = -1) {
		$this->uid = $uid;
		$this->name = "";
		$this->path = "";
		
		if ($uid > 0) $this->fromDB();
	}
	
	function fromDB() {
		if ( (!isset($this->uid)) or ($this->uid < 0) ) {
			errlog(__FILE__.'@'.__LINE__.': uid is not valid in fromDB()');
			return false;
		}
		
		$sql = "SELECT * FROM projects WHERE uid = ".$this->uid.";";

		$result = @mysql_query($sql);
		if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysql_error()." ( SQL = ".$sql.")"); }
		if ($row = mysql_fetch_array($result)) {
			$this->name = $row["name"];
			$this->path = $row["path"];
		} else {
			return false;
		}
			
		return true;
	}
	
}
?>