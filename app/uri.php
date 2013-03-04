<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class URI {

	private $path;

	public function __construct() {
		$uri = array();
        
        if(isset($_SERVER['REQUEST_URI'])) {
            // remove the base uri in case we've installed in a subdirectory.
            $pattern = '/^(' . preg_quote(Config::get('base_uri','/'),'/') . ')/';

            $uri = explode('/', preg_replace($pattern, '', $_SERVER['REQUEST_URI']));
        }
        else throw new Exception('Unable to determine the requested URL - REQUEST_URI not set.');

        if ($uri[0] == '') $uri[0] = 'index';

        $this->path = $uri;
	}

	/**
	 *	Retrieve URI information by index (/0/1/2/3/.../n).
	 *		Or pass 'model' to return index 0.
	 *		Or pass 'full' to return the original uri as a string.
	 */
	public function get($index = null, $default = false) {
		if (!is_null($index)) {

			// the model is defined by the first element in the uri - so 'model' returns the 0 index.
			if ($index == 'model') return $this->path[0];

			// return the full uri as a string.
			if ($index == 'full') {
				// remove the query string from the uri. (preg_replace will generally be fastest)
                return preg_replace('/\?(?!.*\?)\S+/', '', implode('/',$this->path));
			}

            if (isset($this->path[$index]) && $this->path[$index] != '') {
            	// remove the query string from the specified uri segment.
                return preg_replace('/\?(?!.*\?)\S+/', '', $this->path[$index]);
            }
            else return $default;
        }
        // return the path as an array.
        else return $this->path;
	}

	/**
	 *	Match a substring within the URI or within a specific URI segment.
	 *		Optionally include the query string in the URI.
	 */
	public function contains($string, $index = null, $exclude_query = true) {
		// use the whole uri or just one segment.
		is_null($index) ? $uri = implode('/',$this->path) : $uri = $this->get($index);

		// remove the query string
		if ($exclude_query) $uri = preg_replace('/\?(?!.*\?)\S+/', '', $uri);

		return (strpos($uri, $string) !== false);
	}
}