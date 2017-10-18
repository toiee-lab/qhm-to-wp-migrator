<?php
	
// THIS_IS_QHM_TO_QHM_REDIRECTION

/*

*/


$url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST']. dirname($_SERVER["REQUEST_URI"]).'/index.php?'.$_SERVER['QUERY_STRING'];

header( "HTTP/1.1 301 Moved Permanently" );
header("Location: $url");
exit;




?>