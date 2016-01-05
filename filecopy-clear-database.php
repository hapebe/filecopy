<?php /*
filecopy-clear-database.php
removes all data in the filecopy MySQL database (mostly useful for testing)

v2.0a 2015-08-11 *new*
*/
$_VER = 'v2.0a 2015-08-11';

header("Content-Type: text/plain");
//error_reporting(E_ERROR);

require (dirname(__FILE__).'/path.inc.php');
require ($CONFIG_FILE);
$LNK = filecopy_connect_db();

$sql = "DELETE FROM files;";
if (!mysqli_query($LNK, $sql)) die(mysqli_error());
echo 'Done: '.$sql."\n";

$sql = "DELETE FROM projects;";
if (!mysqli_query($LNK, $sql)) die(mysqli_error());
echo 'Done: '.$sql."\n";

$sql = "DELETE FROM reports;";
if (!mysqli_query($LNK, $sql)) die(mysqli_error());
echo 'Done: '.$sql."\n";

?>