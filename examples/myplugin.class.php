<?php

if(!class_exists('AllSpark')){
	require_once 'AllSpark/AllSpark.class.php';
}

class MyPlugin extends AllSpark {
	
	//Set to the minimum required AllSpark version
	protected $required_allspark_version = '0.0.7';

	/**
	 *	Plugin constructor
	 *	
	 *	Set up any local data variables and attach any WP hooks that must be done 
	 *	prior to init.
	 */
	protected function __construct(){	//__construct() should be protected, as it should not be called outside of get_instance()
		
		//If you're overriding __construct, ensure that you call it's parent's initializer
		parent::__construct();
	}

	/**
	 *	Plugin initialization.
	 *
	 *	Perform any tasks to finalize plugin setup. Automatically called as part of
	 *	the WP init action, this is where you would want to setup any custom post
	 *	types or add additional WP hooks.
	 */
	public function init() {
		$this->add_action('wp_footer');
		
		//Setup update server
		$this->updateBlockWP = true; //Block regular WP updates
		//$this->updateUseCustom = 'http://localhost/wp_private_update/update.php'; //Give a URL to the update server
	}

	/** 
	 *	Function that will be called as part of an action
	 */
	public function wp_footer() {
		echo "<small style='position: absolute; top: 50px; right: 0; z-index: 999;'>Hello World</small>";
	}
	
	/**
	 *	Function that can be accessed through a shortcut method defined in the index.php
	 */
	public function my_hello_world(){
		echo "Hello World";
	}
}


/**
 * Always call getInstance at the end of the file:
 *  this instantiates your plugin and registers various hooks that you'll use later
 */
MyPlugin::getInstance();
