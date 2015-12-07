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
	
	// Triggered by sq to initialize routing
	public function start() {
		$uriParts = explode('&', $_SERVER['QUERY_STRING']);
		
		unset($_GET[$uriParts[0]]);
		unset($_REQUEST[$uriParts[0]]);
		
		$uriParts = explode('/', trim($uriParts[0], '/'));
		
		foreach ($this->options['definitions'] as $route => $val) {
			$params = array();
			
			if (is_numeric($route)) {
				$route = $val;
			}
			
			$routeParts = explode('/', $route);
			
			if (count($routeParts) + substr_count($route, '|') < count($uriParts)) {
				continue;
			}
			
			$adjust = 0;
			foreach ($routeParts as $index => $routePart) {
				$index -= $adjust;
				
				if (empty($uriParts[$index]) && !strpos($routePart, '=') && !strpos($routePart, '?')) {
					continue 2;
				}
				
				if ($this->getKey($routePart) == '|' && isset($uriParts[$index]) && isset($uriParts[$index + 1])) {
					$params[$uriParts[$index]] = $uriParts[$index + 1];
					$adjust--;
				} elseif (strpos($routePart, '?') && isset($uriParts[$index]) && in_array($uriParts[$index], $routeParts)) {
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
		
		sq::error('404');
	}
	
	// Make a url that points back to a route with the passed in key / value
	// parameters
	public function to($raw) {
		$fragments = array();
		
		// Handle passing through of a fragment name without a value. In this
		// case use the value from the current URL.
		foreach ($raw as $fragmentName => $fragmentValue) {
			if (is_numeric($fragmentName)) {
				$fragmentName = $fragmentValue;
				$fragmentValue = sq::request()->get($fragmentName);
			}
			
			$fragments[$fragmentName] = $fragmentValue;
		}
		
		foreach (array_reverse($this->options['definitions']) as $route => $params) {
			if (is_numeric($route)) {
				$route = $params;
				$params = array();
			}
			
			// Remove default values from rule string because they aren't needed
			// and mess matching up
			$route = preg_replace('/\=[^)]+\}/U', '?}', $route);
			
			// If the rule has more sections than the supplied url fragments
			// then skip the rule
			if ((substr_count($route, '{') - substr_count($route, '?')) > count($fragments)) {
				continue;
			}
			
			// If the rule has params specified that don't match the supplied
			// url fragments then skip the rule
			foreach ($params as $key => $val) {
				if (!isset($fragments[$key]) || $fragments[$key] != $val) {
					continue 2;
				}
			}
			
			// Loop through the array of url fragments and try to match them
			// with a rule
			foreach ($fragments as $fragmentName => $fragmentValue) {
				if (array_key_exists($fragmentName, $params) && $params[$fragmentName] == $fragmentValue) { 
					continue;
				} elseif (strpos($route, '{'.$fragmentName.'}') === false && strpos($route, '{'.$fragmentName.'?}') === false) {
					if (strpos($route, '{|') !== false) {
						$route = str_replace('{|}', $fragmentName.'/'.$fragmentValue, $route);
						$route = str_replace('{|?}', $fragmentName.'/'.$fragmentValue, $route);
					} else {
						continue 2;
					}
				} else {
					$route = str_replace('{'.$fragmentName.'}', $fragmentValue, $route);
					$route = str_replace('{'.$fragmentName.'?}', $fragmentValue, $route);
				}
			}
			
			$route = preg_replace('/\/?\{[^)]+\}/', '', $route);
			
			return sq::base().$route;
		}
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
	
	// Utility methods to extract keys and values from url fragments
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
}

?>