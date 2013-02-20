<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class User {

	public $is_authenticated = false;

	public $permissions = array();

	public $last_login = 0;

	public $first_login = 0;

	/**
	*	Constructing a User takes the previous request's session id
	*		(available as 'last_id')
	*	and uses it to query the `users` table. Based on that result
	*	we get a list of permissions for that user.
	*
	*	If no user is found, we set permissions to false - this is
	*	the "Anonymous User" state.
	**/

	public function __construct($request) {

		$query = new Query('read', array(
			'table' => 'users',
            // users are bound to a session id that is regenerated for each request.
            //  we use the LAST session id to get the user.
            //  (the user's sess_id is updated with the regenerated id on shutdown)
			'where' => array('sess_id',$request->session('last_id')),
			'groupby'=>'none'
		));
        $user = $query->execute();

        if (empty($user)) {
        	// User is Anonymous
        	$user['id'] = 1;
        	$user['is_superuser'] = 0;
        }
        else {
        	// User is already authenticated.
    		$this->is_authenticated = true;
        }

    	if ($user['is_superuser'] === 1) {
    		$this->permissions = true;
    	}
    	else {
        	// All Users belong to at least one Group (the "other" group, by default)
        	//	Permissions are contained in groups.
        	$query = new Query('by', array(
        		'table'	=>	'groups',
        		'join'	=>	'users',
        		'relationship'	=>	'm-m',
        		'fields'=>	'permissions',
        		'where'	=>	$user['id']
        	));
        	$permissions = $query->execute();

        	foreach($permissions as $permission) {
        		if (!isset($this->permissions[$permission[0]]))
        			$this->permissions[$permission[0]] = array($permission[1]);
        		else $this->permissions[$permission[0]][] = $permission[1];
        	}
    	}

    	// now that we've authenticated the user, we can update their sess_id.
        //  note: after this (i.e. in templates) simply use session_id() to retrieve user data.
        $query = new Query('update',array(
            'table'	=> 'users',
            'data'	=> array('sess_id'=>$request->session('id')),
            'where' => array('sess_id',$request->session('last_id'))
        ));
        $query->execute();
	}

	public function hasPermission($table, $permission, $default = false) {
		if ($this->permissions === true) return true;
		else if ($this->permissions === false) return $default;
		else if (isset($this->permissions[$table]) && in_array($permission, $this->permissions[$table])) return true;
		else return $default;
	}

	public function login($username, $password, $username_failed = 'Incorrect Username', $password_failed = 'Incorrect Password') {

		// If we're already authenticated, can't login again...
		if ($this->is_authenticated) {
				return true;
		}
		else {
			// Get User by username
			$user_query = new Query('read',array(
				'table'=>'users',
				'fields'=>array('id','hash'),
				'where'=>array('username',$username),
				'groupby'=>'none'
			));
			$user = $user_query->execute();
			
			// supplied username matches no database records
			if (empty($user)) return $username_failed;

			// verify incoming password against stored hash using PasswordHash.		
			else {
				$hasher = Authentication::hasher();
				
				if ($hasher->CheckPassword($password, $user['hash'])) {
					
					// A User is logged in if their sess_id is set to the current session id.

					$this->last_login = time();

					$update_user = new Query('update',array(
						'table'	=>	'users',
						'data'	=>	array('sess_id'=>session_id(),'last_login'=>$this->last_login),
						'where'	=>	$user['id']
					));
					$update_user->execute();
					
					// everything checks out!				
					return true;
				}
				// incoming password doesn't match stored hash
				else return $password_failed;	
			}
		}
	}

	public function logout() {
		// Regenerating the session id will throw the user out of sync with the
        //  session id - the next request won't be able to find a known user as
        //  their stored session id won't match.
        session_regenerate_id();
	}
}