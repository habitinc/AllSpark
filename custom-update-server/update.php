<?php
/**
 *	AllSpark Plugin Custom Update service
 *
 *	Implementation of a WP Plugin update server for AllSpark derived plugins
 */

define('DEBUG', false);
 
$ret = new stdClass();
$ret->status = 'ok';

if(empty($_REQUEST['plugin'])) {
	//No plugin, no joy
	http_response_code(400);
	$ret->status = 'error';
	$ret->errCode = '400';
	$ret->errText = 'A plugin slug was not passed to the service.';
}
else if(! (1 === preg_match('/^[a-z\-]+$/', $_REQUEST['plugin']) && is_dir($_REQUEST['plugin']) && is_readable($_REQUEST['plugin'].'/data.json'))){
	//Can't find information on that plugin
	// (Sorry...watch the tricky negation. Wanted the error states together)
	http_response_code(404);
	$ret->status = 'error';
	$ret->errCode = '404';
	$ret->errText = 'The given plugin slug could not be located.';
	$ret->slug = $_REQUEST['plugin'];
}
else {
	//Serve up the hot, fresh update info
	$ret->data = json_decode(file_get_contents($_REQUEST['plugin'].'/data.json')); //We tested the plugin request earlier, it can only have a-z and -, so it shouldn't be able to access other dirs
	
	
	
	//TODO: If we want to implement any sort of automatic-github-zipping, or download counters (or anything else fancy), can do it here
	
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

echo json_encode($ret);

if(DEBUG) {
	$ret->__request = $_REQUEST;
	$ret->__timestamp = date('Z');
	$fp = fopen('debug.log', 'a');
	fwrite($fp, json_encode($ret)."\n");
	fclose($fp);
}