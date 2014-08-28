<?php
/**
 *	AllSpark Plugin Custom Update service
 *
 *	Implementation of a WP Plugin update server for AllSpark derived plugins
 */

// Load Config
define("DOING_UPDATE", true);
if(!is_readable('update-config.php')) {
	copy('update-config-sample.php', 'update-config.php') or die("Could not initialize the configuration file");
}
require 'update-config.php';

// ---

$ret = new stdClass();
$ret->status = 'ok';
if(isset($_REQUEST['stats'])) {
	$pluginVersions = array();
	$pluginDir = false;

	try {
		$pluginDir = new DirectoryIterator(PLUGIN_BASE);
		
		foreach($pluginDir as $plugin) {
			$name = $plugin->getFilename();
			if(is_readable(PLUGIN_BASE.$name.'/data.json')) {
				$pluginData = json_decode(file_get_contents(PLUGIN_BASE.$name.'/data.json'));
				
				$pluginVersions[$name] = array(
					"name"          => $pluginData->name,
					"version"       => $pluginData->version,
					"updated"       => $pluginData->last_updated,
					"download_link" => PLUGIN_BASE.$name.'/'.$pluginData->download_link,
				);
			}
		}
		$ret->data = $pluginVersions;
		
	}
	catch(Exception $e) {
		http_response_code(500);
		$ret->status = 'error';
		$ret->errCode = '500';
		$ret->errText = 'Could not load the plugin base directory';
	}
}
else if(empty($_REQUEST['plugin'])) {
	//No plugin, no joy
	http_response_code(400);
	$ret->status = 'error';
	$ret->errCode = '400';
	$ret->errText = 'A plugin slug was not passed to the service.';
}
else if(! (1 === preg_match('/^[A-Za-z\-]+$/', $_REQUEST['plugin']) && is_dir(PLUGIN_BASE.$_REQUEST['plugin']) && is_readable(PLUGIN_BASE.$_REQUEST['plugin'].'/data.json'))){
	//Can't find information on that plugin
	// (Sorry...watch the tricky negation. Wanted the error states together)
	http_response_code(404);
	$ret->status = 'error';
	$ret->errCode = '404';
	$ret->errText = 'The given plugin slug could not be located.';
	$ret->slug = $_REQUEST['plugin'];
}
else {
	$plugin_slug = $_REQUEST['plugin'];
	//Serve up the hot, fresh update info
	$ret->data = json_decode(file_get_contents(PLUGIN_BASE.$plugin_slug.'/data.json')); //We tested the plugin request earlier, it can only have a-z and -, so it shouldn't be able to access other dirs
	
	//Sanity check
	if($ret->data->slug != $plugin_slug) {
		//If the slug doesn't match, then we have a problem. Don't give the client anything
		http_response_code(500);
		$ret->status = 'error';
		$ret->errCode = '500';
		$ret->errText = 'The plugin data file does not correspond to the expected plugin';
		unset($ret->data);
	}
	else {
		//
		$ret->data->external = true;
		$ret->data->download_link = PUBLIC_PLUGIN_BASE.$plugin_slug.'/'.$ret->data->download_link;
		
		if(isset($ret->data->sections)) {
			if(DEBUG) {
				if(!isset($ret->data->sections->other_notes)) {
					$ret->data->sections->other_notes = '';
				}
				$ret->data->sections->other_notes .= "<p><em>Served by ".$_SERVER['HTTP_HOST']."</em></p>";
			}
		
			//WP needs this as a PHP associative array, which isn't technically possible in JSON. So, we cheat a bit.
			$ret->data->sections = get_object_vars($ret->data->sections);
		}
		
		$ret->data = serialize($ret->data);
	}
}

header('Content-type: application/json; charset=utf-8');
echo json_encode($ret);

if(DEBUG) {
	$ret->__request = $_REQUEST;
	$ret->__timestamp = date('Z');
	$fp = fopen('debug.log', 'a');
	fwrite($fp, json_encode($ret)."\n");
	fclose($fp);
}