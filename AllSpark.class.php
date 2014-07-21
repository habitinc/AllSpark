<?php

if(!class_exists('AllSpark')) {	
	
	//Requires PHP 5.3+
	if(!version_compare(PHP_VERSION, '5.3.0', '>=')) {
		trigger_error('Cannot load AllSpark plugin class: Requires at least PHP 5.3. Derived plugins may fail.', E_USER_WARNING);
	}

	abstract class AllSpark{

		/**  @internal	**/
		const VERSION = "0.0.7";
		
		/** Base file for plugin @see _loadPluginInfo() @internal */
		protected $pluginBase = false;
		
		/** Plugin metadata @see _loadPluginInfo() @internal */
		protected $pluginInfo = false;
		
		/** Plugin Slug @see _loadPluginInfo() @internal */
		protected $pluginSlug = false;
		
		/** Flag to block checking WP for updates to this plugin. */
		protected $updateBlockWP = false;
		
		/** Flag to enable checking for updates in a custom plugin repository */
		protected $updateUseCustom = false;
		
		/** 
		The __construct method bootstraps the entire plugin. It should not be modified. It is possible to override it, but you probably don't want to
		
		@internal	**/
		protected function __construct($req_allspark_version = false){
			//If the required version param wasn't passed to the contructor, but was defined by the base class, we can use that instead.
			if(!$req_allspark_version && isset($this->required_allspark_version)){
				$req_allspark_version = $this->required_allspark_version;
			}
			
			//Make sure AllSpark is running at least the version specified by the implementing plugin
			if($req_allspark_version !== false && !version_compare($req_allspark_version, self::VERSION, '<=')){
				trigger_error("The required version ({$req_allspark_version}) of the AllSpark plugin (".self::VERSION.") was not loaded. Please update your plugins.", E_USER_ERROR);
				return;
			}
			
			$this->_loadPluginInfo();
			
			//Register plugin hooks
			register_activation_hook($this->pluginBase, array($this, 'pluginDidActivate'));
			register_deactivation_hook($this->pluginBase, array($this, 'pluginDidDeactivate'));
			//register_uninstall_hook($this->pluginBase, array($this, 'pluginWillBeDeleted'));
			
			$this->add_action('init', '_init', 0, 1);	//ensure our internal init function gets called no matter what
			$this->add_action('init');					//make it so subclasses can use `init` as well
			
			//Add filters for handling custom plugin updates
			$this->add_filter('pre_http_request', 'maybe_block_wp_plugin_update', 10, 3);
			$this->add_filter('pre_set_site_transient_update_plugins', 'maybe_register_plugin_update');
			$this->add_filter('plugins_api', 'maybe_modify_plugin_update_info', 10, 3);
		}
		
		/**
		Attempts to automatically determine the implementing class' plugin base file.
		
		Function is normally called during the __construct phase of plugin setup. After calling, the protected members
		$pluginBase and $pluginInfo should be set to their appropriate values. If you need to override this function 
		in an implementing class (i.e. if this generic code fails to properly detect the plugin base), have your function
		set $this->pluginBase to the path of the base plugin file (normally something like 'my-plugin/index.php') and 
		$this->pluginInfo to an array of metadata (see the example output for the get_plugins function in the WP Codex)
		
		*/
		protected function _loadPluginInfo() {
			if (!function_exists( 'get_plugins' )) {
				//To use get_plugins on the front-end, this file needs to be included
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			//Determine the location of the implementing class, and try to find the plugin base file in that directory
			$classInfo = new ReflectionClass($this);
			$pluginRootDir = '/'.explode( '/', plugin_basename($classInfo->getFileName()))[0];
			$pluginInfo = get_plugins($pluginRootDir);
			
			//Set our protected members
			$this->pluginBase = substr($pluginRootDir . '/' . array_pop(array_keys($pluginInfo)), 1);;
			$this->pluginInfo = array_pop($pluginInfo);
			$this->pluginSlug = sanitize_title_with_dashes($this->pluginInfo['Name']);
		}
		
		/**
		Add the rewrite rules for APIs
		
		If you override this function, ensure you call `super` on it before returning		
		
		@internal	**/
		function pluginDidActivate(){
			flush_rewrite_rules();
		}
		
		/**
		Clean up the rewrite rules when deactivating the plugin
		
		If you override this function, ensure you call `super` on it before returning		
		
		@internal	**/
		function pluginDidDeactivate(){
			flush_rewrite_rules();
		}
		
		/**
		One last chance to clean everything up before the plugin is erased forever. Be sure to clean up tables and chairs, kids.
		
		If you override this function, ensure you call `super` on it before returning		
		
		@internal
		
		**/
		function pluginWillBeDeleted(){
		}
		
		/**
		Attaches a method on the current object to a WordPress hook. By default, the method name is the same as the hook name. In some cases, this behavior may not be desirable and can be overridden.
		
		@param string $name The name of the action you wish to hook into
		@param string $callback [optional] The class method you wish to be called for this hook
		@param int $priority [optional] Used to specify the order in which the functions associated with a particular action are executed. Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the filter.  
		@param int $accepted_args [optional] The number of arguments the hooked function accepts. In WordPress 1.5.1+, hooked functions can take extra arguments that are set when the matching do_action() or apply_filters() call is run.
		*/
		protected function add_action($name, $callback = false, $priority = 10, $accepted_args = 1){
		
			if(!$callback){
				$callback = $name;
			}
			
			if(is_object($callback) && ($callback instanceof Closure)){
				add_action($name, $callback, $priority, $accepted_args);
			}
			else if(method_exists($this, $callback)){
				add_action($name, array($this, $callback), $priority, $accepted_args);
			}
		}
		
		/**
		Attaches a method on the current object to a WordPress hook. By default, the method name is the same as the hook name. In some cases, this behavior may not be desirable and can be overridden.
		
		@param string $name The name of the action you wish to hook into
		@param string $callback [optional] The class method you wish to be called for this hook
		@param int $priority [optional] Used to specify the order in which the functions associated with a particular action are executed. Lower numbers correspond with earlier execution, and functions with the same priority are executed in the order in which they were added to the filter.  
		@param int $accepted_args [optional] The number of arguments the function(s) accept(s). In WordPress 1.5.1 and newer hooked functions can take extra arguments that are set when the matching apply_filters() call is run. 
		*/
		protected function add_filter($name, $callback = false, $priority = 10, $accepted_args = 1) {
			if(!$callback){
				$callback = $name;
			}
			
			if(is_object($callback) && ($callback instanceof Closure)){
				add_filter($name, $callback, $priority, $accepted_args);
			}
			else if(method_exists($this, $callback)) {
				add_filter($name, array($this, $callback), $priority, $accepted_args);
			}
		}
		
		/**
		Provides a hack to allow using closures for UI inclusion (which dramatically improves code readability).
		
		If you're on PHP < 5.4, you can do something like the following:
		
		$self = $this;
		
		add_some_menu_page('name', function() use ($self){
			$self->addUI('path-to-ui-file.php');
		});
		
		And references to $this in that file will refer to the plugin object. Once we make PHP 5.4 a dependency for this project, it'll be trivial to replace $self with $this in all the relevant locations and clean up the code a little.
		
		@param string $path The relative path of the UI file you wish to embed
		@param boolean $isRelativePath [optional] True if we should treat the $path parameter as relative to the implementing plugin (the least-surprising behaviour)
		*/
		public function addUI($path, $isRelativePath = true){
			if($isRelativePath) {
				$classInfo = new ReflectionClass($this);
				require dirname($classInfo->getFileName()).'/'.$path;
			}
			else {
				require $path;
			}
		}
		
		/**
		Attaches a method on the current object to a WordPress ajax hook. The method name is ajax_[foo] where `foo` is the action name	*
		
		@param string $name The name of the action you wish to hook into
		 
		*/
		protected function listen_for_ajax_action($name, $must_be_logged_in = true){
			
			$self = $this;
			
			$action = function() use ($self){
				return $self->call($_REQUEST['action'], $_REQUEST);
			};
			
			if($must_be_logged_in !== true){
				add_action( 'wp_ajax_nopriv_' . $name, $action);
			}
			
			add_action( 'wp_ajax_' . $name, $action );
		}
		
		/*
		**
		**	WP Callbacks
		**
		*/
		
		/**
		Handles callbacks from the `init` action.
		
		@internal **/
		public function _init(){
			$self = $this; //closure hack
			
			//Register the most commonly used actions
			$this->add_action('admin_menu');
			$this->add_action('admin_init');
			$this->add_action('save_post');
			$this->add_action('add_meta_boxes');
			$this->add_action('load-themes.php', 'themeDidChange');
			$this->add_action( 'admin_enqueue_scripts' );
			$this->add_action( 'wp_enqueue_scripts' );
			
			//Add a hook that'll allow handling POST requests for a given URL
			if('POST' == $_SERVER['REQUEST_METHOD']){
				$this->add_action('admin_enqueue_scripts', 'handle_form_post_for_url');
			}

			//Add callbacks for admin pages and script/style registration
			add_action('admin_menu', function() use ($self){
				
				$self->call('add_admin_pages');
				$self->call('register_scripts');
				$self->call('register_styles');
			});
		
			//Set up the settings shortcut feature
			$this->create_settings_shortcut_on_plugin_page();
			
			//Register common AllSpark styles, if the file is present
			$filepath = '/allspark-resources/style.css';
			
			if(file_exists(plugin_dir_path(__FILE__) . $filepath)){
				wp_register_style( 'allspark', plugin_dir_url(__FILE__) . $filepath, false, '1.0.0' );
			}
		}

		/**
		Having a settings URL on your plugin page is a nice little touch. To add it automagically, just create a class variable called `settings_url` and this function will take care of the rest
		**/
			
		private function create_settings_shortcut_on_plugin_page(){
			
			//if it's not defined, there's no reason to do anything further
			if(!isset($this->settings_url)){
				return false;
			}
			
			//grab the settings url from the subclass
			$settings_url = $this->settings_url;
			
			// the $this is important - we want the subclass, not the super
			$unique_name = get_class($this) . '_did_attach_settings_link';  
			
			//this closure has a `use` declaration to get around the PHP 5.3 lack of `$this` in closures			
			$filter = function($links, $file)  use ($settings_url, $unique_name){
						
				if ($file == $this->pluginBase && wp_cache_get( $unique_name, __CLASS__ ) == false) {
						
					$settings_url = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . $settings_url;
					$settings_link = "<a href='$settings_url'>Settings</a>";
					array_unshift($links, $settings_link);

					wp_cache_set( $unique_name, true, __CLASS__ );
				}

				return $links;			
			};
			
			if(!has_filter('plugin_action_links', $filter)){
				add_filter('plugin_action_links', $filter, 10, 2);
			}			
		}

		/*
		**
		**	WP Automatic Updates
		**
		*/
		
		/**
		(Possibly) Block this plugin from the standard Wordpress update
		
		Hooks any http request going to api.wordpress.org/plugins/update-check/1.1 to remove all hints of this plugin. Have the implementing
		class toggle either $updateBlockWP or $updateUseCustom to enable this behaviour.
		@internal */
		function maybe_block_wp_plugin_update($ret, $args, $url) {
			if(!$this->updateBlockWP && !$this->updateUseCustom) {
				//We're not trying to block or override an update, just let everything continue
				return $ret;
			}
			if(false === strpos($url, 'api.wordpress.org/plugins/update-check')) {
				//This is not the update-check URL, let it continue unhindered
				return $ret;
			}
			if(false === strpos($url, 'update-check/1.1') || !isset($args['body']['plugins'])) {
				//This is a plugin-update request, but to a different API than we're capable of overriding. Boo
				trigger_error('AllSpark '.self::VERSION.': Wordpress Core is using an unrecognized update mechanism. Please manually update your AllSpark plugins.', E_USER_WARNING);
				return $ret;
			}
				
			//At this point, we know the HTTP request is for a plugin update using the 1.1 endpoint. We can modify this.
			$pluginData = json_decode($args['body']['plugins']);
			$pluginBase = $this->pluginBase;
			
			//Remove our plugin's entry from the list of plugins
			if(isset($pluginData->plugins->$pluginBase)) {
				unset($pluginData->plugins->$pluginBase);
			}
			
			//Remove our plugin from active plugins
			foreach($pluginData->active as $i => $plugin) {
				if($plugin == $pluginBase) {
					if(is_array($pluginData->active)) {
						unset($pluginData->active[$i]);
					}
					else if(is_object($pluginData->active) && isset($pluginData->active->$i)){
						unset($pluginData->active->$i);
					}
				}
			}
			
			//Reassemble the args
			$args['body']['plugins'] = json_encode($pluginData);
			
			//Let any other pre_http_request filters run at this time
			remove_filter('pre_http_request', array(&$this, 'maybe_block_wp_plugin_update'), 10); //if we ever do a remove_filter helper, switch it here
			$filteredHTTPRequest = apply_filters('pre_http_request', false, $args, $url);
			
			//If the filteredHTTPRequest is false, then we're responsible to perform the HTTP request
			if(false === $filteredHTTPRequest) {
				$http = _wp_http_get_object();
				$filteredHTTPRequest = $http->request($url, $args);
			}
			
			//Add this filter back in
			$this->add_filter('pre_http_request', 'maybe_block_wp_plugin_update', 10, 3);
			
			return $filteredHTTPRequest;
		}
		
		/**
		Query an external private server for plugin updates
		
		If $updateUseCustom is set, when Wordpress attempts to save the results of it's own plugin update check, we'll
		go and check the $updateUseCustom URL for update information
		@internal */
		function maybe_register_plugin_update($transient) {
			if(!$this->updateUseCustom) {
				//If we're not going to try a custom update, just return the transient value as-is
				return $transient;
			}
			
			//Call the custom update url to find out if an update is available
			$update = $this->getCustomUpdateInfo();
						
			if(version_compare($update->version, $this->pluginInfo['Version'], '>')) {
				//Insert our custom update response
				$myCustomUpdate = new stdClass();
				$myCustomUpdate->slug = $update->slug;
				$myCustomUpdate->new_version = $update->version;
				$myCustomUpdate->url = $this->updateUseCustom.'?plugin='.$this->pluginSlug;
				$myCustomUpdate->package = $update->download_link;
				$transient->response[$this->pluginBase] = $myCustomUpdate;
			}
			return $transient;
		}
		
		/**
		Use custom server information for a plugin
		
		If we're using a custom server for updating this plugin, modify the update info to match.
		@internal */
		function maybe_modify_plugin_update_info($ret, $action, $args) {
			if($this->updateUseCustom) {
				if($args->slug === $this->pluginSlug) {
					return $this->getCustomUpdateInfo();
				}
			}
			return $ret;
		}
		
		/**
		Get the update info from the custom update server
		
		@internal **/
		protected function getCustomUpdateInfo() {
			if(!$this->updateUseCustom) {
				return false;
			}
			
			$updateRequest = wp_remote_get($this->updateUseCustom.'?plugin='.$this->pluginSlug);
			if(!is_wp_error($updateRequest) || 200 === wp_remote_retrieve_response_code($updateRequest)) {
				$ret = json_decode($updateRequest['body']);
				if($ret->status == 'ok') {
					return unserialize($ret->data);
				}
			}
			
			//The request failed
			return false;
		}
		
		
		
		/**
		Internal command dispatching
		
		@todo: When this project has PHP 5.4 as a requirement, make this private
		@internal	**/
		function call($command, $params = null){
			if(method_exists($this, $command)){
				if(is_array($params)){
					return call_user_func_array(array($this, $command), $params);
				}
				else{
					return call_user_func(array($this, $command));
				}
			}
			else{
				return false;
			}
		}
			
		/**  
		Returns the singleton instance of this plugin class	
			
		@staticvar AllSpark $instance The singleton instance of this class 
		@return AllSpark The singleton instance 	**/
		public static function getInstance() {
			static $instance = null;
			if(null == $instance) {
				$instance = new static();
			}

			return $instance;
		}

		/** 
		Prevent cloning (breaks singleton pattern)
			
		@internal	**/
		final private function __clone() {
			trigger_error('Cannot clone an instance of a singleton AllSpark-derived plugin', E_USER_ERROR);			
		}
		
		/** 
		Prevent serializing (because we can't unserialize)
			
		@internal	**/
		final private function __sleep() {
			trigger_error('Cannot serialize an instance of a singleton AllSpark-derived plugin', E_USER_ERROR);	
		}
		
		/** 
		Prevent unserializing (breaks singleton pattern)
			
		@internal	**/
		final private function __wakeup() {
			trigger_error('Cannot unserialize an instance of a singleton AllSpark-derived plugin', E_USER_ERROR);	
		}
	}
}
