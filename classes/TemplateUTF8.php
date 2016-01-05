<?php
/*
  TemplateUTF8.php

  v1.00 2007-12-17 from Template.php
 */

mb_internal_encoding("UTF-8");

class TemplateUTF8 {

	public $delimiter1;
	public $delimiter2;
	public $components;
	public $strings;
	public $vars;
	public $valid;
	public $success;

	function __construct($htmlTemplateUTF8, $delimiter1 = '<!--@', $delimiter2 = '-->') {
		$this->delimiter1 = $delimiter1;
		$this->delimiter2 = $delimiter2;

		$this->components = array();
		$this->strings = array();
		$this->vars = array();

		$this->valid = true;
		$this->success = false;

		$cursor = 0;
		$startIdx = 0;
		while (($openIdx = mb_strpos($htmlTemplateUTF8, $this->delimiter1, $cursor)) !== false) {
			if (($openIdx - $startIdx) > 0) {
				$this->strings[] = mb_substr($htmlTemplateUTF8, $startIdx, ($openIdx - $startIdx));
				$this->components[] = "string:" . (count($this->strings) - 1);
			}

			// sicher:  openIdx ist gültig.
			$cursor = $openIdx + 1;
			if (($closeIdx = mb_strpos($htmlTemplateUTF8, $this->delimiter2, $cursor)) !== false) {
				$varName = mb_substr(
						$htmlTemplateUTF8, $openIdx + mb_strlen($this->delimiter1), $closeIdx - ($openIdx + mb_strlen($this->delimiter1))
				);
			} else {
				$slice = mb_substr($htmlTemplateUTF8, $cursor - 10, 40);
				FX::errlog(__FILE__ . '@' . __LINE__ . ': TemplateUTF8-Platzhalter ab position ' . $openIdx . ' nicht geschlossen? (' . $slice . '...)');
				$this->valid = false;
				return false;
			}

			$this->vars[] = $varName;
			$this->components[] = "var:" . (count($this->vars) - 1);

			$cursor = $closeIdx + mb_strlen($this->delimiter2);
			$startIdx = $cursor;
		}
		$this->strings[] = mb_substr($htmlTemplateUTF8, $startIdx, mb_strlen($htmlTemplateUTF8) - $startIdx);
		$this->components[] = "string:" . (count($this->strings) - 1);
	}

	function autoFill() {
		$params = array();
		foreach ($this->vars as $var) {
			$params[$var] = @$GLOBALS[$var];
		}
		return $this->fill($params);
	}

	function fill($params) {
		if (!$this->valid) {
			FX::errlog(__FILE__ . '@' . __LINE__ . ': TemplateUTF8 ist !valid!');
			return false;
		}
		$retval = array();

		$this->success = true;

		foreach ($this->components as $component) {
			list($t, $idx) = explode(":", $component);
			if ($t == "string")
				$retval[] = $this->strings[$idx];
			if ($t == "var") {
				if (isset($params[$this->vars[$idx]])) {
					$retval[] = $params[$this->vars[$idx]];
				} else {
					$this->success = false;
					FX::errlog(__FILE__ . '@' . __LINE__ . ', ' . $_SERVER["PHP_SELF"] . ': unset param "' . $this->vars[$idx] . '"', 'W');
				}
			}
		}
		// echo FX::toHtmlTable($retval, true);
		$retval = implode("", $retval);
		// echo FX::toHtmlTable($retval, true);
		return $retval;
	}

	function fillByFieldName() {
		$params = array();
		foreach ($this->vars as $idx => $varname) {
			$params[$varname] = $varname;
		}
		return $this->fill($params);
	}

	function getFields() {
		return $this->vars;
	}

}