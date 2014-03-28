<?php

/**
 * Controller base class
 *
 * Individual controllers extend controller which in turn extends this class. To
 * extend the base controller in your app add a class named controller to your 
 * project.
 * 
 * Contains base methods to deal with taint checking reading url parameters and
 * some basic actions to deal with errors and debuging.
 */

abstract class sqController extends component {
	
	/**
	 * Calls actions within the controller. Actions calls an action method after
	 * checking if the method  is valid. It is called by the sq::controller 
	 * global and can also be called directly. A single argument can be passed
	 * into the action method with the arg parameter. Actions can return views
	 * in which case the view replaces the existing layout.
	 */
	public function action($action, $arg = false) {
		
		// Check filter method to see if action should be called
		$filter = $this->filter(strtolower($action));
		
		if ($filter === true || $action == 'error' || $action == 'debug') {
			$action .= 'Action';
			
			// If arg is set add an argument to the action call
			if ($arg === false) {
				$data = $this->$action();
			} else {
				$data = $this->$action($arg);
			}
			
			// If something was returned save it as a new layout
			if ($data !== null) {
				$this->layout = $data;
			}
		
		// Filter can return a view as well such as a login screen
		} elseif ($filter) {
			$this->layout = $filter;
		}
	}
	
	// Function that calls a render on the controller layout
	public function render() {
		if (is_object($this->layout) && !url::ajax()) {
			$this->layout->full = true;
		}
		
		return $this->layout;
	}
	
	// Default filter action to be overridden in controller classes. Filter 
	// takes is passed the name of the action and returns true or false if it 
	// should be executed. By default it always returns true.
	public function filter($action) {
		return true;
	}
	
	// Default index action is none is defined. Index action is called when no
	// other action is defined. By default the action will generate the default
	// -view file.
	public function indexAction() {
		$class = get_class($this);
		
		// Check if the home view exists
		if (file_exists(sq::root().'views/'.$class.'/index.php')) {
			
			// If a layout exists use the view as content
			if (is_object($this->layout)) {
				$this->layout->content = sq::view($class.'/index');
			} else {
				return sq::view($class.'/index');
			}
		} else {
			sq::error('404');
		}
	}
	
	// Default action is called when the specific action method doesn't exist.
	// The action argument is the name of the called action that could not be
	// found. The default action calls the view controller/action by default.
	public function defaultAction($action) {
		$class = get_class($this);
		
		// Check if the file exists. If it doesn't throw a 404 error
		if (file_exists(sq::root().'views/'.$class.'/'.$action.'.php')) {
			
			// If a layout exists use the view as content
			if (is_object($this->layout)) {
				$this->layout->content = sq::view($class.'/'.$action);
			} else {
				return sq::view($class.'/'.$action);
			}
		} else {
			sq::error('404');
		}
	}
	
	// Default error action that may be overridden in the controller. You can 
	// also just create your own 404.php in the views directory and the 
	// framework will use your view with this action.
	public function errorAction($error) {
		if (!headers_sent()) {
			header(':', true, $error['code']);
		}
		
		// If a layout exists use the view as content
		if (is_object($this->layout)) {
			$this->layout->content = sq::view('error', array(
				'error' => $error));
		} else {
			return sq::view('error', array('error' => $error));
		}
	}
	
	// If config['debug'] is true this method will be used instead of the error
	// action above. This method prints out a stack trace of php errors instead
	// of a generic save 404 page. Again the view can be overridden like above.
	public function debugAction($error) {
		if (!headers_sent()) {
			header(':', true, $error['code']);
		}
		
		if (is_object($this->layout)) {
			$this->layout->content = sq::view('debug', array(
				'error' => $error));
		} else {
			return sq::view('debug', array('error' => $error));
		}
	}
}

?>