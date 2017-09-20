<?php

/*
Plugin Name: Mark Registration Form
Plugin URI: http://www.mark.ph
Description: Mark Registration Form by Mark Panado
Version: 1
Author: Mark Panado
Author URI: http://www.mark.ph/
*/

class MarkRegistrationForm{
	var $wpdb;

	var $allowed_pages;
	var $current_page;

	var $form_id;
	var $form_label;
	var $form_name;
	var $form_confirm_submit;
	var $form_date_available;
	var $form_date_expire;

	var $fields;

	var $validation_errors;

	var $form_success;

	var $user_folder;
	var $user_url;
	var $user_download_url;

	var $event_name;
	var $event_url;
	var $event_logo;
	var $event_email;

	var $FB_sign_up;

	public function __construct($form_id){
		// VARS
		global $wpdb;
		global $post;

		$this->wpdb 			= $wpdb;

		$this->allowed_pages	= [];
		$this->current_page		= $post->post_name;
		$this->form_id 			= $form_id;
		$this->fields 			= [];

		$this->validation_errors= [];

		$this->form_success 	= false;

		$this->user_folder		= '';
		$this->user_url			= '';
		$this->user_download_url= '';

		$this->event_name 		= get_bloginfo('name');
		$this->event_url 		= get_site_url();
		$this->event_email 		= 'info@sustainabilitysummit.ph';

		// $tmp = wp_get_attachment_image_src(432,'large');
		$tmp = wp_get_attachment_image_src(533,'large');
		$this->event_logo		= $tmp[0];
	}

	public function trigger(){
		if(empty($this->allowed_pages) || in_array($this->current_page, $this->allowed_pages)):
			// FIND THE FORM
			$this->findForm();

			// LOAD FIELDS
			$this->loadFields();

			// GET FIELDS POST VALUES
			$this->getFieldsPOST();

			// SCRIPTS
			$this->scripts();

			// PROCESS FORM
			$this->processForm();

			// GENERATE SHORTCODE
			$this->shortCode();
		endif;
	}

	public function allowedPages($pages){
		$this->allowed_pages = $pages;
	}

	// FIND THE FORM FIRST
	private function findForm(){

		$results = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT
				`form`.`id` 			as 'id',
				`form`.`label` 			as 'label',
				`form`.`confirm_submit` as 'confirm_submit',
				`form`.`date_available` as 'date_available',
				`form`.`date_expire` 	as 'date_expire'
			FROM `mark_reg_forms` as `form`
			WHERE
				`form`.`id` = %d
				AND `form`.`enabled` = 1
		", $this->form_id));

		if(count($results)):
			$this->form_confirm_submit	= $results[0]->confirm_submit;
			$this->form_label 			= $results[0]->label;
			$this->form_date_available	= $results[0]->date_available;
			$this->form_date_expire		= $results[0]->date_expire;
			$this->form_name			= 'mark_reg_form_'.$this->form_id;
		else:
			$this->log("FORM NOT FOUND FOR ID: $this->form_id");
		endif;
	}

	// LOAD THE FIELDS OF THE FORM
	private function loadFields(){
		$this->fields = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT
				`field`.`id` 			'field_id',
				`type`.`name` 			'type',
				`field`.`name`			'name',
				`field`.`label`			'label',
				`field`.`default`		'default',
				`field`.`required`		'required',
				`field`.`depends_id`	'depends_id',
				`field`.`depends_value`	'depends_value',
				`field`.`placeholder`	'placeholder'
			FROM `mark_reg_fields` `field`
			LEFT JOIN `mark_reg_field_types` `type` ON `type`.`id` = `field`.`type_id`
			WHERE
				`field`.`enabled` = 1
				AND `field`.`form_id` = %d
				AND `field`.`backend_only` = 0
			ORDER BY
				`field`.`order`
		", $this->form_id));
	}

	private function getFieldsPOST(){
		foreach($this->fields as $field_key => $field):
			// GET FIELD'S POST VALUE
			$field_post_value = is_array($x=$_POST[$field->name])?$x:strip_tags(trim($x));

			// FIX FOR SELECT FIELD
			if($field->type=='select'):
				$field_post_value = isset($field_post_value)?$field_post_value:0;
			// FOR NUMBERS WITH 0
			elseif($field->type=='number'):
				$field_post_value = $field_post_value<1?1:$field_post_value;
			elseif($field->type=='checkbox'):
				// Convert to array
				$field_post_value = !empty($field_post_value)?$field_post_value:[];
			endif;

			// SET IT IF PROVIDED
			$this->fields[$field_key]->value = isset($field_post_value)?$field_post_value:NULL;
		endforeach;
	}

	private function processForm(){
		$fields_to_save = [];
		$fields_to_email= [];

		// CHECK IF FORM WAS SUBMITTED
		if($this->form_name == $_POST['form_name']):
			// SET VALIDATOR
			$validator = new FormValidator();

			$email_user = '';
			$rate_type 	= 0;
			$slot_count = 0;

			// VALIDATION FOR REQUIRED FIELDS
			foreach($this->fields as $field_key => $field):
				// IF REQUIRED
				if($field->required):
					$req = $field->type==($x='email')?$x:'req';

					$req = $field->type==($x='number')?'num':$req;

					$depend = true;
					// FOR DEPENDS
					if(!empty($field->depends_id)):
						// SEARCH DEPEND
						foreach($this->fields as $depend_key => $depend_value):
							if($depend_value->field_id == $field->depends_id):
								$depend = $depend_value->value==$field->depends_value;
								break;
							endif;
						endforeach;
					endif;

					if($depend)
						if(!is_array($field->value))
							$validator->addValidation($field->name, $req, "Please provide valid value for <span>$field->label</span>");
				endif;

				// Get users email
				if($field->type == 'email'):
					$email_user = $field->value;
				// Get rate type
				elseif($field->name == 'rate_type'):
					$rate_type = $field;
				// Get slout cout
				elseif($field->name == 'slot_count'):
					$slot_count = $field;
				endif;
			endforeach;

			// CUSTOM VALIDATION
			if(file_exists($x="$this->user_folder/php/validation.php")):
				require($x);
			endif;

			// JUDGEMENT DAY
			if(count($this->fields))
			if(!$validator->ValidateForm() || count($this->validation_errors)):
				// $this->validation_errors = $validator->GetErrors();
				$this->validation_errors = array_merge($this->validation_errors, $validator->GetErrors());
			else:
				// INSERT THE MAIN ROW FIRST
				$this->wpdb->insert(
					'mark_reg_data',
					[
						'id'		=>NULL,
						'form_id'	=> $this->form_id,
					]
				);

				// GET MAIN ROW ID
				$row_id = $this->wpdb->insert_id;
				$uniqid = uniqid($row_id);

				// ADD UNIQID
				$this->wpdb->update(
					'mark_reg_data',
					[
						'uniqid'=> $uniqid,
					],
					[
						'id'	=> $row_id,
					]
				);

				// FACEBOOK IMPORT DATA
				if(!empty($this->FB_sign_up)):
					if(!empty($this->FB_sign_up->user_data)):
						$this->fields[] = (object)array('field_id' => '32', 'value' => $this->FB_sign_up->user_data);
					endif;
				endif;

				// INSERT EACH FIELD IN THE RECORD DATA
				foreach($this->fields as $field_key => $field ):
					$db_inserts = [];
					$db_inserts['row_id'] = $row_id;

					foreach([
						'field_id',
						'value'
					] as $col_key => $col):
						// $db_inserts[$col] = $field->{$col};
						$db_inserts[$col] = is_array(($x=$field->{$col}))?json_encode($x):$x;
					endforeach;

					// THE START OF THE SHOW
					$this->wpdb->insert(
						'mark_reg_data_row',
						$db_inserts
					);
				endforeach;

				if($this->form_id == 4):
					// EMAIL USER
					$email_enderun 	= mj_is_dev_env()?'mark.panado@enderuncolleges.com':$this->event_email;
					$email_subject 	= "$this->event_name, Registration #$uniqid";
					$email_headers 	= 'Content-type: text/html; charset=utf-8' . "\n";
					$email_headers 	.= 'Reply-To: Enderun Colleges <'.$email_enderun.'>\r\n';

					$email_body = 'Hello!';

					$email_body = file_get_contents(dirname(__FILE__).'/views/email.php');

					$email_map = [];
					$email_map['[row_id]'] 			= $uniqid;
					$email_map['[REMOTE_ADDR]'] 	= $_SERVER['REMOTE_ADDR'].(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])?' ( '.$_SERVER['HTTP_X_FORWARDED_FOR'].' )':NULL);
					$email_map['[DATE_TIME]'] 		= current_time('F j, Y g:i a');
					$email_map['[HTTP_REFERER]'] 	= $_SERVER["HTTP_REFERER"];
					$email_map['[HTTP_USER_AGENT]'] = $_SERVER['HTTP_USER_AGENT'];

					$email_map['[event_logo]']		= $this->event_logo;
					$email_map['[event_email]']		= $this->event_email;
					$email_map['[event_url]']		= $this->event_url;
					$email_map['[event_name]']		= $this->event_name;

					$payment_quantity 	= 1;
					$payment_amount 	= 0;
					$payment_method 	= 0;
					$payment_rate_type	= '';

					foreach($this->fields as $field_key => $field):
						$value = $field->value;

						if($field->type == 'select'):
							$value = $this->selectValue($field->field_id, $field->value, 'label');
						endif;

						$email_map['['.$field->name.']'] = !empty($value)?$value:'–––';

						// Quantity
						if($field->name == 'slot_count'):
							$payment_quantity = $field->value;
						// Rate Type
						elseif($field->name == 'rate_type'):
							$payment_amount 	= $this->selectValue($field->field_id, $field->value, 'amount');
							$payment_rate_type 	= $this->selectValue($field->field_id, $field->value, 'label');
						// Payment Method
						elseif($field->name == 'payment_method'):
							$payment_method = $field->value;
						endif;
					endforeach;

					// PAYPAL
					# SET PAYPAL PAYMENT
					$paypal_item_name		= urlencode($this->event_name." - $payment_rate_type");
					$paypal_item_number		= urlencode($row_id);
					$paypal_cn				= urlencode('Additional notes:');
					// $paypal_return			= urlencode($_SERVER['HTTP_REFERER']);
					$paypal_return			= urlencode(site_url().'/success');
					$paypal_cancel_return	= urlencode($_SERVER['HTTP_REFERER']);
					$paypal_site			= urlencode(site_url());
					$paypal_amount			= urlencode($payment_amount);
					$paypal_quantity		= $payment_quantity;#urlencode($fieldsToSave['quantity']);

					$paypal_id				= 'SZYCBEPVH4454'; // 'L84WJ79ZVU5CS';
					$discount_rate 			= 0;
					# If group rate
					$discount_amount_var	= '';
					// if($paypal_quantity==10):
					// 	$discount_amount_var = "&discount_amount=5000";
					// endif;

					if($discounted_rate):
						$discount_rate = $promo_code_rate;
					endif;

					$paypalLink				= "https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=$paypal_id&lc=PH&item_name=$paypal_item_name&item_number=$paypal_item_number&amount=$paypal_amount&currency_code=PHP&button_subtype=services&no_note=0&cn=$paypal_cn&no_shipping=1&rm=1&return=$paypal_return&cancel_return=$paypal_cancel_return&bn=PP%2dBuyNowBF%3abtn_paynow_LG%2egif%3aNonHosted&custom=$paypal_site&quantity=$paypal_quantity".$discount_amount_var."&discount_rate=$discount_rate";
					// $this->log($paypalLink);
					$this->form_success = true;

					$email_map['[PAYPAL_LINK]'] = $paypalLink;

					foreach($email_map as $email_map_key => $email_map_value):
						$email_body = str_replace($email_map_key, $email_map_value, $email_body);
					endforeach;

					// EMAIL ENDERUN
					wp_mail($email_enderun, $email_subject, $email_body, $email_headers);
					wp_mail($email_user, $email_subject, $email_body, $email_headers);

					// DESTROY FB LOGIN IMMEDIATELY
					if(isset($_SESSION['facebook_access_token'])):
						unset($_SESSION['facebook_access_token']);
					endif;

					if($payment_method==1):
						header("Location:".$paypalLink);
						die();
					else:
						header("Location:". get_site_url().'/success');
						die();
					endif;
				elseif($this->form_id == 5):
					// $this->log('process PDF here');
					$email 		= '';
					$full_name	= '';
					$event_name = '';
					$event_url	= get_site_url();

					// Get constant values
					$event_name = get_bloginfo('name');

					// Get values for email and full name
					foreach($this->fields as $field_key => $field):
						// Get users email
						if($field->type == 'email'):
							$email = $field->value;
						elseif($field->name == 'full_name'):
							$full_name = str_replace('ñ', 'Ñ', strtoupper($field->value));
						endif;
					endforeach;

					# CREATE PDF
					require_once 'Zend/Pdf.php';
					// $pdfFileName	= 'Sustainable-Enterprise-Summit-2016.pdf';
					$pdfFileName	= 'BigDataConference.pdf';
					$newPdfFileName = uniqid(rand()).'.pdf';
					$pdf 			= Zend_Pdf::load($this->user_folder.'/pdf/'.$pdfFileName);
					$font 			= Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA);
					$fontSize 		= 35;

					// # SET PAGE DEFAULTS
					$pdf->properties['Title'] = get_bloginfo('name');

					foreach($pdf->pages as $key => $value):
						$pdf->pages[$key]->setFont($font, $fontSize);
					endforeach;

					// THIS IS IT
					$text = $full_name;
					$textWidth = $this->getTextWidth(str_replace(['Ñ', 'ñ'], 'N', $full_name), $font, $fontSize);
					$pdf->pages[0]->drawText($text, $pdf->pages[0]->getWidth() - ($textWidth * 2) - 140, 370, 'UTF-8');

					$pdf_file = "$this->user_folder/downloads/$newPdfFileName";
					$pdf->save($pdf_file);
					$this->user_download_url = "$this->user_url/downloads/$newPdfFileName";

					# EMAIL
					$email_body = file_get_contents(dirname(__FILE__).'/views/certificates.php');

					foreach([
						'[full_name]' 	=> $full_name,
						'[event_name]'	=> $event_name,
						'[event_url]'	=> $event_url,
					] as $email_map_key => $email_map_value):
						$email_body = str_replace($email_map_key, $email_map_value, $email_body);
					endforeach;

					$email_subject 	= get_bloginfo('name').' Certificate of Participation';
					$email_headers 	= 'Content-type: text/html; charset=utf-8' . "\n";
					// $email_headers 	.= "Reply-To: \"Enderun Colleges\" <$email_enderun>\r\n";

					if(wp_mail("$full_name <$email>", $email_subject, $email_body, $email_headers, $pdf_file)):
						unlink($pdf_file);
					endif;

					header("Location:". get_site_url()."/$this->current_page?success");
					die();
				elseif($this->form_id == 6): // FOR SUBSCRIBERS
					// EMAIL USER
					$email_enderun 	= mj_is_dev_env()?'mark.panado@enderuncolleges.com':$this->event_email;
					$email_subject 	= $this->event_name;
					$email_headers 	= 'Content-type: text/html; charset=utf-8' . "\n";
					$email_headers 	.= 'Reply-To: Enderun Colleges <'.$email_enderun.'>\r\n';

					$email_body = 'Hello!';

					$email_body = file_get_contents(dirname(__FILE__).'/views/subscribe.php');

					foreach([
						'[event_name]'	=> $this->event_name,
						'[event_url]'	=> $this->event_url,
						'[event_logo]'	=> $this->event_logo,
					] as $email_map_key => $email_map_value):
						$email_body = str_replace($email_map_key, $email_map_value, $email_body);
					endforeach;

					$email_map = [];
					// $email_map['[row_id]'] 			= $row_id;
					// $email_map['[REMOTE_ADDR]'] 	= $_SERVER['REMOTE_ADDR'].(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])?' ( '.$_SERVER['HTTP_X_FORWARDED_FOR'].' )':NULL);
					// $email_map['[DATE_TIME]'] 		= current_time('F j, Y g:i a');
					// $email_map['[HTTP_REFERER]'] 	= $_SERVER["HTTP_REFERER"];
					// $email_map['[HTTP_USER_AGENT]'] = $_SERVER['HTTP_USER_AGENT'];

					foreach($this->fields as $field_key => $field):
						$value = $field->value;

						if($field->type == 'select'):
							$value = $this->selectValue($field->field_id, $field->value, 'label');
						endif;

						$email_map['['.$field->name.']'] = $value;

						// Quantity
						if($field->name == 'slot_count'):
							$payment_quantity = $field->value;
						// Rate Type
						elseif($field->name == 'rate_type'):
							$payment_amount 	= $this->selectValue($field->field_id, $field->value, 'amount');
							$payment_rate_type 	= $this->selectValue($field->field_id, $field->value, 'label');
						// Payment Method
						elseif($field->name == 'payment_method'):
							$payment_method = $field->value;
						endif;
					endforeach;

					foreach($email_map as $email_map_key => $email_map_value):
						$email_body = str_replace($email_map_key, $email_map_value, $email_body);
					endforeach;

					// EMAIL ENDERUN
					// wp_mail($email_enderun, $email_subject, $email_body, $email_headers);
					wp_mail($email_user, $email_subject, $email_body, $email_headers);
				endif;
			endif;
		endif;
	}

	private function getTextWidth($text, $font, $font_size) {
		$drawing_text = iconv('', 'UTF-8', $text);
		$characters    = array();

		for ($i = 0; $i < strlen($drawing_text); $i++) {
	    	$characters[] = (ord($drawing_text[$i++]) << 8) | ord ($drawing_text[$i]);
	    	// $characters[] = ord ($drawing_text[$i]);
		}

		$glyphs        = $font->glyphNumbersForCharacters($characters);
		$widths        = $font->widthsForGlyphs($glyphs);
		$text_width   = (array_sum($widths) / $font->getUnitsPerEm()) * $font_size;

		return $text_width;
	}

	private function selectValue($id, $value, $prop){
		$result = $this->wpdb->get_results($this->wpdb->prepare("
			SELECT
				`f`.`default` as 'default'
			FROM `mark_reg_fields` as `f`
			WHERE `f`.`id` = %d
		", $id));

		if(count($result)):
			$options = json_decode($result[0]->default);

			// return $options[$value-1]->{$prop};
			foreach($options as $opt_key => $opt_val):
				if($opt_val->value == $value):
					return $opt_val->{$prop};
				endif;
			endforeach;
		endif;

		// return 'NOT FOUND';
		return '';
	}

	public function shortCode(){
		add_shortcode($this->form_name, function(){
			$return = '';

			// START BLOCKER *************************************************************************

			# get date today
			$date_now = current_time('Y-m-d H:i:s');

			# check if date availability is provided
			if(!empty($this->form_date_available)):

				# if form is not yet available
				if($date_now < $this->form_date_available):

					$return .= "Form will be available on $this->form_date_available";

					return $return;
				endif;
			endif;

			# check if form is expired
			if(!empty($this->form_date_expire)):

				# if form is expired
				if($date_now > $this->form_date_expire):
					// $return .= "The online registration has now been closed. Should you be interested to attend the conference on May 24-25, you are more than invited to walk-in. Walk-in rate is Php9,000 for a one-day pass and 16,000 for a two-day pass/participant.";
					$return = '<p>To be announced.</p>';

					return $return;
				endif;
			endif;
			// END BLOCKER *************************************************************************

			$fields = '';

			$disabled		= '';
			$form_success 	= '';

			$adwords 		= '
				<!-- Google Code for HRC Conference - Lead Conversion Page -->
				<script type="text/javascript">
				/* <![CDATA[ */
				var google_conversion_id = 977837039;
				var google_conversion_language = "en";
				var google_conversion_format = "3";
				var google_conversion_color = "ffffff";
				var google_conversion_label = "AnYhCPz-9GIQ77ei0gM";
				var google_remarketing_only = false;
				/* ]]> */
				</script>
				<script type="text/javascript" src="//www.googleadservices.com/pagead/conversion.js">
				</script>
				<noscript>
				<div style="display:inline;">
				<img height="1" width="1" style="border-style:none;" alt="" src="//www.googleadservices.com/pagead/conversion/977837039/?label=AnYhCPz-9GIQ77ei0gM&amp;guid=ON&amp;script=0"/>
				</div>
				</noscript>
			';

			if($this->form_success):
				$disabled = ' disabled';

				$form_success = 'mark-reg-form-submitted-success';
			endif;

			// GENERATE FIELDS
			foreach($this->fields as $field_key => $field):
				$fields .= $this->fieldFactory($field);
			endforeach;

			// $return .= "<iframe src=\"$this->user_download_url\" width=\"100%\" height=\"1000\"></iframe>";

			$return .= '<form id="'.$this->form_name.'" class="mark-reg-form validate '.$this->form_name.' '.$form_success.'" method="post" data-confirm-submit="'.$this->form_confirm_submit.'" action="/">';

			if($this->form_success || isset($_GET['success'])):
				$return .= '<div class="mark-reg-form-msg mark-reg-form-success">';
					if($this->form_id == 5):
						$return .= '<h1>Certificate Generated!</h1>';
						$return .= '<p>Please check your email for the copy of your certificate.</p>';
					else:
						$return .= '<h1>Thank You!</h1>';
						$return .= '<p>Your registration has been submitted. Please check your email for more details.</p>';
						// $return .= $adwords;
					endif;
				$return .= '</div>';
			elseif(count($this->validation_errors)):
				$return .= '<div class="mark-reg-form-msg mark-reg-form-error">';
					$return .= '<ul>';
						foreach($this->validation_errors as $error_key => $error_value):
							$return .= '<li>';
								$return .= $error_value;
							$return .= '</li>';
						endforeach;
					$return .= '</ul>';
				$return .= '</div>';
			endif;

			/*<div class="neck">
				'.(!empty($this->FB_sign_up)?$this->FB_sign_up->render():'').'
			</div>*/

			$return	.= '<div class="head">
						<input type="hidden" name="form_name" value="'.$this->form_name.'" />
					</div>

					<div class="body megan-fox">
						'.$fields.'
					</div>
					<div class="mj-msg">
						<p>For group registrations, please call us at 856-5000 local 505/525 or email us at <a href="mailto:'.$this->event_email.'" target="_blank">'.$this->event_email.'</a></p>
					</div>
					<div class="foot">
						<input type="submit" value="Submit"'.$disabled.' />
					</div>
				</form>
			';

			return $return;
		});
	}

	private function fieldFactory($field){
		$return 	= '';

		$type 			= $field->type;
		$name 			= $field->name;
		$default 		= $field->default;
		$value 			= !empty($field->value)?$field->value:$field->default;
		$label 			= $field->label;
		$required 		= $field->required;

		$depends_id		= $field->depends_id;
		$depends_value	= $field->depends_value;

		$placeholder 	= $field->placeholder;

		$disabled 	= '';

		// CLASSES
		$classes_list = [];

		// ADDED ELEMENTS
		$required_indicator = '';

		// LOOP FOR FB DATA HERE
		if(!empty($this->FB_sign_up)):
			foreach($this->FB_sign_up->mapping as $FB_map_key => $FB_map_val):
				if($FB_map_val['db_field'] == $name):
					// FOR STRINGS
					if($FB_map_val['type'] == 'string'):
						$value = $this->FB_sign_up->user_data[$FB_map_val['fb_field']];
					// FOR OBJECTS
					elseif($FB_map_val['type'] == 'object'):
						if(!empty($tmp = $this->FB_sign_up->user_data[$FB_map_val['fb_field']])):
							$value = get_object_vars($tmp)[$FB_map_val['fb_prop']];

							if($field->type == 'date'):
								$value = date('Y-m-d', strtotime($value));
							endif;
						endif;
					// FOR ARRAYS
					elseif($FB_map_val['type'] == 'array'):
						if(!empty($tmp = $this->FB_sign_up->user_data[$FB_map_val['fb_field']])):
							$tmp = $tmp[$FB_map_val['fb_index']];

							// LOOP TO ARRAYS PROP
							foreach($FB_map_val['fb_prop'] as $fb_prop_key => $fb_prop_val):
								$tmp = $tmp[$fb_prop_val];
							endforeach;

							$value = $tmp;
						endif;
					endif;
				endif;
			endforeach;
		endif;

		if($required):
			array_push($classes_list, 'required');
			$required_indicator = ' <span class="req"></span>';
		endif;

		// VALIDATION ERRORS
		if(!empty($this->validation_errors[$name])):
			array_push($classes_list, 'error');
		endif;

		if($this->form_success):
			$disabled = ' disabled';
		endif;

		// DATA ATTRIBUTES
		$data_attributes = [];

		if(!empty($depends_id)):
			$result = $this->wpdb->get_results($this->wpdb->prepare("
				SELECT
					`field`.`name`		as 'name'
				FROM `mark_reg_fields` as `field`
				WHERE
					`field`.`enabled` = 1
					AND `field`.`id` = %d
			", $depends_id));

			$data_attributes['data-depends'] 	= $result[0]->name;
		endif;

		if(!empty($depends_value)) 	$data_attributes['data-depends-value'] 	= $depends_value;

		$data_attributes_html = [];

		foreach($data_attributes as $da_key => $da_value):
			$data_attributes_html[] = "$da_key=\"".esc_attr($da_value)."\"";
		endforeach;

		$data_attributes_html = implode(' ', $data_attributes_html);

		$classes = implode(' ', $classes_list);

		$return .= '<div class="field-row field-'.$type.' field-name-'.$name.'">';
			$return .= '<div class="field-label">';
				$return .= '<label for="'.$name.'">'.$label.$required_indicator.'</label>';
			$return .= '</div>';

			$return .= '<div class="field">';
				// TEXT, NUMBER, EMAIL
				if(in_array($type, ['text', 'number', 'email', 'date'])):
					$return .= '<input type="'.$type.'" name="'.$name.'" value="'.$value.'" id="'.$name.'" class="'.$classes.'"'.$disabled.' '.$data_attributes_html.' placeholder="'.$placeholder.'" />';
				// TEXTAREA
				elseif($type=='textarea'):
					$return .= '<textarea name="'.$name.'" id="'.$name.'" class="'.$classes.'"'.$disabled.' '.$data_attributes_html.'>'.$value.'</textarea>';
				// SELECT
				elseif($type=='select'):
					$default 	= json_decode($default);
					$value 		= $field->value;

					$return .= '<select name="'.$name.'" id="'.$name.'" class="'.$classes.'" data-selected-index="'.$value.'"'.$disabled.' '.$data_attributes_html.'>';
						$return .= '<option value="">--SELECT--</option>';

						foreach($default as $select_key => $select):
							// GET EXTRA ATTRIBUTES
							$select_attributes = [];

							foreach($select as $select_obj_key => $select_obj_val):
								if(!in_array($select_obj_key, ['value', 'label'])):
									array_push($select_attributes, "data-$select_obj_key=\"".esc_attr($select_obj_val)."\"");
								endif;
							endforeach;

							$return .= '<option value="'.$select->value.'" '.implode(' ', $select_attributes).'>'.$select->label.'</option>';
						endforeach;
					$return .= '</select>';

					$return .= '<div class="html-display">';

					$return .= '</div>';
				// RADIO
				elseif(in_array($type, ['radio', 'checkbox'])):
					$default 	= json_decode($default);
					$value 		= $field->value;

					$return .= '<ul>';

						foreach($default as $option_key => $option):
							// GET EXTRA ATTRIBUTES
							$option_attributes = [];

							foreach($option as $option_obj_key => $option_obj_val):
								if(!in_array($option_obj_key, ['value', 'label'])):
									array_push($option_attributes, "data-$option_obj_key=\"".esc_attr($option_obj_val)."\"");
								endif;
							endforeach;

							$option_id = $name.'_'.$option->value;

							// SET CHECKED
							if(is_array($value)):
								// CHECKBOX
								$checked = in_array($option->value, $value)?'checked':'';
							else:
								// RADIO
								$checked = $value==$option->value?'checked':'';
							endif;

							// FOR CHECKBOXES
							$name_append = $type=='checkbox'?'[]':'';

							$return .= '<li>';
								$return .= '<input type="'.$type.'" name="'.$name.$name_append.'" value="'.$option->value.'" id="'.$option_id.'" class="'.$classes.'"'.$disabled.' '.trim(implode(' ', $option_attributes)." $checked").' '.$data_attributes_html.' />';
								$return .= '<label for="'.$option_id.'">'.$option->label.'</label>';
							$return .= '</li>';
						endforeach;

					$return .= '</ul>';
				endif;
			$return .= '</div>';
		$return .= '</div>';

		return $return;
	}

	public function scripts(){
		$url 		= plugin_dir_url(__FILE__);
		$dir 		= plugin_dir_path(__FILE__);
		$user_forms = 'user/forms';

		// PHP
		require_once("$dir/lib/php/php-form-validator/formvalidator.php");
		require_once("$dir/lib/php/php-graph-sdk-5.3/Facebook/autoload.php");

		// CSS JS
		wp_register_style($x='mark-reg-form-responsive', $url.'css/responsive.min.css');
		wp_enqueue_style($x);
		wp_register_style($x='mark-reg-form', $url.'css/css.min.css');
		wp_enqueue_style($x);

		// Scripts
		wp_register_script($x='mark-reg-form-js-validate', $url.'lib/js/jquery-validation-1.14.0/jquery.validate.min.js', [], false, true);
		wp_enqueue_script($x);
		wp_register_script($x='mark-reg-form-js-validate-additional-methods', $url.'lib/js/jquery-validation-1.14.0/additional-methods.min.js', [], false, true);
		wp_enqueue_script($x);
		wp_register_script($x='mark-reg-form-js-scrolltofixed', $url.'lib/js/jquery-scrolltofixed-min.js', [], false, true);
		wp_enqueue_script($x);

		// User Scripts
		# Check if forms folder exists
		foreach(scandir($dir.$user_forms) as $key => $folder):
			if(!in_array($folder, array('.','..'))):
				# check if folder for the current form exists
				if($this->form_id == substr($folder, 0, strpos($folder, ' '))):
					$this->user_folder 	= "$dir$user_forms/$folder";
					$this->user_url		= "$url$user_forms/$folder";

					# check if php autoload file exist
					if(file_exists($tmp = $dir.$user_forms."/$folder/php/autoload.php")):
						require_once $tmp;
					endif;

					# check if js file exist
					if(file_exists($dir.$user_forms.($tmp="/$folder/js/js.min.js"))):
						# load js file
						wp_register_script($x="mark-reg-form-$this->form_id-user-js", $url.$user_forms.$tmp, [], false, true);
						wp_enqueue_script($x);
					endif;

					# check if css file exist
					if(file_exists($dir.$user_forms.($tmp="/$folder/css/css.min.css"))):
						# load css file
						wp_register_style($x="mark-reg-form-$this->form_id-user", $url.$user_forms.$tmp);
						wp_enqueue_style($x);
					endif;
				endif;
			endif;
		endforeach;

		// JS
		wp_register_script($x='mark-reg-form-js', $url.'js/js.min.js', [], false, true);
		wp_enqueue_script($x);
	}

	private function log($msg){
		error_log("[MARK_REG_FORMS] $msg");
	}

	private function dump($var){
		echo '<pre>';
		var_dump($var);
		echo '</pre>';
	}
}

if(!is_admin()):
	add_action('wp', function(){
		$reg_form = new MarkRegistrationForm(4); // 4 is Sustainability Summit
		$reg_form->allowedPages(['home']);
		$reg_form->trigger();

		$cert = new MarkRegistrationForm(5); // 5 is Certificate Generator
		$cert->allowedPages(['certificate']);
		$cert->trigger();

		$subscribers = new MarkRegistrationForm(6); // 6 is for subscribers
		// $subscribers->allowedPages(['home']);
		$subscribers->trigger();
	});
endif;
