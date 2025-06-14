<?php

class SFE_Products {
	
	public function __construct() {
		
		// Change "5 in stock" to "5 seats available"
		add_filter( 'woocommerce_get_availability', array( $this, 'get_stock_availability_language' ), 10, 2 );
		
		// Change "Add to Cart" buttons to go directly to the checkout page
		add_filter( 'woocommerce_add_to_cart_form_action', array( $this, 'add_to_cart_to_checkout' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		
		return self::$instance;
	}
	
	// Utilities
	
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
			'_sold_individually' => 'yes', // Allow only one ticket per order
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
	 * Get the event ID assigned to a product
	 *
	 * @param int $product_id
	 *
	 * @return int|false
	 */
	public static function get_event_from_product_id( $product_id ) {
		// Get the product ID associated with the event
		$event_post_id = (int) get_post_meta( $product_id, '_sfe_event_id', true );
		if ( get_post_type($event_post_id) !== 'tribe_events' ) return false;
		
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
	
	// Actions
	
	/**
	 * Change "5 in stock" to "5 seats available"
	 *
	 * @param $availability
	 * @param $_product
	 *
	 * @return mixed
	 */
	public function get_stock_availability_language( $availability, $_product = false ) {
		$event_post_id = $_product ? $this->get_event_from_product_id( $_product->get_id() ) : false;
		
		if ( $event_post_id ) {
			$labels = array(
				'in stock' => __( 'seats available', 'soulflags-events' ),
			);
			$availability['availability'] = str_replace( array_keys( $labels ), array_values( $labels ), $availability['availability'] );
			$availability['availability'] = str_replace( '1 seats available', '1 seat available', $availability['availability'] );
		}
		
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
	
}

SFE_Products::get_instance();