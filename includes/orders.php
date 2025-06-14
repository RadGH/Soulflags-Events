<?php

class SFE_Orders {
	
	public function __construct() {
		
		// Remove event products from the cart if they are not assigned the required event ID in cart meta
		add_action( 'woocommerce_check_cart_items', array( $this, 'remove_invalid_event_products_from_cart' ) );
		
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
	 * Check if an event product is in the cart and returns the event ID if it is.
	 * @param WC_Product|int $product_id The product ID to check.
	 * @return array|false {
	 *     @type string $cart_item_key
	 *     @type int $product_id
	 *     @type int $event_post_id
	 * }
	 */
	public function get_event_product_from_cart( $product_id ) {
		if ( ! WC()->cart ) return null;
		
		if ( $product_id instanceof WC_Product ) {
			$product_id = $product_id->get_id();
		}
		
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['product_id'] ) && $cart_item['product_id'] === $product_id ) {
				if ( isset( $cart_item['sfe_event_id'] ) && is_numeric( $cart_item['sfe_event_id'] ) ) {
					return array(
						'cart_item_key' => $cart_item['key'],
						'product_id' => $product_id,
						'event_post_id' => (int) $cart_item['sfe_event_id'],
					);
				}
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
		
		// Loop through each product
		// Check if the product is an event product
		// If it is, check that it contains an event id
		// If no event id, remove from cart with message that you must buy it through the event page
		
		if ( $cart_items ) foreach ( $cart_items as $key => $cart_item ) {
			$product_id = $cart_item['product_id'] ?? false;
			if ( ! $product_id ) continue;
			if ( ! SFE_Products::is_event_product( $product_id ) ) continue;
			
			$event_post_id = $cart_item['sfe_event_id'] ?? false;
			
			if ( ! $event_post_id ) {
				// Remove the item from the cart if it does not match
				wc()->cart->remove_cart_item( $key );
				
				// Add a notice to the cart
				$message = sprintf(
					__( '<strong>%s</strong>: You must purchase this product through the event page.', 'soulflags-events' ),
					get_the_title( $product_id )
				);
				wc_add_notice( $message, 'error' );
			}
		}
		
		wc_print_notices();
	}
	
	/**
	 * Prevents event products from being purchasable on the single product page.
	 * @param bool $is_purchasable Whether the product is purchasable.
	 * @param WC_Product $product The product object.
	 * @return bool
	 */
	public function prevent_product_purchase( $is_purchasable, $product ) {
		if ( ! SFE_Products::is_event_product( $product ) ) return $is_purchasable;
		
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
		if ( ! SFE_Products::is_event_product( $product ) ) return;
		
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
		if ( ! SFE_Products::is_event_product( $product_id ) ) return $cart_item_data;
		
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
	 * @param array $item_data The item data.
	 * @param array $cart_item The cart item.
	 * @return array The modified item data.
	 */
	function display_cart_meta( $item_data, $cart_item ) {
		$product_id = $cart_item['product_id'] ?? false;
		if ( ! SFE_Products::is_event_product( $product_id ) ) return $item_data;
		
		$event_post_id = (int) $cart_item['sfe_event_id'];
		if ( ! $event_post_id ) return $item_data;
		
		$item_data[] = array(
			'name' => __( 'Event', 'soulflags-events' ),
			'value' => get_the_title( $event_post_id ),
		);
		
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
			$html .= '<div><strong>Event ID:</strong> ' . $event_post_id . '</div>';
			
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
	
}

SFE_Orders::get_instance();