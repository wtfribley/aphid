<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Request {
 
    /*
	*	The format in which results will be desired - i.e. the Accept header.
	*/
    public $format = 'html';
    
    /*
    *   The CRUD action (i.e. create, read, update, delete).
    */
    public $action;
    
    /*
    *   The database table indicated by the request.
    */
    public $table;
    
    /*
    *   Options array to be passed to the Query constructor.
    */
    public $options = array();
    
    /*
    *   Holds the session object.
    */
    public $session;
    
    /*
    *   Which actions are allowed - these defaults are overwritten by Authentication.
    */
    public $allow = array(
        'view' => true,
        'read' => true,
        'write' => true,
        'login' => true
    );
    
    /*
    *   Mapping of request methods to CRUD.
    */
    private $crud_map = array(
        'POST' => 'create',
        'GET' => 'read',
        'PUT' => 'update',
        'DELETE' => 'delete'
    );
    
    /*
    *   A list of accepted options to check the query string against.
    */
    private $options_list = array(
        'join', 'having', 'by', 'add', 'as',
        'fields',
        'data',
        'where',
        'orderby',
        'limit'
    );
    
    /*
    *   The requested URI, exploded into an array.   
    */
    private $path_info;
    
    public function __construct() {
        // open the session.
        $this->session = new Session();
    
    	// get the desired output format, which will alter behavior down the road.
    	if (strpos($_SERVER['HTTP_ACCEPT'], 'html') !== false)
    		$this->format = 'html';
    	else if (strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false)
    		$this->format = 'json';
        
        // explode the URI
        $uri = array();
        if(isset($_SERVER['REQUEST_URI'])) {
            // remove the base uri in case we've installed in a subdirectory.
            $pattern = '/^(' . preg_quote(Config::get('base_uri','/'),'/') . ')/';
            $uri = explode('/', preg_replace($pattern, '', $_SERVER['REQUEST_URI']));
        }
        else throw new Exception('Unable to determine the requested URL - REQUEST_URI not set.');        
        $this->path_info = $uri;
        
        // save the requested table name.
        //  (options needs this as well, to be passed to Query)
        $this->table = $this->path_info(0,'index');
        $this->options['table'] = $this->table;
        
        // save the request method (GET, POST, PUT, DELETE) as a CRUD action.
        //  (options needs this as well, to be passed to Query)
        $this->action = $action = $this->crud_map[$_SERVER['REQUEST_METHOD']];
        
        // the remainder of the Request will be created depending upon the action required.
        $this->$action();
    }
    
    public function get($property, $default = false) {
        if (isset($this->$property)) return $this->$property;
        else return $default;
    }
    
    public function test($property, $test) {
        if (is_array($test)) return in_array($this->$property, $test);
        return ($test == $this->$property);    
    }
    
    public function options($key = null, $default = false) {
        if (!is_null($key)) {
            if (isset($this->options[$key])) return $this->options[$key];
            else return $default;
        }
        else if (!is_null($this->options)) return $this->options;
        else return $default;
    }
    
    public function session($key, $default = false) {
        if ($key == 'id' || $key == 'last_id' || $key == 'csrf')
            return $this->session->$key;
        else
            return Session::get($key, $default);
    }
    
    public function allow($action) {
        return $this->allow[$action];    
    }
    
    public function path_info($i = false, $default = false) {
        if ($i !== false) {
            if (isset($this->path_info[$i]))
                return preg_replace('/\?(?!.*\?)\S+/', '', $this->path_info[$i]);
            else return $default;
        }
        else return $this->path_info;
    }
    
    /*
    *	create populates $this->options['data'] from the $_POST array.
    */
    private function create() {	    
	    $this->options['data'] = $_POST;
    }
    
    /*
    *   read populates $this->options from the $_GET array, handling relationships and the where clause.
    */
    private function read() {
        
        $options = $_GET;      
        // an array of keys that don't match 'normal' keys defined above.
        $diff = array_values(array_diff(array_keys($options),$this->options_list));
                
        // if 'where' isn't explicitly passed, we'll attempt to add it now...
        if (!isset($options['where'])) {
	        if (isset($options['id'])) {
	            $options['where'] = $options['id'];
	            unset($options['id']);
	        }
	        // if 'id' is somehow not present, we'll use the URI fragment directly.
	        else if ($this->path_info(1)) {
	            $id = $this->path_info(1);
	            $options['where'] = $id;       
	        }
	        // none of the above? if there's a single unknown key in the GET array, we'll
	        //  assume that it should be used to search the database.
	        else if (count($diff) == 1) {
	            $options['where'] = array($diff[0],$_GET[$diff[0]]);
	            unset($options[$diff[0]]);
	        }
        }
        
        if (isset($options['having'])) {
            $this->action = 'having';
            
            $options['join'] = $options['having'];
            unset($options['having']);
        }
        if (isset($options['join']) && isset($options['where']))
            $this->action = 'by';
        if (isset($options['add']))
            $this->action = 'add';
        
        $this->options = array_merge($this->options, $options);     
    }
    
    /*
    *	update populates $this->options['data'] and handles the where clause.
    */
    private function update() {
		$data = json_decode(file_get_contents('php://input'), true);
		
		// if 'where' isn't explicitly passed, we'll attempt to add it now...
		if (isset($data['id'])) {
            $this->options['where'] = $data['id'];
            unset($data['id']);
        }
        // using the URI fragment directly.
        else if ($this->path_info(1)) {
            $id = $this->path_info(1);
            $this->options['where'] = $id;       
        }
        else if (!isset($data['where'])) {
	        throw new Exception('PUT (update) request must indicate which record to update. (no id or where clause)');
	    }
	    else {
		    $this->options['where'] = $data['where'];
		    unset($data['where']);
	    }
		
		$this->options['data'] = $data;  
    }
    
    /*
    *	delete handles the where clause.
    */
    private function delete() {
    	$where = json_decode(file_get_contents('php://input'), true);
    
	 	// if 'where' isn't explicitly passed, we'll attempt to add it now...
		if (isset($where['id'])) {
            $this->options['where'] = $where['id'];
            unset($data['id']);
        }
        // $where = array('where'=>x) - user should've simply passed an int instead.
        else if (is_numeric(current($where))) {
	        $this->options['where'] = current($where);
        }
        // using the URI fragment directly.
        else if ($this->path_info(1)) {
            $id = $this->path_info(1);
            $this->options['where'] = $id;       
        }
        else if (!isset($where)) {
	        throw new Exception('DELETE request must indicate which record to delete. (no id or where clause)');
	    }
	    else {
		    $this->options['where'] = $where;
	    }    
    }
}