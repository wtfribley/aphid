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
     *      - Fourth digit indicates the level required to be allowed to log in.
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
	
    /**
     *  Authenticate a new Request
     *  @param Request $request
     */
	public function __construct($request) {
		$this->request = $request;
		
		// get the permissions we're interested in - default is everything public
		$permissions = Config::get('permissions');
		if (isset($permissions[$request->table]))
			$this->permissions = $permissions[$request->table];
		else $this->permissions = '0000';
		
		// if there is a recognized user, get their authentication level
		if (Session::get('user')) {
			$query = new Query(array(
				'type' => 'read',
				'table' => 'users',
				'fields' => 'auth_level',
                // users are bound to a session id that is regenerated for each request.
                //  we use the LAST session id to get the user.
                //  (the user's sess_id is updated with the regenerated id on shutdown)
				'where' => array('sess_id',$request->session('last_id'))
			));
            $user_auth_level = $query->execute();
            // this means something fishy is going on.
            //  session says user is logged in, but their id doesn't match any known user.
            if (empty($user_auth_level)) {
                Error::page('401',$request);
            }
            
            $this->user_auth_level = $user_auth_level;
            
            // now that we've authenticated the user, we can update their sess_id.
            //  note: after this (i.e. in templates) simply use session_id() to retrieve user data.
            $query = new Query(array(
                'type' => 'update',
                'table' => 'users',
                'data' => array('sess_id'=>$request->session('id')),
                'where' => array('sess_id',$request->session('last_id'))
            ));
            $query->execute();              
		}
		
		// set our allowed actions
        $this->request->allow['view'] = ($this->user_auth_level >= $this->permissions[0]);
        $this->request->allow['read'] = ($this->user_auth_level >= $this->permissions[1]);
        $this->request->allow['write'] = ($this->user_auth_level >= $this->permissions[2]);
        $this->request->allow['login'] = ($this->user_auth_level >= $this->permissions[3]);       
	}
    
    /**
     *  Simply return the (now-altered) request object.
     */
    public function request() {
		return $this->request;
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
    
    /**
     *  Compare data in the request to current session value to prevent CSRF.
     *  @param Request $request - passed by reference, modifies the request object.
     */
    public static function CSRF($request) {
        if (!isset($request->options['data']['csrf']) || ($request->data('csrf') != $request->session('csrf'))) {
            Error::page('401', $request);                  
        }
        // we have to unset the csrf element so it doesn't try to use it in subsequent queries.
        else if (isset($request->options['data']['csrf']) && ($request->data('csrf') == $request->session('csrf')))
            unset($request->options['data']['csrf']);
            
        return $request;
    }
    
    /*
    *   Generate a secure random token - from http://stackoverflow.com/a/13733588
    *   @param: int $length
    */
    public static function getToken($length = 16) {
        $token = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars.= 'abcdefghijklmnopqrstuvwxyz';
        $chars.= '0123456789';
        for ($i=0;$i<$length;$i++) {
            $token.= $chars[static::secure_rand_num(0,strlen($chars))];        
        }
        return $token;
    }
   
    /*
    *   Generate a secure random number between $min and $max.
    *       from http://stackoverflow.com/a/13733588
    */
    private static function secure_rand_num($min, $max) {
        $range = $max - $min;
        if ($range < 0) return $min; // not so random...
        $log = log($range, 2);
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;    
    }
}