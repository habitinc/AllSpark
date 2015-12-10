<?php
date_default_timezone_set('UTC');

//Init the data.json structure
$data = new StdClass();
$data->last_updated = date('Y-m-d');

//Load settings passed by the build server
if($argc != 2) {
	$progname = basename($argv[0]);
	echo "{$progname} missing parameters \n - usage: {$progname} plugin-slug\n";
	exit(1);
}
$data->slug = $argv[1];

//Parse out info from readme.txt
if(!is_readable('readme.txt')) {
	echo "Cannot find a readme.txt \n - Please include a standard Wordpress plugin readme (http://tinyurl.com/p5374jb)\n";
	
	//Try to choose some defaults so it's not completely broken
	$data->name = $data->slug;
	$data->version = "0.0-dev";
}
else {
	//Run the readme parser
	require_once(dirname(__FILE__) . '/WordPress-Readme-Parser/markdown.php');
	require_once(dirname(__FILE__) . '/WordPress-Readme-Parser/ReadmeParser.php');
	$readme_vars = Baikonur_ReadmeParser::parse_readme('readme.txt');
	
	//Remove unneccessary properties
	unset($readme_vars->is_excerpt);
	unset($readme_vars->is_truncated);
	
	//Copy all remaining properties to the data object (don't overwrite existing)
	foreach(get_object_vars($readme_vars) as $key => $val) {
		if(empty($data->$key)) {
			$data->$key = $val;
		}
	}
}

//Generate the zipped build
$output = array();
$return_var = 0;
exec("AllSpark/build/build-zip.sh {$data->slug} {$data->version}", $output, $return_var);
if($return_var > 0) {
	echo "Failed to run build-zip.sh, check output\n";
	echo implode("\n", $output);
	echo "\n";
	exit(2);
}
$data->download_link = "{$data->slug}-{$data->version}.zip";

//Write out the data.json
if(false === file_put_contents('data.json', json_encode($data))) {
	echo "Failed to write data.json file\n";
	exit(3);
}