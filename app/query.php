<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Query {
    
    private $table;
    
    private $fields = '[TABLE].*';
    
    private $add = '';
    
    private $as_field = '';
    
    private $left = '';
    
    private $join = '';
    
    private $on = 'ON ([TABLE].id = [JOIN].[TABLE]_id)';
    
    private $where = array();
    
    private $orderby = '';
    
    private $limit = '';
    
    private $groupby = '';
    
    private $data = array();
    
    private $relationship;
    
    private $sql = '';
    
    private $bindings = array();
    
    private $single_field = false;
 
    private $sql_templates = array(
        'create' => 'INSERT INTO [TABLE] [DATA]',
        'read' => 'SELECT [FIELDS] [AS] FROM [TABLE] [OPTIONS] [GROUPBY]',
        'update' => 'UPDATE [TABLE] [DATA] [WHERE]',
        'delete' => 'DELETE FROM [TABLE] [WHERE]',
        'join' => '[LEFT] JOIN [JOIN] [ON] [WHERE] [ORDERBY] [LIMIT]'
    );
    
    /**
     *  $type: create, read, having, by, add, update, delete.
     *      - having: get all entries from 'table' having an associated entry from 'join.'
     *      requires: join, relationship
     *    
     *      - by: get entries from table, searching join using 'where.'
     *      requires: join, relationship, where
     *    
     *      - add: get entries from table, append column 'add' from 'join' to results, optionally renaming it as 'as.'
     *      requires: join, relationship, add
     *    
     *  $options['table']: the table from which entries will be returned - required! 
     *    
     *  $options['join']: the table used in relational queries (having, by, add).
     *    
     *  $options['relationship']: relationship type (o-m, m-o, m-m) - required if using 'join.'
     *      - o-m: returning results from the 'many' side.
     *      - m-o: returning results from the 'one' side.
     *    
     *  $options['add']: these fields from 'join' will be appended to the results (including 'all' or '*')
     *      - required if $type == add.
     *    
     *  $options['as']: used to rename fields added with $options['add'].
     *    
     *  $options['fields']: a field or an array of fields in 'table' to return - defaults to all fields.
     *    
     *  $options['where']: an array in the form (field,value) OR (field,operator,value) 
     *      - can also be an integer, in which case 'id' is assumed for the field and '=' for the operator.
     *    
     *  $options['orderly']: the field in which to order descending OR an array in the form (field, order).
     *    
     *  $options['limit']: an integer to limit the resulting rows OR an array in the form (limit, offset).
     *    
     *  $options['data']: an array of insertion or update data in the form (key=>value). 
     */

    public function __construct($type, $options = null) {
    
        if (isset($options['table'])) {
            $this->table = $options['table'];
        }
        else throw new Exception('Must specify a table to query.');
            
        if (isset($options['join'])) {            
            if (isset($options['relationship'])) $this->relationship = $options['relationship'];
            else throw new Exception('Must specify relationship type.');
            
            $this->join = $options['join'];
            
            if (isset($options['add'])) $this->add = $options['add'];
                
            if (isset($options['as'])) $this->as_field = 'AS ' . $options['as'];
        }
        
        if (isset($options['fields'])) $this->fields($options['fields']);
        
        if (isset($options['where'])) $this->where($options['where']);
        
        if (isset($options['orderby'])) {
            if (is_array($options['orderby'])) call_user_func_array(array($this, 'orderby'), $options['orderby']);
            else $this->orderby($options['orderby']);
        }
        
        if (isset($options['limit'])) {
            if (is_array($options['limit'])) call_user_func_array(array($this, 'limit'), $options['limit']);
            else $this->limit($options['limit']);
        }
        
        if (isset($options['groupby'])) $this->groupby($options['groupby']);
        else $this->groupby = 'GROUP BY ' . $this->table . '.id';
        
        if (isset($options['data'])) $this->data($options['data']);
        
        $this->$type();
    }
    
    public function fields($fields = null) {
        if ($fields == 'all' || $fields == '*') { }// do nothing (i.e. use default)
        else {
            if (is_array($fields)) {
                $this->fields = '';
                foreach($fields as $field) {
                    $this->fields.= $this->table . '.' . $field . ',';
                }
                $this->fields = rtrim($this->fields,',');
            }
            else if (is_string($fields)) {
                $this->single_field = true;
                $this->fields = $this->table . '.' . $fields;    
            }
        }
    }
    
    public function where($where) {
        if (count($where) == 3) {
            $this->where = array($where[0],$where[1]);
            $this->bindings[] = $where[2];
        }
        else if (count($where) == 2) {
            $this->where = array($where[0],'=');
            $this->bindings[] = $where[1];        
        }
        else if (is_numeric($where)) {
            $this->where = array($this->table . '.id','=');
            $this->bindings[] = $where;    
        }
    }
    
    public function orderby($field, $order = 'DESC') {
        if (strpos($field, '.') === false) $field = $this->table . '.' . $field;
        $this->orderby = 'ORDER BY ' . $field . ' ' . $order;
    }
    
    public function limit($limit, $offset = null) {       
        if (is_null($offset)) $this->limit = 'LIMIT ' . $limit;
        else $this->limit = 'LIMIT ' . $offset . ',' . $limit;        
    }
    
    public function groupby($groupby) {
        if ($groupby == 'none') $this->groupby = '';
        else $this->groupby = 'GROUP BY ' . $this->table . '.' . $groupby;
    }
    
    public function data($data) {
        // serialize arrays    
        foreach($data as $k => $v) {
            if(is_array($v)) $data[$k] = serialize($v);
        }
        
        $this->data = array_keys($data);
        $this->bindings = array_merge(array_values($data),$this->bindings);
    }
    
    private function create() {
        $this->sql = $this->sql_templates['create'];
        
        if (!empty($this->data)) {
            $sql = '(';
                
            for ($i=0;$i<count($this->data);$i++) {
                $sql.= $this->data[$i] . ',';
            }
            $sql = rtrim($sql, ',');
  
            $sql.= ') VALUES (';
            
            for ($i=0;$i<count($this->data);$i++) {
                $sql.= "?,";
            }
            $sql = rtrim($sql, ',');
            
            $this->data = $sql . ')';
        }
        else $this->data = '';
    }
    
    private function read() {
        $this->sql = str_replace('[OPTIONS]','[WHERE] [ORDERBY] [LIMIT]',$this->sql_templates['read']);
    }
    
    private function having() {
        $this->sql = str_replace('[OPTIONS]',$this->sql_templates['join'],$this->sql_templates['read']);
        
        switch ($this->relationship) {
            case 'm-o':
                $this->on = 'ON ([JOIN].id = [TABLE].[JOIN]_id)';
                break;
            case 'm-m':
                $this->join = array($this->table, $this->join);
                implode('_',sort($this->join));
                break;
        }
    }
                                      
    private function by() {
        switch ($this->relationship) {
            case 'o-m':
                $this->read();
                $this->where[0] = '`' . $this->table . '.' . $this->join . '_id`';
                break;
            case 'm-o':
                $this->read();
                break;
            case 'm-m':
                $this->sql = str_replace('[OPTIONS]',$this->sql_templates['join'],$this->sql_templates['read']);
                $this->left = 'LEFT ';
                $reference = array($this->table, $this->join);
                implode('_',sort($reference));
                $this->where[0] = '`' . $reference . '.' . $this->join . '_id`';
                $this->join = $reference;
                break;
        }
    }
                                      
    private function add() {       
        $this->single_field = false;
        
        switch ($this->relationship) {
            case 'o-m':
                $this->fields.= ', ' . $this->group_concat();
                $this->left = 'LEFT ';
                $this->sql = str_replace('[OPTIONS]',$this->sql_templates['join'],$this->sql_templates['read']);
                break;
            case 'm-o':
                $this->fields.= ', ' . $this->concat();
                $this->left = 'LEFT ';
                $this->on = 'ON ([JOIN].id = [TABLE].[JOIN]_id)';
                $this->sql = str_replace('[OPTIONS]',$this->sql_templates['join'],$this->sql_templates['read']);
                break;
            case 'm-m':
                $this->fields.= ', ' . $this->group_concat();
                $reference = array($this->table, $this->join);
                implode('_',sort($reference));
                $ref_join = 'LEFT JOIN ' . $reference . ' ON (' . $this->table . '.id = ' . $reference . '.' . $this->table . '_id) ';
                $this->on = 'ON (' . $this->join . '.id = ' . $reference . '.' . $this->join . '_id)';
                $this->sql = str_replace('[OPTIONS]',$ref_join . $this->sql_templates['join'],$this->sql_templates['read']);
                break;
        }
    }
                                     
    private function update() {
        $this->sql = $this->sql_templates['create'];
        
        if (!empty($this->data)) {
            $sql = 'SET ';
                
            for ($i=0;$i<count($this->data);$i++) {
                $sql.= $this->data[$i] . '=?,';
            }
            $sql = rtrim($sql, ',');
            
            $this->data = $sql;
        }
        else $this->data = '';
    }
                                     
    private function delete() {
        $this->sql = $this->sql_templates['delete'];    
    }
                                     
    public function execute() {
        // prepare and execute the query
        $stmt = DB::Prepare($this->parse_sql());
        $executed = $stmt->execute($this->bindings);
        
        // clean the returning data
        $data = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
            foreach($row as $key => $value) {
                // unserialize fails on empty arrays - help it out a bit
                if($value == 'a:0:{}')
                    $row[$key] = array();
                // decode json (into assoc array) or unserialize data if need be
                if (is_string($value) && @json_decode($value)) $row[$key] = json_decode($value, true);
                else if (is_string($value) && @unserialize($value)) $row[$key] = unserialize($value);
                
                // explode concatenated results
                if (!is_null($value) && $key == substr($this->as_field, 3)) {
                    if (strpos($value,'|') !== false) {
                        $value = explode('|',$value);
                        for ($i=0;$i<count($value);$i++) {
                            if (strpos($value[$i],',') !== false) {
                                $value[$i] = explode(',',$value[$i]);
                                $assoc = array();
                                for ($n=0;$n<count($value[$i]);$n++) {
                                    $assoc[$this->add[$n]] = $value[$i][$n];        
                                }
                                $value[$i] = $assoc;
                            }
                        }
                    }
                    else if (strpos($value,',')) { 
                        $value = explode(',',$value);
                        $assoc = array();
                        for ($i=0;$i<count($value);$i++) {
                            $assoc[$this->add[$i]] = $value[$i];        
                        }
                        $value = $assoc;
                    }
                    $row[$key] = $value;
                }
            }        
            
            $data[] = $row;            
        } 
        
        // if we're only returning a single row, let's not have a double array.
        if (count($data) == 1) {
            $data = $data[0];
            // single row and single field? lets not return an array at all!
            if ($this->single_field == true) $data = array_values($data)[0];
        }
        
        return (empty($data)) ? $executed : $data;
    }
    
    public function parse_sql() {
        $search = array(
            '[TABLE]',
            '[LEFT]',
            '[JOIN]',
            '[FIELDS]',
            '[AS]',
            '[ON]',
            '[WHERE]',
            '[ORDERBY]',
            '[LIMIT]',
            '[GROUPBY]',
            '[DATA]'
        );
        
        // [TABLE] will probably still exist in fields.
        $this->fields = str_replace('[TABLE]',$this->table,$this->fields);
        
        // [TABLE] and [JOIN] will most likely still be in $this->on
        $this->on = str_replace(array('[TABLE]','[JOIN]'),array($this->table,$this->join),$this->on);
        
        // must generate WHERE sql, as it's still an array
        if (!empty($this->where)) $this->where = 'WHERE ' . implode('',$this->where) . '?';
        else $this->where = '';
        
        // data will usually be an empty array - replace with empty string.
        if (empty($this->data)) $this->data = '';
        
        $replace = array(
            $this->table,
            $this->left,
            $this->join,
            $this->fields,
            $this->as_field,
            $this->on,
            $this->where,
            $this->orderby,
            $this->limit,
            $this->groupby,
            $this->data
        );
                
        // do replacement, then strip extra white space
        return trim(preg_replace('/\s\s+/',' ',str_replace($search, $replace, $this->sql))) . ';';
    }
                                     
    private function concat() {
        $concat = "CONCAT_WS(',',";
        
        if ($this->add == 'all' || $this->add == '*') {
            $query = new Query('read',array(
                'table' => 'information_schema.columns',
                'fields' => 'column_name',
                'where' => array('table_name',$this->join)
            ));
            $fields = $query->execute();
            foreach ($fields as $field) {
                $concat.= $this->join . '.' . $field . ',';
            }
            $concat = rtrim($concat,',') . ')';
        }
        else if (is_array($this->add)) {
            foreach ($this->add as $field) {
                $concat.= $this->join . '.' . $field . ',';
            }
            $concat = rtrim($concat,',') . ')';
        }
        else if (is_string($this->add)) {
            $concat = $this->join . '.' . $this->add;
        }
        
        return $concat;
    }
                                     
    private function group_concat() {
        return "GROUP_CONCAT(" . $this->concat() . " SEPARATOR '|')";    
    }
}