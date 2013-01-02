<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Error {
	
	public static function page($page, $message = null) {
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
		if (!is_null($error = error_get_last())) {
			extract($error, EXTR_SKIP);
			static::exception(new ErrorException($message, $type, 0, $file, $line));
		}
	}
	
	public static function exception($e) {
		// clean output buffer
		ob_get_level() and ob_end_clean();
		
		// log the exception
		Log::exception($e);
		
		// setup and display the error
		$message = $e->getMessage();
		$file = str_replace(PATH, '', $e->getFile());		
		$line = $e->getLine();
		
		$path = THEME . '500.php';
		if (file_exists($path))		
			require $path;
		else require PATH . 'app/views/500.php';
		
		exit(1);
	}
}