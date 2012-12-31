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
    private $request;
    
    /*
    *   The action to perform - i.e. the request type.
    */
    private $action;
    
    /*
    *   List of special tables that requre special actions
    */
    private $special_tables = array(
        'index'    
    );
    
    public function __construct($request, $format = 'json') {
        $this->request = $request;
        $this->action = $this->request->action();
        $table = $this->request->table();
        
        if (in_array($table, $this->special_tables)) {
            $this->$table();    
        }
        else {
            
            $query = new Query(
                $this->request->options()
            );
            
            $this->results = $query->execute();
        }
        
        // if we want html, we have to check the templates table...
        if ($format == 'html') {
            
            $field = (count($this->results) > 1 ? 'plural' : 'single');
            
            $view_query = new Query(array(
                'type' => 'read',
                'table' => 'templates',
                'fields' => $field,
                'where' => array('table',$table)
            ));
            
            $this->view = $view_query->execute();
        }
    }
    
    public function index() {
        $this->results = array('index testes');    
    }
}