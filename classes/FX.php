<?php
/**
 * FX.php
 * 
 * object based step-in for functions.lib.php
 */
class FX {

	static function xmlentities( $string ) {
		$j = mb_strlen( $string, 'UTF-8' );
		$result = "";
		for( $k = 0; $k < $j; $k++ ) {
			$char = mb_substr( $string, $k, 1, 'UTF-8' );

			switch( $char )	{
				case "&": $result .= "&amp;"; break;
				case "\"": $result .= "&quot;"; break;           
				case "<": $result .= "&lt;"; break;
				case ">": $result .= "&gt;"; break;
				default:
					$num = uniord( $char );
					if( $num <= 31
						|| $num == 39 // "'"
						|| $num == 96 // "`"
						|| $num >= 127 )
						$result .= "&#".$num.";";
					else
						$result .= $char;
					break;
			}
		}
	   
		return $result;
	}


	static function makeDBDate($unixtime = false, $short = false) {
		if ($unixtime === false) $unixtime = time();
		if ($short)	return strftime("%Y-%m-%d", $unixtime);
		return strftime("%Y-%m-%d %H:%M:%S",$unixtime);
	}
	
	static function parseDBDate($dbDate) {
		$dbDate = trim($dbDate);
		$dbDate = str_replace(" ",":",$dbDate);
		$dbDate = str_replace("-",":",$dbDate);
		$dbDateArray = explode(":",$dbDate);
		if (count($dbDateArray) == 6)	{
			$unixtime = mktime(
										$dbDateArray[3],
										$dbDateArray[4],
										$dbDateArray[5],
										$dbDateArray[1],
										$dbDateArray[2],
										$dbDateArray[0]    );
			return $unixtime;
		}
		if (count($dbDateArray) == 3)	{
			$unixtime = mktime(
										0,
										0,
										0,
										$dbDateArray[1],
										$dbDateArray[2],
										$dbDateArray[0]    );
			return $unixtime;
		}
		if (count($dbDateArray) == 1) {
			$unixtime = mktime(
										substr($dbDate,8,2),
										substr($dbDate,10,2),
										substr($dbDate,12,2),
										substr($dbDate,4,2),
										substr($dbDate,6,2),
										substr($dbDate,0,4)
									);
			return $unixtime;
		}
		FX::warn("invalid format in parseDBDate(\$dbDate=$dbDate).");
		return false;
	}

	static function toUTF8($s) {
		$enc = mb_detect_encoding($s, 'ASCII, UTF-8, ISO-8859-1', true);
		if ($enc != 'UTF-8') {
			$s = utf8_encode($s);
		}
		return $s;
	}

	static function html_encode($var) {
		return htmlentities($var, ENT_QUOTES, 'UTF-8') ;
	}

	static function microtime_diff($m1,$m2) {
		$tmp1 = explode(" ",$m1);
		$tmp2 = explode(" ",$m2);
		$s_diff = $tmp1[1] - $tmp2[1];
		$ms_diff = $tmp1[0] - $tmp2[0];
		if ($ms_diff < 0)	{
			$s_diff --;
			$ms_diff += 1;
		}
		$ms_str = $ms_diff."";
		return $s_diff.substr(($ms_str),1);
	}
	
	static function strf_microtime($mt = false) {
		if ($mt === false) $mt = microtime();
		$mt = explode(" ",$mt);
		$hms = strftime("%H:%M:%S",$mt[1]);
		return $hms.substr(($mt[0]),1);
	}
	
	static function startswith($needle,$haystack) {
		if (mb_substr($haystack, 0, mb_strlen($needle)) == $needle)	{
			return true;
		}	else {
			return false;
		}
	}
	
	static function endswith($needle,$haystack) {
		if (mb_substr($haystack, mb_strlen($haystack) - mb_strlen($needle)) == $needle)	{
			return true;
		}	else	{
			return false;
		}
	}


	/**
	 * remove single double-quotes from the start and the end of a string
	 */
	static function trimQuotes($s) {
		if (mb_substr($s,0,1) == '"') $s = mb_substr($s,1);
		if (mb_substr($s,mb_strlen($s)-1,1) == '"') $s = mb_substr($s,0,mb_strlen($s)-1);
		return $s;
	}

	static function trimArray(&$a) {
		$result = array();
		foreach ($a as $a_key => $a_value) {
			$result[$a_key] = trim($a_value);
		}
		return $result;
	}



	
	static function errlog($message, $severity = "!") {
		if (!isset($GLOBALS["ERRLOG_FILE"])) $GLOBALS["ERRLOG_FILE"] = 'errlog.txt';
		
		$f = fopen ($GLOBALS["ERRLOG_FILE"],"a");
		$message = strftime("%Y-%m-%d %H-%M-%S") ."\t". $severity ."\t". $message ."\n";
		if ($severity == "!") $message .= print_r(debug_backtrace(), true)."\n";
		fwrite($f,$message);
		fclose($f);
	}
	
	static function warn($message) {
		FX::errlog($message, "W");
	}

	static function legal($message) {
		FX::errlog($message, "L");
	}
	
	static function debug($message,$stream = "") {
		if (!isset($GLOBALS["ERRLOG_FILE"])) $GLOBALS["ERRLOG_FILE"] = 'errlog.txt';

		if (isset($GLOBALS["_debug"]) and $GLOBALS["_debug"]) {
			if ($stream == "") {
				$trgt_file = $GLOBALS["ERRLOG_FILE"];
			}	else {
				$trgt_file = dirname($GLOBALS["ERRLOG_FILE"])."-".$stream.".txt";
			}
			$f = fopen ($trgt_file,"a");
			$message = strftime("%Y-%m-%d %H-%M-%S") . " D\t" . $message . "\n";
			fwrite($f,$message);
			fclose($f);
		}
	}	

	static function toHtmlTable($obj, $htmlEncode = false) {
		$retval = array();
		
		if (is_object($obj)) {
			$retval[] = '<table>';
			$retval[] = '<tr><th>'.get_class($obj).'</th><th>&nbsp;</th></tr>';
			$retval[] = '<tr><th>Field</th><th>Value</th></tr>';
			
			foreach (get_object_vars($obj) as $key => $value) {
				if ($value === false) $value = '<em>false</em>';
				if ($value === null) $value = '<em>null</em>';
				if (is_array($value) or is_object($value)) {
					$value = FX::toHtmlTable($value, $htmlEncode);
				} else {
					if ($htmlEncode) $value = FX::html_encode($value);
				}
				$retval[] = '<tr><td valign="top">'.$key.'</td><td valign="top">'.$value.'</td></tr>';
			}
			$retval[] = '</table>';
		} else if (is_array($obj)) {
			if (count($obj) == 0) {
				$retval[] = '<em>(empty array)</em>'; 
			} else {
				$retval[] = '<table>';
				$retval[] = '<tr><th>Key</th><th>Value</th></tr>';

				foreach ($obj as $key => $value) {
					if ($value === false) $value = '<em>false</em>';
					if ($value === null) $value = '<em>null</em>';
					if (is_array($value) or is_object($value)) {
						$value = FX::toHtmlTable($value, $htmlEncode);
					} else {
						if ($htmlEncode) $value = FX::html_encode($value);
					}
					$retval[] = '<tr><td valign="top">'.$key.'</td><td valign="top">'.$value.'</td></tr>';
				}
				$retval[] = '</table>';
			}
		} else {
			if ($htmlEncode) $obj = FX::html_encode($obj);
			$retval[] = $obj;
		}
		
		return implode("\n", $retval);
	}
	
	/**
	 * @return all UTF8 characters of $s only if they are within special ranges (Chinese characters)
	 */
	static function chineseChars($s, $enc = 'UTF-8') {
		$retval = '';
		$len = mb_strlen($s);
		for ($i=0; $i < $len; $i++) {
			$c = mb_substr($s, $i, 1, $enc);
			$code = FX::mbOrd($c);
			if (($code >= 0x4E00) && ($code <= 0x9FFF)) {
				$retval .= $c;
			} elseif (($code >= 0x4E00) && ($code <= 0x9FFF)) {
				$retval .= $c;
			} elseif (($code >= 0x3400) && ($code <= 0x4DFF)) {
				$retval .= $c;
			} elseif (($code >= 0x20000) && ($code <= 0x2A6DF)) {
				$retval .= $c;
			} elseif (($code >= 0x2F800) && ($code <= 0x2FA1F)) {
				$retval .= $c;
			}
		}
		return $retval;
	}
	
	/**
	 * @return all UTF8 characters of $s EXCEPT FOR if they are within special ranges (Chinese characters)
	 */
	static function noChineseChars($s, $enc = 'UTF-8') {
		$retval = '';
		$len = mb_strlen($s);
		for ($i=0; $i < $len; $i++) {
			$c = mb_substr($s, $i, 1, $enc);
			$code = FX::mbOrd($c);
			if (($code >= 0x4E00) && ($code <= 0x9FFF)) {
				continue;
			} elseif (($code >= 0x4E00) && ($code <= 0x9FFF)) {
				continue;
			} elseif (($code >= 0x3400) && ($code <= 0x4DFF)) {
				continue;
			} elseif (($code >= 0x20000) && ($code <= 0x2A6DF)) {
				continue;
			} elseif (($code >= 0x2F800) && ($code <= 0x2FA1F)) {
				continue;
			}
			$retval .= $c;
		}
		return $retval;
	}
	
	static function mbOrd($string) {
		if (extension_loaded('mbstring') === true) {
			mb_language('Neutral');
			mb_internal_encoding('UTF-8');
			mb_detect_order(array('UTF-8', 'ISO-8859-15', 'ISO-8859-1', 'ASCII'));
			
			$result = unpack('N', mb_convert_encoding($string, 'UCS-4BE', 'UTF-8'));
			
			if (is_array($result) === true) {
				return $result[1];
			}
		}
		return ord($string);
	}
	
	static function RandomBase64($length = 12) {
		$codes = array();
		for ($i=0; $i < $length; $i++) {
			$codes[] = chr( mt_rand(0,255) );
		}
		return str_replace('=', '', base64_encode(implode('', $codes)));
	}
	
	
	

}