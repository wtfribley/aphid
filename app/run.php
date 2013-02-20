<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

/*
 *      Get and Set the Autoloader
 */

require PATH . 'app/autoloader.php';

// we can map known classes for faster loading
Autoloader::map(array(
    'Authentication' => PATH . 'app/authentication.php',
    'Config' => PATH . 'app/config.php',
    'Controller' => PATH . 'app/controller.php',
    'DB' => PATH . 'app/db.php',
    'Error' => PATH . 'app/error.php',
    'Log' => PATH . 'app/log.php',
    'PasswordHash' => PATH . 'app/lib/PasswordHash.php',
    'Query' => PATH . 'app/query.php',
    'Request' => PATH . 'app/request.php',
    'Response' => PATH . 'app/response.php',
    'Session' => PATH . 'app/session.php',
    'User' => PATH . 'app/user.php'
));

// Set the directory(-ies) in which we'll keep our classes
Autoloader::directory(array(
    PATH . 'app/',
    PATH . 'lib/'
));

// Register the Autoloader
Autoloader::register();


/*
 *		Set Error Reporting / Handling & Register Shutdown Functions
 */

error_reporting(-1);
ini_set('display_errors',false);

// Register exception handler
set_exception_handler(array('Error','exception'));

// Register the native PHP error handler
set_error_handler(array('Error','native'));

// Register the general shutdown handler -- defined at the bottom of this file.
register_shutdown_function('aphid_shutdown');

// Register the shutdown error handler
register_shutdown_function(array('Error','shutdown'));


/*
 *      Load Configuration Data, define our THEME path, set timezone, set environment.
 */

Config::load();
define('THEME', PATH . 'themes/' . Config::get('theme','default') . '/');

date_default_timezone_set(Config::get('timezone','America/Los_Angeles'));

// setting to 'console' will simply log output, rather than echo anything to the browser.
//		(note: this setting will not be saved - it must be hardcoded here)
//Config::set('env','console');
 

/*
 *      Run Aphid - Follow the Request Object...
 */

$request = new Request();

// Create a new User for this request (will load current user if there is one)
$user = new User($request);

// ...and pass them to the Controller.
$controller = new Controller($user, $request);

// create a Response and send it where it needs to go.
$response = new Response($controller);
$response->send();


/*
 *		Run all of our shutdown functions.
 */
 
function aphid_shutdown() {
 	// Save any changes made to the config settings.
 	Config::save();
 	
 	// Save the current referring URL in case we need to redirect to it next...
 	$s = empty($_SERVER["HTTPS"]) ? '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
 	$url = 'http' . $s . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; 	
 	Session::set('referrer',$url);
}