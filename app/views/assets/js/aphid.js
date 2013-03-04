/**============================
==		Aphid Main App	
============================**/

(function(){

	// save reference to window
	var root = this;

	var Aphid;
	Aphid = root.Aphid = {};

	// jQuery owns the $
	Aphid.$ = root.jQuery;

}).call(this);


/**============================
==		Aphid jQuery Plugins	
============================**/


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

	/*
	*	AJAX
	*/

	$ajax_forms = $('form[data-ajax-action]');

	$ajax_forms.on('submit', function(e) {
		e.preventDefault();
		
		var data = $(this).aphidSerializeForm();

		$.ajax({
			type: 'POST',
			url: $(this).attr('data-ajax-action'),
			dataType: 'json',
			data: data,
			success: function(response) {
				console.log(response);
			}
		});

		// some forms clear on submit
		if ($(this).is('[data-aphid-clear-on-submit]'))
			$(this).find('input, textarea').val('').trigger('keyup');
	});
};

$.fn.aphidSerializeForm = function() {
	var o = {};
    var a = this.serializeArray();

    // handle comma lists
	$(this).find('[data-aphid-comma-list]').each(function(i,el) {
		var arr = $(el).val().split(',');
		o[$(el).attr('name')] = arr;
	});

    $.each(a, function() {
        if (o[this.name] === undefined) {
            o[this.name] = this.value || '';
        }
    });
    return o;
}

$(document).aphidForms();

/**
 *	Auto vertical centering... this is totally janky right now. (i.e. a quick fix for a specific thing)
 */
$.fn.aphidVerticalCenter = function(parent_el) {
	var this_height = $(this).height(),
		parent_height = (parent_el == 'undefined') ? $(this).parent().height() : parent_el.height(),
		margin = (parent_height-this_height)/4;
		
	$(this).css('margin-top',margin);
}

$('#login_form').aphidVerticalCenter($('.wrapper'));