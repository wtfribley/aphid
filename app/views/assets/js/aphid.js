/**
 *	Forms - relies on HTML5 browser functionality.
 */

$.fn.aphidForms = function() {
		
	/*
	*	Validation
	*/

	// Required Fields
	$requireds = $('input[required]');
	
	if ($requireds.length > 0) {
		$('[type="submit"]').attr('disabled',true);
	}
	
	$requireds.on('keyup',function() {
		$invalid_requireds = $('input[required]:invalid');
		if ($invalid_requireds.length == 0) {
			$('[type="submit"]').removeAttr('disabled');
		}
		else {
			$('[type="submit"]').attr('disabled',true);	
		}
	});
	
};

$(document).aphidForms();