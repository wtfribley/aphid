<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Query {

	/**
	 *	Holds the SQL string used to make the query.
	 */
	public $sql;

	/**
	 *	The Tabel (i.e. Model)
	 */
	private $table;

	/**
	 *	Holds the Fields string.
	 *
	 *	for selects: 'table.column1, table.column2, table.column3'
	 *  for inserts, updates: 'SET column1=?, column2=?, column3=?'
	 */
	private $fields = '';

	/**
	 *	Holds the Values array - used as bindings when executing the PDOStatement.
	 */
	private $values = array();

	/**
	 *	Holds the Join string.
	 */
	private $join = '';

	/**
	 *	Holds the Where string OR an array of Where strings to be imploded using 'AND'.
	 *
	 *	note: values to search for are replaced with '?' to be bound to when
	 *		  executing the PDOStatement.
	 */
	private $where = '';

	/**
	 *	Holds the Search value(s) - used as a bindings when executing the PDOStatement.
	 */
	private $search = array();

	/**
	 *	ORDER BY clause.
	 */
	private $order = '';

	/**
	 *	LIMIT clause.
	 */
	private $limit = '';

	private $crud_map = array(
		'create'	=>	'INSERT',
		'read'		=>	'SELECT',
		'update'	=>	'UPDATE',
		'delete'	=>	'DELETE'
	);


	public function __construct($crud, $options = null) {

		// model is synonmous with table...
		if (isset($options['model']) && ! isset($options['table'])) {
			$options['table'] = $options['model'];
			unset($options['model']);
		}

		// and 'table' is the only mandatory option
		if ( ! isset($options['table'])) throw new Exception('Query must be given $options[\'table\']');
		$this->table = $options['table'];
		unset($options['table']);

		// validate the crud.
		if ( ! array_key_exists($crud, $this->crud_map)) throw new Exception('Query recieved invalid CRUD action.');
		$this->sql = $this->crud_map[$crud] . ' ';

		/**
		 *	The (various) 'where' options are shared by read, update, and delete.
		 *	so we'll sort them out here... (notice the hierarchy)
		 */

		// transpose 'id' and 'name' to where.
		if (isset($options['id'])) $options['where'] = array('id', $options['id']);
		else if (isset($options['name'])) $options['where'] = array('name', $options['name']);

		// format and add the where option.
		if (isset($options['where']) && $options['where'] != 'all') {
			$this->add_where($options['where']);
		}

		// format and add any 'and' options
		if (isset($options['and'])) {

			// to pass multiple 'and' statements, they must be in a sub-array with the key: 'and'
			//	this is only so we can test for that case here.
			if (isset($options['and']['and'])) {
				foreach ($options['and']['and'] as $and) {
					$this->add_where($and);
				}
			}
			else {
				$this->add_where($options['and']);
			}
		}

		// all other behavior depends on the crud action.
		$this->$crud($options);
	}

	//--------------------
	//	CRUD Methods
	//--------------------

	private function create($options) {

		// the 'data' options is mandatory.
		if ( ! isset($options['data']) || $options['data'] == '') throw new Exception('Create Query did not recieve data.');

		// format fields and add values to $this->values.
		$this->add_fields_values($options['data']);

		// build sql string.
		$this->sql.= 'INTO ' . $this->table . ' ' . $this->fields . ';';
	}

	/**
	 *	Formats a SELECT query (i.e. the Big Kahuna)
 	 */
	private function read($options) {

		// build the primary fields string.
		if ( ! isset($options['fields']) || $options['fields'] == 'all' || $options['fields'] == '*') {
			$this->fields = $this->table . '.*';
		}

		else if (is_string($options['fields'])) {
			$this->fields = $this->table . '.' . $options['fields'];
		}

		else if (is_array($options['fields'])) {
			$this->fields = '';

			foreach ($options['fields'] as $field) {
				$this->fields.= $this->table . '.' . $field . ',';
			}
			
			// clean up the trailing comma.
			$this->fields = rtrim($this->fields,',');
		}
		
		// build the relatives' fields string.
		if (isset($options['rel_fields'])) {

			// convert to array.
			if (is_string($options['rel_fields'])) $options['rel_fields'] = array($options['rel_fields']);

			
			if (is_array($options['rel_fields'])) {
				$rel_fields = '';

				foreach ($options['rel_fields'] as $tablefield) {
					$field = explode('.', $tablefield);
					$rel_fields.= $tablefield . ' AS ' . $field[0] . '_' . $field[1] . ',';
				}

				// clean up the trailing comma.
				$rel_fields = rtrim($rel_fields,',');
			}
			else throw new Exception('Read Query recieved invalid $options[\'rel_fields\']');

			// add to primary table fields.
			$this->fields = $this->fields . ',' . $rel_fields;
		}

		// add any joins - note: joins must be given as straight sql.
		if (isset($options['join']) && is_string($options['join'])) $this->join = trim($options['join']) . ' ';

		// add order by
		if (isset($options['order'])) {
			// 'desc' is the default order.
			if (is_string($options['order'])) $options['order'] = array($options['order'],'desc');

			$this->order = 'ORDER BY ' . $options['order'][0] . ' ' . strtoupper($options['order'][1]);
		}

		// related_to - note that this completely REPLACES the where clause.
		//				and the second element must be the FULL SQL of the subquery.
		if (isset($options['related_to'])) {
			$this->where = 'WHERE ' . $this->table . '.' . $options['related_to'][0] . ' IN ';
			$this->where.= '(' . $options['related_to'][1] . ')';
		}

		// add limit / offset / page
		$per_page = Config::get('settings.per_page');

		if (isset($options['limit'])) {
			$this->add_limit($options['limit']);
		}
		// we'll default per_page to 10
		else if (isset($options['page'])) {
			if ($per_page === false) $per_page = 10;

			$this->add_limit(array(($options['page']*$per_page)-1,$per_page));
		}
		else if ($per_page != '') {
			$this->add_limit($per_page);
		}

		// build sql string.
		$this->sql.= $this->fields . ' FROM ' . $this->table . ' ';
		$this->sql.= $this->join;
		$this->sql.= $this->where;

		if ($this->order != '') $this->sql.= ' ' . $this->order;
		if ($this->limit != '') $this->sql.= ' ' . $this->limit;

		$this->sql.= ';';

	}

	private function update($options) {

		// the 'data' and 'where' options are mandatory.
		// 	note that, although made mandatory here, $options['where'] is handled in the constructor.
		if ( ! isset($options['data'])) throw new Exception('Update Query did not recieve data.');
		if ( ! isset($options['where'])) throw new Exception('Update Query did not recieve $options[\'where\'].');

		// format fields and add values to $this->values.
		$this->add_fields_values($options['data']);

		// build sql string.
		$this->sql.= $this->table . ' ' . $this->fields . ';';
	}

	private function delete($options) {

		// the 'where' options is mandatory.
		// 	note that, although made mandatory here, $options['where'] is handled in the constructor.
		if ( ! isset($options['where'])) throw new Exception('Delete Query did not recieve $options[\'where\'].');

		// add limit
		if (isset($options['limit'])) {
			$this->add_limit($options['limit']);
		}

		// build sql string.
		$this->sql.= 'FROM ' . $this->table . ' ' . $this->where;

		if ($this->limit != '') $this->sql.= ' ' . $this->limit;

		$this->sql.= ';';
	}

	//--------------------
	//	Helper Methods
	//--------------------

	private function add_fields_values($fields_values) {

		$this->fields = 'SET ';
		foreach ($fields_values as $field=>$value) {
			$this->fields.= $field . '=?,';
			$this->values[] = $value;
		}

		// clean up the trailing comma.
		$this->fields = rtrim($this->fields,',');
	}

	private function add_where($where, $table = null) {

		// handle passing straight sql
		if ($where[0] == 'sql') {
			if ($this->where === '') $this->where = 'WHERE ' . $where[1];
			else $this->where.= ' AND ' . $where[1];
		}
		else {

			if (is_null($table)) $table = $this->table;

			// an integer means we're searching against 'id'
			if (is_int($where)) $where = array('id', '=', $where);

			// a string means we're searching against 'name'
			if (is_string($where)) $where = array('name', '=', $where);

			// add the column name we're searching in to $this->where
			if ($this->where === '') {
				$this->where = 'WHERE ' . $table . '.' . $where[0];
			}
			else {
				$this->where.= ' AND ' . $table . '.' . $where[0];
			}

			// two-element syntax - just convert to 3 elements.
			if (count($where) == 2) {

				// an IN statement.
				if (is_array($where[1])) $where = array($where[0],'in',$where[1]);

				// default is '='
				else $where = array($where[0],'=',$where[1]);
			}

			// three-element syntax
			if (count($where) == 3) {

				// add operator (i.e. the middle element)
				if (strtolower($where[1]) == 'in') $where[1] = ' IN (';
				$this->where.= $where[1];

				if (is_array($where[2])) {
					// add placeholders (?) and bindings.
					foreach ($where[2] as $w) {
						$this->where.= '?,';
						$this->search[] = $w;
					}
					$this->where = rtrim(',', $this->where) . ')';	
				}
				else {
					$this->where.= '?';
					$this->search[] = $where[2];
				}
			}
			else throw new Exception('Query::add_where() recieved an invalid argument.');
		}
	}

	private function add_limit($limit) {
		$this->limit = 'LIMIT ';

		// handle possible offset.
		if (is_array($options['limit'])) $this->limit.= $options['limit'][0] . ',' . $options['limit'][1];
		else $this->limit.= $options['limit'];
	}

	//-------------------------
	//	Build, Execute, Format
	//-------------------------

	public function execute() {

		// prepare the query
		$stmt = DB::Prepare($this->sql);

		// merge bindings - note that order DOES matter here.
		//					UPDATE will contain values THEN a search term(s).
		$bindings = array_merge($this->values, $this->search);

		// execute!
		$stmt->execute($bindings);

		// write-type requests simply return the number of rows affected.
		if (in_array($this->sql[0], array('I','U','D'))) {
			$data = $stmt->rowCount();
		}
		else {
			// format the returning data.
			$data = array();
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

				foreach($row as $key => $value) {

					// unserialize fails on empty arrays - help it out a bit
					if($value == 'a:0:{}') $row[$key] = array();

					// decode json (into assoc array) or unserialize data if need be
					if (is_string($value) && @json_decode($value)) $row[$key] = json_decode($value, true);
					else if (is_string($value) && @unserialize($value)) $row[$key] = unserialize($value);
				}

				// add formatted row to data.
				$data[] = $row;
			}

			// if we're only returning a single row, let's not have a double array.
			if (count($data) == 1) {
				$data = $data[0];
				
				// single row and single field? lets not return an array at all!
				if (count($data) == 1) {
					$data = array_values($data);
					$data = $data[0];
				}
			}
		}

		// all done!
		return $data;
	}
}