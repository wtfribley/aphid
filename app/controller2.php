<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Controller {

	public $response;

	private $success_code_map = array(
		'create'	=>	201,
		'read'		=>	200,
		'update'	=>	202,
		'delete'	=>	204,
		'not found'	=>	404
	);

	public function __construct($action, $format, $options) {

		// Check for custom controllers.
		if (file_exists(PATH . 'app/controllers/' . $options['model'] . '.php')) {
			$custom_controller = new $options['model']($action, $format, $options);
			$this->response = $custom_controller->response;

			unset($custom_controller);
		}
		// Run Aphid's Default Controller
		else {

			//-------------------------------
			//	Create Model, Handle Errors
			//-------------------------------

			try {
				// instatiate the model.
				$model = new Model($action, $options);

				// no model data means a 404.
				if (empty($model->data)) {
					$action = 'not found';

					// we'll be passing on the model's name to the 404 template.
					$model->data = array('model'=>$options['model']);
					$template = '404';
				}
				else {
					$template = $model->get_template();
				}

				$this->response = new Response($this->success_code_map[$action], $model->data, $format, $template);

				unset($model);

			}
			catch (ModelException $e) {
				$this->response = new Response($e->get_code(), $e->get_message(), $format, $e->get_code('string'));				
			}
		}
	}
}