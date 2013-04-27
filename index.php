<?php

/**
 *      RESTful API for flexible and transparent database interaction.
 *
 *      Developed by @wtfribley. 
 */

// Define the Base Path

define('PATH', pathinfo(__FILE__, PATHINFO_DIRNAME) . '/');

// Block Direct Access

define("PRIVATE", true);

// Bootstrap

require PATH . 'app/run.php';

?>
