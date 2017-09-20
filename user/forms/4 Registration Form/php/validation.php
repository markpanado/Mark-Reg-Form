<?php

// $rate_type of 2 is equal to Group Rate
// if($rate_type->value==2):
// 	$validator->addValidation($slot_count->name, 'gt=2', "Group <span>$rate_type->label</span> needs a minimum of 3 participants");
// endif;

/*
╔═╗╔═╗╔╦╗  ╦ ╦╔═╗╔═╗╦═╗  ╔═╗╔╦╗╔═╗╦╦  
║ ╦║╣  ║   ║ ║╚═╗║╣ ╠╦╝  ║╣ ║║║╠═╣║║  
╚═╝╚═╝ ╩   ╚═╝╚═╝╚═╝╩╚═  ╚═╝╩ ╩╩ ╩╩╩═╝
*/
$field_id   = 6;
$user_email = null;

foreach($this->fields as $field_key => $field_val):
    if($field_val->field_id == $field_id):
        $user_email = $field_val->value;

        break;
    endif;
endforeach;


/*
╔═╗╔═╗╦═╗  ╔═╗╦═╗╔═╗╔╦╗╔═╗  ╔═╗╔═╗╔╦╗╔═╗
╠╣ ║ ║╠╦╝  ╠═╝╠╦╝║ ║║║║║ ║  ║  ║ ║ ║║║╣ 
╚  ╚═╝╩╚═  ╩  ╩╚═╚═╝╩ ╩╚═╝  ╚═╝╚═╝═╩╝╚═╝
*/

$db_field_id_reg_promo_code = 34; // WILL BE FILLED BY USER INPUT

$db_field_id_promo_code     = 36;
$db_field_id_promo_rate     = 37;
$db_field_id_promo_note     = 38;

$db_field_id_eligible_email     = 39;
$db_field_id_eligible_code_id   = 40;

$db_field_id_eligible_domain    = 41;
$db_field_id_eligible_domain_id = 42; 

$user_promo_code            = null;
$discounted_rate            = false;

$promo_code                 = '';
$promo_code_rate            = 0;
$promo_code_note            = '';


// FIND PROMO CODE FIELD
foreach($this->fields as $field_key => $field_val):
    if($field_val->field_id == $db_field_id_reg_promo_code):
        // ASSIGN USER'S PROMO CODE
        $user_promo_code = strtoupper($field_val->value);

        // IF USER HAS PROMO CODE
        if(!empty($user_promo_code)):
            // REASSIGNING AGAIN THE VALUE OF USER'S PROMO CODE
            // TO FORCE UPPERCASE FORMAT
            $field_val->value = $user_promo_code;

            // FIND MATCHING PROMO CODE
            $promo_code_result = $this->wpdb->get_results($sql="
                SELECT
                    d.id 'id', 
                    -- GET PROMO CODE
                    (
                        SELECT
                            r.value
                        FROM mark_reg_data_row r
                        WHERE r.enabled 	= 1
                            AND r.field_id 	= $db_field_id_promo_code
                            AND r.row_id 	= d.id
                    ) 'code',
                    -- GET PROMO RATE    
                    (
                        SELECT
                            r.value
                        FROM mark_reg_data_row r
                        WHERE r.enabled 	= 1
                            AND r.field_id 	= $db_field_id_promo_rate
                            AND r.row_id 	= d.id
                    ) 'rate',
                    -- GET PROMO NOTE 
                    (
                        SELECT
                            r.value
                        FROM mark_reg_data_row r
                        WHERE r.enabled 	= 1
                            AND r.field_id 	= $db_field_id_promo_note
                            AND r.row_id 	= d.id
                    ) 'note',
                    -- CHECK IF PROMO CODE IS EXCLUSIVE
                    IF((
                        SELECT 
                            COUNT(r.id) 'count'
                        FROM mark_reg_data_row r
                        WHERE
                            r.enabled = 1
                            AND r.field_id = $db_field_id_eligible_code_id 
                            AND r.value = d.id
                    ) >0, 1, 0) 'exclusive',
                    -- CHECK IF PROMO CODE IS DOMAIN EXCLUSIVE
                    IF((
                        SELECT 
                            COUNT(r.id) 'count'
                        FROM mark_reg_data_row r
                        WHERE
                            r.enabled = 1
                            AND r.field_id = $db_field_id_eligible_domain_id 
                            AND r.value = d.id
                    ) >0, 1, 0) 'domain_exclusive'
                FROM mark_reg_data d
                WHERE
                    d.enabled = 1 
                    AND d.id = (
                        SELECT
                            r.row_id
                        FROM mark_reg_data_row r
                        WHERE
                            r.field_id 		= $db_field_id_promo_code 
                            AND r.enabled	= 1
                            AND r.VALUE		= '$field_val->value'	
                    )

                ;
            ");
            // die($sql);
            
            $passed             = false;
            $promo_code_error   = "Invalid <span>$field_val->label</span>";

            if(count($promo_code_result)):
                $passed = true;

                // CHECK IF PROMO CODE IS EXCLUSIVE
                if($promo_code_result[0]->exclusive):
                    // CHECK IF EMAIL ADDRESS IS ELIGIBLE
                    $check_eligiblity = $this->wpdb->get_results("
                        SELECT 
                            COUNT(r.id) 'count'
                        FROM mark_reg_data_row r
                        WHERE 
                            r.enabled = 1 
                            AND r.field_id = $db_field_id_eligible_email
                            AND r.value = '$user_email'
                            AND r.row_id IN (
                                -- GET LIST FOR ELIGIBLE PROMO CODES 
                                SELECT 
                                    r.row_id  
                                FROM mark_reg_data_row r
                                WHERE 
                                    r.enabled = 1 
                                AND r.field_id = $db_field_id_eligible_code_id
                                AND r.value = ".$promo_code_result[0]->id."
                            )
                    ");
                    
                    // IF NOT ELIGIBLE
                    if(empty($check_eligiblity[0]->count)):
                        $passed = false;
                        $promo_code_error   = "Sorry you are not eligible for this promo code.";
                    endif;
                // CHECK IF PROMO CODE IS DOMAIN EXCLUSIVE
                elseif($promo_code_result[0]->domain_exclusive):
                    // CHECK IF EMAIL ADDRESS' DOMAIN IS ELIGIBLE
                    $check_eligiblity = $this->wpdb->get_results($sql="
                        SELECT 
                            COUNT(r.id) 'count'
                        FROM mark_reg_data_row r
                        WHERE 
                            r.enabled = 1 
                            AND r.field_id = $db_field_id_eligible_domain
                            AND '$user_email' LIKE CONCAT('%', r.value)
                            AND r.row_id IN (
                                -- GET LIST FOR ELIGIBLE PROMO CODES 
                                SELECT 
                                    r.row_id  
                                FROM mark_reg_data_row r
                                WHERE 
                                    r.enabled = 1 
                                AND r.field_id = $db_field_id_eligible_domain_id
                                AND r.value = ".$promo_code_result[0]->id."
                            )
                    ");

                    // IF NOT ELIGIBLE
                    if(empty($check_eligiblity[0]->count)):
                        $passed = false;
                        $promo_code_error   = "Sorry you are not eligible for this promo code.";
                    endif;
                endif;

                if($passed):
                    $discounted_rate    = true;

                    $promo_code         = $promo_code_result[0]->code;
                    $promo_code_rate    = $promo_code_result[0]->rate;
                    $promo_code_note    = $promo_code_result[0]->note;
                endif;
            endif;

            if(!$passed):
                // MODIFY VALIDATION AND MAKE THE USER'S PROMO CODE INVALID
                $this->validation_errors[$field_val->name] = $promo_code_error;
            endif;

            // $this->validation_errors['FAKE_ERROR'] = "FAKE ERROR";

            // // CHECK STATIC PROMO CODE IF EQUAL TO USER'S PROMO CODE
            // if($user_promo_code !== $promo_code):
            //     // MODIFY VALIDATION AND MAKE THE USER'S PROMO CODE INVALID
            //     $this->validation_errors[$field_val->name] = "Invalid <span>$field_val->label</span>";
            // else:
            //     // SET DISCOUNT RATE BOOLEAN TO TRUE
            //     $discounted_rate = true;
            // endif;
        endif;

        break;
    endif;
endforeach;



