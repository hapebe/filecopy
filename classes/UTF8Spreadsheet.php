<?php
/*
UTF8Spreadsheet.php
Funktionen zur Manipulation von Excel-kompatiblen "Unicode Text" Dateien
Erwartet Input in UTF8 - Excel gibt UTF16 aus, ggf. vorher konvertieren!

v1.05 2009-10-05 setField() - see getField()
v1.04 2009-07-02 bugfixes, improved toHtmlTable()
v1.03 2009-07-01 moved helper function to class FX
v1.02 2008-12-01 getField() - use named columns as property names
v1.01 2008-10-30 exchanged substr for mb_substr
v1.00 2008-05-19 *new* (from csv_tools.lib.php)
*/
class UTF8Spreadsheet {
	var $data_matrix;
	var $default_value = "";
	var $title;
	var $has_colnames;
	var $has_rownames;
	var $col_count;
	var $row_count;
	var $colDict;

	var $col_bgColors = array();
	var $col_fgColors = array();
	var $col_tdAttribs = array();
	var $col_contentStyleAttribs = array();

	var $row_bgColors = array();
	var $row_fgColors = array();
	var $row_tdAttribs = array();
	var $row_contentStyleAttribs = array();

	var $cell_bgColors = array();
	var $cell_fgColors = array();

	var $col_separator = "\t";
	var $row_separator = "\n"; // not used in readFile(), lines are divided by file()

	function __construct() {
		$this->clear();
	}

	function clear() {
		$this->title = false;
		$this->colnames = array(); // look-up
			$this->colDict = array(); // reverse look-up
		$this->rownames = array();
		$this->data_matrix = array();
		$this->col_count = -1;
		$this->row_count = -1;

		$this->has_colnames = false;
		$this->has_rownames = false;
	}

	/**
	 * $value === false will unset the value!
	 */
	function setValue($row,$col,$value)	{
		if (($row+1) > $this->row_count) $this->row_count = $row+1;
		if (($col+1) > $this->col_count) $this->col_count = $col+1;

		if (!isset($this->data_matrix[$row])) $this->data_matrix[$row] = array();

		$this->data_matrix[$row][$col] = $value;
		if ($value === false) unset($this->data_matrix[$row][$col]);
		return true;
	}

	function getValue($row,$col) {
		if (!isset($this->data_matrix[$row][$col]))	{
			/*
			FX::errlog("UTF8Spreadsheet->getValue(\$row = $row, \$col = $col): value is not set.");
			exit;
			*/
			return $this->default_value;
		}

		return $this->data_matrix[$row][$col];
	}
	
	function setField($row, $field, $value) {
		if (!$this->has_colnames) return false;

		if (!isset($this->colDict[$field])) return false;

		if ( (!is_numeric($row)) and (is_numeric($field)) ) {
			$tmp = $row;
			$row = $field;
			$field = $tmp;
		}
		
		return $this->setValue($row, $this->colDict[$field], $value);
	}

	function getField($row, $field) {
		if (!$this->has_colnames) return false;

		if ( (!is_numeric($row)) and (is_numeric($field)) ) {
			$tmp = $row;
			$row = $field;
			$field = $tmp;
		}
		
		if (!isset($this->colDict[$field])) return false;
		
		return $this->getValue($row, $this->colDict[$field]);
	}

	function readArray($lines, $has_colnames = false, $has_rownames = false) {
		$this->clear();

		$this->has_colnames = $has_colnames;
		$this->has_rownames = $has_rownames;

			$this->row_count = count($lines);
			if ($this->has_colnames) $this->row_count = count($lines) - 1;
			$first_line_tokens = explode($this->col_separator,$lines[0]);
			$this->col_count = count($first_line_tokens);
			if ($this->has_rownames) $this->col_count = count($first_line_tokens) - 1;
				$should_be_token_count = $this->col_count;
				if ($this->has_rownames) $should_be_token_count = $this->col_count + 1;

		$first_col_idx = 0;
		if ($this->has_rownames) $first_col_idx = 1;

		for ($i=0; $i<count($lines); $i++) {
			$line = $lines[$i];
			
			// patch Excel line breaks inside cells: --> "\t"
			// $line = mb_ereg_replace("\"\t\"", '|', $line);

			$tokens = explode($this->col_separator,$line);
			$tokens = FX::trimArray($tokens);
			
			// support ; inside "" ...
			$open = false;
			$newTokens = array();
			for ($j=0; $j<count($tokens); $j++) {
				if (strlen($tokens[$j]) > 0) {
					if (!$open) {
						if ($tokens[$j]{0} == '"') {
							$open = true;
							$newToken = "";
						} else {
							$newTokens[] = $tokens[$j];
						}
					}
					if ($open) {
						if (strlen($newToken) > 0) {
							$newToken .= ";".$tokens[$j];
						} else {
							$newToken = $tokens[$j];
						}
						if ($tokens[$j]{strlen($tokens[$j])-1} == '"') {
							$open = false;
							$newTokens[] = FX::trimQuotes($newToken);
						} else {
							// no action now
						}
					}
				} else {
					$newTokens[] = $tokens[$j]; // empty token
				}
			}
			$tokens = $newTokens;




			if (($i == 0) and ($this->has_colnames)) {
				if ($this->has_rownames) $this->title = FX::trimQuotes($tokens[0]);
				
				//Erste Zeile mit Spaltennamen
				for ($j=$first_col_idx; $j<count($tokens); $j++) {
					$colName = $tokens[$j];
					$this->colnames[] = $colName;
					$this->colDict[$colName] = $j - $first_col_idx;
				}
				continue;
			}


			//Datenzeile:
			$data_y_idx = $i;
			if ($this->has_colnames) $data_y_idx = $i - 1;

			// check expected column count
			if ( (count($tokens) == 1) and (mb_strlen($tokens[0]) == 0) ) {
				// tolerate empty lines:
				; // nop
			} else {
				if (count($tokens) != $should_be_token_count)	{
					FX::warn("A UTF8-txt line does not have the expected column count (".count($tokens)." <-> ".$should_be_token_count.", line #".$i.': '.$line.').');
				}
			}
			
			if ($this->has_rownames) { $this->rownames[$data_y_idx] = $tokens[0]; }
			
			for ($j=$first_col_idx; $j<count($tokens); $j++) {
				if(!isset($this->data_matrix[$data_y_idx])) $this->data_matrix[$data_y_idx] = array();

				$data_x_idx = $j;
				if ($this->has_rownames) $data_x_idx = $j - 1;

				//EXCEL national number format patch...
				if (!preg_match("/[a-zA-Z!_:]/",$tokens[$j])) {
					$tokens[$j] = str_replace(",",".",$tokens[$j]);
				}

				$this->data_matrix[$data_y_idx][$data_x_idx] = $tokens[$j];
			}
		}

	}// end function readArray()

	function readString($string, $has_colnames = false, $has_rownames = false) {
		$lines = explode("\n",$string);
		$this->readArray($lines,$has_colnames,$has_rownames);
	}

	function readFile($filename, $has_colnames = false, $has_rownames = false) {
		$srcLines = file($filename);
		/*
		$lines = array();
		foreach ($srcLines as $srcLine) {
			echo $srcLine."<br>\n";
			$lines[] = utf8_decode($srcLine);
		} */
		$lines = $srcLines;
		$this->readArray($lines,$has_colnames,$has_rownames);
	}

	function writeFile($filename, $excelCompat=true)	{
		$data = $this->getString($excelCompat);

		$f = fopen($filename,"w");
		if ($f) {
			fputs($f,$data);
			fclose($f);
		} else {
			return false;
		}
		
		return true;
	} // end function writeFile()

	function getString($excelCompat = true)	{
		$data = array();

		if ($this->has_rownames) {
			if (count($this->rownames) > $this->row_count) $this->row_count = count($this->rownames);
		}

		if ($this->has_colnames) {
			if (count($this->colnames) > $this->col_count) $this->col_count = count($this->colnames);

			$line = "";
			if ($this->has_rownames) $line .= $this->col_separator;
			for ($i=0; $i<$this->getColCount(); $i++)	{
				$line .= $this->getColName($i);
				if ($i<($this->getColCount()-1)) $line .= $this->col_separator;
			}
			$data[] = $line.$this->row_separator;
		}

		for ($j=0; $j<$this->getRowCount(); $j++)	{
			$line = "";
			if ($this->has_rownames) $line .= $this->getRowName($j).$this->col_separator;
			for ($i=0; $i<$this->getColCount(); $i++)	{
				$token = $this->getValue($j,$i);
				if ($excelCompat)	{
					if (is_numeric($token)) {
						$token = str_replace(".",",",$token); //EXCEL national number format patch...
					}
					if (mb_strpos($token, "\n") !== false) {
						$token = str_replace("\r",'', $token);
						$token = str_replace("\t",' ', $token);
						$token = str_replace("\n",'#+#*#', $token);
						// $token = "'".$token."'";
					}
				}
				$line .= $token;
				if ($i<$this->getColCount()-1) $line .= $this->col_separator;
			}
			$data[] = $line.$this->row_separator;
		}
		return implode("",$data);
	} // end function getString()

	function transpose() {
		if ($this->has_colnames) $new_rownames = $this->colnames;
		if ($this->has_rownames) $new_colnames = $this->rownames;
		if ($this->has_colnames) $this->rownames = $new_rownames;
		if ($this->has_rownames) $this->colnames = $new_colnames;
		//Beschriftungen transponiert...
		if ( ($this->has_colnames) and (!$this->has_rownames) )	{
			$this->has_colnames = false;
			$this->has_rownames = true;
		}	elseif ( (!$this->has_colnames) and ($this->has_rownames) )	{
			$this->has_colnames = true;
			$this->has_rownames = false;
		}
		//Flags ggf. getauscht.

		$tmp_matrix = array();
		for ($row = 0; $row < $this->getRowCount(); $row++)	{
			for ($col = 0; $col < $this->getColCount(); $col++)	{
				if (!isset($tmp_matrix[$col])) $tmp_matrix[$col] = array();

				$tmp_matrix[$col][$row] = $this->getValue($row,$col);

			}
		}
		$this->data_matrix = $tmp_matrix;
		//Daten transponiert...
		$tmp = $this->col_count;
		$this->col_count = $this->row_count;
		$this->row_count = $tmp;
		//Dimensionszähler transponiert...

	}//end function transpose()

	function getColCount() {
		return $this->col_count;
	}

	function getColName($idx)	{
		if (!$this->has_colnames)	{
			FX::errlog("UTF8Spreadsheet->getColName($idx): trying, although !$this->has_colnames"); exit;
		}
		if (!isset($this->colnames[$idx])) {
			FX::errlog(__FILE__.'@'.__LINE__.': colname not set for column['.$idx.']',"W");
			return false;
		}
		return $this->colnames[$idx];
	}

	function getColByName($paramName)	{
		if (!$this->has_colnames)	{
			echo('UTF8Spreadsheet->getColByName('.$paramName.'): trying, although !$this->has_colnames'); exit;
		}

		if (isset($this->colDict[$paramName])) return $this->colDict[$paramName];
		
		foreach ($this->colnames as $idx => $colName)	{
			if ($paramName == $colName) return $idx;
		}

		//not found - error!
		FX::errlog("UTF8Spreadsheet->getColByName($paramName) not found.");
		return -1;
	}

	function setColName($idx, $colname) {
		$this->has_colnames = true;
		if ($idx >= $this->col_count) $this->col_count = $idx + 1;

		$this->colnames[$idx] = $colname; // look-up
		$this->colDict[$colname] = $idx; // reverse look-up
	}

	function getRowCount() {
		return $this->row_count;
	}

	function getRowName($idx)	{
		if (!$this->has_rownames)	{
			FX::errlog("UTF8Spreadsheet->getRowName($idx): trying, although !$this->has_rownames"); exit;
		}
		if (!isset($this->rownames[$idx])) {
			FX::errlog(__FILE__.'@'.__LINE__.': rowname not set for row['.$idx.']',"W");
			return false;
		}
		return $this->rownames[$idx];
	}

	function getRowByName($paramName)	{
		if (!$this->has_rownames)	{
			FX::errlog("UTF8Spreadsheet->getRowByName($paramName): trying, although !$this->has_rownames");
			return false;
		}

		foreach ($this->rownames as $idx => $rowName) {
			if ($paramName == $rowName) return $idx;
		}

		//not found - error!
		return false;
	}

	function setRowName($idx, $rowname)	{
		$this->has_rownames = true;
		$this->rownames[$idx] = $rowname;
		if ($idx > $this->row_count) $this->row_count = $idx +1;
	}


	function toHTMLTable() {
		$html = array();
		$html[] = '<table border="0" cellpadding="1" cellspacing="1" bgcolor="#aaaaaa">';

		if ($this->has_colnames) {
			$html[] = '<tr>';
			if ($this->has_rownames) $html[] = '<td>'.($this->title?'<strong>'.$this->title.'</strong>':'&nbsp;').'</td>';
			foreach($this->colnames as $idx => $colname) {
				$html[] = '<th><small>'.$idx.': </small>'.$colname."</th>";
			}
			$html[] = '</tr>';
		}

		for ($i=0; $i<$this->getRowCount(); $i++) {
			$html[] = '<tr>';
			if ($this->has_rownames) $html[] = '<th><small>'.$i.': </small>'.$this->rownames[$i].'</th>';

			for( $j=0; $j<$this->getColCount(); $j++) {
				$html[] = $this->formatDataCell($i,$j,$this->getValue($i,$j));
			}
			$html[] = '</tr>';
		}
		$html[] = '</table>';
		return implode("\n", $html);
	} // end function toHTMLTable()
	
	function toExcelHTMLTable() {
		$html = array(
			'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
			'<html>',
  		'<head>',
  		'  <meta http-equiv="content-type" content="text/html; charset=utf-8" />',
      '  <title>Data Table</title>',
      '</head>',
    	'<body><table border="1">',
		);
		
		if ($this->has_colnames) {
			$html[] = '<tr>';
			if ($this->has_rownames) $html[] = '<td>&nbsp;</td>';
			foreach($this->colnames as $idx => $colname) {
				$html[] = '  <th>'.$colname.'</th>';
			}
			$html[] = '</tr>';
		}

		for ($i=0; $i<$this->getRowCount(); $i++) {
			$html[] = '<tr>';
			if ($this->has_rownames) $html[] = '  <th scope="row">'.$this->rownames[$i].'</th>';

			for( $j=0; $j<$this->getColCount(); $j++) {
				$html[] = '  <td valign="middle">'.$this->getValue($i,$j).'</td>';
			}
			$html[] = '</tr>';
		}
		
		$html[] = '</table></body>';
		$html[] = '</html>';
		return implode("\n", $html);
	} // end function toExcelHTMLTable()


	function formatDataCell($row, $col, $value) {
		$tdStyle = $this->getCellBgColor($row,$col);
		if (!$tdStyle) $tdStyle = $this->getRowBgColor($row);
		if (!$tdStyle) $tdStyle = $this->getColBgColor($col);
		if (!$tdStyle) $tdStyle = "#ffffff";
		$tdStyle = "background-color:".$tdStyle.";";
		$tdAttribs = "";
		if (count($this->getRowTdAttribs($row) > 0)) $tdAttribs .= ' '.$this->getRowTdAttribString($row);
		if (count($this->getColTdAttribs($col) > 0)) $tdAttribs .= ' '.$this->getColTdAttribString($col);
		$contentStyle = 'color:'.$this->getCellColor($row,$col).';';
		//echo "count(\$this->getRowContentStyleAttribs(\$row = $row) = ".count($this->getRowContentStyleAttribs($row))." <br />\n";
		if (count($this->getRowContentStyleAttribs($row) > 0)) $contentStyle .= ' '.$this->getRowContentStyleAttribsString($row);
		if (count($this->getColContentStyleAttribs($col) > 0)) $contentStyle .= ' '.$this->getColContentStyleAttribsString($col);
		$td = '<td style="'.$tdStyle.'"'.$tdAttribs.'>';
		$td .= '<span style="'.$contentStyle.'">';
		$td .= htmlspecialchars($value);
		$td .= '</span></td>'."\n";
		return $td;
	}

	// ------ follows style and color functions:
	function setRowColor($row,$color) {
		$this->row_fgColor[$row] = $color;
	}

	function getRowColor($row) {
		if (!isset($this->row_fgColor[$row])) return false;
		return $this->row_fgColor[$row];
	}

	function setRowBgColor($row,$color) {
		$this->row_bgColor[$row] = $color;
	}

	function getRowBgColor($row) {
		if (!isset($this->row_bgColor[$row])) return false;
		return $this->row_bgColor[$row];
	}

	function addRowTdAttrib($row,$attrib) {
		if (!isset($this->row_tdAttribs[$row])) $this->row_tdAttribs[$row] = array();
		$this->row_tdAttribs[$row][] = $attrib;
	}

	function getRowTdAttribs($row) {
		if (!isset($this->row_tdAttribs[$row])) $this->row_tdAttribs[$row] = array();
		return $this->row_tdAttribs[$row];
	}

	function getRowTdAttribString($row) {
		$result = "";
		$attribs = $this->getRowTdAttribs($row);
		foreach ($attribs as $attrib) {
			if ($result != "") $result .= " ";
			$result .= $attrib;
		}
		return $result;
	}

	function addRowContentStyleAttrib($row,$attrib) {
		if (!isset($this->row_contentStyleAttribs[$row])) $this->row_contentStyleAttribs[$row] = array();
		$this->row_contentStyleAttribs[$row][] = $attrib;
	}

	function getRowContentStyleAttribs($row) {
		if (!isset($this->row_contentStyleAttribs[$row])) $this->row_contentStyleAttribs[$row] = array();
		return $this->row_contentStyleAttribs[$row];
	}

	function getRowContentStyleAttribsString($row) {
		$result = "";
		$attribs = $this->getRowContentStyleAttribs($row);
		foreach ($attribs as $attrib) {
			if ($result != "") $result .= " ";
			$result .= $attrib;
		}
		return $result;
	}


	function setColColor($col,$color) {
		$this->col_fgColor[$col] = $color;
	}

	function getColColor($col) {
		if (!isset($this->col_fgColor[$col])) return false;
		return $this->col_fgColor[$col];
	}

	function setColBgColor($col,$color) {
		$this->col_bgColor[$col] = $color;
	}

	function getColBgColor($col) {
		if (!isset($this->col_bgColor[$col])) return false;
		return $this->col_bgColor[$col];
	}

	function addColTdAttrib($col,$attrib) {
		if (!isset($this->col_tdAttribs[$col])) $this->col_tdAttribs[$col] = array();
		$this->col_tdAttribs[$col][] = $attrib;
	}

	function getColTdAttribs($col) {
		if (!isset($this->col_tdAttribs[$col])) $this->col_tdAttribs[$col] = array();
		return $this->col_tdAttribs[$col];
	}

	function getColTdAttribString($col) {
		$result = "";
		$attribs = $this->getColTdAttribs($col);
		foreach ($attribs as $attrib) {
			if ($result != "") $result .= " ";
			$result .= $attrib;
		}
		//echo "UTF8Spreadsheet::getColTdAttribString returns: ".$result." <br />\n";
		return $result;
	}

	function addColContentStyleAttrib($col,$attrib) {
		if (!isset($this->col_contentStyleAttribs[$col])) $this->col_contentStyleAttribs[$col] = array();
		$this->col_contentStyleAttribs[$col][] = $attrib;
	}

	function getColContentStyleAttribs($col) {
		if (!isset($this->col_contentStyleAttribs[$col])) $this->col_contentStyleAttribs[$col] = array();
		return $this->col_contentStyleAttribs[$col];
	}

	function getColContentStyleAttribsString($col) {
		$result = "";
		$attribs = $this->getColContentStyleAttribs($col);
		foreach ($attribs as $attrib) {
			if ($result != "") $result .= " ";
			$result .= $attrib;
		}
		return $result;
	}


	function setCellColor($row,$col,$color)	{
		if (!isset($this->cell_fgColor[$row])) $this->cell_fgColor[$row] = array();
		$this->cell_fgColor[$row][$col] = $color;
	}

	function getCellColor($row,$col) {
		if (!isset($this->cell_fgColor[$row][$col])) return false;
		return $this->cell_fgColor[$row][$col];
	}

	function setCellBgColor($row,$col,$color)	{
		if (!isset($this->cell_bgColor[$row])) $this->cell_bgColor[$row] = array();
		$this->cell_bgColor[$row][$col] = $color;
	}

	function getCellBgColor($row,$col) {
		if (!isset($this->cell_bgColor[$row][$col])) return false;
		return $this->cell_bgColor[$row][$col];
	}

}
