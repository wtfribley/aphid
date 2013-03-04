<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class logout {

	public function __construct($user, $request) {
		// log the user out
		$user->logout();

		// handle redirects (client can pass a redirect option)
        if ($request->options('redirect')) {
            $redirect = ($request->options('redirect') == 'index') ? '/' : '/' . $request->options('redirect');
        }
        else { $redirect = Session::get('referrer'); }

	    header('Location: ' . $redirect);
	    exit(0);
	}
}