<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Response {

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

	/**
	 *	Construct a new Response given a status code, data to be sent to the client, and
	 *	the format which that data should take (only html and json are currently supported).
	 */
	public function __construct($code, $data = array(), $format = 'json', $template = null) {

		// redirects are handled up front - the only thing required is a location.
		if ($code == 303) {
			// the redirect location can be passed as either a string or in an array
			//	with the key 'redirect' (handy if coming from a query string, for example)
			if (is_array($data)) $data = $data['redirect'];
			header('Location: ' . $data);
			exit(0);
		}

		// we can set the response to simply go to the log file.
		if (Config::get('env') == 'console') {
			$response = print_r($data, true);
			Log::write($code . ' ' . $this->http_status_code_map[$code], $response);
			exit(0);
		}

		// set the status header
		header('HTTP/1.1 ' . $code . ' ' . $this->http_status_code_map[$code]);

		// set the content type header
		header('Content-type: ' . $this->content_type_map[$format]);

		// format the response body.
		if ($format == 'html') {

			// an html-formatted response must have a template.
			if (is_null($template)) {
				new Response(500, array(
					'error'	=> 'Template Not Specified'
				), 'html', '500');
			}

			// check for the template in the theme folder.
			if (file_exists(THEME . $template . '.php')) {
				$path = THEME . $template . '.php';
			}
			//	check the default path as a backup.
			else if (file_exists(PATH . '/app/views/' . $template . '.php')) {
				$path = PATH . '/app/views/' . $template . '.php';
			}
			//	respond with a 404 if it can't be found.
			else { 
				new Response(404, array(
					'error' => THEME . $template . '.php'
				), 'html', '404');
			}

			// @todo: figure out templating functions.
			ob_start();
				include $path;
			$data = ob_get_clean();
		}
		else if ($format == 'json') {
			$data = json_encode($data);
		}

		// send the body!
		echo $data;
		exit(0);
	}
}