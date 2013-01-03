<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

// @todo: figure dis shit out.

class Session {

    /**
     *  Store the session data.
     *  @var array $data
     */
    public $data = array();
    
    public function __construct() {
        // start the session, get the data and id.
        session_start();
        $this->data = $_SESSION;
        $this->data['id'] = session_id();
        // for your safety!
        session_regenerate_id();
        
    }
    
    public function get($key, $default = false) {
        if (isset($this->data[$key])) return $this->data[$key];
        else return $default;
    }
    
    public function set($key, $value) {
        $this->data[$key] = $value;    
    }
    
    public function end() {
        $_SESSION = $this->data;
    }
}