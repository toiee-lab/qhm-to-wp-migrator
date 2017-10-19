<?php
	
$wp_logged_in_adfadfabie = false;
foreach($_COOKIE as $key=>$val)
{
	if( preg_match('/^wordpress_logged_in_/', $key) ){
		$wp_logged_in_adfadfabie = true;
		break;
	}
}

if( $wp_logged_in_adfadfabie ){
	
	define('WP_USE_THEMES', true);

	/** Loads the WordPress Environment and Template */
	require( dirname( __FILE__ ) . '/wp-blog-header.php' );
	exit;

}
else{

	error_reporting(E_ERROR | E_PARSE);

	define('DATA_HOME',	'');
	define('LIB_DIR',	'lib/');
	
	require(LIB_DIR . 'pukiwiki.php');
	exit;

}