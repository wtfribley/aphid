<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Authentication {
	
	/**
	 *	The incoming request object goes here - to be altered during authentication.
	 *	@var Request $request
	 */
	public $request;
	
	/**
	 *	The permissions code for this request.
	 *		- First digit is the authentication level required to view the associated template.
	 *		- Second digit is the level required to read data.
	 *		- Third digit is the level required to read/write data.
	 *		 
	 *	@var array $permissions
	 */
	private $permissions;
	
	/*
	 *	The authentication level of the current user.
	 *		- 0 is no authentication (i.e. visitor)
	 *		- 1 is user
	 *		- 2 is admin
	 */
	private $user_auth_level = '0';
	
	public function __construct($request) {
		$this->request = $request;
		
		// get the permissions we're interested in - default is everything public
		$permissions = Config::get('permissions');
		if (isset($permissions[$request->table]))
			$this->permissions = $permissions[$request->table];
		else $this->permissions = '000';
		
		// if there is a recognized user, get their authentication level
		if ($request->session('id')) {
			$query = new Query(array(
				'type' => 'read',
				'table' => 'users',
				'fields' => 'auth_level',
				'where' => array('session',$request->session('id'))
			));
			$this->user_auth_level = $query->execute();
		}
		
		// @todo: modify the request object in accordance with the permissions.
	}
	
	public function request() {
		return $this->request;
	}
}