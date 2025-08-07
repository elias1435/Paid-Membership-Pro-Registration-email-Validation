<?php

/* 1. option page */
add_action( 'acf/init', function() {
	acf_add_options_page( array(
	'page_title' => 'Valid domains',
	'menu_slug' => 'validate-settings',
	'menu_title' => 'Valid domains',
	'position' => '',
	'redirect' => false,
	'menu_icon' => array(
		'type' => 'dashicons',
		'value' => 'dashicons-email-alt',
	),
	'icon_url' => 'dashicons-email-alt',
) );
} );



/* 2. ACF Repeater Email validtion field creation */
add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_62c56c0edf3fc',
	'title' => 'Validate Email Domain List',
	'fields' => array(
		array(
			'key' => 'field_62c56c45ab69d',
			'label' => 'Domain List',
			'name' => 'domain_list',
			'aria-label' => '',
			'type' => 'repeater',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'collapsed' => '',
			'min' => 0,
			'max' => 0,
			'layout' => 'table',
			'button_label' => 'Add Row',
			'sub_fields' => array(
				array(
					'key' => 'field_62debdbcf1710',
					'label' => 'Add Email Domain',
					'name' => 'email_domain',
					'aria-label' => '',
					'type' => 'text',
					'instructions' => '',
					'required' => 0,
					'conditional_logic' => 0,
					'wrapper' => array(
						'width' => '',
						'class' => '',
						'id' => '',
					),
					'default_value' => '',
					'placeholder' => '',
					'prepend' => '',
					'append' => '',
					'maxlength' => '',
					'parent_repeater' => 'field_62c56c45ab69d',
				),
			),
			'rows_per_page' => 20,
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'options_page',
				'operator' => '==',
				'value' => 'validate-settings',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );




/* 3. Doamin validation */
function my_pmpro_registration_checks_restrict_email_addresses( $value ) {
	$email = $_REQUEST['bemail'];
	
	if(is_null($email)){
		return $value;
	}
	
	if( !my_checkForValidDomain( $email ) ) {
		global $pmpro_msg, $pmpro_msgt;
		$pmpro_msg = "Your email address does not appear to be associated with a Surrey Care Association. For help with this please contact sca@surreycare.org.uk";
		$pmpro_msgt = "pmpro_error";
		$value = false;
	}
	
	return $value;
}
add_filter( 'pmpro_registration_checks','my_pmpro_registration_checks_restrict_email_addresses', 10, 1 );


//extract-domain-name-from-an-e-mail-address-string.html
function my_getDomainFromEmail( $email ) {
    // Get the data after the @ sign
    $domain = substr(strrchr($email, "@"), 1);

    return $domain;
}
function my_checkForValidDomain( $email ) {
	$domain = my_getDomainFromEmail( $email );

// start Repeater fild from here
	$valid_domains = []; // Declare an empty array for valid domains.
	
	// Loop through the domain list and store in the array.
	if ( have_rows('domain_list', 'option') ) :
		while( have_rows('domain_list', 'option') ) : the_row();
			$valid_domains[] = trim( get_sub_field('email_domain') ); // Remove empty spaces from start and end of the domain.
		endwhile;
		
		$valid_domains = array_filter( $valid_domains ); // Remove empty entries.
	endif;

	foreach($valid_domains as $valid_domain) {
		$components = explode(".", $valid_domain);
		$domain_to_check = explode(".", $domain);

		if(!empty($components[0]->config == "*") && sizeof($domain_to_check->config > 2)) {
			if($components[1] == $domain_to_check[1] && $components[2] == $domain_to_check[2]) {
				return true;
			}
		} else {
			if( !( strpos($valid_domain, $domain) === false) ) {
				return true;
			}
		}
	}
	return false;
}
