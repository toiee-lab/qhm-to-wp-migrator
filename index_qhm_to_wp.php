<?php
	
// THIS_IS_QHM_TO_WP_REDIRECTION

/*
 非公開になったときに、 index_qhm.php と index_qhm_proxy.php に上書きするファイル	
*/


$url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST']. dirname($_SERVER["REQUEST_URI"]).'/'.$_SERVER['QUERY_STRING'];

header( "HTTP/1.1 301 Moved Permanently" );
header("Location: $url");
exit;




?>