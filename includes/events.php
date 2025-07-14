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
		
		// Use a default venue from the Soulflags settings page
		add_filter( 'tribe_get_venue_id', array( $this, 'use_default_venue' ), 10, 2 );
		
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
		if ( !$details ) {
			$message = __( 'Registration details are not yet available for this event.', 'soulflags-events' );
			
			return '<div class="sfe-event-details sfe-event-details--error">' . wpautop( $message ) . '</div>';
		}
		
		// Registration enabled, start building message
		$message = '';
		
		// Add number of registrations
		$message .= sprintf(
			__( 'This event has used %d of %d ticket(s):', 'soulflags-events' ),
			$details['ticket_count'],
			$details['inventory_total']
		);
		
		// Add a list of orders
		$orders = SFE_Registration::get_event_order_ids( $event_post_id );
		if ( $orders ) {
			$message .= '<ul class="ul-disc sfe-event-order-list">';
			foreach( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				$order_status = $order->get_status();
				$order_date = $order->get_date_created();
				$order_date_formatted = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order_date ) );
				$order_date_relative = human_time_diff( strtotime( $order_date ) );
				
				$display_status = ucwords( str_replace( '-', ' ', $order_status ) );
				$order_link = get_edit_post_link( $order_id );
				
				$customer_list = array();
				
				$customer_email = $order->get_billing_email();
				
				foreach( $order->get_items() as $order_item ) {
					if ( $order_item->get_type() === 'line_item' && $order_item->get_meta( '_sfe_tickets' ) ) {
						$tickets = $order_item->get_meta( '_sfe_tickets' );
						
						foreach( $tickets as $ticket_index => $ticket ) {
							$name = isset( $ticket['name'] ) ? esc_html( $ticket['name'] ) : '';
							$age = isset( $ticket['age'] ) ? esc_html( $ticket['age'] ) : '';
							
							$display_key = sprintf( __( 'Ticket #%d', 'soulflags-events' ), $ticket_index + 1 );
							$display_label = trim( $name . ( $age ? ' (age ' . $age . ')' : '' ) ); // Bob (age 30), John Smith (age 25)
							
							$customer_list[] = sprintf(
								'%s: %s',
								$display_key,
								$display_label
							);
						}
						break;
					}
				}
				
				// If no customers for this order
				if ( empty( $customer_list ) ) {
					$customer_submenu = '<ul class="ul-circle"><li>' . __( '(No tickets found)', 'soulflags-events' ) . '</li></ul>';
				}else{
					$customer_submenu = '<ul class="ul-circle"><li>' . implode( '</li><li>', $customer_list ) . '</li></ul>';
				}
				
				$message .= sprintf(
					'<li>Order <a href="%s" target="_blank">#%d</a> (%s) &ndash; <a href="%s" target="_blank">%s</a> &ndash; %s (%s ago) %s</li>',
					esc_url( $order_link ),
					(int)$order_id,
					$display_status,
					esc_attr( 'mailto:' . $customer_email ),
					esc_html( $customer_email ),
					$order_date_formatted,
					$order_date_relative,
					$customer_submenu
				);
			}
			$message .= '</ul>';
		}
		
		// Return formatted message with wrapper
		return '<div class="sfe-event-details sfe-event-details--success">' . wpautop( $message ) . '</div>';
	}
	
	/**
	 * Get the product assigned to an event
	 *
	 * @param int $event_post_id
	 *
	 * @return int|false
	 */
	public static function get_event_product_id( $event_post_id ) {
		if ( !SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Get the product ID associated with the event
		$product_id = (int)get_post_meta( $event_post_id, 'sfe_product_id', true );
		if ( !$product_id ) return false;
		
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
		if ( !SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Get the product ID associated with the event
		$product_id = self::get_event_product_id( $event_post_id );
		if ( !$product_id ) return false;
		
		// Get the product price
		$product = wc_get_product( $product_id );
		if ( !$product || !$product->exists() ) return false;
		
		// Get the product price
		$cost = $product->get_price();
		
		if ( $with_currency_symbol ) {
			$currency_symbol = get_woocommerce_currency_symbol();
			$cost = $currency_symbol . $cost;
		}
		
		return $cost;
	}
	
	/**
	 * Get the remaining stock for an event. Returns NULL if unlimited stock is available.
	 *
	 * @param int $event_post_id
	 *
	 * @return int|null
	 */
	public static function get_stock_remaining( $event_post_id ) {
		if ( !SFE_Registration::is_registration_enabled( $event_post_id ) ) return 0;
		
		$total = self::get_stock_total( $event_post_id );
		if ( $total === null ) return null; // Unlimited stock
		
		$stock_used = self::get_stock_used( $event_post_id );
		
		return max( 0, $total - $stock_used ); // Ensure non-negative stock
	}
	
	/**
	 * Gets the amount of stock used for an event based on current orders and the number of tickets per order.
	 *
	 * @param int $event_post_id
	 *
	 * @return int
	 */
	public static function get_stock_used( $event_post_id ) {
		// @todo: Cache this value until orders are updated or a new order is placed?
		$orders = SFE_Registration::get_event_order_ids( $event_post_id );
		$stock_used = 0;
		
		if ( $orders ) {
			foreach( $orders as $order_id ) {
				$order = wc_get_order( $order_id );
				foreach( $order->get_items() as $order_item ) {
					if ( $order_item->get_type() != 'line_item' ) continue;
					
					$item_event_id = $order_item->get_meta( '_sfe_event_id' );
					$item_tickets = $order_item->get_meta( '_sfe_tickets' );
					
					// Check if it's the correct event
					if ( $item_event_id != $event_post_id ) continue;
					
					// Add the number of tickets
					if ( is_array( $item_tickets ) && count( $item_tickets ) > 0 ) {
						$stock_used += count( $item_tickets );
					}
				}
			}
		}
		
		return $stock_used;
	}
	
	/**
	 * Gets the stock available for an event.
	 * Returns the number of available tickets for the event (which may be zero), or null if unlimited tickets are allowed.
	 *
	 * @param $event_post_id
	 *
	 * @return int|null
	 */
	public static function get_stock_total( $event_post_id ) {
		if ( ! SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		$inventory = get_field( 'total_inventory', $event_post_id );
		
		// If blank = Unlimited tickets
		if ( $inventory === '' ) return null;
		
		// Ensure it's a non-negative integer
		return max( 0, (int) $inventory );
	}
	
	/**
	 * Get the stock HTML for an event
	 *
	 * @param int $event_post_id
	 * @param int|null|false $stock_remaining The remaining stock as an int or null. Leave blank to fetch it automatically (false).
	 *
	 * @return string
	 */
	public static function get_event_stock_html( $event_post_id, $stock_remaining = false ) {
		if ( ! SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Hide stock for expired events
		if ( self::is_event_expired( $event_post_id ) ) return 'Event expired';
		
		// Get stock remaining, if not provided by 2nd arg
		if ( $stock_remaining === false ) $stock_remaining = self::get_stock_remaining( $event_post_id );
		
		// If unlimited stock, return null
		if ( $stock_remaining === null ) return null;
		
		return sprintf(
			_n('%d ticket available', '%d tickets available', $stock_remaining, 'soulflags-events'),
			$stock_remaining
		);
	}
	
	/**
	 * Gets stock html based on woocommerce templates/single-product/stock.php
	 *
	 * @param int $amount
	 *
	 * @return string
	 */
	public static function get_stock_html( $amount, $in_stock_label = '%d in stock', $out_of_stock_label = 'Out of stock' ) {
		$class = $amount > 0 ? 'in-stock' : 'out-of-stock';
		
		if ( $amount > 0 ) {
			$label = __( $in_stock_label, 'soulflags-events' );
		}else{
			$label = __( $out_of_stock_label, 'soulflags-events' );
		}
		
		return '<p class="stock '. esc_attr( $class ) .'">'. sprintf( $label, $amount ) .'</p>';
	}
	
	/**
	 * Checks if an event is available for purchase
	 *
	 * @param int $event_post_id
	 *
	 * @return bool
	 */
	public static function is_available_for_purchase( $event_post_id ) {
		// Check if valid event
		if ( ! SFE_Registration::is_registration_enabled( $event_post_id ) ) return false;
		
		// Check if not expired
		if ( self::is_event_expired( $event_post_id ) ) return false;
		
		// Check if stock is available
		$stock_remaining = self::get_stock_remaining( $event_post_id );
		if ( $stock_remaining === null ) return true;
		
		return $stock_remaining > 0;
	}
	
	/**
	 * Checks if an event is expired based on its start and end date (whichever is greater). Events expire at the end of their last day.
	 *
	 * @param int $event_post_id
	 * @param string|null $start_date Optional start date in Y-m-d format. If not provided, it will be fetched from post meta.
	 * @param string|null $end_date   Optional end date in Y-m-d format. If not provided, it will be fetched from post meta.
	 *
	 * @return bool
	 */
	public static function is_event_expired( $event_post_id, $start_date = null, $end_date = null ) {
		// If start and end dates are not provided, get them from post meta
		if ( $end_date === null ) $end_date = get_post_meta( $event_post_id, '_EventEndDate', true );
		if ( $start_date === null ) $start_date = get_post_meta( $event_post_id, '_EventStartDate', true );
		
		// Prefer to use the end date if available, otherwise use the start date
		$date_ymd = $end_date ?: $start_date;
		if ( ! $date_ymd ) return false; // No date, doesn't expire
		
		// Check if it expired
		$now_ts = current_time( 'timestamp' );
		$date_ts = strtotime( $date_ymd );
		
		return $date_ts < $now_ts;
	}
	
	/**
	 * Create a WooCommerce product for an event.
	 *
	 * @param int $event_post_id The ID of the event post.
	 * @param float $price The price of the product.
	 * @param string $sku The SKU for the product.
	 * @param int $stock_quantity The stock quantity for the product.
	 *
	 * @return int|false The ID of the created product or false on failure.
	 */
	public static function create_product_for_event( $event_post_id, $price = 0.00, $sku = '', $stock_quantity = 0 ) {
		$event_title = get_the_title( $event_post_id );
		$event_url = get_permalink( $event_post_id );
		$featured_image_id = get_post_thumbnail_id( $event_post_id );
		
		// Set up initial product metadata including the product type
		$meta = array(
			'_sfe_event_id' => $event_post_id, // Store the event ID in product meta
			'_sku' => $sku,
			'_price' => $price,
			'_regular_price' => $price,
			'_stock' => $stock_quantity,
			'_manage_stock' => $stock_quantity === -1 ? 'no' : 'yes', // If stock quantity is -1, don't manage stock
			'_stock_status' => $stock_quantity != 0 ? 'instock' : 'outofstock',
			'_visibility' => 'hidden', // Hide from catalog
			'_thumbnail_id' => $featured_image_id, // Store the featured image ID
			'_virtual' => 'no', // Mark as virtual product
			'_downloadable' => 'no', // Not downloadable
			'_sold_individually' => 'no', // Allow multiple quantity
			'_backorders' => 'no', // Do not allow backorders
		);
		
		// Create a product associated with the event
		$product_data = array(
			'post_title' => $event_title,
			'post_content' => 'Event: <a href="'. esc_url($event_url) .'">' . esc_html($event_title) . '</a>',
			'post_status' => 'publish',
			'post_type' => 'product',
			'meta_input' => $meta,
		);
		
		try {
			
			// Insert the product as a post
			$product_id = wp_insert_post( $product_data );
			if ( is_wp_error( $product_id ) ) throw new Exception( $product_id->get_error_message() );
			
			// Get product and save it again using WooCommerce to ensure all metadata is set correctly
			$product = wc_get_product( $product_id );
			$product->save();
			
			// Assign the default product category (from Soulflags Events > Product Category)
			$category_term_id = get_field( 'product_category', 'sfe_settings' );
			if ( $category_term_id ) wp_set_post_terms( $product_id, $category_term_id, 'product_cat' );
			
		}catch( Exception $e ) {
			
			// Handle any errors that occur while getting the product object
			// This is used in a notice on the Event screen
			/** @see SFE_Events::display_product_created_failed_notice() */
			update_post_meta( $event_post_id, '_sfe_product_creation_error', $e->getMessage() );
			return false;
			
		}
		
		return $product_id;
	}
	
	/**
	 * Get a formatted date range for an event. This date range will be succinct, only showing the year if it is different from the current year (for example).
	 *
	 * @param int $event_post_id
	 * @param bool $force_year   Default false, which will hide the year if it's the same as the current year. Set to true to always display the year.
	 * @param bool $allow_html   Default true, which will include the attribute <time datetime="..."> surrounding the date.
	 *
	 * @return string|false
	 */
	public static function get_event_date_range( $event_post_id, $force_year = false, $allow_html = true ) {
		$event_start_date = get_post_meta( $event_post_id, '_EventStartDate', true );
		$event_end_date = get_post_meta( $event_post_id, '_EventEndDate', true );
		if ( ! $event_start_date && ! $event_end_date ) return false;
		
		// Compare the dates to see how specific we need to be
		$end_ts = strtotime( $event_end_date );
		$start_ts = strtotime( $event_start_date );
		$now_ts = time();
		
		// Comparing year also compares to the current year
		$diff_year = date('Y', min($end_ts, $start_ts, $now_ts)) != date('Y', max($end_ts, $start_ts, $now_ts));
		if ( $force_year ) $diff_year = true;
		
		// Month and day only compare the start and end dates (not the current date)
		$diff_month = date('F', $start_ts) != date('F', $end_ts);
		
		// Check if both dates are the same, in order to show just a single date.
		$same_dates = date('Y-m-d', $start_ts) === date('Y-m-d', $end_ts);
		
		// Only include necessary details
		if ( $same_dates ) {
			// Single date display
			if ( $diff_year ) {
				$date_range = date_i18n( 'F j, Y', $start_ts );
			} else {
				$date_range = date_i18n( 'F j', $start_ts );
			}
		}else{
			// Date range display
			if ( $diff_year ) {
				$date_range = date_i18n( 'F j, Y', $start_ts ) . ' &ndash; ' . date_i18n( 'F j, Y', $end_ts );
			} elseif ( $diff_month ) {
				$date_range = date_i18n( 'F j', $start_ts ) . ' &ndash; ' . date_i18n( 'F j', $end_ts );
			} else {
				$date_range = date_i18n( 'F j', $start_ts ) . '&ndash;' . date_i18n( 'j', $end_ts );
			}
		}
		
		if ( $allow_html ) {
			// Add <time> HTML tag with datetime attribute
			$date_range = sprintf(
				'<time datetime="%s">%s</time>',
				date( 'Y-m-d', $start_ts ),
				$date_range
			);
		}
		
		return $date_range;
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
				
				echo '<span class="sfe-event-registration-summary">';
				
				$icon_available = '<span class="dashicons dashicons-yes-alt"></span> <span class="screen-reader-text">' . __( 'Registration Enabled', 'soulflags-events' ) . '</span>';
				$icon_outofstock = '<span class="dashicons dashicons-no-alt"></span> <span class="screen-reader-text">' . __( 'Registration Disabled', 'soulflags-events' ) . '</span>';
				
				$total = self::get_stock_total( $post_id );
				if ( $total === null ) {
					// Unlimited stock
					echo $icon_available;
					echo __( 'Unlimited', 'soulflags-events' );
				} else {
					$remaining = self::get_stock_remaining( $post_id );
					
					if ( $remaining === 0 ) {
						echo $icon_outofstock;
					} else {
						echo $icon_available;
					}
					
					echo sprintf( __( '%d of %d Tickets Available', 'soulflags-events' ), $remaining, $total );
					
					if ( $remaining === 0 ) {
						// Out of stock
						echo '<br>';
						echo '<em>' . __( 'Out of Stock', 'soulflags-events' ) . '</em>';
					}
				}
				
				echo '</span>';
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
		
		$price = $settings['price'] ?? false;
		$sku = $settings['sku'] ?? false;
		
		/*
		$limited_stock = $settings['limited_stock'] ?? false;
		$stock_quantity = $settings['stock_quantity'] ?? 0;
		
		// Validate product settings
		if ( ! $limited_stock ) {
			$stock_quantity = -1; // -1 means unlimited stock
		}
		*/
		
		// Now stock is managed using an ACF field on the event (total_inventory)
		// The WooCommerce product will always have unlimited stock
		$stock_quantity = -1; // -1 means unlimited stock
		
		// Create the product associated with the event
		$product_id = self::create_product_for_event( $event_post_id, $price, $sku, $stock_quantity );
		
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
		
		// Hide add to cart button if the event is expired
		if ( self::is_event_expired($event_post_id) ) return;
		
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
		$stock = self::get_event_stock_html( $event_post_id );
		
		if ( $stock ) {
			$stock = wp_strip_all_tags( $stock );
			$cost .= ' &ndash; ' . wp_strip_all_tags( $stock );
		}
		
		return $cost;
	}
	
	/**
	 * Use a default venue from the Soulflags settings page
	 *
	 * @param int|false $venue_id The Venue ID for the specified event.
	 * @param int $post_id The ID of the event whose venue is being looked for.
	 *
	 * @return int|false
	 */
	public function use_default_venue( $venue_id, $post_id = null ) {
		// Keep pre-defined venue
		if ( $venue_id ) return $venue_id;
		
		$default_venue_id = get_field( 'default_venue', 'sfe_settings' );
		
		return $default_venue_id ?: false;
	}
	
}

SFE_Events::get_instance();