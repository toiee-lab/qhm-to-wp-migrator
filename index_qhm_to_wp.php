<?php
	
// THIS_IS_QHM_TO_WP_REDIRECTION

$url = dirname($_SERVER["REQUEST_URI"]).'/'.$_SERVER['QUERY_STRING'];

header("Location: $url");
exit;




?>
