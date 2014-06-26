<?php
/*
Plugin Name: My Great Plugin
Plugin URI: http://habithq.ca
Description: Provides some sweet functionality
Version: 1.0
Author: Habit
Author URI: http://habithq.ca
*/

if ( ! defined('ABSPATH') ) {
	die('Please do not load this file directly.');
}

require_once 'myplugin.class.php';


/*
 *	If you have any functions that are intended to be used outside this plugin 
 *	(i.e. in theme development), define them here as global functions
 */

if(!function_exists("my_hello_world")) {
	function my_hello_world() {
		return MyPlugin::getInstance().my_hello_world();
	}
}