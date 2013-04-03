<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class User {

	/**
	 *	User Data - default is set here to indicate an anonymous user.
	 *
	 *  @todo: look into defaulting this in the database instead of hard coding... good idea?
	 */
	public static $data = array(
		'groups'	=>	array('id'=>0,'name'=>'anonymous')
	);

	public static $metadata = array();

	public static function load() {

		// we have a recognized user.
		if ($id = Session::get('user_id')) {
			
			// throws ModelException on error.
			$user = new Model('read', array(
				'model'	=>	'users',
				'id'	=>	$id
			));

			if ( ! empty($user->data)) {
				static::$data = $user->data;
				static::$metadata = $user->metadata;
			}

			unset($user);
		}
	}

	/**
	 *	Return the User's Groups.
	 *
	 *	optionally select a single field (i.e. name or id) to return from each group.
	 */
	public static function get_groups($field = null, $default = false) {

		if (is_null($field)) return static::$data['groups'];

		$groups = array();
		foreach (static::$data['groups'] as $group) {
			if (isset($group[$field])) $groups[] = $group[$field];
			else return $default;
		}

		return $groups;
	}

	public static function login($username, $password, $data = null) {

		// get user by username.			
		$user = new Model('read', array(
			'model'	=>	'users',
			'where'	=>	array('username',$username)
		));

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

			$user = new Model('update', array(
				'model'			=>	'users',
				'data'			=>	$data,
				'id'			=>	static::$data['id'],
				'permit_all'	=>	true
			));
		}

		// a logged-in user is identified by the 'user_id' session value.
		//	...destroy it.
		Session::destroy('user_id');
	}
}