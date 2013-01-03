<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Controller {
 
    /*
    *   Holds the database result.
    */
    public $results;
    
    /*
    *   If we're returning html, this will hold the view name to use.
    */
    public $view = null;
    
    /*
    *   Holds the current request object.
    */
    public $request;
    
    /*
    *   The action to perform - either read or write.
    *       (this is NOT the same as the request's action,
    *        but it is compared against what the request is allowed to do)
    */
    private $action;
    
    /*
    *   List of special tables that have their own special methods.
    */
    private $special_tables = array(
        'index'    
    );
    
    public function __construct($request) {
        $this->request = $request;
        $table = $this->request->table();        
        ($request->action('read')) $this->action = 'read' : $this->action = 'write';
        
        // tables listed in the special_tables array run corresponding methods
        if (in_array($table, $this->special_tables)) {
            $this->$table();    
        }
        else {       
            // are we allowed to do the action requested?
            if ($this->request->allow($this->action)) {
                
                // CSRF protection
                if ($this->action == 'write') {
                    if (!isset($this->request->options['data']['csrf']) || ($this->request->data('csrf') != $this->request->session('csrf'))) {
                        Error::page('500','CSRF attack');                  
                    }
                    else if (isset($this->request->options['data']['csrf']))
                        unset($this->request->options['data']['csrf']);
                }
                
                // build and run the query.
                $query = new Query(
                    $this->request->options()
                );           
                $this->results = $query->execute();
            }
            // log an error into the results
            //  the template file or (if json) javascript is responsible for handling these.
            else {
                $this->results['error'][] = 'auth';    
            }
        }
        
        // if we want html, we have to check the templates table...
        if ($this->request->format('html')) {
            
            // set the table to login if it's allowed
            if (!$this->request->allow('view') && $this->request->allow('login')) $table = 'login';
            
            // if we can't login, we have to show access denied.
            if (!$this->request->allow('view') && !$this->request->allow('login')) {
                Error:page('531');        
            }
            
            // view is allowed OR we're going to login
            $field = (count($this->results) > 1 ? 'plural' : 'single');            
            
            $view_query = new Query(array(
                'type' => 'read',
                'table' => 'templates',
                'fields' => $field,
                'where' => array('table',$table)
            ));          
            $view = $view_query->execute();
            
            // if the view isn't set and we're returning html, we'll get the 404 page.
            // if we're returning json, it's javascript's responsibility to handle this error.
            if (empty($view)) $this->results['error'] = '404';
            else $this->view = $view;
        }
    }
    
    public function index() {
        $this->results = array('index testes');    
    }
}