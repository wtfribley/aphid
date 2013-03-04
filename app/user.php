<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class User {

	public $data = array();

	public $groups = array();

	public function __construct($user_id = false) {

		// we have a recognized user.
		if ($user_id) {	// this should evaluate to false if $user_id == 0 as well.

			// get User by session id.
			$query = new Query('read', array(
				'table' 	=> 'users',
				'where' 	=> $user_id,
				'groupby'	=> 'none'
			));
	        $this->data = $query->execute();
	    }
	    // we have no user id - the user is anonymous.
	    else {
	    	$this->data['id'] = 0;
	    }

	    // get all Groups to which this user belongs.
	    //	Groups contain permissions.
	    $this->get_groups();
	}

	public function get($field, $default = false) {
		if (isset($this->data[$field])) return $this->data[$field];
		else return $default;
	}

	/**
	 *	Check if this User has a particular Permission on a particular Model.
	 *
	 *		Note: Allow supercedes Deny - if one group has permission, that takes
	 *		precedence over any other group that may not have permission.
	 */
	public function has_permission($model, $permission, $default = false) {

		// iterate through all Groups to check their associated Permissions.
		for ($i=0;$i<count($this->groups);$i++) {
			if (isset($this->groups[$i]['permissions']))
				$grp_perms = $this->groups[$i]['permissions'];
			else {
				$response = new Response(500, array(
					'error'	=>	'Group permissions not found',
					'groups'=>	$this->groups
				), 'html', '500');
			}

			// each group contains an array of permissions - which are themselves arrays.
			// we'll construct an array, then search the group's permissions for that array.
			//		Note: we return true out of the loop on the first found permission - this means
			//		that Allow supercedes Deny. This could be easily amended to switch that prioritization.
			$needle = array($model, $permission);
			if (in_array($needle, $grp_perms)) return true;
		}

		return false;
	}

	public function login($username, $password) {

		// get user by username.
		$query = new Query('read', array(
			'table'=>'users',
			'where'=>array('username',$username),
			'groupby'=>'none'
		));
		$user = $query->execute();

		// no results mean a bad username
		if (empty($user)) {
			return array('error'=>'username failed','username'=>$username);
		}

		// check the retrieved hash against the supplied password.
		else {
			$hasher = Authentication::hasher();
			
			if ($hasher->CheckPassword($password, $user['hash'])) {

				// Set the user_id session variable - future requests will
				// look for this value to find the current user.
				Session::set('user_id',$user['id']);

				// Assign the User to this instance, update the database.
				$this->data = $user;
				$this->data['last_login'] = time();

				$update_user = new Query('update',array(
					'table'	=>	'users',
					'data'	=>	$this->data,
					'where'	=>	$user['id']
				));
				$update_user->execute();

				// get the Groups associated with our new User.
				$this->get_groups();

				return true;
			}
			// the password does not validate against the stored hash.
			else return array('error'=>'password failed');
		}
	}

	public function logout() {
		// a logged-in user is identified by the 'user_id' session value.
		//	...destroy it.
		Session::destroy('user_id');
	}

	public function add_to_group($group) {

		// retrieve the Group's id.
		$query = new Query('read', array(
			'table'	=>	'groups',
			'fields'=>	'id',
			'where'	=>	array('name', $group)
		));
		$group_id = $query->execute();

		// insert record into the reference table groups_users
		$query = new Query('create', array(
			'table'	=>	'groups_users',
			'data'	=>	array('users_id'=>$this->data['id'],'groups_id'=>$group_id)
		));
		$query->execute();
	}

	private function get_groups() {

		// get Groups by user id
		$query = new Query('by', array(
    		'table'	=>	'groups',
    		'join'	=>	'users',
    		'relationship'	=>	'm-m',
    		'fields'=>	array('name','permissions'),
    		'where'	=>	$this->data['id']
    	));
    	$groups = $query->execute();
    	
    	// simple check to see if we've returned one group (which will have named keys)
    	//	or an array of groups (which will have numerical keys).
    	if (!isset($groups[0])) $groups = array($groups);
    	
    	$this->groups = $groups;
	}
}