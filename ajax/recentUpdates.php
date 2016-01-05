<?php
require_once("../path.inc.php");
require ($CONFIG_FILE);
require ($LIB_PATH."functions.lib.php");
require ($LIB_PATH."datetime.lib.php");
require ($PATH."lib/filecopy.lib.php");


$networkPathTranslations = array(
	"D:/projects" => "\\\\chronos\\projects",
	"D:/public" => "\\\\chronos\\public",
	"D:/media" => "\\\\chronos\\media",
	"D:/install" => "\\\\chronos\\install",
	"D:/angebote" => "\\\\chronos\\angebote",
	"D:/ina" => "\\\\chronos\\ina"
);

// echo var_export($_GET, true);

// fetch projects from DB:
$projects = Project::getAll();
$projectFiles = array();
foreach ($projects as $uid => $dummy) $projectFiles[$uid] = array();

$recentDays = 60;
$recentTime = makeDBDate(time() - $recentDays*24*60*60);


$sql = "SELECT fileid FROM files ".
"WHERE extension IN ('ppt','pptx','doc','docx','xls','xlsx','csv','pdf','zip','txt','sav') ".
" AND status='C' ".
" AND mtime > '".$recentTime."' ".
"ORDER BY mtime DESC ".
"LIMIT 0,1000;";
// warn($sql);
$result = @mysql_query($sql);
if (!$result) { errlog(__FILE__."@".__LINE__.": ".mysql_error()." ( SQL = ".$sql.")"); }
while ($row = mysql_fetch_array($result)) {
	$fileid = $row["fileid"];
	
	$f = new BackupFile($fileid);
	
	foreach ($projects as $uid => $project) {
		if (strpos($f->path, $project->path) !== false) {
			// gotcha!
			$projectFiles[$uid][] = $f;
		}
	}
}


$sectionTemplate = new Template(file_get_contents($PATH."templates/recentUpdates-section.html"));
$lineTemplate = new Template(file_get_contents($PATH."templates/recentUpdates-line.html"));


$output = array();
foreach ($projects as $uid => $project) {
	if (count($projectFiles[$uid]) > 0) {
		$lines = array();
		foreach($projectFiles[$uid] as $f) {
			$subPath = str_replace($project->path, "", $f->path);
			$subPath = substr($subPath, 1); // strip leading slash
			
			$networkPath = $f->path;
			foreach ($networkPathTranslations as $src => $dst) {
				$networkPath = str_replace($src, $dst, $networkPath);
			}
			$networkPath = str_replace("/","\\",$networkPath);
			
			$age = time() - $f->mtime;
			$age = flexFormatTime($age, false, false, false);
			
			$href = '/explorer.php?param='.urlencode($networkPath."\\".$f->fname);
			$title = 'Diese Datei im Projekt-Ordner anzeigen - dazu klicken und "ausführen"!';
			$title = utf8entities(utf8_encode($title));
			
			$timeStatus = "+&nbsp;".$age;
			$icon = '<img src="/img/icon-'.strtolower($f->extension).'.gif" width="16" height="16" alt="" align="absbottom">';
			$filename = utf8entities(utf8_encode($f->fname));
			$filepath = utf8entities(utf8_encode($subPath));
			
			$lines[] = $lineTemplate->autoFill();
		}
		
		$networkPath = $project->path;
		foreach ($networkPathTranslations as $src => $dst) {
			$networkPath = str_replace($src, $dst, $networkPath);
		}
		$networkPath = str_replace("/","\\",$networkPath);
		$explorerHref = '/explorer.php?param='.urlencode($networkPath);
		$title = 'Projekt-Ordner anzeigen - dazu klicken und "ausführen"!';
		$title = utf8entities(utf8_encode($title));
		$projectName = utf8entities($project->name);
		$projectId = $project->uid;
		$fileCntMsg = "(".count($projectFiles[$uid])." Dateien in den letzten ".$recentDays." Tagen)";
		$output[] = $sectionTemplate->autoFill();
	}
}
			



$output = implode("\n", $output);

/*
$f = fopen("dump.txt", "w");
fputs($f, $output);
fclose($f);
*/

echo $output;

?>