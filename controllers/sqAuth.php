<?php

/**
 * Auth controller
 *
 * Basic authorization controller. Handles actions like login and logout and has
 * helper methods check(), login(), logout(), hash() and user() to allow 
 * integration into a web application.
 *
 * The login system uses the phpass library (the same one used by wordpress) to
 * securly hash passwords. Cookie support as well as session based logins are
 * possible.
 */

abstract class sqAuth extends controller {
	public $options = array(
		'cache' => true
	);
	
	public $user, $level, $isLoggedIn = false;
	
	public function __construct($options) {
		parent::__construct($options);
		
		if (!isset($_SESSION)) {
			session_start();
		}
		
		$this->user = sq::model('users')->limit();
		
		// Check session for login
		if (isset($_SESSION['sq-username'])) {
			$this->user->find(array($this->options['username-field'] => $_SESSION['sq-username']));
			
		// If no session than check for a cookie if cookie login is enabled
		} elseif (isset($_COOKIE['sq-auth']) && sq::config('auth/remember-me')) {
			$this->user->find(array('hashkey' => $_COOKIE['sq-auth']));
			
			// If a user is found log the user in again to increase the length
			// of the cookie
			if (isset($this->user->level)) {
				self::login($user->{sq::config('auth/username-field')}, true);
			}			
		}
		
		if (isset($this->user->level)) {
			$this->level = $this->user->level;
			$this->isLoggedIn = true;
		}
	}
	
	// Logs the user with the username and password supplied into the system and
	// sets a session. If the remember argument is true and the remember-me
	// option is true a cookie will be set as well.
	public function login($username, $password, $remember = false) {
		if (!$username || !$password) {
			form::error($this->options['login-failed-message']);
			return false;
		}
		
		$user = sq::model('users', array('load-relations' => false))
			->find(array(sq::config('auth/username-field') => $username));
		
		if (self::authenticate($password, $user->password)) {
			
			// Set the user info to the session
			$_SESSION['sq-username'] = $user->{sq::config('auth/username-field')};
			$_SESSION['sq-level'] = $user->level;
			
			if ($remember && sq::config('auth/remember-me')) {
				$timeout = time() + sq::config('auth/cookie-timeout');
				
				// A hashkey is saved to the user and into a cookie. If these two
				// parameters match the user will be allowed to log in.
				$hash = self::hash($user->{sq::config('auth/username-field')}.$user->password);
				
				setcookie('auth', $hash, $timeout, '/');
				
				$user->hashkey = $hash;
				$user->update();
			}
			
			return true;
		}
		
		form::error($this->options['login-failed-message']);
		return false;
	}
	
	// Logs the current user out of the system
	public function logout() {
		$this->user = null;
		$this->isLoggedIn = false;
		$this->level = null;
		
		$_SESSION['sq-level'] = null;
		$_SESSION['sq-username'] = null;
		
		// Clear the cookie
		setcookie('auth', null, time() - 10, '/');
	}
	
	// Checks login posted from form
	public function loginPostAction($username, $password, $remember = false) {
		$this->login($username, $password);
		
		sq::response()->redirect();
	}
	
	// Checks a password against a hashed password
	public static function authenticate($password, $hash) {
		$hasher = new PasswordHash(8, sq::config('auth/portable-hashes'));
		return $hasher->checkPassword($password, $hash);
	}
		
	// Returns hashed string
	public static function hash($password) {
		$hasher = new PasswordHash(8, sq::config('auth/portable-hashes'));
		
		return $hasher->HashPassword($password);
	}
	
	// This function is a special action for the admin module that handles the
	// changing of passwords in the admin interface
	public function passwordGetAction($model, $id) {
		if (sq::config('admin/require-login') || !self::check('admin')) {
			return sq::view('admin/login');
		}
		
		$users = sq::model($model, array('load-relations' => false))
			->where($id)
			->read();
		
		return sq::view('admin/forms/password', array(
			'model' => $users
		));
	}
	
	public function passwordPostAction($password, $confirm, $model) {
		if ($password == $confirm) {
			$users->password = self::hash($password);
			$users->update();
			
			sq::response()->redirect(sq::base().'admin/'.$model);
		}
	}
}

?>