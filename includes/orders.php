<?php

class SFE_Orders {
	
	public function __construct() {
		
		// Remove event products from the cart if they are not assigned the required event ID in cart meta
		add_action( 'woocommerce_check_cart_items', array( $this, 'remove_invalid_event_products_from_cart' ) );
		
		// Add a WooCommerce notice if an event product was removed from the cart based on the URL parameter ?sfe_removed_from_cart
		add_action( 'et_before_main_content', array( $this, 'maybe_notify_removed_from_cart' ) );
		
		// Display a notice about the product being removed in The Events Calendar notices area.
		add_filter( 'tec_events_single_event_id', array( $this, 'maybe_notify_in_the_event_calendar_notices' ), 10, 2 );
		
		// Prevent product from being purchasable on the single product page (you must buy it through the event page)
		add_filter( 'woocommerce_is_purchasable', array( $this, 'prevent_product_purchase' ), 10, 2 );
		
		// Add a hidden field to the Add to Cart form to pass the event ID
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_event_id_field' ) );
		
		// When added to cart, also add the event ID to the cart item data
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_event_id_to_cart_item_data' ), 10, 2 );
		
		// Display the event ID cart meta on the cart page
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_meta' ), 10, 2 );
		
		// Load the event ID from session cart item data. Without this, the event ID would not persist when the cart is reloaded.
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'load_event_id_from_session' ), 10, 2 );
		
		// Add the event ID to the order item metadata.
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_event_id_to_order_item_meta' ), 10, 3 );
		
		// Display the event ID in the admin order item details.
		add_filter( 'woocommerce_display_item_meta', array( $this, 'display_event_meta_on_order' ), 10, 3 );
		
		// Change "5 in stock" to "5 seats available"
		add_filter( 'woocommerce_get_availability', array( $this, 'get_stock_availability_language' ), 10, 2 );
		
		// Change "Add to Cart" buttons to go directly to the checkout page
		add_filter( 'woocommerce_add_to_cart_form_action', array( $this, 'add_to_cart_to_checkout' ) );
		
		// Format the event details on the admin order item meta
		add_action( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'format_admin_event_meta' ), 15, 2 );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		
		return self::$instance;
	}
	
	// Utilities
	/**
	 * Gets the event ID of the current page.
	 * @return int|false
	 */
	public function get_current_event_id() {
		$event_post_id = get_the_ID();
		if ( ! $event_post_id ) return false;
		if ( get_post_type( $event_post_id ) !== 'tribe_events' ) return false;
		
		return $event_post_id;
	}
	
	/**
	 * Checks if a product is an event product
	 * @param WC_Product|int $product
	 * @return bool
	 */
	public static function is_event_product( $product ) {
		// Get the product
		if ( is_numeric( $product ) ) $product = wc_get_product( $product );
		if ( ! is_a( $product, 'WC_Product' ) ) return false;
		
		// Check if the product is assigned the Event Product Category
		$term_id = (int) get_field( 'product_category', 'sfe_settings' );
		if ( ! $term_id ) return false;
		
		// Check if the product is assigned that term (product_cat)
		if ( ! has_term( $term_id, 'product_cat', $product->get_id() ) ) return false;
		
		return true;
	}
	
	/**
	 * Check if an event product is in the cart and returns the event ID if it is.
	 *
	 * @param int[]|null $product_ids The product ID(s) to look for. Default null, which checks for any event products.
	 *
	 * @return array[]|null {
	 *     @type string $cart_item_key
	 *     @type array $cart_item
	 *     @type int $product_id
	 *     @type int $event_post_id
	 *     @type int $quantity
	 * }
	 */
	public static function get_event_products_from_cart( $product_ids = null ) {
		if ( ! WC()->cart ) return null;
		
		$event_products = array();
		
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$cart_product_id = $cart_item['product_id'] ?? false;
			if ( ! $cart_product_id ) continue;
			
			$check_item = ( $product_ids === null || in_array( (int) $cart_product_id, $product_ids, true ) );
			
			if ( $check_item ) {
				if ( isset( $cart_item['sfe_event_id'] ) && is_numeric( $cart_item['sfe_event_id'] ) ) {
					$event_products[ $cart_item['key'] ] = array(
						'cart_item_key' => $cart_item['key'],
						'cart_item' => &$cart_item, // By reference
						'product_id' => $cart_product_id,
						'event_post_id' => (int) $cart_item['sfe_event_id'],
						'quantity' => $cart_item['quantity'] ?? 1,
					);
				}
			}
		}
		
		return $event_products;
	}
	
	/**
	 * Gets an event product from the cart by its cart item key. Returns false if invalid or not an event product.
	 *
	 * @param string $cart_item_key
	 *
	 * @return array|false {
	 *     @type string $cart_item_key
	 *     @type array $cart_item
	 *     @type int $product_id
	 *     @type int $event_post_id
	 *     @type int $quantity
	 * }
	 */
	public static function get_event_product_from_cart_by_key( $cart_item_key ) {
		if ( ! WC()->cart ) return null;
		
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		
		if ( $cart_item ) {
			$cart_product_id = $cart_item['product_id'] ?? false;
			if ( ! $cart_product_id ) return false;
			
			if ( ! self::is_event_product( $cart_product_id ) ) return false;
			
			if ( isset( $cart_item['sfe_event_id'] ) && is_numeric( $cart_item['sfe_event_id'] ) ) {
				return array(
					'cart_item_key' => $cart_item_key,
					'cart_item' => $cart_item,
					'product_id' => $cart_product_id,
					'event_post_id' => (int) $cart_item['sfe_event_id'],
					'quantity' => $cart_item['quantity'] ?? 1,
				);
			}
		}
		
		return false;
	}
	
	// Actions
	
	/**
	 * Remove event products from the cart if they are not assigned the required event ID in cart meta
	 *
	 * @return void
	 */
	public function remove_invalid_event_products_from_cart() {
		$cart_items = WC()->cart->get_cart();
		
		// Variables to track removed items
		$redirect_url = false;
		$removed_event_id = false;
		$removed_reason = false;
		
		// Iterate through cart items
		if ( $cart_items ) foreach ( $cart_items as $key => $cart_item ) {
			$product_id = $cart_item['product_id'] ?? false;
			if ( ! $product_id ) continue;
			
			// Only check event products
			if ( ! self::is_event_product( $product_id ) ) continue;
			
			try {
				
				// Get the event ID from cart meta (required)
				$event_post_id = $cart_item['sfe_event_id'] ?? false;
				
				// Check if event is valid
				if ( ! $event_post_id || get_post_type( $event_post_id ) !== 'tribe_events' ) {
					throw new Exception('invalid_event');
				}
				
				// Check if the event is expired
				if ( SFE_Events::is_event_expired( $event_post_id ) ) {
					throw new Exception('event_expired');
				}
				
			} catch ( Exception $e ) {
				
				// If an exception was thrown, it means the event is invalid or expired and the product should be removed from the cart
				$removed_event_id = $event_post_id;
				$removed_reason = $e->getMessage();
				
				// Remove the item from the cart if it does not match
				wc()->cart->remove_cart_item( $key );
				
				// If the event post id was provided, redirect to that page after
				if ( $removed_event_id ) {
					$redirect_url = get_permalink( $removed_event_id );
					$redirect_url = add_query_arg( 'sfe_removed_from_cart', $removed_event_id, $redirect_url );
					$redirect_url = add_query_arg( 'sfe_removed_reason', $removed_reason, $redirect_url );
				}
				
			}
		}
		
		// If a product was removed, display a notice or redirect
		if ( $removed_event_id ) {
			if ( $redirect_url ) {
				// Redirect to the event page, which will display a notice
				/** @see SFE_Orders::maybe_notify_removed_from_cart() */
				wp_redirect( $redirect_url );
				exit;
			}else{
				// Display a notice
				wc_add_notice(
					self::get_event_removed_from_cart_message( $removed_event_id, $removed_reason ),
					'error'
				);
				
				wc_print_notices();
			}
		}
	}
	
	/**
	 * Gets a string message to be displayed when an event product is removed from the cart.
	 *
	 * @param int $event_post_id
	 * @param string $reason_code One of: invalid_event, event_expired
	 *
	 * @return string
	 */
	public static function get_event_removed_from_cart_message( $event_post_id, $reason_code ) {
		switch( $reason_code ) {
			case 'invalid_event':
				$message = __( 'You must purchase this product through the event page.', 'soulflags-events' );
				break;
				
			case 'event_expired':
			default:
				$message = __( 'This event is no longer available '. $reason_code .'.', 'soulflags-events' );
				break;
		}
		
		return sprintf(
			'<strong>%s removed from cart:</strong> %s',
			get_the_title( $event_post_id ),
			esc_html( $message )
		);
	}
	
	/**
	 * Adds a notice if an event product was removed from the cart based on the URL parameter ?sfe_removed_from_cart
	 * @return void
	 */
	public function maybe_notify_removed_from_cart() {
		if ( ! isset( $_GET['sfe_removed_from_cart'] ) ) return;
		
		// Do not display this notice on a single event page (use the event calendar notices instead)
		if ( is_singular( 'tribe_events' ) ) return;
		
		$event_post_id = intval( $_GET['sfe_removed_from_cart'] );
		$reason = sanitize_text_field( $_GET['sfe_removed_reason'] );
		if ( ! $event_post_id ) return;
		
		$message = self::get_event_removed_from_cart_message( $event_post_id, $reason );
		
		// Add a notice to be displayed
		wc_add_notice(
			$message,
			'error'
		);
		
		wc_print_notices();
	}
	
	/**
	 * Display a notice about the product being removed in The Events Calendar notices area.
	 *
	 * @param int $event_post_id
	 *
	 * @return int
	 */
	public function maybe_notify_in_the_event_calendar_notices( $event_post_id ) {
		if ( ! isset( $_GET['sfe_removed_from_cart'] ) ) return $event_post_id;
		
		$removed_event_id = intval( $_GET['sfe_removed_from_cart'] );
		if ( $removed_event_id !== $event_post_id ) return $event_post_id;
		
		// Get the reason for removal
		$reason = sanitize_text_field( $_GET['sfe_removed_reason'] );
		
		// Get the message
		$message = self::get_event_removed_from_cart_message( $event_post_id, $reason );
		
		// Add a notice to The Events Calendar
		Tribe__Notices::set_notice( 'sfe_event_removed_from_cart', $message );
		
		return $event_post_id;
	}
	
	/**
	 * Prevents event products from being purchasable on the single product page.
	 * @param bool $is_purchasable Whether the product is purchasable.
	 * @param WC_Product $product The product object.
	 * @return bool
	 */
	public function prevent_product_purchase( $is_purchasable, $product ) {
		if ( ! self::is_event_product( $product ) ) return $is_purchasable;
		
		// Prevent purchase on the single product page
		if ( is_product() && did_action( 'woocommerce_before_single_product' ) ) return false;
		
		return $is_purchasable;
	}
	
	/**
	 * Adds a hidden field to the WooCommerce Add to Cart form to pass the event ID.
	 * @return void
	 */
	public function add_event_id_field() {
		global $product;
		if ( ! isset($product) ) return;
		if ( ! self::is_event_product( $product ) ) return;
		
		$event_post_id = $this->get_current_event_id();
		
		// Output a hidden field with the event ID
		if ( $event_post_id ) {
			echo '<input type="hidden" name="sfe_event_id" value="' . esc_attr( $event_post_id ) . '" />';
		}
	}
	
	/**
	 * Adds the event ID to the cart item data when the product is added to the cart.
	 * @param array $cart_item_data The cart item data.
	 * @param int $product_id The product ID.
	 * @return array The modified cart item data.
	 */
	public function add_event_id_to_cart_item_data( $cart_item_data, $product_id ) {
		if ( ! self::is_event_product( $product_id ) ) return $cart_item_data;
		
		// Get the event ID from the hidden field
		if ( isset( $_POST['sfe_event_id'] ) && is_numeric( $_POST['sfe_event_id'] ) ) {
			$cart_item_data['sfe_event_id'] = (int) $_POST['sfe_event_id'];
		}
		
		// Since a new product is being added to the cart, ignore the purchase validation
		remove_filter( 'woocommerce_is_purchasable', array( $this, 'prevent_product_purchase' ), 10, 2 );
		
		return $cart_item_data;
	}
	
	/**
	 * Displays the event ID in the cart item data on the cart page.
	 *
	 * @param array $item_data The item data.
	 * @param array $cart_item The cart item.
	 *
	 * @return array
	 */
	function display_cart_meta( $item_data, $cart_item ) {
		$product_id = $cart_item['product_id'] ?? false;
		if ( ! self::is_event_product( $product_id ) ) return $item_data;
		
		$event_post_id = (int) $cart_item['sfe_event_id'];
		if ( ! $event_post_id ) return $item_data;
		
		$item_data[] = array(
			'name' => __( 'Event', 'soulflags-events' ),
			'value' => get_the_title( $event_post_id ),
		);
		
		// Display the event date
		$event_date_range = SFE_Events::get_event_date_range( $event_post_id );
		
		if ( $event_date_range ) {
			$item_data[] = array(
				'name' => __( 'Event Date', 'soulflags-events' ),
				'value' => $event_date_range,
			);
		} else {
			$item_data[] = array(
				'name' => __( 'Event Date', 'soulflags-events' ),
				'value' => __( '(Not specified)', 'soulflags-events' ),
			);
		}
		
		return $item_data;
	}
	
	/**
	 * Load the event ID from session cart item data. Without this, the event ID would not persist when the cart is reloaded.
	 *
	 * @param array $cart_item The cart item data being restored.
	 * @param array $values     The original values stored in the session.
	 * @return array Modified cart item data with event ID if set.
	 */
	public function load_event_id_from_session( $cart_item, $values ) {
		if ( isset( $values['sfe_event_id'] ) ) {
			$cart_item['sfe_event_id'] = $values['sfe_event_id'];
		}
		return $cart_item;
	}
	
	/**
	 * Add the event ID to the order item metadata.
	 *
	 * @param int    $item_id        Order item ID.
	 * @param array  $values         Cart item values.
	 * @param string $cart_item_key  Cart item key.
	 */
	public function add_event_id_to_order_item_meta( $item_id, $values, $cart_item_key ) {
		if ( isset( $values['sfe_event_id'] ) ) {
			wc_add_order_item_meta( $item_id, '_sfe_event_id', $values['sfe_event_id'] );
			wc_add_order_item_meta( $item_id, '_sfe_event_title', get_the_title( $values['sfe_event_id'] ) );
			
			$event_start_date = get_post_meta( $values['sfe_event_id'], '_EventStartDate', true );
			$date_formatted = $event_start_date ? date('Y-m-d', strtotime($event_start_date)) : '(no date specified)';
			wc_add_order_item_meta( $item_id, '_sfe_event_start_date', $date_formatted );
		}
	}
	
	/**
	 * Display the event ID in the admin order item details.
	 *
	 * @param string       $html Displayed HTML.
	 * @param WC_Order_Item $item Order item object.
	 * @param array        $args Display args.
	 * @return string Modified HTML with event ID shown.
	 */
	public function display_event_meta_on_order( $html, $item, $args ) {
		$event_post_id = $item->get_meta( '_sfe_event_id' ) ?? false;
		
		if ( $event_post_id ) {
			if ( is_admin() ) {
				$html .= '<div><strong>Event ID:</strong> ' . $event_post_id . '</div>';
			}
			
			$event_title = $item->get_meta( '_sfe_event_title' ) ?: get_the_title( $event_post_id );
			$html .= '<div><strong>Event Title:</strong> ' . $event_title . '</div>';
			
			$event_start_date = $item->get_meta( '_sfe_event_start_date' );
			if ( $event_start_date ) {
				$date_formatted = date('F j, Y', strtotime($event_start_date));
				$html .= '<div><strong>Event Date:</strong> ' . $date_formatted . '</div>';
			}
		}
		
		return $html;
	}
	
	/**
	 * Change "5 in stock" to "5 seats available"
	 *
	 * @param $availability
	 * @param $_product
	 *
	 * @return mixed
	 */
	public function get_stock_availability_language( $availability, $_product = false ) {
		if ( ! self::is_event_product( $_product->get_id() ) ) return $availability;
		
		$labels = array(
			'in stock' => __( 'seats available', 'soulflags-events' ),
		);
		
		$availability['availability'] = str_replace( array_keys( $labels ), array_values( $labels ), $availability['availability'] );
		$availability['availability'] = str_replace( '1 seats available', '1 seat available', $availability['availability'] );
		
		return $availability;
	}
	
	/**
	 * Change "Add to Cart" buttons to go directly to the checkout page
	 * @param string $action
	 * @return string
	 */
	public function add_to_cart_to_checkout( $action ) {
		return wc_get_checkout_url();
	}
	
	/**
	 * Format the event details on the admin order item meta
	 *
	 * @param object[] $formatted_meta
	 * @param WC_Order_Item $order_item
	 * @return array
	 */
	public function format_admin_event_meta( $formatted_meta, $order_item ) {
		if ( ! is_admin() ) return $formatted_meta;
		
		$product_id = $order_item->get_product_id();
		if ( ! SFE_Orders::is_event_product( $product_id ) ) return $formatted_meta;
		
		$event_post_id = $order_item->get_meta( '_sfe_event_id' ) ?? false;
		
		$display_title = $order_item->get_meta( '_sfe_event_title' ) ?? false;
		$display_event_date = $order_item->get_meta( '_sfe_event_start_date' ) ?? false;
		
		// Remove default meta
		foreach( $formatted_meta as $i => $m ) {
			$key = $m->key ?? '';
			
			if ( in_array( $key, array('_sfe_event_id', '_sfe_event_title', '_sfe_event_start_date'), true ) ) {
				unset( $formatted_meta[ $i ] );
			}
		}
		
		if ( get_post_type( $event_post_id ) == 'tribe_events' ) {
			$display_title = get_the_title( $event_post_id );
			$display_title .= ' (<a href="'. get_edit_post_link( $event_post_id ).'">Edit</a>)';
			$display_event_date = SFE_Events::get_event_date_range( $event_post_id, true );
		}
		
		$formatted_meta[] = (object) array(
			'key'           => '_sfe_event_id',
			'value'         => '',
			'display_key'   => __( 'Event ID', 'soulflags-events' ),
			'display_value' => $event_post_id,
		);
		
		$formatted_meta[] = (object) array(
			'key'           => '_sfe_event_title',
			'value'         => '',
			'display_key'   => __( 'Event Title', 'soulflags-events' ),
			'display_value' => $display_title,
		);
		
		$formatted_meta[] = (object) array(
			'key'           => '_sfe_event_start_date',
			'value'         => '',
			'display_key'   => __( 'Event Date', 'soulflags-events' ),
			'display_value' => $display_event_date,
		);
		
		return $formatted_meta;
	}
	
}

SFE_Orders::get_instance();