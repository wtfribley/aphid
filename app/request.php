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
        'join', 'on', 'by', 'as',
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
    *   read populates $this->options from the $_GET array, handling relationships and the where clause.
    */
    private function read() {
        
        $options = $_GET;
        
        // parse WHERE options
        $diff = array_values(array_diff(array_keys($options),$this->options_list));        
        // normal operation dictates that the id is included in the passed GET data.
        if (isset($options['id'])) {
            $options['where'] = $options['id'];
            unset($options['id']);
        }
        // if it is somehow not present, we'll use the URI fragment directly.
        else if ($this->path_info(1)) {
            $id = $this->path_info(1);
            $options['where'] = $id;       
        }
        // none of the above? if there's an unknown key in the GET array, we'll
        //  assume that it should be used to search the database.
        else if (count($diff) == 1) {
            $options['where'] = array($diff[0],$_GET[$diff[0]]);
            unset($options[$diff[0]]);
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
}