<?php

class SFE_Settings {
	
	public function __construct() {
		
		// Add an ACF settings page to manage settings
		add_action( 'acf/init', array( $this, 'acf_add_settings_pages' ) );
		
		// Include JS and CSS on the frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		
		// Include JS and CSS on the admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Add options to the ACF field for "Required Order Statuses" using WooCommerce order statuses
		// add_filter( 'acf/load_field/key=field_68362ebe34c3e', array( $this, 'acf_load_required_order_statuses' ) );
		
	}
	
	// Singleton instance
	protected static $instance = null;
	
	public static function get_instance() {
		if ( !isset( self::$instance ) ) self::$instance = new static();
		return self::$instance;
	}
	
	// Utilities
	
	// Actions
	
	/**
	 * Add an ACF settings page to manage settings
	 */
	public function acf_add_settings_pages() {
		if ( function_exists('acf_add_options_page') ) {
			acf_add_options_page( array(
				'menu_title' => 'Soulflags Events',
				'page_title' => 'Soulflags Events Settings (sfe_settings)',
				'menu_slug' => 'sfe-settings',
				'capability' => 'manage_options',
				'post_id' => 'sfe_settings', // get_field( 'something', 'sfe_settings' );
				'position' => 8,
			) );
		}
	}
	
	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style( 'sfe', SFE_URL . '/assets/public.css', array(), SFE_VERSION );
		// wp_enqueue_script( 'sfe', SFE_URL . '/assets/public.js', array(), SFE_VERSION, true );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets() {
		wp_enqueue_style( 'sfe-admin', SFE_URL . '/assets/admin.css', array(), SFE_VERSION );
		// wp_enqueue_script( 'sfe-admin-js', SFE_URL . '/assets/admin.js', array(), SFE_VERSION, true );
		
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if ( ! $screen ) return;
		
		// Check if editing a single event
		if ( $screen->id === 'tribe_events' ) {
			wp_enqueue_script( 'sfe-edit-event', SFE_URL . '/assets/edit-event.js', array( 'jquery' ), SFE_VERSION, true );
			
			$product_id = SFE_Events::get_event_product_id( get_the_ID() );
			$product_title = $product_id ? get_the_title( $product_id ) : '';
			$product_url = $product_id ? get_permalink( $product_id ) : false;
			$edit_product_url = $product_id ? get_edit_post_link( $product_id ) : false;
			
			wp_localize_script( 'sfe-edit-event', 'sfeEditEventData', array(
				'nonce'         => wp_create_nonce( 'sfe-edit-event' ),
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'debug_mode'    => current_user_can('administrator'),
				'event_post_id' => get_the_ID(),
				'event_price'   => SFE_Events::get_event_price( get_the_ID(), false ),
				'product_id'    => $product_id,
				'product_title' => $product_title,
				'product_url'   => $product_url,
				'edit_product_url'   => $edit_product_url,
			) );
		}
	}
	
	
	/**
	 * Load the WooCommerce order statuses into the ACF field for "Required Order Statuses"
	 *
	 * @param array $field
	 * @return array
	 */
	/*
	public function acf_load_required_order_statuses( $field ) {
		// Ignore the ACF field group and import/export screens
		if ( acf_is_screen('acf-field-group') ) return $field;
		if ( acf_is_screen('acf_page_acf-tools') ) return $field;
		
		// Get all WooCommerce order statuses
		$order_statuses = wc_get_order_statuses();
		
		// Set the choices for the ACF field
		$field['choices'] = array();
		foreach ( $order_statuses as $status => $label ) {
			// Remove the 'wc-' prefix from the status
			if ( str_starts_with($status, 'wc-' ) ) {
				$status = substr( $status, 3 );
			}
			
			$field['choices'][ $status ] = $label;
		}
		
		return $field;
	}
	*/
	
	
}

SFE_Settings::get_instance();