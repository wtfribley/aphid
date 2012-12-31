<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Response {

    /*
    *   Holds the controller which handled this request.
    */
    private $controller;
        
    
    public function __construct($controller) {        
        $this->controller = $controller;
    }
    
    public function send($format = 'json') {
        // default to json - simply encode results as json.
        if ($format == 'json') {
            header('Content-Type: application/json; charset=utf8');
            echo json_encode($this->controller->results);
        }
        // if we want to use html views (i.e. templates), we need to do more work...
        else if ($format == 'html' && (is_null($this->controller->view) === false)) {
            if (file_exists(PATH . 'views/' . $this->controller->view . '.php')) {
                ob_start();      
                    include PATH . 'views/' . $this->controller->view . '.php';
                    $content = ob_get_clean();
                
                header('Content-Type: text/html; charset=utf8');
                echo $content;
            }
            else throw new Exception('Specified view file ' . PATH . 'views/' . $this->controller->view . '.php not found');
        }
        else throw new Exception ('Invalid response format');
    }
}