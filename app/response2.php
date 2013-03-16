<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Response {

	public $headers = array();

	public $body;

	public $template = null;

	private $http_status_code_map = array(
		200	=>	'OK',
		201	=>	'Created', 
		202	=>	'Accepted', 		// Updated - Aphid uses this as the code for a successful update.
		204 =>	'No Content', 		// Deleted - Aphid uses this as the code for a successful deletion.
		303 =>	'See Other',		// Redirected - Aphid uses this as the code for a redirection. 
		401 =>	'Unauthorized',
		403	=>	'Forbidden',
		404	=>	'Not Found',
		500 =>	'Server Error'
	);

	private $content_type_map = array(
		'json'	=>	'application/json',
		'html'	=>	'text/html'
	);


	public function __construct($code, $data = '', $format = 'json', $template = null) {

		// generate CSRF Token for security.
		$csrf = Authentication::getToken();
		Session::set('csrf',$csrf);

		// status code
		$this->headers[] = 'HTTP/1.1 ' . $code . ' ' . $this->http_status_code_map[$code];

		// data (i.e. response body)
		$this->body = array(
			'data'	=>	$data,
			'user'	=>	User::$data,
			'csrf'	=>	$csrf
		);

		// format
		$this->headers[] = 'Content-type: ' . $this->content_type_map[$format];

		// template
		if ($format == 'html') {

			if ( ! is_string($template)) throw new Exception('Response format is HTML - must provide a template (and in proper form).');

			// check for the template in the theme folder.
			if (file_exists(THEME . $template . '.php')) {
				$this->template = THEME . $template . '.php';
			}
			//	check the default path as a backup.
			else if (file_exists(PATH . '/app/views/' . $template . '.php')) {
				$this->template = PATH . '/app/views/' . $template . '.php';
			}
			//	send a 404 response if it can't be found.
			else { 
				$response = new Response(404, array('template'=>THEME.$template.'.php'), 'html', '404');
				$response->send();
			}
		}

		// code 303 (redirect) replaces the format header.
		if ($code == 303) {
			$this->headers[1] = 'Location: ' . $data;

			// go now!
			$this->send();
		}
	}

	public function send($json_to_file = false) {

		// no matter the format, json can be saved to a file in the theme folder.
		//	this can be useful for bootstrapping certain Javascript apps.
		if (is_string($json_to_file)) {
			
			$path = THEME . 'assets/json/' . $json_to_file . '.js';
			$contents = json_encode($this->body);
			file_put_contents($path, 'Aphid_JSON = ' . $contents . ';');
		}

		// we have a template, which means we should respond with html.
		if (is_string($this->template)) {

			// @todo: figure out how best to implement templating functions.
			ob_start();
				include $this->template;
			$this->body = ob_get_clean();
		}
		// a non-string template (i.e. null) means we should respond with json.
		else {
			// prevent encoding twice.
			(isset($contents)) ? $this->body = $contents : $this->body = json_encode($this->body);
		}

		// check for the console environment
		if (Config::get('settings.env') == 'console') {
			Log::write($this->headers[0], $this->body);
		}
		// normal operation.
		else {
			// send headers.
			foreach ($this->headers as $header) {
				header($header);
			}
			// send body.
			echo $this->body;
		}
	}

	public function get_body() {
		return $this->body;
	}

	public function get_headers() {
		return $this->headers;
	}
}