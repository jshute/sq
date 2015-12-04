<?php

/**
 * sq base static class
 *
 * The absolute first class called in the app. Handles calls to the controller 
 * and provides useful static variables and methods to the rest of the app such 
 * as config.
 *
 * Also manages overriding defaults with config values and a few other setup 
 * tasks.
 */

class sq {
	
	// Global static properties. $config is the merged configuration of the app
	// and error is the current application error (404, PHP warning, etc...).
	private static $config, $error;
	
	// Store for components so they don't have to be realoaded from memory
	// unnecessarily
	private static $cache = array();
	
	// Startup static function for the entire app. Handles setup tasks and 
	// starts the controller bootstrap.
	public static function init() {
		
		// Error handling function for the entire framework
		function sqErrorHandler($number, $string, $file, $line, $context) {
			$trace = debug_backtrace();
			
			// Remove this function from the trace
			array_shift($trace);
			
			if (sq::config('debug') || $number == E_USER_ERROR) {
				sq::error('500', array(
					'debug'   => 'A PHP error has occured.',
					'number'  => $number,
					'string'  => $string,
					'file'    => $file,
					'line'    => $line,
					'context' => $context,
					'trace'   => $trace
				));
			}
			
			// Logging can be disabled
			if (sq::config('log-errors')) {
				error_log('PHP '.sq::config('error-labels/'.$number).':  '.$string.' in '.$file.' on line '.$line);
			}
		}
		
		// Define framework custom error handler
		set_error_handler('sqErrorHandler');
		
		// Framework config defaults
		self::load('/defaults/main');
		
		// Set the date timezone to avoid error on some systems
		date_default_timezone_set(self::config('timezone'));
		
		// Start session
		session_start();
		
		// Set up the autoload function to automatically include class files. 
		// Directories checked by the autoloader are set in the global config.
		spl_autoload_register('sq::load');
		
		// Route urls using php config file
		sq::route()->start();
		
		// If module is url parameter exists call the module instead of the
		// controller
		if (sq::request()->any('module')) {
			echo self::module(sq::request()->any('module'));
		} else {
			
			// Get controller parameter from the url. If no controller parameter
			// is set then we call the default-controller from config.
			$controller = sq::request()->any('controller', self::config('default-controller'));
			
			// Call the currently specified controller
			$controller = self::controller($controller);
			
			// Check for routing errors before calling controller actions
			if (!self::$error) {
				$controller->action(sq::request()->any('action'));
			}
			
			echo $controller;
		}
	}
	
	// Adds error to the error array. Can be called anywhere in the app as 
	// self::error().
	public static function error($code = null, $details = array()) {
		if ($code) {
			$details['code'] = $code;
			
			// Only set error if one doesn't already exist
			if (!self::$error) {
				self::$error = $details;
			}
			
			view::reset();
		}
		
		return self::$error;
	}
	
	// Autoloader. Can be called directly. Checks for class files in the app
	// directories then in the framework directories. The paths checked are
	// specified in the autoload config option.
	public static function load($class, $type = null) {
		if (strpos($class, '\\')) {
			$class = explode('\\', $class);
			
			if ($class[0]) {
				$type = $class[0];
			}
			
			$class = array_pop($class);
		}
		
		$directories = array($type);
		$direct = false;
		
		if (!$type) {
			if ($class[0] == '/') {
				$directories = array(substr($class, 1));
				
				$direct = true;
			} elseif (self::config('autoload')) {
				$directories = self::config('autoload');
			} else {
				$directories = array('config');
			}
		}
		
		foreach ($directories as $dir) {
			if ($direct) {
				$path = $dir.'.php';
			} else {
				$path = $dir.'/'.$class.'.php';
			}
			
			$returned = null;
			if (file_exists($path)) {
				$returned = require_once($path);
			} elseif (file_exists(self::path().'/'.$path)) {
				$returned = require_once(self::path().'/'.$path);
			}
			
			// Add configuration to the application
			if (is_array($returned)) {
				self::$config = self::merge($returned, self::$config);
			}
		}
		
		if (strpos($class, 'sq') !== 0 && !class_exists($class, false) && class_exists('sq'.ucfirst($class))) {
			eval("class $class extends sq$class {}");
		}
	}
	
	// Combines the global, module, and passed in options for use in a component
	private static function configure($name, $options, $component = null) {
		
		// Explode pieces. Strings with a '/' are part of a module.
		$pieces = explode('/', $name);
		$name = end($pieces);
		
		// Load configuration
		self::load('/config/'.$name);
		self::load('/defaults/'.$name);
		
		// Merge direct config
		if (isset($pieces[1])) {
			$config = self::merge(self::config($pieces[0]), self::config($pieces[1]));
		} else {
			$config = self::config($pieces[0]);
		}
		
		$type = false;
		
		// Get component config
		if ($component) {
			$component = self::config($component);
			$config = self::merge($component, $config);
			
			$type = $component['default-type'];
		}
		
		// Merge type options
		if (isset($config['type'])) {
			$type = $config['type'];
		}
		
		// Merge type options
		if ($type) {
			$config = self::merge(self::config($type), $config);
		}
		
		// Merge passed in options
		$options = self::merge($config, $options);
		
		// Set name to config if it doesn't exist
		if (!isset($options['name'])) {
			$options['name'] = $name;
		}
		
		// Set class to name if it doesn't exist
		if (!isset($options['class'])) {
			$options['class'] = $name;
		}
		
		// Set type to config if it doesn't exist
		if (!isset($options['type'])) {
			$options['type'] = $type;
		}
		
		return $options;
	}
	
	// Maps method calls to sq::component so calling sq::mailer($arg) is the 
	// equivalent of calling sq::component('mailer', $arg)
	public static function __callStatic($name, $args = null) {
		array_unshift($args, $name);
		
		return forward_static_call_array(array('sq', 'component'), $args);
	}
	
	/**
	 * Returns a component object
	 *
	 * Configures and returns the component object requested by name. For 
	 * instance calling sq::component('mailer') returns the mailer component
	 * object fully configured.
	 */
	public static function component() {
		$args = func_get_args();
		$name = array_shift($args);
		
		// Check for cached component object
		if (isset(self::$cache[$name])) {
			return self::$cache[$name];
		}
		
		if (class_exists('components\\'.$name)) {
			$class = 'components\\'.$name;
		} elseif (class_exists($name) && is_subclass_of($name, 'component')) {
			$class = $name;
		}
		
		$reflection = new ReflectionClass($class);
		$paramCount = $reflection->getConstructor()->getNumberOfParameters();
		
		$options = array();
		if (isset($args[$paramCount - 1])) {
			$options = array_pop($args);
		}
		
		$args[$paramCount - 1] = self::configure($name, $options, 'component');
		
		$component = $reflection->newInstanceArgs($args);
		
		// Force override with passed in options
		$component->options = self::merge($component->options, $options);
		
		// Cache the component if configured
		if ($component->options['cache']) {
			self::$cache[$name] = $component;
		}
		
		return $component;
	}
	
	/**
	 * Returns a model object
	 *
	 * The type of model generated can be explicity passed in or specified in 
	 * the config. If no type is determined the framework default is used.
	 */
	public static function model($name, $options = array()) {
		$config = self::configure($name, $options, 'model');
		
		$class = $config['type'];
		if (class_exists('models\\'.$config['class'])) {
			$class = 'models\\'.$config['class'];
		} elseif (class_exists($config['class']) && is_subclass_of($config['class'], 'model')) {
			$class = $config['class'];
		}
		
		$model = new $class($config);
		
		// Force override with passed in options
		$model->options = self::merge($model->options, $options);
		
		return $model;
	}
	
	/**
	 * Returns a view object
	 *
	 * Data my be initially set to the view using the data argument. Echoing the
	 * view causes it to render. Once the view is returned values can be added
	 * to it.
	 */
	public static function view($file, $data = array()) {
		return new view(self::config('view'), $file, $data);
	}
	
	/**
	 * Runs and then returns a controller object
	 *
	 * Optionally a different action parameter may be included. If no action 
	 * argument is given then the global action parameter will be used.
	 */
	public static function controller($name, $options = array()) {
		$config = self::configure($name, $options, 'component');
		
		$class = 'controllers\\'.$config['class'];
		if (class_exists($class)) {
			$controller = new $class($config);
		} elseif (class_exists($config['class']) && is_subclass_of($config['class'], 'controller')) {
			$controller = new $config['class']($config);
		} else {
			
			// Throw an error for unfound controller
			self::error('404');
			
			// Return the default controller if none is found
			return self::controller(self::config('default-controller'), $options);
		}
		
		// Force override with passed in options
		$controller->options = self::merge($controller->options, $options);
		
		return $controller;
	}
	
	/**
	 * Runs and returns a module object
	 *
	 * Modules are like mini apps. They contain there own views, models and
	 * controllers.
	 */
	public static function module($name, $options = array()) {
		$config = self::configure($name, $options, 'module');
		
		if (!isset($config['default-controller'])) {
			$config['default-controller'] = $config['name'];
		}
		
		$module = new module($config);
		
		// Force override with passed in options
		$module->options = self::merge($module->options, $options);
		
		return $module;
	}
	
	/**
	 * Getter / setter for framework configuration
	 *
	 * Returns the full config array with no arguments. With one argument
	 * config() returns a config parameter using slash notation "sql/dbname" 
	 * etc... Using two arguemnts the function sets a config value.
	 */
	public static function config($name = null, $change = -1) {
		
		// Return the entire config array with no arguments
		if (!$name) {
			return self::$config;
		}
		
		// If the first argument is an array add it to config
		if (is_array($name)) {
			if ($change === true) {
				self::$config = self::merge($name, self::$config);
			} else {
				self::$config = self::merge(self::$config, $name);
			}
			
			return self::$config;
		}
		
		// Get or set the config parameter based on array path notation
		$name = explode('/', $name);
		
		// Sets the changed parameter to the config array by looping  backwards
		// over the name and creating nested arrays
		if ($change !== -1) {
			foreach (array_reverse($name) as $val) {
				$change = array($val => $change);
			}
			
			self::$config = self::merge(self::$config, $change);
		}
		
		// Find the requested parameter by looping through the name of the
		// requested parameter until it is found
		$config = self::$config;
		foreach ($name as $val) {
			if (isset($config[$val])) {
				$config = $config[$val];
			} else {
				return null;
			}
		}
		
		return $config;
	}
	
	// Returns the framework path
	public static function path() {
		return dirname(__FILE__).'/';
	}
	
	// Returns the application path
	public static function root() {
		return dirname($_SERVER['SCRIPT_FILENAME']).'/';
	}
	
	// Returns the document root of the application
	public static function base() {
		if (self::config('base')) {
			return self::config('base');
		}
		
		// If no root path is set then determine from php
		$base = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
		$base .= $_SERVER['HTTP_HOST'];
		
		if (dirname($_SERVER['PHP_SELF'])) {
			$base .= dirname($_SERVER['PHP_SELF']);
		}
		
		if (substr($base, -1) != '/') {
			$base .= '/';
		}
		
		return $base;
	}
	
	// Recursively merge the config and defaults arrays. array1 will be
	// overwritten by array2 where named keys match. Otherwise arrays will be
	// merged.
	public static function merge($array1, $array2) {
		if (is_array($array2)) {
			foreach ($array2 as $key => $val) {
				
				// Merge sub arrays together only if the array exists in both
				// arrays and is every key is a string
				if (is_array($val)
					&& isset($array1[$key]) && is_array($array1[$key])
					&& array_unique(array_map("is_string", array_keys($val))) === array(true)
				) {
					$val = self::merge($array1[$key], $val);
				}
				
				$array1[$key] = $val;
			}
		}
		
		return $array1;
	}
}

?>