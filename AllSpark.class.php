<?php

if(!class_exists('AllSpark')) {	
	
	//Requires PHP 5.3+
	if(!version_compare(PHP_VERSION, '5.3.0', '>=')) {
		trigger_error('Cannot load AllSpark plugin class: Requires at least PHP 5.3. Derived plugins may fail.', E_USER_WARNING);
	}

	abstract class AllSpark{
		/**  @internal	**/
		private $version = 0.03;
		
		/** 
		The __constuct method bootstraps the entire plugin. It should not be modified. It is possible to override it, but you probably don't want to
		
		@internal	**/
		protected function __construct($req_allspark_version = false){
			if($req_allspark_version && $req_allspark_version > $this->version) {
				trigger_error("The required version ({$req_allspark_version}) of the AllSpark plugin ({$this->version}) was not loaded. Please update your plugins.", E_USER_ERROR);
				return;
			}
			
			
			//if the main plugin file isn't called index.php, activation hooks will fail
			register_activation_hook( dirname(__FILE__) . '/index.php', array($this, 'pluginDidActivate'));
			register_deactivation_hook( dirname(__FILE__) . '/index.php', array($this, 'pluginDidDeactivate'));
			register_uninstall_hook(__FILE__, array($this, 'pluginWillBeDeleted'));
			
			$this->add_action('init', '_init', 0, 1);	//ensure our internal init function gets called no matter what
			$this->add_action('init');					//make it so subclasses can use `init` as well
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
		 
		*/
		protected function add_action($name, $callback = false){
		
			if(!$callback){
				$callback = $name;
			}
		
			if(method_exists($this, $callback)){
				add_action($name, array($this, $callback));
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
		
		*/
		public function addUI($path){
			require_once($path);
		}
		
		/**
		Attaches a method on the current object to a WordPress ajax hook. The method name is ajax_[foo] where `foo` is the action name	*
		
		@param string $name The name of the action you wish to hook into
		 
		*/
		protected function listen_for_ajax_action($name, $must_be_logged_in = true){
			
			$self = $this;
			
			if($must_be_logged_in !== true){
				add_action( 'wp_ajax_nopriv_' . $name, array($this, function() use ($self){
					$self->call($_REQUEST['action'], $_REQUEST);
				}));
			}
			
			add_action( 'wp_ajax_' . $name, array($this, function() use ($self){
				$self->call($_REQUEST['action'], $_REQUEST);
			}));
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
			
			//Add a hook to allow enqueing scripts and styles for a given URL
			$this->add_action('admin_enqueue_scripts', 'enqueue_items_for_url');
			
			//Add a hook that'll allow handling POST requests for a given URL
			if('POST' == $_SERVER['REQUEST_METHOD']){
				$this->add_action('admin_enqueue_scripts', 'handle_form_post_for_url');
			}

			//Add callbacks for admin pages and script/style registration
			add_action('admin_menu', function() use ($self){
				$self->call('add_admin_pages');
			
				foreach(array(
					'register_scripts',
					'register_styles'
				) as $command){		
					$self->call($command);
				}
			});
		
			//Set up the settings shortcut feature
			$this->create_settings_shortcut_on_plugin_page();
			
			//Register common AllSpark styles
			wp_register_style( 'allspark', plugin_dir_url(__FILE__) . '/allspark-resources/style.css', false, '1.0.0' );
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
						
				if ($file == basename(dirname(__FILE__)) . '/index.php' && wp_cache_get( $unique_name, __CLASS__ ) == false) {
						
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
		Prevent unserializing (breaks singleton pattern)
			
		@internal	**/
		final private function __wakeup() {
			trigger_error('Cannot unserialize an instance of a singleton AllSpark-derived plugin', E_USER_ERROR);	
		}
	}
}