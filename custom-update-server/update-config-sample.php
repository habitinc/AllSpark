<?php

if ( ! defined('DOING_UPDATE') ) {
	die('Please do not load this file directly.');
}

/**
 *	Base configurations for AllSpark update server
 */

/** Set to the base of the plugin data directories */
define('PLUGIN_BASE', './');
define('PUBLIC_PLUGIN_BASE', 'http://'.$_SERVER['SERVER_NAME'].'/'.PLUGIN_BASE);


/** Set to true to enable debug logging */
define('DEBUG', false);