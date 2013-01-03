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
    public $options;
    
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
    *   The requested URI, exploded into an array.   
    */
    private $path_info;
    
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
        'fields',
        'data',
        'where',
        'orderby',
        'limit'
    );
    
    
    public function __construct() {
    
    	// get the desired output format, which will alter behavior down the road.
    	if (strpos($_SERVER['HTTP_ACCEPT'], 'html') !== false)
    		$this->format = 'html';
    	else if (strpos($_SERVER['HTTP_ACCEPT'], 'json') !== false)
    		$this->format = 'json';
     
        // save the request method (GET, POST, PUT, DELETE) as a CRUD action.
        $this->action = $action = $this->crud_map[$_SERVER['REQUEST_METHOD']];
        $this->options['type'] = $action;
        
        // explode the URI
        $uri = array();
        if(isset($_SERVER['REQUEST_URI'])) {
            $uri = explode('/', $_SERVER['REQUEST_URI']);
            $uri = array_slice($uri, 1); // remove an empty first element
        }
        else throw new Exception('Unable to determine the requested URL - REQUEST_URI not set.');        
        $this->path_info = $uri;
        
        // save the requested table name
        $this->table = $this->options['table'] = $this->path_info(0,'index');
        
        // the remainder of the Request will be created depending upon the action required.
        $this->$action();
    }
    
    public function format($test_against = false) {
	    if ($test_against) return ($test_against == $this->format);
	    else return $this->format;
    }
    
    public function action($test_against = false) {
        if ($test_against) return ($test_against == $this->action);
        else return $this->action;    
    }
    
    public function table($test_against = false) {
        if ($test_against) return ($test_against == $this->table);
        else return $this->table;    
    }
    
    public function options($key = false, $default = false) {
        if ($key !== false) {
            if (isset($this->options[$key])) return $this->options[$key];
            else return $default;
        }
        else if (!is_null($this->options)) return $this->options;
        else return $default;
    }
    
    public function data($key = null, $default = false) {
        if (!is_null($key)) {
            if (isset($this->options['data'][$key])) return $this->options['data'][$key];
            else return $default;
        }
        else if (isset($this->options['data'])) return $this->options['data'];
        else return $default;
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
    *   Populate $this->options['data'] from the request body (i.e. $_POST).
    */
    private function create() {        
        $this->options['data'] = $_POST;
    }
    
    /*
    *   Populate $this->options from the URI and the query string (i.e. $_GET).
    */
    private function read() {
        
        $options = $_GET;
        $diff = array_values(array_diff(array_keys($options),$this->options_list));
        
        // normal operation dictates that the id is included in the passed GET data.
        if (isset($options['id'])) {
            $options['where'] = array('id', $options['id']);
            unset($options['id']);
        }
        // if it is somehow not present, we'll use the URI fragment directly.
        else if ($this->path_info(1)) {
            $id = $this->path_info(1);
            $options['where'] = array('id', $id);       
        }
        // none of the above? if there's an unknown key in the GET array, we'll
        //  assume that it should be used to search the database.
        else if (count($diff) == 1) {
            $options['where'] = array($diff[0],$_GET[$diff[0]]);
            unset($options[$diff[0]]);
        }
        
        $this->options = array_merge($this->options, $options);
    }
    
    private function update() {
        $data = json_decode(file_get_contents('php://input'), true); // return assoc array
        if (isset($data['false'])) unset($data['false']); // cleanup what Backbone gives us.
        
        if (isset($data['id'])) { 
            $this->options['where'] = array('id',$data['id']);
            unset($data['id']);
        }
        else throw new Exception('No ID found in PUT request');
        
        $this->options['data'] = $data;
    }
    
    private function delete() {
        $data = json_decode(file_get_contents('php://input'), true);            
        $this->options['where'] = array('id',$data['id']);
    }
}