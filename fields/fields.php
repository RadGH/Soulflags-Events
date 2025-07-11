<?php
add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	
	acf_add_local_field_group( array(
		'key' => 'group_684c75910fae3',
		'title' => 'Soulflags Events Settings - About',
		'fields' => array(
			array(
				'key' => 'field_684c75918e967',
				'label' => 'About Soulflags Events',
				'name' => '',
				'aria-label' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Soulflags Events allows you to create events using The Events Calendar and connect them to WooCommerce Products, allowing you to sell tickets to each event. You can also limit the number of tickets available using WooCommerce\'s Inventory system.

To get started, create or edit an event and enable "Event Registrations". Then check the box to "Automatically create a product when this event is saved". Fill out the rest of the event details and save the event. A product will be created automatically based off of the event.',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'options_page',
					'operator' => '==',
					'value' => 'sfe-settings',
				),
			),
		),
		'menu_order' => -10,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => 'Options Page: Soulflags Events',
		'show_in_rest' => 0,
	) );
	
	acf_add_local_field_group( array(
		'key' => 'group_684a23aeac1cf',
		'title' => 'Event Registration',
		'fields' => array(
			array(
				'key' => 'field_684a23b04c8f4',
				'label' => 'Registrations',
				'name' => 'sfe_registration_enabled',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Enable registration using WooCommerce',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
				'ui' => 1,
			),
			array(
				'key' => 'field_686d8855db574',
				'label' => 'Instructions',
				'name' => '',
				'aria-label' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'With event registration enabled, the following fields will automatically be imported from the assigned Class Type if you leave them blank:

<ul class="ul-disc">
<li>Post Content</li>
<li>Featured Image</li>
<li>Venue</li>
<li>Price</li>
</ul>',
				'new_lines' => 'wpautop',
				'esc_html' => 0,
			),
			array(
				'key' => 'field_684a24424c8f9',
				'label' => 'Product',
				'name' => 'sfe_product_id',
				'aria-label' => '',
				'type' => 'post_object',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'post_type' => array(
					0 => 'product',
				),
				'post_status' => '',
				'taxonomy' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 1,
				'allow_in_bindings' => 0,
				'bidirectional' => 0,
				'ui' => 1,
				'bidirectional_target' => array(
				),
			),
			array(
				'key' => 'field_684c598e49475',
				'label' => 'Create Product',
				'name' => 'sfe_create_product',
				'aria-label' => '',
				'type' => 'true_false',
				'instructions' => 'When the event is saved a product will be created with the same name, price, and featured image.',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
						array(
							'field' => 'field_684a24424c8f9',
							'operator' => '==empty',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => 'Automatically create a product when this event is saved',
				'default_value' => 0,
				'allow_in_bindings' => 0,
				'ui' => 0,
				'ui_on_text' => '',
				'ui_off_text' => '',
			),
			array(
				'key' => 'field_684c59d649477',
				'label' => 'Product Settings',
				'name' => 'sfe_product_settings',
				'aria-label' => '',
				'type' => 'group',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
						array(
							'field' => 'field_684a24424c8f9',
							'operator' => '==empty',
						),
						array(
							'field' => 'field_684c598e49475',
							'operator' => '==',
							'value' => '1',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'layout' => 'block',
				'sub_fields' => array(
					array(
						'key' => 'field_684c5f53d9d16',
						'label' => 'Price',
						'name' => 'price',
						'aria-label' => '',
						'type' => 'number',
						'instructions' => '',
						'required' => 0,
						'conditional_logic' => 0,
						'wrapper' => array(
							'width' => '',
							'class' => '',
							'id' => '',
						),
						'default_value' => '',
						'min' => 0,
						'max' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'step' => '0.01',
						'prepend' => '$',
						'append' => '',
					),
					array(
						'key' => 'field_684c5f13abd8a',
						'label' => 'SKU',
						'name' => 'sku',
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
						'maxlength' => '',
						'allow_in_bindings' => 0,
						'placeholder' => '',
						'prepend' => '',
						'append' => '',
					),
				),
			),
			array(
				'key' => 'field_687033acc2265',
				'label' => 'Total Inventory',
				'name' => 'total_inventory',
				'aria-label' => '',
				'type' => 'number',
				'instructions' => 'This is the number of tickets that can be purchased for the event. Leave blank to allow unlimited tickets. Set to zero to force the product to be out of stock.',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'default_value' => '',
				'min' => 0,
				'max' => '',
				'allow_in_bindings' => 0,
				'placeholder' => '',
				'step' => 1,
				'prepend' => '',
				'append' => '',
			),
			array(
				'key' => 'field_684a24d697f08',
				'label' => 'Registration Details',
				'name' => '',
				'aria-label' => '',
				'type' => 'message',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => array(
					array(
						array(
							'field' => 'field_684a23b04c8f4',
							'operator' => '==',
							'value' => '1',
						),
						array(
							'field' => 'field_684a24424c8f9',
							'operator' => '!=empty',
						),
					),
				),
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'message' => '<div class="sfe-event-details sfe-event-details--loading"><p>Loading registration details&hellip;</p></div>',
				'new_lines' => '',
				'esc_html' => 0,
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'tribe_events',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'acf_after_title',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => true,
		'description' => 'Post Type: Events',
		'show_in_rest' => 0,
	) );
	
	acf_add_local_field_group( array(
		'key' => 'group_684c76a964d10',
		'title' => 'Soulflags Event Settings - General',
		'fields' => array(
			array(
				'key' => 'field_684c76a99375a',
				'label' => 'Product Category',
				'name' => 'product_category',
				'aria-label' => '',
				'type' => 'taxonomy',
				'instructions' => 'This category will be automatically assigned to products that are assigned to an event.',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'taxonomy' => 'product_cat',
				'add_term' => 1,
				'save_terms' => 0,
				'load_terms' => 0,
				'return_format' => 'id',
				'field_type' => 'select',
				'allow_null' => 1,
				'allow_in_bindings' => 0,
				'bidirectional' => 0,
				'multiple' => 0,
				'bidirectional_target' => array(
				),
			),
			array(
				'key' => 'field_686d7d5c35190',
				'label' => 'Default Featured Image',
				'name' => 'default_featured_image_id',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'all',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'allow_in_bindings' => 0,
				'preview_size' => 'medium',
			),
			array(
				'key' => 'field_686d7d6b35191',
				'label' => 'Default Venue',
				'name' => 'default_venue',
				'aria-label' => '',
				'type' => 'post_object',
				'instructions' => 'If an event is not assigned to a venue, this one will be used instead',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'post_type' => array(
					0 => 'tribe_venue',
				),
				'post_status' => '',
				'taxonomy' => '',
				'return_format' => 'id',
				'multiple' => 0,
				'allow_null' => 1,
				'allow_in_bindings' => 0,
				'bidirectional' => 0,
				'ui' => 1,
				'bidirectional_target' => array(
				),
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'options_page',
					'operator' => '==',
					'value' => 'sfe-settings',
				),
			),
		),
		'menu_order' => 0,
		'position' => 'normal',
		'style' => 'default',
		'label_placement' => 'left',
		'instruction_placement' => 'field',
		'hide_on_screen' => '',
		'active' => true,
		'description' => 'Options Page: Soulflags Events',
		'show_in_rest' => 0,
	) );
	
	acf_add_local_field_group( array(
		'key' => 'group_686d79fb1159a',
		'title' => 'Event Type Details',
		'fields' => array(
			array(
				'key' => 'field_686d79fd27e57',
				'label' => 'Default Featured Image',
				'name' => 'default_featured_image_id',
				'aria-label' => '',
				'type' => 'image',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'return_format' => 'id',
				'library' => 'all',
				'min_width' => '',
				'min_height' => '',
				'min_size' => '',
				'max_width' => '',
				'max_height' => '',
				'max_size' => '',
				'mime_types' => '',
				'allow_in_bindings' => 0,
				'preview_size' => 'medium',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'taxonomy',
					'operator' => '==',
					'value' => 'class_type',
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
		'description' => 'Taxonomy: Events > Class Types',
		'show_in_rest' => 0,
	) );
	
} );