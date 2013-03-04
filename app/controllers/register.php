<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class register {

	public $results;

	public function __construct($user, $request) {
		// Run CSRF authentication
        Authentication::CSRF($request);

        // get submitted user data
        $new_user_data = $request->options('data');
        // register the user
        $user->register($new_user_data);

        // if this request comes via ajax, simply return a success message.
        if ($request->test('format','json')) {
            $this->results['data'] = array('user_registered'=>'success');
        }
        // if this request comes directly from a form submission, lets redirect.
        else if ($request->test('format','html')) {
            header('Location: ' . Session::get('refferer'));
            exit(0);
        }
	}
}