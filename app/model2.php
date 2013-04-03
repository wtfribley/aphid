<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Model {

	public $metadata;

	public $data = array();

	private $options = array();

	public function __construct($action = null, $options = array()) {

		// table is synonmous with model...
		if (isset($options['table'])) {
			if ( ! isset($options['model'])) $options['model'] = $options['table'];
			unset($options['table']);
		}
		// and 'model' is the only mandatory option
		if ( ! isset($options['model'])) throw new Exception('Model must be given $options[\'model\']');

		//-----------------------
		//	METADATA
		//-----------------------

		// get model metadata - permissions, relationships, templates.
		$query = new Query('read', array(
			'table'	=>	'model_meta',
			'name'	=>	$options['model']
		));
		$this->metadata = $query->execute();

		// no results mean we've requested a model that doesn't exist.
		if (empty($this->metadata)) {
			$this->data['error_code'] = 404;
			$this->data['error_message'] = 'Model ' . $options['model'] . ' was not found.';
			
			throw new ModelException($this->data);
		}

		if ($action != 'metadata') {

			//-----------------------
			//	PERMISSIONS
			//-----------------------

			// per-model
			if ( ! isset($options['permit_all']) || $options['permit_all'] !== true) {
				if ( ! $this->authorize($action)) throw new ModelException($this->data);
			}

			// per-row @todo: only works for read queries... figure out how to implement with the rest of crud (well, really only ud).
			if ( ! empty(array_intersect($this->metadata['per_row'], User::get_groups('id'))) ) {
				$options['related_to'] = array('users', User::$data['id']);
			}

			//----------------
			//	TAKE ACTION
			//----------------

			if ( ! is_null($action)) {
				// is action valid?
				if (in_array($action, array('create','read','update','delete'))) {
					
					// run the action, throws ModelException if there's an error.
					$this->$action($options);
				}
				// no.
				else throw new Exception($action . ' is not a supported Model action.');
			}
			// no action yet? store options for later.
			else {
				$this->options = $options;
			}
		}
	}

	/**
	 *	Create
	 */
	public function create($options) {

		// merge stored options.
		$options = array_merge($this->options, $options);

		// 'data' is a mandatory option.
		if ( ! isset($options['data'])) {

			$this->data['error_message'] = 'Create requires $options[\'data\']';
			
			throw new ModelException($this->data);
		}

		// build & run query.
		$query = new Query('create', array(
			'table'	=>	$this->metadata['name'],
			'data'	=>	$options['data']
		));
		
		// test & format results.
		if ($query->execute()) {
			if ( ! isset($options['data']['id'])) $options['data']['id'] = DB::lastId();
			$this->data = $options['data'];
			return true;
		}
		else {
			$this->data['error_message'] = 'create query failed';
			$this->data['query'] = $query;
			throw new ModelException($this->data);
		}
	}

	/**
	 *	Read
	 *
	 *	To get data from related models, we use the following steps:
	 *
	 *		1. Build a query to information_schema that will fetch the
	 *		   column names for all related tables.
	 *		
	 *		2. Use those table names to build one large query that will
	 *		   fetch data from the main model's table AND all related
	 *		   models' tables as well.
	 *
	 *		3. Parse the fetched data into a tree-like structure.
	 */
	public function read($options = array()) {

		// merge stored options.
		$options = array_merge($this->options, $options);

		// for convenience
		$self = $options['table'] = $this->metadata['name'];

		//-------------------------------
		//	1 & 2.a Build Field/As List
		//-------------------------------

		// get all fields of the primary model by default.
		if ( ! isset($options['fields'])) $options['fields'] = 'all';

		// get all fields of related models by default. (remember: rel_fields expects 'table.column' format)
		if ( ! isset($options['rel_fields'])) {
			$options['rel_fields'] = $this->get_related_column_names();
		}

		//---------------------------------------
		//	2.b Build Joins (i.e. Relationships)
		//---------------------------------------

		$joins = array();

		// add children - left join $child on ($self.id = $child.$self_id).
		foreach ($this->metadata['children'] as $child) {
			$joins[] = 'LEFT JOIN ' . $child . ' ON (' . $self . '.id = ' . $child . '.' . $self . '_id)';
		}

		// add parents - left join $parent on ($self.$parent_id = $parent.id).
		foreach ($this->metadata['parents'] as $parent) {
			$joins[] = 'LEFT JOIN ' . $parent . ' ON (' . $self . '.' . $parent . '_id = ' . $parent . '.id)';
		}

		// add siblings -
		//	left join $self_$sibling on ($self.id = $self_$sibling.$self_id)
		//	left join $sibling on ($sibling.id = $self_$sibling.$sibling_id)
		foreach ($this->metadata['siblings'] as $sibling) {
			// create cross-reference table - self_sibling, in alphabetical order.
			$xref = array($self, $sibling);
			sort($xref);
			$xref = implode('_', $xref);

			$ljoin = 'LEFT JOIN ' . $xref . ' ON (' . $self . '.id = ' . $xref . '.' . $self . '_id) ';
			$ljoin.= 'LEFT JOIN ' . $sibling . ' ON (' . $sibling . '.id = ' . $xref . '.' . $sibling . '_id)';

			$joins[] = $ljoin; 
		}

		// glue $joins into a string. note: we're not checking if $options['join'] exists because we don't
		//									want users passing that option - so it gets overwritten here.
		$options['join'] = implode(' ', $joins);

		//------------------
		//	2.c Related To
		//------------------

		if (isset($options['related_to'])) {

			// format for children.
			if (in_array($options['related_to'][0], $this->metadata['children'])) {

				$where = $self . '.id IN (';

				$subquery = new Query('read', array(
					'table'	=>	$options['related_to'][0],
					'fields'=>	$self . '_id',
					'where'	=>	$options['related_to'][1]
				));
			}

			// format for parents.
			else if (in_array($options['related_to'][0], $this->metadata['parents'])) {

				$where = $self . '.' . $options['related_to'][0] . '_id IN (';

				$subquery = new Query('read', array(
					'table'	=>	$options['related_to'][0],
					'fields'=>	'id',
					'where'	=>	$options['related_to'][1]
				));
			}

			// format for siblings.
			else if (in_array($options['related_to'][0], $this->metadata['siblings']) || in_array($options['related_to'][0], $this->metadata['per_row'])) {

				// for readability
				$sibling = $options['related_to'][0];

				$where = $self . '.id IN (';

				// create cross-reference table - self_sibling, in alphabetical order.
				$xref = array($self, $sibling);
				sort($xref);
				$xref = implode('_', $xref);

				$subquery = new Query('read', array(
					'table'	=>	$xref,
					'fields'=>	$self . '_id',
					'join'	=>	'JOIN ' . $sibling . ' ON (' . $xref . '.' . $sibling . '_id = ' . $sibling . '.id)',
					'where'	=>	$options['related_to'][1]
				));
			}
			else throw new Exception($options['related_to'][0] . ' is not related to this model (' . $self . ').');

			// add the subquery's sql.
			$where.= rtrim(';',$subquery->sql) . ')';

			// 'related_to' is not a supported Query option - so we're passing straight SQL as the 'where' option.
			$options['where'] = array('sql',$where);
			unset($options['related_to']);
		}

		//---------------------------------
		//	2.d Run Main Query
		//---------------------------------

		$query = new Query('read', $options);

		// fetch raw results.
		$results = $query->execute();

		if ($results) {

			//--------------------------------------
			//	3. Parse Query Results to Data Tree
			//--------------------------------------

			$relations = array_merge($this->metadata['children'], $this->metadata['parents'], $this->metadata['siblings']);

			if (is_string($results)) {
				$this->data = $results;
				return true;
			}
			// turn a single associate array (i.e. a single row)
			//	into an array for processing.
			if (is_array($results) && ! isset($results[0])) $results = array($results);

			foreach ($results as $row) {

				// first, put each relative in its own branch on this row.
				foreach ($relations as $relative) {
					
					// set up branch
					$row[$relative] = array();

					// look through each field in the row to see if it belongs in this relative's branch.
					foreach($row as $field=>$value) {
						// columns from related tables are in the form: relative_fieldname.
						if (strpos($field, $relative.'_') === 0) {

							if ( ! is_null($value)) {

								// remove the 'relative_' prefix.
								$rel_field = substr($field, strlen($relative.'_'));

								// add the new field=>value pair to this relative's branch.
								$row[$relative][$rel_field] = $value;
							}

							// remove the original, un-branched field.
							unset($row[$field]);
						}
					}
				}

				// next, merge rows with the same id.
				if ( ! isset($this->data[$row['id']])) {
					$this->data[$row['id']] = $row;
				}
				// row has already been added...
				else {

					// we need to iterate through all the relative fields in this row.
					foreach ($relations as $relative) {

						// an associative array on the 'target' indicates we haven't merged anything yet...
						if ( ! isset($this->data[$row['id']][$relative][0]) ) {

							// so we can simply say that if our row isn't equal to the target, we should add it.
							if ($this->data[$row['id']][$relative] != $row[$relative] ) {
								$this->data[$row['id']][$relative] = array($this->data[$row['id']][$relative], $row[$relative]);	
							}

						}
						// a numerical array means that we have to check if our row is already there (with in_array).
						else if ( ! in_array($row[$relative], $this->data[$row['id']][$relative]) ) {
							$this->data[$row['id']][$relative][] = $row[$relative];
						}
					}
				}
			}

			return true;
		}
		// query failed.
		else {
			$this->data['error_message'] = 'read query failed';
			$this->data['query'] = $query;
			throw new ModelException($this->data);
		}
	}

	/**
	 *	Update
	 */
	public function update($options) {

		// merge stored options.
		$options = array_merge($this->options, $options);

		// transpose 'id' and 'name' to where.
		if (isset($options['id'])) $options['where'] = array('id', $options['id']);
		else if (isset($options['name'])) $options['where'] = array('name', $options['name']);

		// 'data' and 'where' are mandatory options.
		if ( ! isset($options['data']) || ! isset($options['where'])) {

			$this->data['error_message'] = 'Update requires $options[\'data\'] and $options[\'where\']';
			
			throw new ModelException($this->data);
		}

		// build & run query
		$query = new Query('update', array(
			'table'	=>	$this->metadata['name'],
			'data'	=>	$options['data'],
			'where'	=>	$options['where']	
		));

		// there's a chance we've updated the model's id - so we have to check for it in $options['data']
		if ( ! isset($options['data']['id'])) $options['data']['id'] = DB::lastId();
		
		// update this model's data - this very well may only be a partial data...
		$this->data = array_merge($this->data, $options['data']);

		// ...so lets get the entire entry.
		if ($query->execute()) {

			$row = new Model($this->metadata['name']);
			$options = array(
				'where'	=>	$options['data']['id'],
				'limit'	=>	1
			);
			
			if ($row->read($options) === false) {

				$this->data['error_message'] = 'retrieving updated entry failed';
				$this->data['query'] = $row->data['query'];

				unset($row);
				return false;
			}
			// we have the updated row's data.
			else {
				$this->data = $row->data;

				unset($row);
				return true;
			}
		}
		else {
			$this->data['error_message'] = 'update query failed';
			$this->data['query'] = $query;
			throw new ModelException($this->data);
		}
	}

	/**
	 *	Delete
	 */
	public function delete($options) {

		// merge stored options.
		$options = array_merge($this->options, $options);

		// 'where' is a mandatory option
		if ( ! isset($options['where'])) {

			$this->data['error_message'] = 'Delete requires $options[\'where\']';
			
			throw new ModelException($this->data);
		}

		// build & run query.
		$query = new Query('delete', array(
			'table'	=>	$this->metadata['name'],
			'where'	=>	$options['where']	
		));

		// test & format results.
		if ($query->execute()) {
			$this->data = array();
			return true;
		}
		else {
			$this->data['error_message'] = 'delete query failed';
			$this->data['query'] = $query;
			throw new ModelException($this->data);
		}
	}

	/**
	 *	Return the proper template (based on number of entries selected).
	 */
	public function get_template() {
		if (is_string($this->data) || count($this->data) > 1) return $this->metadata['s_template'];
		else return $this->metadata['pl_template'];
	}

	/**
	 *	@method: get_related_column_names
	 *
	 *	Queries information_schema to return a list of column names
	 *	formated like so:
	 *
	 *	array('tableA.column1','tableA.column2','tableB.column1','tableB.column2')
	 *
	 *	If there are no relations, returns an empty array.
	 */
	private function get_related_column_names() {

		// we will get column names for all related tables at once
		$relations = array_merge($this->metadata['children'], $this->metadata['parents'], $this->metadata['siblings']);

		if ( ! empty($relations)) {

			$query = new Query('read', array(
				'table'		=>	'information_schema.columns',
				'fields'	=>	array('table_name','column_name'),
				'where'		=>	array('table_name', $relations)
			));
			$columns = $query->execute();

			// parse column names into the form 'table.column'
			$related_columns = array();
			foreach ($columns as $row) {
				$related_columns = $row['table_name'] . '.' . $row['column_name'];
			}
			unset($columns);

			return $related_columns;
		}
		// no relations.
		else return array();
	}

	/**
	 *	Per-Model Authorization.
	 *
	 *	Authorize this model to do the desired action, given the current User's Groups' permissions.
	 *
	 *	@todo: validate relatives - currently this is a MASSIVE security hole.
	 */
	private function authorize($action) {

		// if an intersection of the user's groups' ids and the model's list of allowed group ids
		//	for this action produces an empty array, it means we DO NOT have permission.
		if ( empty(array_intersect($this->metadata[$action], User::get_groups('id'))) ) {
			
			// if the user is in the anonymous group, they cannot be in any others - and it means they haven't logged in.
			($user_groups[0] == 0) ? $error = 401 : $error = 403;
			$this->data['error_code'] = $error;

			return false;
		}
		else return true;
	}
}