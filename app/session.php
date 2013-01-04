<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

// @todo: figure dis shit out.

class Session {

    /**
     *  Store the session's CURRENT id.
     *  @var string $id
     */
    public $id;
    
    /**
     *  Store the session's PREVIOUS id.
     *      This is used to identify a user, as it is this value that will be in storage.
     *  @var string $id
     */
    public $last_id;
    
    /**
     *  The CSRF token that any write-type request must match.
     *  @var string $csrf
     */
    public $csrf = null;
    
    public function __construct() {
        // start the session, store the old id.
        session_start();
        $this->last_id = session_id();
        // get the csrf token if it exists - this is for convenience more than anything else.
        if (isset($_SESSION['csrf'])) $this->csrf = $_SESSION['csrf'];
        // for your safety!
        session_regenerate_id();
        $this->id = session_id();
    }
    
    public static function get($key, $default = false) {
        if (isset($_SESSION[$key])) return $_SESSION[$key];
        else return $default;
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;    
    }
}