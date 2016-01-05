<?php
//für filecopy.php

//v2.0a 2015-08-10 Linux / not dependent on Apache
//v2.0 2008-06-30 __autoload
//v1.2 2002-06-10 PATH,LIB_PATH,DOCUMENT_ROOT etc.
//v1.1 2002-03-18
/*
Definiert Pfade, die für alle Skripte gelten.
Jedes Skript einzeln muss dafür sorgen, dass dieser Code ausgeführt wird,
bevor Dateioperationen aufgenommen werden.
*/

$PATH = dirname(__FILE__) . '/'; // echo $PATH; exit;
$CONFIG_FILE = $PATH.'cfg/config.inc.php';
?>