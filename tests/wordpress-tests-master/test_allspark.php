<?php

require_once( '../../AllSpark.class.php' );

class AllSparkTestCases extends WP_UnitTestCase {
	
	private $plugin;
	
	function setUp(){
		parent::setUp();
		$this->plugin = AllSparkTest::getInstance();
		$this->plugin->test = $this;
	}
	
	// implicitly test each one of the add_action calls that aren't connected to anything
	// also tests that we can instantiate the object at all
	function test_unused_actions(){
		$this->assertNotNull(AllSparkTest::getInstance());
	}
	
	function test_add_action_by_string(){
		$this->plugin->test_add_action_by_string();
	}
	
	function test_add_action_by_callback(){
		$this->plugin->test_add_action_by_callback();
	}
	
	function test_ajax_action_nopriv(){
		$this->plugin->test_ajax_action_nopriv();
	}
	
	function test_ajax_action_admin(){
		$this->plugin->test_ajax_action_admin();
	}
	
	function test_call(){
		$this->plugin->test_call();
	}
	
	function test_prohibited_operations(){
		$this->plugin->test_prohibited_operations();
	}
}


class AllSparkTest extends AllSpark{
	
	var $test;
	var $required_allspark_version = null;
	
	function __construct(){
		
		if(rand(0,1)){ //ensure that we're gracefully handling what happens if this isn't defined
			$this->required_allspark_version = "0.0.4";
		}
		
		parent::__construct();
	}
		
	public function test_add_action_by_string(){	
		$this->add_action('init', 'dummy_callback');
		
		$has_action = has_action('init', array($this, 'dummy_callback'));
		
		$this->test->assertTrue( $has_action !== false );
	}
	
	public function test_add_action_by_callback(){	
		
		$callback = function(){
			echo "Hello World!";
		};

		$this->add_action('init', $callback);
		$has_action = has_action('init', $callback);

		$this->test->assertTrue( $has_action !== false );
	}
	
	function test_ajax_action_nopriv(){
	
		$callback = 'dummy_callback';
		
		$this->listen_for_ajax_action($callback, false);
		
		global $wp_filter;
		$this->test->assertTrue( isset( $wp_filter['wp_ajax_' . $callback] ) );
				
	
		$hook = $wp_filter['wp_ajax_nopriv_' . $callback][10];
		$hook_function = $hook[array_keys($hook)[0]];
		
		$_REQUEST['action'] = 'dummy_callback';
		$worked = call_user_func($hook_function['function']);
		
		$this->test->assertTrue( $worked );
	}

	function test_ajax_action_admin(){
		$callback = 'dummy_callback';
		
		$this->listen_for_ajax_action($callback);
		
		global $wp_filter;
		$this->test->assertTrue( isset( $wp_filter['wp_ajax_' . $callback] ) );
				
	
		$hook = $wp_filter['wp_ajax_' . $callback][10];
		$hook_function = $hook[array_keys($hook)[0]];
		
		$_REQUEST['action'] = 'dummy_callback';
		$worked = call_user_func($hook_function['function']);
		
		$this->test->assertTrue( $worked );
	}
	
	function test_call(){
		
		//Test calling by string
		$this->test->assertTrue($this->call('dummy_callback'));
		
		//Test calling by string with parameters
		$this->test->assertEquals( $this->call( 'dummy_callback' , array( 1 , 2 , 3 ) ), 3 );
		
		//Test bogus entry
		$this->test->assertFalse($this->call('asdfjaksdflkajsdf'));
	}
	
	 /**
     * @expectedException PHPUnit_Framework_Error
     */
	function test_prohibited_operations(){
		
		$this->try_doomed_function(function(){
			$clone = clone $this;
		});
		
		$this->try_doomed_function(function(){
			$serializer = serialize($this);
		});
	}
	
	function try_doomed_function($func){

		try{
			call_user_func($func);
		}
		catch(Exception $ex){
			$this->test->assertNotNull($ex);
			return;
		}
		
		$this->test->assertNotNull(NULL);	//autofail
	}
			
	function dummy_callback(){
		
		if(func_num_args() == 0){
			return true;
		}
		
		if(func_num_args() == 1 && __FUNCTION__ == func_get_args()[0]){
			return true;
		}
		else{
			return func_num_args();
		}
	}
	
	private function show_attached_hooks($hook){
		 
		 if(isset($wp_filter[$hook])){
			 dd($wp_filter[$hook]);
		 }
		 
		 dd($wp_filter);
	}
}


function dd($input){
	var_dump($input);
	die();
}