<?php

/**
 *      RESTful API for the Mac-Box Vault system for inventory control,
 *      generating quotes, orders, and invoices.
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