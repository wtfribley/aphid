<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

// @todo: figure dis shit out.

class Session {

    public static function start() {

        // start the session, store the old id.
        session_start();
        // for your safety!
        session_regenerate_id();
    }
    
    public static function get($key = null, $default = false) {
        if (is_null($key)) return $_SESSION;

        if (isset($_SESSION[$key])) return $_SESSION[$key];
        else return $default;
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;    
    }

    public static function destroy($key = null) {
        if (is_null($key)) {
            $_SESSION = null;
            session_destroy();
        }
        else {
            if (isset($_SESSION[$key])) unset($_SESSION[$key]);
        }
    }
}