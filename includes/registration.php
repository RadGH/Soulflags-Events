<?php

class SFE_Registration {
	
	public function __construct() {
		
		//
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	/**
	 * Checks whether registration is enabled for a given event post ID. (Does not check if there is available inventory)
	 *
	 * @param int $event_post_id
	 *
	 * @return bool
	 */
	public static function is_registration_enabled( $event_post_id ) {
		// Check if registration is enabled
		return (bool) get_field( 'sfe_registration_enabled', $event_post_id );
	}
	
	/**
	 * Get an array of registration details for a given event post ID. Returns false if registration is not enabled for the event.
	 *
	 * @param int $event_post_id
	 *
	 * @return array|false {
	 *     @type int    $event_id          The ID of the event post.
	 *     @type bool   $inventory_enabled Whether inventory tracking is enabled for the event.
	 *     @type int    $inventory_total   The total inventory available for the event.
	 *     @type int    $registered_count  The number of registrations for the event.
	 *     @type string $registration_url  The URL to the event registration page.
	 * }
	 */
	public static function get_registration_details( $event_post_id ) {
		// Check if registration is enabled
		if ( ! self::is_registration_enabled( $event_post_id ) ) return false;
		
		// Count the number of entries for that event
		$registered_count = SFE_Registration::get_event_entry_count( $event_post_id );
		
		// Get the url to the event registration page
		$registration_url = get_permalink( $event_post_id );
		
		return array(
			'event_id' => $event_post_id,
			'registered_count' => $registered_count,
			'registration_url' => $registration_url,
		);
	}
	
	/**
	 * Get an array of order IDs for a specific event, based on the product assigned to the event.
	 *
	 * @param int $event_post_id
	 * @param string[]|null $order_statuses
	 *
	 * @return int[]
	 */
	public static function get_event_order_ids( $event_post_id, $order_statuses = null ) {
		// Check if registration is enabled
		if ( ! self::is_registration_enabled( $event_post_id ) ) return array();
		
		// Get assigned product ID
		// $product_id = SFE_Events::get_event_product_id( $event_post_id );
		// if ( !$product_id ) return array();
		
		global $wpdb;
		
		// HERE Define the orders status to include IN (each order status always starts with "wc-")
		if ( $order_statuses === null ) $order_statuses = wc_get_is_paid_statuses();
		
		// Add "wc-" prefix to each order status if it doesn't already have it
		$order_statuses = array_map( function( $status ) {
			if ( ! str_starts_with( $status, 'wc-' ) ) {
				return 'wc-' . $status;
			}else{
				return $status;
			}
		}, $order_statuses );
		
		// Escape SQL in order statuses
		$order_statuses = array_map( 'esc_sql', $order_statuses );
		
		// Convert order statuses array to a string for the query
		$orders_statuses = "'" . implode("', '", $order_statuses) . "'";
		
		$sql = <<<MySQL
SELECT
	DISTINCT o.id AS order_id

FROM {$wpdb->prefix}wc_orders o

JOIN wp_woocommerce_order_items oi
	ON oi.order_id = o.id

JOIN wp_woocommerce_order_itemmeta oim
	ON oim.order_item_id = oi.order_item_id

WHERE
	o.type = 'shop_order'
	AND o.status IN ( {$orders_statuses} )
	AND oim.meta_key = '_sfe_event_id'
	AND oim.meta_value = %d

LIMIT 2000;
MySQL;
		
		$sql = $wpdb->prepare( $sql, intval($event_post_id) );
		
		return $wpdb->get_col( $sql );
	}
	
	/**
	 * Count the number of entries for a specific event.
	 *
	 * @param int $event_post_id
	 *
	 * @return int
	 */
	public static function get_event_entry_count( $event_post_id ) {
		return count( self::get_event_order_ids( $event_post_id ) );
	}
	
	// Actions
	
}

SFE_Registration::get_instance();