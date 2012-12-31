<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

/*
 *      Get and Set the Autoloader
 */

require PATH . 'app/autoloader.php';

// we can map known classes for faster loading
Autoloader::map(array(
    'Config' => PATH . 'app/config.php',
    'Controller' => PATH . 'app/controller.php',
    'DB' => PATH . 'app/db.php',
    'Log' => PATH . 'app/log.php',
    'Query' => PATH . 'app/query.php',
    'Request' => PATH . 'app/request.php',
    'Response' => PATH . 'app/response.php'
));

// Set the directory(-ies) in which we'll keep our classes
Autoloader::directory(array(
    PATH . 'app/'
));

// Register the Autoloader
Autoloader::register();


/*
 *      Load Configuration Data
 */

Config::Load();


/*
 *      Get Request, Send to Controller, Render Output.
 */

$request = new Request();

$format = 'json'; // json is the default format, change to 'html' if using html views.

$controller = new Controller($request, $format);

$response = new Response($controller);
$response->send($format);