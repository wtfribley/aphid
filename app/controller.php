<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Controller {

	public $model;

	public $data = array();

	public $template;

	public function __construct($request) {

		$this->model = $request->model;

		// generate a new CSRF token to be returned to the client.
		//	update the session with this new token.
		$this->data['csrf'] = Authentication::getToken();
        Session::set('csrf',$this->data['csrf']);

        // run a Custom Controller if it exists in the app/controllers/ directory.
        if (file_exists(PATH . 'app/controllers/' . $this->model . '.php')) {
        	$model = $this->model;
            $controller = new $model($request, $this->data);
        }
        // otherwise we'll run the "default" Aphid Controller
        else {
        	$method = $request->method;
        	$this->$method($request);
        }
	}

	/**
	 *	Build and run a Create Query
	 *
	 *	Returns the newly-created row (including the id, if recieved from auto-increment)
	 *	in either json or html with template.
	 */
	private function create($request) {

		$query = new Query('create', array(
			'table'	=>	$this->model,
			'data'	=>	$request->data
		));
		
		// query succeeded
		if ($query->execute()) {

			// if we have not specified an id (i.e. using auto increment), use lastID() to retrieve it.
			if (!isset($request->data['id'])) $request->data['id'] = DB::lastId();

			// build out the data to return to the client...
			//	we return the model we just created.
			$this->data['data'] = $request->data;
			$this->data['user'] = $request->user;

			// response is based on format - html or json.
			//	json is simple...
			if ($request->format == 'json') {

				$response = new Response(201, array(
					'created'	=>	$this->model,
					'data'		=>	$this->data
				));
			}
			// html is (slightly) more complicated...
			else if ($request->format == 'html') {

				// get the template name
				$template = $this->get_template($this->model, 'single');

				$response = new Response(200, $this->data, 'html', $template);
			}
		}
		// query failed
		else {
			$response = new Response(500, array(
				'error'	=>	'query failed',
				'query'	=>	$query
			), $request->format, '500');
		}
	}

	/**
	 *	Build and run a Read Query
	 *
	 *	Format results in either json or html, including the retrieval
	 *	of the appropriate html template if necessary.
	 */
	private function read($request) {

		// the default action is a simple read...
		//	relational queries will alter this.
		$action = 'read';

		// build query options...
		$options = $request->data;

		// add the table, if not already set.
		if (!isset($options['table'])) $options['table'] = $this->model;

		// the where option can come from several places...
		if (isset($options['id'])) $where = $options['id'];
		else if ($id = $request->uri->get(1)) $where = $id;
		else $where = false;

		// add the where option, if not already set.
		if (!isset($options['where'])) $options['where'] = $where;

		/**
		 *	Options for Relational Queries.
		 *
		 *	@todo: possibly redo or rethink how to build relational query
		 *		   options - make this more transparent / more generalized.
		 */

		// 	having: get entries from the model iff they have a
		//			corresponding entry in the 'join' table.
		if (isset($options['having'])) {
            $action = 'having';       
            $options['join'] = $options['having'];
            unset($options['having']);
        }
        // by: get entries from the model iff they have a
        //		corresponding entry in the 'join' table that
        //		matches the 'where' option.
        if (isset($options['join']) && $options['where']) $action = 'by';

        // add: add fields from the 'join' table to the results from the model.
        if (isset($options['add'])) $action = 'add';

        /**
         *	Run the Query
         */
        $query = new Query($action, $options);
        
        // build out the data to return to the client.
        $this->data['data'] = $query->execute();
		$this->data['user'] = $request->user;

		// query succeeded OR delivering HTML
		//	(empty results that still have an associated template may be displayed)
		if ($this->data['data'] || $request->format == 'html') {

			// a template is required for html responses
			if ($request->format == 'html') {

				// the template may be different if we've retrieved more than one row.
				(count($this->data['data']) > 1) ? $type = 'plural' : $type = 'single';

				// retrieve the appropriate template.
				$template = $this->get_template($options['table'], $type);
			}
			else $template = null;

			// generate response!
			$response = new Response(200, $this->data, $request->format, $template);
		}
		// query failed.
		else {
			$response = new Response(500, array(
				'error'	=>	'query failed',
				'query'	=>	$query
			), $request->format, '500');
		}
	}

	/**
	 *	Build and run an Update Query
	 *
	 *	Returns the entire updated row in either json or with html template.
	 */
	private function update($request) {

		// an update query requires a table, data, and a where option...

		// the where option can come from several places...
		if (isset($request->data['id'])) $where = $request->data['id'];
		else if ($id = $request->uri->get(1)) $where = $id;
		else $where = false;

		// add the where option, if not already set.
		$where = $request->data['where'] || $where;

		// build and run the query
		$query = new Query('update', array(
			'table'	=>	$this->model,
			'data'	=>	$request->data,
			'where'	=>	$where	
		));

		// query succeeded.
		if ($query->execute()) {

			// get the full updated row
			$query = new Query('read', array(
				'table'	=>	$this->model,
				'where'	=>	$where,
				'limit'	=>	1
			));

			$this->data['data'] = $query->execute();
			$this->data['user']	= $request->user;

			if ($request->format == 'json') {

				$response = new Response(202, array(
					'updated'	=>	$this->model,
					'data'		=>	$this->data
				));
			}
			else if ($request->format == 'html') {

				// get the template name
				$template = $this->get_template($this->model, 'single');

				$response = new Response(200, $this->data, 'html', $template);
			}
		}
		// query failed.
		else {
			$response = new Response(500, array(
				'error'	=>	'query failed',
				'query'	=>	$query
			), $request->format, '500');
		}
	}

	/**
	 *	Build and run a Delete Query
	 *
	 *	Returns the number of rows deleted.
	 */
	private function delete($request) {

		// a delete query requires a table and a where option...

		// the where option can come from several places...
		if (isset($request->data['id'])) $where = $request->data['id'];
		else if ($id = $request->uri->get(1)) $where = $id;
		else $where = false;

		// add the where option, if not already set.
		$where = $request->data['where'] || $where;

		$query = new Query('delete', array(
			'table'	=>	$this->model,
			'where'	=>	$where
		));

		// query succeeded.
		if ($rows_affected = $query->execute()) {

			if ($request->format == 'json') {

				$this->data['data'] = $rows_affected;

				$response = new Response(204, array(
					'deleted'	=>	$this->model,
					'data'		=>	$this->data
				));
			}
			else if ($request->format == 'html') {

				// html delete is currently unsupported...
				// @todo: figure out how best to support this behavior
				$response = new Response(500, array(
					'error'	=>	'Delete requests using the \'Accept: text/html\' header are currently unsupported'
				), 'html', '500');
			}

		}
		// query failed.
		else {
			$response = new Response(500, array(
				'error'	=>	'query failed',
				'query'	=>	$query
			), $request->format, '500');	
		}
	}

	/**
	 *	Retrieve the appropriate template name or a specified default if
	 *	no template is found.
	 */
	private function get_template($model, $type, $default = false) {
            
        $query = new Query('read',array(
            'table' => 'templates',
            'fields' => $type,
            'where' => array('`table`',$model),
            'groupby' => 'none'
        ));          
        $template = $query->execute();

        if (empty($template)) {
 			if ($default) return $default;
 			else {
 				$response = new Response(404, array(
 					'error'	=>	$type . ' template file for ' . $model . ' not found.'
 				), 'html', '404');
 			}       	
        }
        else return $template;
	}
}