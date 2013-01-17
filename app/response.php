<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Response {

    /*
    *   Holds the controller which handled this request.
    */
    private $controller;
        
    
    public function __construct($controller) {        
        $this->controller = $controller;
    }
    
    public function send() {
    	// for convenience...
    	$format = $this->controller->request->get('format');
    
    	// only log the results if we're in console mode
    	if (Config::get('env') == 'console') {
	    	$output = print_r($this->controller, true);
	    	Log::write('output',$output);	
    	}  
        // want json? simply encode results.
        else if ($format == 'json') {
            header('Content-Type: application/json; charset=utf8');
            echo json_encode($this->controller->results);
        }
        // if we want to use html views (i.e. templates), we need to do more work...
        else if ($format == 'html' && !is_null($this->controller->view)) {
            if (file_exists(THEME . $this->controller->view . '.php')) {
            	$path = THEME . $this->controller->view . '.php';    
            }
            else if (file_exists(PATH . 'app/views/' . $this->controller->view . '.php')) {
	            $path = PATH . 'app/views/' . $this->controller->view . '.php';
            }
            else throw new Exception('Specified template file ' . THEME . $this->controller->view . '.php not found.');
            
            ob_start();      
                include $path;
            $content = ob_get_clean();
            
            header('Content-Type: text/html; charset=utf8');
            echo $content;
        }
        // we can only do html or json.
        else throw new Exception ('Invalid response format');
    }
}