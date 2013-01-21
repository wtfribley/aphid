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
        'index',
        'authenticate'    
    );
    
    public function __construct($request) {
        $this->request = $request;
        $table = $request->get('table');        
        $this->action = ($request->test('action',array('read','having','by','add'))) ? 'read' : 'write';
        
        // tables listed in the special_tables array run corresponding methods
        if (in_array($table, $this->special_tables)) {
            $this->$table();    
        }
        else {       
            // are we allowed to do the action requested?
            if ($this->request->allow($this->action)) {
                
                // CSRF protection - javascript MUST include the CSRF token with each write-type request.
                if ($this->action == 'write') {
                    Authentication::CSRF($this->request);
                }
                
                // build and run the query.
                $query = new Query($this->request->action, $this->request->options());
                $this->results['data'] = $query->execute();
                
                // note that even if no data is found, the template file may STILL BE DISPLAYED
                //  i.e. it is up to the template (or javascript if returning json) to handle this error!
                if (empty($this->results)) $this->results['error'][] = '404';
            }
            // again, the template file (or javascript if returning json) is responsible for handling this!
            else {
                $this->results['error'][] = '403';    
            }
        }
        
        // if we want html, we have to check the templates table...
        if ($this->request->test('format','html')) {
            
            // set the table to login if it's allowed
            if (!$this->request->allow('view') && $this->request->allow('login')) $table = 'login';
            
            // if we can't login, we have to show access denied.
            if (!$this->request->allow('view') && !$this->request->allow('login')) {
                Error:page('403', $this->request);        
            }
            
            // view is allowed OR we're going to login
            $field = (count($this->results) > 1 ? 'plural' : 'single');            
            
            $view_query = new Query('read',array(
                'table' => 'templates',
                'fields' => $field,
                'where' => array('`table`',$table),
                'groupby' => 'none'
            ));          
            $view = $view_query->execute();
            
            // if the view isn't set, we have to show the 404 page.
            if (empty($view)) Error::page('404', $this->request);
            else $this->view = $view;
        }
        
        // a new CSRF token is generated with each request.
        //  it's up to javascript to include this token with write-type requests.
        $csrf = Authentication::getToken();
        Session::set('csrf',$csrf);
        if (!is_array($this->results)) $this->results = array($this->results);
        $this->results['csrf'] = $csrf;
    }
    
    public function index() {
        $this->results = array('index testes');    
    }
    
    public function authenticate() {
	    Authentication::user($this->request);
    }
}