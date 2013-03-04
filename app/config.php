<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Config {

	public static $data = array();

	/**
	 *	Load settings from config.ini.php
	 */
	public static function load() {

		// load config file. no room for error here.
		//		@todo: generate a more helpful error response - with link to installer.
		if (file_exists(PATH . 'config.ini.php')) {
	    	static::$data = parse_ini_file(PATH . 'config.ini.php', true);
	    }
	    else throw new Exception('Cannot find configuration file - this probably means you haven\'t installed Aphid properly or at all.');
	}

	/**
	 *	Get an item from Config::data using "dot" notation.
	 *
	 *	(code from the Laravel framework, licensed under the MIT License)
	 */
	public static function get($key = null, $default = false) {

		if (is_null($key)) return static::$data;

		$array = static::$data;

		foreach (explode('.', $key) as $segment) {

			if (! is_array($array) || ! array_key_exists($segment, $array)) return $default;

			$array = $array[$segment];
		}

		return $array;
	}

	/**
	 *	Set an item in Config::data using "dot" notation.
	 *
	 *	(code from the Laravel framework, licensed under the MIT License)
	 */
	public static function set($key, $value) {

		$array =& static::$data;
		$keys = explode('.',$key);

		while (count($keys) > 1) {

			$key = array_shift($keys);

			if (! isset($array[$key]) || ! is_array($array[$key]))
				$array[$key] = array();

			$array =& $array[$key];
		}

		$array[array_shift($keys)] = $value;
	}

	public static function save() {
	    
	    // build ini content.
	    $content = ""; 
        foreach (static::$data as $key=>$elem) { 
            $content .= "[".$key."]\n"; 
            foreach ($elem as $key2=>$elem2) { 
                if(is_array($elem2)) 
                { 
                    for($i=0;$i<count($elem2);$i++) 
                    { 
                        $content .= $key2."[] = \"".$elem2[$i]."\"\n"; 
                    } 
                } 
                else if($elem2=="") $content .= $key2." = \n"; 
                else $content .= $key2." = \"".$elem2."\"\n"; 
            } 
        }

        // Add protection against direct access - can only be accessed by parse_ini_file.
        $safety = ";<?php die(\"Permission Denied. Cannot Access Directly.\");\n";
        $safety.= ";/*\n\n";
        $content = $safety . $content . "\n;*/\n;?>";

        // write to file.
        if (!$handle = fopen(PATH . 'config.ini.php', 'w')) { 
	        throw new Exception('Unable to open Config File.');
	    } 
	    if (!fwrite($handle, $content)) {
	    	fclose($handle);
	        throw new Exception('Unable to write to Config File.'); 
	    } 
	    fclose($handle);
	}
}