<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class authenticate {

	public $data;

	public function __construct($request, $data) {

	    // run user login, returning true on success, an array with error info on failure.
		$auth_status = $request->user->login($request->data['username'], $request->data['password']);

		if ($auth_status === true) {
			
			// json returns a success notice.
			if ($request->format == 'json') {
				$response = new Response(200, array(
					'authentication' => 'success',
					'csrf' => $data['csrf']
				));
			}
			// html requests - because behavioral decisions are made here, on the server, rather
			//	than by javascript on the client - are, by their nature, non-generic. Here I've coded
			//	some basic functionality that is meant to be altered on a case-by-case basis.
			else if ($request->format == 'html') {
				if (isset($request->data['redirect'])) {
					$redirect = $request->data['redirect'];

					if ($redirect == 'group') {
						$redirect =	$request->user->groups[0]['name']; 
					}

					$response = new Response(303, '/'.$redirect);
				}
				else {
					$response = new Response(303, '/');
				}
			}	
		}
		else {
			$response = new Response(401, $auth_status, $this->format, '401');
		}
	}

	private function go_to_index() {
		header('Location: /');
	    exit(0);
	}

	private function go_to_group($user) {
		header('Location: /' . $user->group());
	    exit(0);
	}

	private function go_to_referrer() {
		header('Location: /' . Session::get('referrer'));
	    exit(0);
	}

	private function go_to_admin() {
		header('Location: /admin');
	    exit(0);
	}
}