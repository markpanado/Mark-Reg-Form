/*
	JS file for Mark Registration Forms

 */

var ruler = ruler || {};

jQuery(function($){
	// DEPENDS
	$('.mark-reg-form [data-depends]').each(function(){
		this_depends 	= this;
		depends 		= $('.mark-reg-form [name="'+$(this).data('depends')+'"]');
		depends_type 	= depends.prop('type');
		depends_value 	= $(this).data('depends-value');

		var rule_func = true;

		if(depends_type=='radio'){
			rule_func = function(){
				return depends.parent().find(':checked').val()==depends_value;
			};

			// BEHAVIOURS
			depends.each(function(){
				$(this).change(function(){
					$(this_depends).prop('disabled', $(this).val() != depends_value);
				}).change();
			});
		}

		ruler[$(this).prop('name')] = {
			required: {
	  			depends: rule_func
			}
		};
	});

	(MRF = {
		radioCheckBox	: function(){
			$('.mark-reg-form .field-radio, .mark-reg-form .field-checkbox').each(function(){
				var mode = $('label.error[style*="inline"]', this).length?'add':'remove';
				$('.field', this)[mode+'Class']('error');
			});
		},

		radioCheckBoxBehaviour	: function(){
			// RADIO BEHAVIOUR
			$('.mark-reg-form .field-radio input, .mark-reg-form .field-checkbox input').change(function(){
				MRF.radioCheckBox();
			});
		},

		init	: function(){
			MRF.radioCheckBoxBehaviour();
		}
	}).init();

	// LOADER
	$('body').append('<div id="mark-reg-form-mask"></div>');

	// SET DEFAULT VALUES FOR SELECT FIELD
	$('.mark-reg-form select').each(function(){
		$(this)
		// SET SELECTED INDEX
		.val($(this).data('selected-index'))
		// SELECT ELEMENTS BEHAVIOUR
		.bind('change keyup focus',function(){
			$(this).parent().find('.html-display').html((x=$(this).find(":selected").data('html-display'))?x:'');
		}).change();
	});

	// SET DEFAULT VALUE FOR RADIO AND CHECKBOX
	$('.field-radio, .field-checkbox', '.mark-reg-form').find('.field input[data-selected="1"]').parent().parent().each(function(){
		if(!$(this).find('input:checked').length){
			$(this).find('input[data-selected="1"]').prop('checked', true);
		}
	});

	// PREPARATION FOR BROWSER CLOSE
	$('.mark-reg-form input[type="text"]:visible').each(function(){
		// The moment the user typed in the first text field
		// Execute onbeforeunload event
		if($(this).css('visibility')!='hidden'){
			$(this).keyup(function(){
				if(!$('.mark-reg-form').data('fingered')){
					$('.mark-reg-form').data('fingered', 1);
				}
			});

			// THIS WILL MAKE SURE THAT ONLY THE FIRST VISIBLE TEXT FIELD WILL BE AFFECTED
			return false;
		}
	});

	// DATEPICKER
	$('input[type="date"]').datepicker({
		dateFormat		: 'yy-mm-dd',
	});

	// CONFIRM BROWSER CLOSE
	window.onbeforeunload = function (e) {
	    e = e || window.event;

		if($('.mark-reg-form').data('fingered')){
			// For IE and Firefox prior to version 4
		    if (e) {
		        e.returnValue = 'Sure?';
		    }

		    // For Safari
		    return 'Sure?';
		}
	};

	// TRIGGER FORM VALIDATIONS
	$('.validate').validate({rules: ruler});

	// SUBMIT BEHAVIOUR
	$('.mark-reg-form').submit(function(){

		// FOR RADIO
		MRF.radioCheckBox();

		if($(this).valid()){
			$('#mark-reg-form-mask').fadeIn();

			if($(this).data('confirm-submit')){
				if(!(result = confirm("Are you sure?"))){
					$('#mark-reg-form-mask').fadeOut();
				}{
					$(this).data('fingered', 0);
				}

				if(result){
					that = this;
					setTimeout(function(){
						that.submit();
					},600);

					return !result;
				}else{
					return result;
				}
			}
		}
	});
});
