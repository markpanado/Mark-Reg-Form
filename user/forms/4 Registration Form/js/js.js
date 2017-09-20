/*
	User JS for Registration Form

 */

ruler = {};

jQuery(function($){
	var form_id = '#mark_reg_form_4';
	var form 	= $(form_id);

	// Early bird rate
	$('.mark-reg-form #rate_type option').each(function(){
		if(until=$(this).data('until')){
			date_today = new Date();
			date_until = new Date(until+'T23:59:59');

			// Disable expired early bird
			if(!(date_today <= date_until)){
				$(this).attr('disabled','disabled');
			}
		}
	});

    // TEMPORARY DISABLE OTHER OPTIONS EARLY BIRD RATE IS STILL APPLICABLE
	// Find index of Early Bird Rate
    	// var ebr = $('.mark-reg-form #rate_type option[data-is-early-bird="1"]');
        //
    	// if(ebr.length){
    	// 	// Get index of  Early Bird Rate then increment by 1
    	// 	// to transform it into nth-child
    	// 	ebr_nth_child = ebr.index()+1;
        //
    	// 	// Check if Early Bird Rate is still active
    	// 	if(!$('.mark-reg-form #rate_type option:nth-child('+ebr_nth_child+')').prop('disabled')){
    	// 		// Then disable the rest after early bird rate
    	// 		for(i=ebr_nth_child+1; i<= $('.mark-reg-form #rate_type option').length; i++){
    	// 			$('.mark-reg-form #rate_type option:nth-child('+i+')').attr('disabled','disabled');
    	// 		}
    	// 	}
    	// }

    var ebr = $('.mark-reg-form #rate_type option[data-is-early-bird="1"]:not([disabled])');

    ebr.each(function(index, item){
        var ebr_item = $(item);
        var loop     = true;

        while(loop){
            ebr_item = ebr_item.next();

            if((ebr_item.length > 0) && (typeof ebr_item.data('is-early-bird') === 'undefined')){
                ebr_item.attr('disabled','disabled');
            }else{
                loop = false;
            }
        }
    });

	// Get all register button in pricing area
	$('#pricing .pricing-sign-up').each(function(index, element){
		// add click event
		$(element).click(function(){
			// change rate type
			// also avoid disabled option
			if($('.mark-reg-form #rate_type').val(index+1).focus().find(":selected").prop('disabled')){
				$('.mark-reg-form #rate_type').val('').change();
			}

			// Go to registration form
			$.scrollTo("#register", 500, {
				easing 	:'easeOutExpo',
				offset	:-$("#header").height(),
				onAfter : function(){
					// Focus on the first text box
					$('#register input[type="text"]')[0].focus();
				},
			});

			return false;
		});
	});

	// Change Register button behaviour in main menu
	$('#menu-item-254 a').unbind('click').click(function(){
		$.scrollTo("#register", 1000, {
			easing 	:'easeOutExpo',
			offset	:-$("#header").height(),
			onAfter : function(){
				if($('.mark-reg-form .required.error').length){
					$('.mark-reg-form .required.error').focus();
				}else{
					// Focus on the first text box
					(tmp=$('#register input[type="text"]')[0])?tmp.focus():'';
				}
			},
		});

		return false;
	});

	$(document).ready(function(){
		// FOCUS ON FIELD WITH ERRORS
		// if($('.mark-reg-form .required.error').length){
		if($('.mark-reg-form .error').length){
			$('#menu-item-254 a').click();
		}

		if($('.mark-reg-form-success').length){
			$('#menu-item-254 a').click();
		}

		$('#dob').
			datepicker('option', 'changeMonth', true).
			datepicker('option', 'changeYear', true).
			datepicker('option', 'yearRange', '-100:-8')
		;
	});

	// // AUTO SELECTION FOR NBI
	// $('#breakout_1').change(function(){
	// 	var nbi_val = 4;
	// 	var val 	= $(this).val();

	// 	if(val == nbi_val){
	// 		$('#breakout_2').val(val).change()
	// 	}
	// })

	// CUSTOMIZE RULES
	// ruler['slot_count'] = {
	// 	min: function(){
	// 		return $('.mark-reg-form [name="rate_type"] option:selected').val()==2?3:1;
	// 	},
	// };

	if(false){
		/*
		* ONE DAY PASS MOD
		*/

		// ONE-DAY PASS
		var one_day_name 	= 'one_day';
		var rate_type_name 	= 'rate_type';
		var one_day 		= form.find('[name="'+ one_day_name +'"]');
		var rate_type 		= form.find('[name="'+ rate_type_name +'"]');
		
		// DISABLE THE ONE-DAY PASS DAY PICKER BY DEFAULT
		one_day.attr('disabled', true);

		// ADD BEHAVIOUR FOR RATE TYPE
		rate_type.change(function(){
			var select 	= $(this);
			var action 	= select.find(':selected')
				.data('is-one-day')?
					false
				:
					true
			;

			one_day.attr('disabled', action).focus();
		});
	}


});
