<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Config {
    
    /**
     *  Holds the application's settings.
     *  @var array $settings
     */
    private static $settings = array();       
    
    /**
     *  Loads settings from the DB - called during bootstrap.
     */
    public static function load() {
    
    	static::loadDB();
        
        $query = new Query('read',array(
            'table' => 'config',
            'groupby' => 'none'
        ));
        
        foreach($query->execute() as $row) {
	    	static::$settings[$row['field']] = $row['value'];    
        }
    }
    
    /**
     *	Saves settings back to the DB - called before shutdown.
     */
    public static function save() {
	    // out with the old...
	    $query = new Query('delete',array(
	    	'table' => 'config'
	    ));
	    $query->execute();
	    
	    // and in with the new! (note: because the column name is key, we need backticks)
	    $query = new Query('create',array(
	    	'table' => 'config',
	    	'data' => array('field'=>'','value'=>'')
	    ));
	    // we're running a bunch of inserts - so we'll prepare, then iterate.
	    $stmt = DB::Prepare($query->parse_sql());   
	    foreach (static::$settings as $key => $value) {
	    	// we don't store database connection info in the database.
	    	if ($key != 'db') {
	    		
	    		// the console environment must be set explicity in bootstrap.php - it will not be saved.
	    		if ($key == 'env' && $value == 'console') $value = 'dev';
	    		
	    		// arrays will be json encoded - it's faster than serialize (and don't need benefits of serialize)
	    		if (is_array($value)) $value = json_encode($value);
	    		
		    	$testes = $stmt->execute(array($key,$value));
	    	}
	    }
    }
    
    /**
     *  Retrieve a setting.
     * 
     *  @param string $key 
     *  @param mixed $default optional, defaults to false 
     *  @return mixed The desired setting or the passed default. 
     */
    public static function get($key, $default = false) {
        if (isset(static::$settings[$key])) return static::$settings[$key];
        else return $default;
    }
    
    /**
     * Set a setting.
     * 
     * @param string $key
     * @param mixed $value
     */
    public static function set($key, $value) {
        static::$settings[$key] = $value;
    }
    
    /**
     *	Load up the database config file
     */
    private static function loadDB() {
	    
	    if (file_exists(PATH . 'config.php'))
	    	static::$settings['db'] = require PATH . 'config.php';
	    else throw new Exception('Cannot find database configuration file - this probably means you haven\'t installed Aphid properly or at all.');
    }
}