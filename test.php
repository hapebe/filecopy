<?php

//error_reporting(E_ALL);
//echo "Hallo?!?";
//asdf();
//echo "Hallo?!?";
//ignore_user_abort(true);
//echo ignore_user_abort();

// echo phpinfo();

// $d = dir('D:/nutzerordner/personal/amzv');


/*
while( $entry = $d->read() ) {
	if ( ($entry == ".") || ($entry == "..") ) continue;
	
	$enc = mb_detect_encoding($entry);
	echo $enc;
	if ($enc == 'ASCII') echo iconv("UTF-8", "UTF-16", $entry);
	
	echo "<br>\n";
	
	if( is_dir($entry) ) {
		;
	}
}
*/

$retval = array();
$code = 0;
// exec('dir /x /n D:\\nutzerordner\\personal\\amzv >D:\\nutzerordner\\personal\\amzv\\dir.txt', $retval, $code);
// echo implode("<br>\n", $retval);
// print_r($retval);

//$stream = stream_context_create();
// stream_encoding($stream, 'CP1250');
$fName = 'D:/nutzerordner/personal/amzv/泰国劳动保护法概况.docx';
$fName = iconv('utf-8', 'utf-16', $fName);

$s = file_get_contents($fName); //,0 ,$stream);
echo $s;

// echo mb_detect_encoding(file_get_contents('D:\\nutzerordner\\personal\\amzv\\dir.txt'));
// echo "<br>\n";

// $s = iconv('CP1250', 'UTF-8', $s);
// echo html_encode($s);



function html_encode($var) {
	return htmlentities($var, ENT_QUOTES, 'UTF-8') ;
}



?>