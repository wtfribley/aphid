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
     *      - Fourth digit indicates whether we allow redirect to login (0 or 1).
	 *		 
	 *	@var array $permissions
	 */
	private $permissions;
	
	/**
	 *	The authentication level of the current user.
	 *		- 0 is no authentication (i.e. visitor)
	 *		- 1 is user
	 *		- 2 is admin
     *
     *  @var string $user_auth_level
	 */
	private $user_auth_level = '0';
	
	public function __construct($request) {
		$this->request = $request;
		
		// get the permissions we're interested in - default is everything public
		$permissions = Config::get('permissions');
		if (isset($permissions[$request->table]))
			$this->permissions = $permissions[$request->table];
		else $this->permissions = '0001';
		
		// if there is a recognized user, get their authentication level
		if ($request->session('id')) {
			$query = new Query(array(
				'type' => 'read',
				'table' => 'users',
				'fields' => 'auth_level',
				'where' => array('sess_id',$request->session('id'))
			));
			$this->user_auth_level = $query->execute();
		}
		
		// set our allowed actions
        $this->request->allow['view'] = ($this->user_auth_level >= $this->permissions[0]);
        $this->request->allow['read'] = ($this->user_auth_level >= $this->permissions[1]);
        $this->request->allow['write'] = ($this->user_auth_level >= $this->permissions[2]);
        $this->request->allow['login'] = ($this->permissions[3] == 1);       
	}
    
    /**
     *  Set a table's permissions code - this resides in Config, which will save to the DB.
     *      (see the docs for $permissions in this class for an explanation of the codes)
     *
     *  @param string $table
     *  @param string $code - the 4-digit permissions code.
     */
    public static function set($table, $code) {
        $permissions = Config::get('permissions');
        $permissions[$table] = $code;
        Config::set('permissions');
    }
	
	public function request() {
		return $this->request;
	} 
}