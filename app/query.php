<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Query {
    
    /**
     *  The CRUD type.
     */
    private $type = null;
    
    /**
     *  The table we will be accessing - needed for $this->fields().
     */
    private $table = null;
    
    /**
     *  SQL strings with placeholders.
     */
    private $sql_structures = array(
        'create' => 'INSERT INTO [TABLE] [DATA]',
        'read' => 'SELECT [FIELDS] FROM [TABLE] [WHERE] [ORDERBY] [LIMIT]',
        'update' => 'UPDATE [TABLE] [DATA] [WHERE]',
        'delete' => 'DELETE FROM [TABLE] [WHERE]'
    );
    
    /**
     *  The SQL string that will be executed - populated by replacing the
     *  appropriate SQL Structure with relevent data.
     */
    private $sql;
    
    /**
     *  Bindings for use in PDO Statements
     */
    private $bindings = array();

    /**
    *   Construct a Query...
    *
    *   Pass an array of options (see below) OR a string that identifies
    *   the query type.
    *
    *   Options:
    *
    *   $options['type']    = (string) the CRUD type for this query. REQUIRED.
    *   $options['table']   =     (string) the table to query. REQUIRED.
    *   $options['fields']  = (mixed) array of fields (or single string) to return on a read query.
    *   $options['data']    = (array) data for create/update queries. Form of 'field'=>'value'
    *   $options['where']   = (array) either ('where_field','where_value') assuming an '=' operator 
    *                       OR ('where_field','operator','where_value).
    *   $options['orderby'] = (mixed) either ('order_field','order_type') OR simply 'order_field'
    *                       and order_type defaults to DESC.
    *   $options['limit']   = (mixed) either ('limit','offset') OR simply 'limit' as an integer.
    *
    *   @var $options mixed.
    */
    public function __construct($options) {
     
        // if we've passed an array, we delegate to methods
        if (is_array($options)) {
            
            if (isset($options['type'])) $this->type($options['type']);
            
            if (isset($options['table'])) $this->table($options['table']);
            
            if (isset($options['fields'])) $this->fields($options['fields']);
            
            if (isset($options['data'])) $this->data($options['data']);
            
            if (isset($options['where'])) 
                call_user_func_array(array($this, 'where'), $options['where']);
            
            if (isset($options['orderby'])) {
                if (is_array($options['orderby']))
                    call_user_func_array(array($this, 'orderby'), $options['orderby']);
                else $this->orderby($options['orderby']);
            }
            
            if (isset($options['limit'])) {
                if (is_array($options['limit']))
                    call_user_func_array(array($this, 'limit'), $options['limit']);
                else $this->orderby($options['limit']);
            }
        }
        
        // if we've passed a string, it should be the query type
        else if (is_string($options)) {
            $this->type($options);    
        }
        
        // if we've passed something else, we have a problem
        else throw new Exception('invalid query argument');   
    }
    
    public function type($type) {
        
        if ($type AND in_array($type, array_keys($this->sql_structures))) {
            $this->type = $type;
            $this->sql = $this->sql_structures[$type];
        }
        else
            throw new Exception('invalid query type');
    }
    
    public function table($table) {
        $this->table = $table;
        $this->sql = str_replace('[TABLE]', $table, $this->sql);
    }
    
    public function fields($fields) {
        
        $sql = '';
        
        if (is_array($fields)) {          
            foreach($fields as $field) {
                $sql.= $this->table . '.' . $field . ',';
            }
            $sql = rtrim($sql,',');
        }
        else $sql = $this->table . '.' . $fields;
        
        $this->sql = str_replace('[FIELDS]', $sql, $this->sql);
    }
    
    public function data($data) {
        
        if ($this->type == 'create') {
            $sql = '(';
            
            foreach(array_keys($data) as $field) {
                $sql.= $field . ', ';
            }
            $sql = rtrim($sql, ', ');
            
            $sql.= ') VALUES (';
    
            foreach($data as $f => $v) {
                // serialize arrays
                if(is_array($data[$f])) $data[$f] = serialize($data[$f]);
                $sql.= "?,";
            }
            $sql = rtrim($sql, ',');
            
            $sql.= ')';            
        }
        
        if ($this->type == 'update') {
            $sql = 'SET ';
            
            foreach(array_keys($data) as $field) {
                $sql.= $field . '=?, ';    
            }
            $sql = rtrim($sql, ', ');
               
            // serialize arrays
            foreach($data as $f => $v) {
                if(is_array($data[$f])) $data[$f] = serialize($data[$f]);
            }
        }
        
        $this->sql = str_replace('[DATA]', $sql, $this->sql);
        $this->bindings = array_merge(array_values($data),$this->bindings);
    }
    
    public function where($wherefield, $operator, $wherevalue = null) {
        
        $sql = 'WHERE ';
        
        if (is_null($wherevalue)) {
            $sql.= $wherefield . '=?';
            $this->bindings[] = $operator;
        }
        else {
            $sql.= $wherefield . $operator . '?';
            $this->bindings[] = $wherevalue;
        }
        
        $this->sql = str_replace('[WHERE]', $sql, $this->sql);
    }
    
    public function orderby($field, $order = 'DESC') {       
        $sql = 'ORDER BY ' . $field . ' ' . $order;
        $this->sql = str_replace('[ORDERBY]', $sql, $this->sql);
    }
    
    public function limit($limit, $offset = null) {
        
        $sql = '';
        
        if (is_null($offset)) $sql = 'LIMIT ' . $limit;
        else $sql = 'LIMIT ' . $offset . ',' . $limit;
        
        $this->sql = str_replace('[LIMIT]', $sql, $this->sql);
    }
    
    /*
    *   Execute the query via PHP's PDO functionality.
    */
    public function execute() {
        
        if (is_null($this->type)) throw new Exception('Query must have a type');
        if (is_null($this->table)) throw new Exception('Query must have a table');
        
        $this->clean_sql();
        
        $stmt = DB::Prepare($this->sql);
        $data = $stmt->execute($this->bindings);
        
        if ($this->type == 'read') {
            $data = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                
                // unserialize fails on empty arrays - help it out a bit
                if($row == 'a:0:{}')
                    $row = array();
                // unserialize data if need be
                if (is_string($row) && @unserialize($row)) $row = unserialize($row);
                
                $data[] = $row;            
            }
            if (count($data) == 1) $data = $data[0];
        }
        
        return $data;
    }
    
    private function clean_sql() {        
        $this->sql = str_replace('[FIELDS]', '*', $this->sql);        
        $this->sql = str_replace(array('[WHERE]','[ORDERBY]','[LIMIT]'), '', $this->sql);
        $this->sql = trim($this->sql);
        $this->sql.= ';';
    }
}