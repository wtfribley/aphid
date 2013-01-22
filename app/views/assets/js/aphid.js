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

/**
 *	Auto vertical centering... this is totally janky right now. (i.e. a quick fix for a specific thing)
 */
$.fn.aphidVerticalCenter = function(parent_el) {
	var this_height = $(this).height(),
		parent_height = (parent_el == 'undefined') ? $(this).parent().height() : parent_el.height(),
		margin = (parent_height-this_height)/4;
		
	$(this).css('margin-top',margin);
		
	console.log('this_height: '+this_height);
	console.log('parent_height: '+parent_height);
}

$('#login_form').aphidVerticalCenter($('.wrapper'));