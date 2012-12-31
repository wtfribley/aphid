<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class DB {
    
    /*
    *   Connection details - EDIT THIS to match your information.
    */
    private static $db_config = array (
        'name' => 'vault',
        'host' => 'localhost',
        'user' => 'root',
        'pass' => 'root'
    );
    
    /*
    *  This holds the PDO resource
    */
    private static $dbh = null;
    
    public static function Prepare($sql) {
        if(is_null(self::$dbh))
            self::connect();
            
        return self::$dbh->prepare($sql);
    }
    
    private static function connect() {
        
        $dbname = self::$db_config['name'];
        $host = self::$db_config['host'];
        $user = self::$db_config['user'];
        $pass = self::$db_config['pass'];
        
        $dsn = 'mysql:dbname='.$dbname.';host='.$host;

        self::$dbh = new PDO($dsn, $user, $pass);
    }
    
}