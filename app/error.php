<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

//
//
//	@TODO: FIX ALL DIS SHIT, USE THE NEW Response CLASS
//
//

class Error {

	private $allowed_error_codes = array(
		401, 403, 404, 500
	);
	
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

		// make sure we have an acceptable code.
		$code = $e->getCode();
		if ( ! in_array($code, $this->allowed_error_codes)) $code = 500;
		
        // check our environment to determine level of detail
        if (Config::get('env') == 'dev' || Config::get('env') == 'console') {
        
            // setup and display the error
            $message['message'] = $e->getMessage();
            $message['file'] = str_replace(PATH, '', $e->getFile());		
            $message['line'] = $e->getLine();

            $response = new Response($code, $message, Request::$format, (string)$code);
            $response->send();
        }
        else {
            $response = new Response($code, '', Request::$format, (string)$code);
            $response->send();       
        }
		
		exit(1);
	}
}