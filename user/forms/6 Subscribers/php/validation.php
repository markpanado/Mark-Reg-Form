<?php 

$subscriber_duplicate = $this->wpdb->get_var("
	SELECT
		COUNT(`r`.`id`) 	
	FROM
		`mark_reg_data_row` as `r`
	WHERE
		`r`.`field_id` = 33
		AND `r`.`enabled` = 1 
		AND `r`.`value` = '$email_user'
");

if($subscriber_duplicate):
	$this->validation_errors['email'] = 'Email Address Already Subscribed.';
endif;