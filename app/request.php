<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Request {

	/**
	 *	Essentially $_SESSION.
	 */
	public $session;

	/**
	 *	The format desired by the client.
	 */
	public $format;

	/**
	 *	The URI Object.
	 */
	public $uri;

	/**
	 *	The method (or verb) of the request.
	 */
	public $method;

	/**
	 *	The model to be acted upon.
	 */
	public $model;

	/**
	 *	An array of data passed with the request.
	 */
	public $data;

	/**
	 *	The User Object.
	 */
	public $user;

	private $crud_map = array(
        'POST' => 'create',
        'GET' => 'read',
        'PUT' => 'update',
        'DELETE' => 'delete'
    );

    /**
     *	Constructing a new Request gathers all incoming data - from the session,
     *	from the client's HTTP request, and from the associated user (if there is one).
	 */
	public function __construct() {

		// get Session data
		$this->session = Session::get();

		// get Response format (json, unless html is explicitly asked for)
		(strpos($_SERVER['HTTP_ACCEPT'], 'html')) ? $this->format = 'html' : $this->format = 'json';

		// get URI data
		$this->uri = new URI();

		// get Request method
		$this->method = $this->crud_map[$_SERVER['REQUEST_METHOD']];

		// get Model
		$this->model = $this->uri->get('model', 'index');

		// get Request body
		//		@todo: combine this switch into a simpler statement that uses php://input for all methods
		//				and can tell if data is url encoded (parse_str) or json.
		switch($this->method) {
			case 'create':
				$this->data = $_POST;
				break;
			case 'read':
				$this->data = $_GET;
				break;
			case 'update':
				parse_str(file_get_contents('php://input'), $_put);
				$this->data = $_put;
				break;
			case 'delete':
				parse_str(file_get_contents('php://input'), $_delete);
				$this->data = $_delete;
				break;
		}

		// get User data
		$this->user = new User(Session::get('user_id',0));
	}

	public function get($field = null, $default = false) {
		if (is_null($field)) return $this->data;

		if (isset($this->data[$field])) return $this->data[$field];
		else return $default;
	}

	/**
	 *	Run this function to authorize the request.
	 *
	 *	If the script is still executing after this function, the
	 *	Request is authorized.
	 */
	public function authorize() {

		// the anonymous user's id is 0 - user has not logged in.
		if ($this->user->get('id') === 0) {

			// does the user have permission to perform the desired action on the requested model?
			if ( ! $this->user->has_permission($this->model, $this->method)) {

				// no. does the user have permission to be redirected to the login?
				if ($this->user->has_permission($this->model, 'login')) {
					$response = new Response(303, Config::get('base_uri','/') . 'login');
				}
				// no. respond with 401.
				else {
					$response = new Response(401, array(
						'error'	=>	'You do not have permission to do that.'
					), $this->format, '401');
				}
			}
		}
		// the user's id is something other than 0 - the user has logged in.
		else {
			// does the user have permission to perform the desired action on the requested model?
			if ( ! $this->user->has_permission($this->uri->get('model'), $this->method)) {
				// no. respond with 403.
				$response = new Response(403, array(
					'error'	=>	'You do not belong to a group that has permission to do that.'
				), $this->format, '403');
			}
		}

		// if we've made it this far, the user has the proper permissions.
		//	now we check for CSRF on any request that may alter the database.
		if (in_array($this->method, array('create','update','delete'))) {

			// the client must pass a CSRF token.
			$client_token = $this->data['csrf'] || false;

			// which must match the token stored in the session.
			$session_token = $this->session['csrf'] || false;

			// no client token or tokens that don't match generates an error.
			if ($client_token === false || $client_token !== $session_token) {
				$response = new Response(401, array(
					'error'	=>	'Cross Site Request Forgery'
				), $this->format, '401');
			}
		}
	}
}