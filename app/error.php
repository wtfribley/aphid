<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

//
//
//	@TODO: FIX ALL DIS SHIT, USE THE NEW Response CLASS
//
//

class Error {
	
    /**
     *  Display an error page.
     *      note that the Request object is available to any error page templates...
     *      for security's sake, be careful about what information is displayed.
     */
	public static function page($page, $request = null) {
		$path = THEME . $page . '.php';
		if (file_exists($path))		
			require $path;
		else require PATH . 'app/views/' . $page . '.php';
		
		exit(1);
	}
	
	public static function native($code, $error, $file, $line) {
		// abide by PHP's error_reporting
		if (error_reporting() === 0) return;
		
		$exception = new ErrorException($error, $code, 0, $file, $line);
		
		static::exception($exception);
	}
	
	public static function shutdown() {
		if ( ! is_null($error = error_get_last())) {
			extract($error, EXTR_SKIP);
			static::exception(new ErrorException($message, $type, 0, $file, $line));
		}
	}
	
	public static function exception($e) {
		// clean output buffer
		ob_get_level() and ob_end_clean();
		
		// log the exception
		Log::exception($e);
		
        // check our environment to determine level of detail
        if (Config::get('env') == 'dev' || Config::get('env') == 'console') {
        
            // setup and display the error
            $message = $e->getMessage();
            $file = str_replace(PATH, '', $e->getFile());		
            $line = $e->getLine();
            
            require PATH . 'app/views/exception.php';
        }
        else {
            $path = THEME . '500.php';
            if (file_exists($path))		
                require $path;
            else require PATH . 'app/views/500.php';        
        }
		
		exit(1);
	}
}