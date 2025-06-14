<?php

class SFE_Events {
	
	public function __construct() {
	
		// Add a column to the tribe_events post type to indicate if registration is enabled
		add_filter( 'manage_tribe_events_posts_columns', array( $this, 'add_event_columns' ) );
		
		// Display the custom column on the tribe_events post type screen
		add_action( 'manage_tribe_events_posts_custom_column', array( $this, 'display_event_columns' ), 10, 2 );
		
		// Add an ajax action to get registration details
		add_action( 'wp_ajax_sfe_get_registration_details', array( $this, 'ajax_get_registration_details' ) );
		
		// Add an ajax action to get registration details message HTML
		add_action( 'wp_ajax_sfe_get_registration_details_message_html', array( $this, 'ajax_get_registration_details_message_html' ) );
		
		// When saving an event, optionally create a product associated with the event
		add_action( 'acf/save_post', array( $this, 'maybe_create_product' ) );
		
		// Display a notice that the product was created
		add_action( 'admin_notices', array( $this, 'display_product_created_notice' ) );
		
		// Display a notice if the product creation failed
		add_action( 'admin_notices', array( $this, 'display_product_created_failed_notice' ) );
		
		// Replace the "Event Cost" with the price of the assigned product
		add_filter( 'tribe_events_event_cost', array( $this, 'replace_event_cost_with_product_price' ), 10, 2 );
		add_filter( 'tribe_get_cost', array( $this, 'replace_event_cost_with_product_price' ), 10, 3 );
		
		// Add a button to add to cart on the event page
		add_action( 'tribe_events_single_event_after_the_content', array( $this, 'insert_add_to_cart_button_on_event' ), 7 );
		
		// Add stock label to the Event Cost
		add_filter( 'tribe_get_formatted_cost', array( $this, 'insert_stock_in_event_cost' ) );
		
		// Add Add to Cart button on calendar list view
		add_action( 'tec_events_view_venue_after_address', array( $this, 'insert_add_to_cart_button_on_event' ), 20 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Gets an HTML message used to display on the Edit Event page to summarize the registration details.
	 *
	 * @param int $event_post_id
	 *
	 * @return string
	 */
	public static function get_registration_details_message_html( $event_post_id ) {
		$details = SFE_Registration::get_registration_details( $event_post_id );
		
		// Registration not enabled
		if ( ! $details ) {
			$message = __( 'Registration details are not yet available for this event.', 'soulflags-events' );
			return '<div class="sfe-event-details sfe-event-details--error">'. wpautop( $message ) .'</div>';
		}
		
		// Registration enabled, start building message
		$message = '';
		
		// Add number of registrations
		$message .= sprintf(
			_n(
				__( 'This event has <strong>%d</strong> registration:', 'soulflags-events' ),
				__( 'This event has <strong>%d</strong> registrations:', 'soulflags-events' ),
				$details['registered_count']
			),
			$details['registered_count']
		);
		
		// Add a list of orders
		$orders = SFE_Registration::get_event_order_ids( $event_post_id );
		if ( $orders ) {
			$message .= '<ul class="ul-disc sfe-event-order-list">';
			foreach( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				$order_status = $order->get_status();
				$order_date = $order->get_date_created();
				$order_date_formatted = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime($order_date) );
				$order_date_relative = human_time_diff( strtotime($order_date) );
				
				$display_status = ucwords(str_replace('-', ' ', $order_status));
				$order_link = get_edit_post_link( $order_id );
				
				$customer_submenu = '<ul class="ul-circle"><li>' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . ' &lt;' . $order->get_billing_email() . '&gt;</li>';
				
				$message .= sprintf(
					'<li>Order <a href="%s" target="_blank">#%d</a> (%s) &ndash; %s (%s ago) %s</li>',
					esc_url( $order_link ),
					(int) $order_id,
					$display_status,
					$order_date_formatted,
					$order_date_relative,
					$customer_submenu
				);
			}
			$message .= '</ul>';
		}
		
		// Return formatted message with wrapper
		return '<div class="sfe-event-details sfe-event-details--success">'. wpautop( $message ) .'</div>';
	}
	
	/**
	 * Get the product assigned to an event
	 *
	 * @param int $event_post_id
	 *
	 * @return int|false
	 */
	public static function get_event_product_id( $event_post_id ) {
		if ( ! SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Get the product ID associated with the event
		$product_id = (int) get_post_meta( $event_post_id, 'sfe_product_id', true );
		if ( ! $product_id ) return false;
		
		// Check if the product exists
		if ( get_post_type( $product_id ) !== 'product' ) {
			return false; // Product does not exist or is not a product
		}
		
		return $product_id;
	}
	
	/**
	 * Get the price of an event (only for events that use the registration system)
	 *
	 * @param int $event_post_id
	 * @param bool $with_currency_symbol
	 *
	 * @return string|false
	 */
	public static function get_event_price( $event_post_id, $with_currency_symbol = true ) {
		if ( ! SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Get the product ID associated with the event
		$product_id = self::get_event_product_id( $event_post_id );
		if ( ! $product_id ) return false;
		
		// Get the product price
		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->exists() ) return false;
		
		// Get the product price
		$cost = $product->get_price();
		
		if ( $with_currency_symbol ) {
			$currency_symbol = get_woocommerce_currency_symbol();
			$cost = $currency_symbol . $cost;
		}
		
		return $cost;
	}
	
	// Actions
	/**
	 * Add a column to the tribe_events post type to indicate if registration is enabled
	 *
	 * @param array $columns
	 * @return array
	 */
	public function add_event_columns( $columns ) {
		$new_columns = array(
			'sfe_registration_enabled' => __( 'Event Registration', 'soulflags-events' ),
		);
		
		if ( isset($columns['tags']) ) unset($columns['tags']);
		
		// Add after 2nd column
		return array_slice( $columns, 0, 2, true ) +
			$new_columns +
			array_slice( $columns, 2, null, true );
	}
	
	/**
	 * Display the custom column on the tribe_events post type screen
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	public function display_event_columns( $column, $post_id ) {
		if ( $column === 'sfe_registration_enabled' ) {
			$registration_enabled = get_post_meta( $post_id, 'sfe_registration_enabled', true );
			if ( $registration_enabled ) {
				echo '<span class="dashicons dashicons-yes-alt"></span> <span class="screen-reader-text">' . __( 'Registration Enabled', 'soulflags-events' ) . '</span>';
			} else {
				echo '<span class="screen-reader-text">' . __( 'Registration Disabled', 'soulflags-events' ) . '</span>';
			}
		}
	}
	
	/**
	 * Ajax handler to get registration details
	 */
	public function ajax_get_registration_details() {
		// Validate nonce "sfe-get-registration-details"
		$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : 0;
		if ( ! wp_verify_nonce( $nonce, 'sfe-get-event-details' ) ) {
			wp_send_json_error( __( 'Session expired. Reload the page and try again.', 'soulflags-events' ) );
		}
		
		// Get the event post ID and check it is valid
		$event_post_id = isset( $_REQUEST['event_post_id'] ) ? intval( $_REQUEST['event_post_id'] ) : 0;
		if ( ! $event_post_id || get_post_type( $event_post_id ) !== 'tribe_events' ) {
			wp_send_json_error( sprintf( __( 'Invalid event specified (#%s).', 'soulflags-events' ), esc_html($event_post_id) ) );
		}
		
		// Check if registration is enabled
		$enabled = SFE_Registration::is_registration_enabled( $event_post_id );
		if ( ! $enabled ) {
			$result = array(
				'registration_enabled' => false,
			);
			wp_send_json_success( $result );
		}
		
		// Get the registration details
		$details = SFE_Registration::get_registration_details( $event_post_id );
		if ( ! $details ) {
			wp_send_json_error( __( 'Registration is not enabled this event, or the details could not be loaded.', 'soulflags-events' ) );
		}
		
		$details['registration_enabled'] = true;
		wp_send_json_success( $details );
	}
	
	/**
	 * Ajax handler to get registration details
	 */
	public function ajax_get_registration_details_message_html() {
		// Validate nonce "sfe-get-registration-details"
		$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : 0;
		if ( ! wp_verify_nonce( $nonce, 'sfe-edit-event' ) ) {
			wp_send_json_error( __( 'Session expired. Reload the page and try again.', 'soulflags-events' ) );
		}
		
		// Get the event post ID and check it is valid
		$event_post_id = isset( $_REQUEST['event_post_id'] ) ? intval( $_REQUEST['event_post_id'] ) : 0;
		if ( ! $event_post_id || get_post_type( $event_post_id ) !== 'tribe_events' ) {
			wp_send_json_error( sprintf( __( 'Invalid event specified (#%s).', 'soulflags-events' ), esc_html($event_post_id) ) );
		}
		
		// Send the registration details
		$message = self::get_registration_details_message_html( $event_post_id );
		wp_send_json_success( $message );
	}
	
	/**
	 * When saving an event, optionally create a product associated with the event
	 *
	 * @param string $acf_id
	 */
	public function maybe_create_product( $acf_id ) {
		if ( ! is_numeric($acf_id) ) return;
		if ( get_post_type( $acf_id ) !== 'tribe_events' ) return;
		
		$event_post_id = (int) $acf_id;
		
		// Check if the product should be created
		$create_product = get_field( 'sfe_create_product', $event_post_id );
		if ( ! $create_product ) return;
		
		// Get product settings
		$settings = get_field( 'sfe_product_settings', $event_post_id );
		
		$limited_stock = $settings['limited_stock'] ?? false;
		$price = $settings['price'] ?? false;
		$sku = $settings['sku'] ?? false;
		$stock_quantity = $settings['stock_quantity'] ?? 0;
		
		// Validate product settings
		if ( ! $limited_stock ) {
			$stock_quantity = -1; // -1 means unlimited stock
		}
		
		// Create the product associated with the event
		$product_id = SFE_Products::create_product_for_event( $event_post_id, $price, $sku, $stock_quantity );
		
		if ( $product_id ) {
			// Update the event post with the product ID
			update_field( 'sfe_product_id', $product_id, $event_post_id );
			
			// Clear the Create Product and Product Settings fields
			update_field( 'sfe_create_product', false, $event_post_id );
			update_field( 'sfe_product_settings', array(), $event_post_id );
			
			// Add post meta used to display a notice
			update_post_meta( $event_post_id, 'sfe_product_created', date('Y-m-d H:i:s') );
		}
	}
	
	/**
	 * Display a notice that the product was created
	 */
	public function display_product_created_notice() {
		if ( ! is_admin() ) return;
		
		// Check if the current screen is the tribe_events post type
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'tribe_events' ) return;
		
		// Check if the product was created
		$event_post_id = get_the_ID();
		
		$product_id = get_post_meta( $event_post_id, 'sfe_product_id', true );
		$created_date = get_post_meta( $event_post_id, 'sfe_product_created', true );
		if ( ! $product_id || ! $created_date ) return; // No product created notice to display
		
		// Clear the notice for next time
		delete_post_meta( $event_post_id, 'sfe_product_created' );
		
		// Check if created in the last 1 hour
		$created_time = strtotime( $created_date );
		if ( abs( time() - $created_time ) > HOUR_IN_SECONDS ) return;
		
		$edit_link = get_edit_post_link( $product_id );
		
		$message = __( 'Product created successfully for this event.', 'soulflags-events' );
		$message .= "\n\n";
		$message .= sprintf(
			'<a href="%s" class="button button-secondary" target="_blank">View Product</a>',
			esc_url( $edit_link )
		);
		
		echo '<div class="notice notice-success is-dismissible">' . wpautop( $message ) . '</div>';
	}
	
	/**
	 * Display a notice if the product creation failed
	 */
	public function display_product_created_failed_notice() {
		if ( ! is_admin() ) return;
		
		// Check if the current screen is the tribe_events post type
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen || $screen->id !== 'tribe_events' ) return;
		
		// Check if the product was created
		$event_post_id = get_the_ID();
		
		$error = get_post_meta( $event_post_id, '_sfe_product_creation_error', true );
		if ( ! $error ) return;
		
		// Display the notice
		delete_post_meta( $event_post_id, '_sfe_product_creation_error' );
		
		$message = __( 'Failed to create product:', 'soulflags-events' );
		$message .= "\n\n";
		$message .= '<em>'. $error . '</em>';
		
		echo '<div class="notice notice-success">' . wpautop( $message ) . '</div>';
	}
	
	/**
	 * Replace the "Event Cost" with the price of the assigned product
	 *
	 * @param string $cost
	 * @param int $event_post_id
	 * @param bool $with_currency_symbol
	 * @return string
	 */
	public static function replace_event_cost_with_product_price( $cost, $event_post_id, $with_currency_symbol = true ) {
		$event_price = self::get_event_price( $event_post_id, $with_currency_symbol );
		return $event_price === false ? $cost : $event_price;
	}
	
	/**
	 * Add a button to add to cart on the event page
	 * @return void
	 */
	public function insert_add_to_cart_button_on_event() {
		global $event_post_id, $product_id, $product;
		
		$event_post_id = get_the_ID();
		$product_id = self::get_event_product_id( $event_post_id );
		$product = $product_id ? wc_get_product( $product_id ) : false;
		
		if ( $product ) {
			include SFE_PATH . '/templates/add-to-cart.php';
		}
	}
	
	/**
	 * Add stock label to the Event Cost
	 *
	 * @param $cost
	 *
	 * @return mixed|string
	 */
	public function insert_stock_in_event_cost( $cost ) {
		$event_post_id = get_the_ID();
		$product_id = self::get_event_product_id( $event_post_id );
		$product = $product_id ? wc_get_product( $product_id ) : false;
		
		if ( $product ) {
			$stock = wc_get_stock_html( $product );
			$stock = wp_strip_all_tags( $stock );
			
			if ( $stock ) {
				$cost .= ' &ndash; ' . wp_strip_all_tags( $stock );
			}
		}
		
		return $cost;
	}
	
}

SFE_Events::get_instance();