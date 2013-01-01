<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Config {
    
    /**
     *  Holds the application's settings.
     *  @var array $settings
     */
    private static $settings = array();
    
    /**
     *  Loads settings from the DB - called during bootstrap
     */
    public static function Load() {
        
        $query = new Query(array(
            'type' => 'read',
            'table' => 'config'
        ));
        
        self::$settings = $query->execute();
    }
    
    /**
     *  Retrieve a setting.
     * 
     *  @param string $key 
     *  @param mixed $default optional, defaults to false 
     *  @return mixed The desired setting or the passed default. 
     */
    public static function Get($key, $default = false) {
        if (isset(self::$settings[$key])) return self::$settings[$key];
        else return $default;
    }
    
    /**
     * Set a setting.
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function Set($key, $value) {
        self::$settings[$key] = $value;
    }
}