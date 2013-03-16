<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Model {

	public $data = array();

	public $metadata = array();

	public function __construct($name) {

		// get model metadata - groups that have permission, relationships, templates.
		$query = new Query('read', array(
			'table'	=>	'models',
			'where'	=>	array('name',$name)
		));
		$this->metadata = $query->execute();

		// explode permissions
		$this->metadata['create'] = explode(',', $this->metadata['create']);
		$this->metadata['read']   = explode(',', $this->metadata['read']);
		$this->metadata['update'] = explode(',', $this->metadata['update']);
		$this->metadata['delete'] = explode(',', $this->metadata['delete']);

		// explode relations
		$this->metadata['oo'] = explode(',', $this->metadata['oo']);
		$this->metadata['om'] = explode(',', $this->metadata['om']);
		$this->metadata['mo'] = explode(',', $this->metadata['mo']);
		$this->metadata['mm'] = explode(',', $this->metadata['mm']);
	}

	public function create($data) {

		// get permission.
		if ( ! $this->authorize('create')) return false;

		$query = new Query('create', array(
			'table'	=>	$this->metadata['name'],
			'data'	=>	$data
		));
		
		if ($query->execute()) {
			if (!isset($data['id'])) $data['id'] = DB::lastId();
			$this->data = $data;
			return true;
		}
		else {
			$this->data['error'] = 'create query failed';
			$this->data['query'] = $query;
			return false;
		}
	}

	public function read($options) {

		// get permission.
		if ( ! $this->authorize('read')) return false;

		// extract and then unset the 'getRelations' option (if present).
		if (isset($options['getRelations'])) {
			$relations = $options['getRelations'];
			unset($options['getRelations']);
		}

		// add model name to options
		$options['table'] = $this->metadata['name'];

		$query = new Query('read', $options);
		$this->data = $query->execute();

		if ($this->data === false) {
			$this->data = array(
				'error' => 'read query failed',
				'query' => $query
			);
			return false;
		}
		else {
			// get all related models... yes, as mentioned, this violates the n+1 query rule. Deal with it.
			if (isset($relations)) $this->getRelations($relations);
			return true;
		}
	}

	public function update($data, $id) {

		// get permission.
		if ( ! $this->authorize('update')) return false;
		
		$query = new Query('update', array(
			'table'	=>	$this->metadata['name'],
			'data'	=>	$data,
			'where'	=>	$id	
		));

		// update the model's data
		if (!isset($data['id'])) $data['id'] = $id;
		$this->data = array_merge($this->data, $data);

		if ($query->execute()) {

			// get the full updated row
			$query = new Query('read', array(
				'table'	=>	$this->metadata['name'],
				'where'	=>	$data['id'],
				'limit'	=>	1
			));
			$data = $query->execute();
			
			if ($data === false) {
				$this->data['error'] = 'retrieving updated row query failed';
				$this->data['query'] = $query;
				return false;
			}
			else {
				$this->data = $data;
				return true;
			}
		}
		else {
			$this->data['error'] = 'update query failed';
			$this->data['query'] = $query;
			return false;
		}
	}

	public function delete($id) {

		// get permission.
		if ( ! $this->authorize('delete')) return false;

		$query = new Query('delete', array(
			'table'	=>	$this->metadata['name'],
			'where'	=>	$id	
		));

		if ($query->execute()) {
			$this->data = array();
			return true;
		}
		else {
			$this->data['error'] = 'delete query failed';
			$this->data['query'] = $query;
			return false;
		}
	}

	public function getRelations($names) {

		// Get either a limited array of relations' names, all relations' names, or a single relation's name.
		if (is_array($names)) {
			$oo = array_intersect($this->metadata['oo'], $names);
			$om = array_intersect($this->metadata['om'], $names);
			$mo = array_intersect($this->metadata['mo'], $names);
			$mm = array_intersect($this->metadata['mm'], $names);
		}
		else if ($names == 'all') {
			$oo = $this->metadata['oo'];
			$om = $this->metadata['om'];
			$mo = $this->metadata['mo'];
			$mm = $this->metadata['mm'];
		}
		else {
			(in_array($names, $this->metadata['oo'])) ? $oo = array($names) : $oo = array();
			(in_array($names, $this->metadata['om'])) ? $om = array($names) : $om = array();
			(in_array($names, $this->metadata['mo'])) ? $mo = array($names) : $mo = array();
			(in_array($names, $this->metadata['mm'])) ? $mm = array($names) : $mm = array();
		}

		// iterate through returned rows, getting related rows - again, here's the n+1 problem. Again, deal with it.
		if ( ! is_array($this->data)) $this->data = array($this->data); // possibly unneccesary if I change Query.
		foreach ($this->data as &$row) {

			// in one-to-one relationships, the two models' ids match.
			foreach ($oo as $relation) {
				$query = new Query('read', array(
					'table'	=>	$relation,
					'where'	=>	$row['id']
				));
				$row[$relation] = $query->execute();
			}

			// in one-to-many relationships, the 'many' model has a field called 'one_id' that matches the id field on the 'one' model. 
			foreach ($om as $relation) {
				$query = new Query('read', array(
					'table'	=>	$relation,
					'where'	=>	array($this->metadata['name'] . '_id', $row['id'])
				));
				$row[$relation] = $query->execute();
			}

			// in many-to-one relationships, the 'one' model's id matches the 'one_id' field on the 'many' model.
			foreach ($mo as $relation) {
				$query = new Query('read', array(
					'table'	=>	$relation,
					'where'	=>	$relation . '_id'
				));
				$row[$relation] = $query->execute();
			}

			// in many-to-many relationships, the 'parent' model's id matches the xref.parent_id field on the xref table.
			foreach ($mm as $relation) {
				// the cross-reference table is named by the two corresponding tables, sorted alphabetically, with an underscore between them.
				$xref = array($this->metadata['name'], $relation);
                sort($xref);
                $xref = implode('_',$xref);

				$query = new Query('read', array(
					'table'	=>	$relation,
					'join'	=>	array($xref, $relation . '_id'),
					'where'	=>	array($xref . '.' . $this->metadata['name'] . '_id', $row['id'])
				));
				$row[$relation] = $query->execute();
			}
		}
	}

	public function getTemplate($respond_with_error = true) {

		// default behavior (i.e. if there are no results) is to return the plural tempalte.
		(count($this->data) == 1) ? $tmpl = $this->metadata['s_template'] : $tmpl = $this->metadata['pl_template'];

		if ($respond_with_error && $tmpl == '') {
			$response = new Response(404, array(
				'error'	=>	'No template file found for ' . $this->metadata['name']
			), 'html', '404');
		}
		else return $tmpl;
	}

	private function authorize($action) {

		$user_group_ids = User::getGroupIds();

		// if an intersection of the user group ids and the model's list of allowed group ids
		//	for this action produces an empty array, it means we DO NOT have permission.
		if (empty(array_intersect($this->metadata[$action], $user_group_ids))) {
			$this->data['error'] = 401;
			return false;
		}
		else return true;
	}
}

