<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class DB {
    
    /*
    *   Holds the connection details
    */
    private static $db_config = array ();
    
    /*
    *  This holds the PDO resource
    */
    private static $dbh = null;
    
    public static function Prepare($sql) {
        if(is_null(static::$dbh))
            static::connect();
            
        return static::$dbh->prepare($sql);
    }

    public static function lastId() {
        if(is_null(static::$dbh)) return false;
        else return static::$dbh->lastInsertId();
    }
    
    private static function connect() {
        
        static::$db_config = Config::get('database');
        
        $dbname = static::$db_config['name'];
        $host = static::$db_config['host'];
        $user = static::$db_config['user'];
        $pass = static::$db_config['pass'];
        
        $dsn = 'mysql:dbname='.$dbname.';host='.$host;

        static::$dbh = new PDO($dsn, $user, $pass);
    }
    
}