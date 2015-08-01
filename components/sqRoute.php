<?php

/**
 * Route component
 *
 * Handles routing for sq application.
 */

abstract class sqRoute extends component {
	public $options = array(
		'cache' => true
	);
	
	public function __construct($options) {
		parent::__construct($options);
		
		$uriParts = explode('&', $_SERVER['QUERY_STRING']);
		
		unset($_GET[$uriParts[0]]);
		unset($_REQUEST[$uriParts[0]]);
		
		$uriParts = explode('/', $uriParts[0]);
		
		foreach ($this->options['definitions'] as $route => $val) {
			$params = array();
			
			if (is_numeric($route)) {
				$route = $val;
			}
			
			$routeParts = explode('/', $route);
			
			if (count($routeParts) < count($uriParts)) {
				continue;
			}
			
			$adjust = 0;
			foreach ($routeParts as $index => $routePart) {
				$index -= $adjust;
				
				if (empty($uriParts[$index]) && !strpos($routePart, '=') && !strpos($routePart, '?')) {
					continue 2;
				}
				
				if (strpos($routePart, '?') && isset($uriParts[$index]) && in_array($uriParts[$index], $routeParts)) {
					$adjust++;
				} elseif (isset($uriParts[$index]) && $routePart[0] == '{') {
					$params[$this->getKey($routePart)] = $uriParts[$index];
				} elseif (empty($uriParts[$index]) && strpos($routePart, '=') !== false) {
					$params[$this->getKey($routePart)] = $this->getValue($routePart);
				} elseif (isset($uriParts[$index]) && $uriParts[$index] !== $routePart && $routePart[0] != '{') {
					continue 2;
				}
			}
			
			if (is_array($val)) {
				foreach ($val as $key => $val) {
					$params[$key] = $val;
				}
			}
			
			$_GET += $params;
			$_REQUEST += $params;
			
			return;
		}
	}
	
	private function getValue($val) {
		$val = str_replace('{', '', $val);
		$val = str_replace('}', '', $val);
		$val = explode('=', $val);
		
		return $val[1];
	}
	
	private function getKey($key) {
		$key = explode('=', $key);
		$key = $key[0];
		
		$key = str_replace('{', '', $key);
		$key = str_replace('}', '', $key);
		$key = str_replace('?', '', $key);
		
		return $key;
	}
	
	// Makes a url out of an array of components
	public function to($fragments) {
		$url = '';
		
		if (!is_array($fragments)) {
			$fragments = func_get_args();
		}
		
		foreach ($fragments as $fragment) {
			if ($fragment) {
				$url .= '/'.$fragment;
			}
		}
		
		return sq::base().ltrim($url, '/');
	}
	
	// Converts a string into a clean url
	public static function format($url, $separator = '-') {
		$url = str_replace('&', 'and', $url);
		$url = preg_replace('/[^0-9a-zA-Z ]/', '', $url);
		$url = preg_replace('!\s+!', ' ', $url);
		$url = str_replace(' ', $separator, $url);
		$url = strtolower($url);
		
		return urlencode($url);
	}
}

?>