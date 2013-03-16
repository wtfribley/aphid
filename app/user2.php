<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class User {

	public static $data = array(
		'groups'	=>	array('id'=>0,'name'=>'anonymous')
	);

	public static function load() {

		// we have a recognized user.
		if ($id = Session::get('user_id')) {

			try {			
				$user = new Model('read', array(
					'model'	=>	'users',
					'id'	=>	$id
				));
			}
			catch (ModelException $e) {
				$response = new Response($e->get_code(), $e->get_message(), 'html', $e->get_code('string'));
				$response->send();
				exit(0);
			}

			if ( ! empty($user->data)) static::$data = $user->data;

			unset($user);
		}
	}

	public static function get_groups($field = null) {

		if (is_null($field)) return static::$data['groups'];

		$groups = array();
		foreach (static::$data['groups'] as $group) {
			if (isset($group[$field])) $groups[] = $group[$field];
			else throw new Exception('User::get_groups() - Groups model does not have field ' . $field);
		}

		return $groups;
	}

	public static function login($username, $password, $data = null) {

		// get user by username.
		try {			
			$user = new Model('read', array(
				'model'	=>	'users',
				'where'	=>	array('username',$username)
			));
		}
		catch (ModelException $e) {
			$response = new Response($e->get_code(), $e->get_message(), 'html', $e->get_code('string'));
			$response->send();
			exit(0);
		}

		// no results mean a bad username
		if (empty($user->data)) {
			return array('error'=>'username failed','username'=>$username);
		}

		// more than one result means a problem with the unique check during registration - this should never happen.
		else if (count($user->data) > 1) throw new Exception('Username uniqueness not enforced!');

		// check the retrieved hash against the supplied password.
		else {

			$hasher = Authentication::hasher();
			
			if ($hasher->CheckPassword($password, $user->data[0]['hash'])) {

				// Set the user_id session variable - future requests will
				// look for this value to find the current user.
				Session::set('user_id', $user->data[0]['id']);

				// Update User data here...
				static::$data = $user->data[0];
				static::$data['last_login'] = time();
				if (is_array($data)) static::$data = array_merge(static::$data, $data);

				// ...and in the database.
				$user->update(array(
					'data'			=>	static::$data,
					'id'			=>	static::$data['id'],
					'permit_all'	=>	true
				));

				unset($user);

				return true;
			}
			// the password does not validate against the stored hash.
			else {
				unset($user);
				return array('error'=>'password failed');
			}
		}
	}

	public static function logout($data = null) {

		// passed data is used to update the user on logout.
		if (is_array($data)) {

			try {
				$user = new Model('update', array(
					'model'			=>	'users',
					'data'			=>	$data,
					'id'			=>	static::$data['id'],
					'permit_all'	=>	true
				));
			} 
			catch (ModelException $e) {
				$response = new Response($e->get_code(), $e->get_message(), 'html', $e->get_code('string'));
				$response->send();
				exit(0);
			}
		}

		// a logged-in user is identified by the 'user_id' session value.
		//	...destroy it.
		Session::destroy('user_id');
	}
}