<?php

/**
 * Request component
 *
 * Handles get, request and post values for urls as well as basic sanitation.
 */

abstract class sqRequest extends component {
	public $options = array(
		'cache' => true
	);
	
	public $get, $post, $any, $isAjax;
	
	// Set values to the various public properties
	public function __construct($options) {
		parent::__construct($options);
		
		$this->get = $_GET;
		$this->post = $_POST;
		$this->any = $_REQUEST;
		$this->isPost = $this->post();
		$this->isGet = $this->get();
		$this->context = $this->any('sqContext');
		
		// Boolean marking if the request is an ajax request
		$this->isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
			&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
	}
	
	/**
	 * Methods used to get url parameters
	 *
	 * These methods retrieve values from get, post and request globals and
	 * return null if the requested parameter is not set.
	 */
	public function get($param = null) {
		return $this->param($param, 'get');
	}
	
	public function post($param = null) {
		return $this->param($param, 'post');
	}
	
	public function any($param = null) {
		return $this->param($param, 'any');
	}
	
	// Gets a model passed as part of a form
	public function model($name) {
		if ($this->post('sq-model') && in_array($name, $this->request('sq-model'))) {
			return sq::model($name)->set(self::request($name));
		}
	}
	
	// Implementation for the get() post() and request() methods above
	private function param($param, $type) {
		if (!$param) {
			return $_SERVER['REQUEST_METHOD'] == strtoupper($type);
		}
		
		if (isset($this->{$type}[$param])) {
			return $this->{$type}[$param];
		}
	}
}

?>