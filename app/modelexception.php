<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class ModelException extends Exception {


	public function __contruct($model) {

		(isset($model['error_message'])) ? $message = $model['error_message'] : $message = 'Model Exception';

		(isset($model['error_code'])) ? $code = $model['error_code'] : $code = 500;

		if (isset($model['query'])) {
			$query = print_r($model['query'], true);

			$message.= "\n" . $query;
		}

		parent::__contruct($message, $code);
	}
}