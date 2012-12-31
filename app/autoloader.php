<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

/*
 *      Helper function to set php_ini
 */

function ini_safe_set($key, $value)
{
    // some hosts disable ini_set for security 
    // lets check so see if its disabled
    if(($disable_functions = ini_get('disable_functions')) !== false) {
            // if it is disabled then return as there is nothing we can do
            if(strpos($disable_functions, 'ini_set') !== false) {
                    return false;
            }
    }

    // set it and return true if the result is not equal to false
    return (ini_set($key, $value) != false);
}

/*
 *      Autoloader - searches an array of directories, translates
 *      underscores (_) into slashes (/) when searching for classes.
 */

class Autoloader {
    
    private static $mappings = array();
    private static $directories = array();

    public static function register() {
            spl_autoload_register(array('Autoloader', 'load'));
    }

    public static function unregister() {
            spl_autoload_unregister(array('Autoloader', 'load'));
    }

    public static function map($map) {
            self::$mappings = array_merge(self::$mappings, $map);
    }

    public static function directory($dir) {
            self::$directories = array_merge(self::$directories, $dir);
    }
    
    public static function load($class) {
        // does the class have a direct map
        if(isset(self::$mappings[$class])) {
            // load class
            require self::$mappings[$class];

            return true;
        }

        // search directories
        $file = str_replace('_', '/', trim(strtolower($class), '/'));

        // get file path
        if(($path = self::find($file)) === false) {
            return false;
        }

        require $path;

        return true;
    }

    public static function find($file) {
        // search for classes in the directories we've set
        foreach(self::$directories as $path) {
            if(file_exists($path . $file . '.php')) {
                return $path . $file . '.php';
            }
        }

        return false;
    }
}