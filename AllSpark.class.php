<?php

if(!class_exists('AllSpark')) {	
	
	//Requires PHP 5.3+
	if(!version_compare(PHP_VERSION, '5.3.0', '>=')) {
		trigger_error('Cannot load AllSpark plugin class: Requires at least PHP 5.3. Derived plugins may fail.', E_USER_WARNING);
	}

	abstract class AllSpark{
		/**  @internal	**/
		private $version = 0.02;
		
		
		/** 
		The __constuct method bootstraps the entire plugin. It should not be modified. It is possible to override it, but you probably don't want to
		
		@internal	**/
		protected function __construct($req_allspark_version = false){
			if($req_allspark_version && $req_allspark_version > $this->version) {
				trigger_error("The required version ({$req_allspark_version}) of the AllSpark plugin ({$this->version}) was not loaded. Please update your plugins.", E_USER_ERROR);
				return;
			}
			
			$this->add_action('init');
			
			//if the main plugin file isn't called index.php, activation hooks will fail
			register_activation_hook( dirname(__FILE__) . '/index.php', array($this, 'pluginDidActivate'));
			register_deactivation_hook( dirname(__FILE__) . '/index.php', array($this, 'pluginDidDeactivate'));
			register_uninstall_hook(__FILE__, array($this, 'pluginWillBeDeleted'));
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
		Attaches a method on the current object to a WordPress ajax hook. The method name is ajax_[foo] where `foo` is the action name	*
		
		@param string $name The name of the action you wish to hook into
		 
		*/
		protected function listen_for_ajax_action($name, $must_be_logged_in = true){
			
			if($must_be_logged_in !== true){
				add_action( 'wp_ajax_nopriv_' . $name, array($this, 'handle_ajax_action'));
			}
			
			add_action( 'wp_ajax_' . $name, array($this, 'handle_ajax_action'));
		}
		
		/**
		Internal forwarding of AJAX requests from WordPress into this class
		
		@internal	**/
		function handle_ajax_action(){
			$this->call($_REQUEST['action'], $_REQUEST);
		}
				
		/*
		**
		**	WP Callbacks
		**
		*/
		
		/**
		Handles callbacks from the `init` action
		
		@internal **/
		public function init(){
	
			$self = $this;
			$this->add_action('admin_menu');
			$this->add_action('admin_init');
			$this->add_action('save_post');
			$this->add_action('add_meta_boxes');
			$this->add_action('load-themes.php', 'themeDidChange');
			
			$this->add_action('admin_enqueue_scripts', 'enqueue_items_for_url');
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
		}
		
		/**
			}
		}
		
		/**
		Internal command dispatching
		
		@internal	**/
		private function call($command, $params = null){
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