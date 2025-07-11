<?php

class SFE_Settings {
	
	public function __construct() {
		
		// Add an ACF settings page to manage settings
		add_action( 'acf/init', array( $this, 'acf_add_settings_pages' ) );
		
		// Include JS and CSS on the frontend
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		
		// Include JS and CSS on the admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		
		// Customize the admin menu pages to simplify the Events menu
		add_action( 'admin_menu', array( $this, 'customize_admin_menu' ), 100000 );
		
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
	 * Customize the admin menu pages to simplify the Events menu
	 *
	 * @return void
	 */
	public function customize_admin_menu() {
		global $menu, $submenu;
		
		// Pages to remove:
		// From these parents:
		// edit.php?post_type=tribe_events
		// tribe-common
		//
		// Sub-pages:       [2]
		// Help             tec-events-help-hub
		// Troubleshooting  tec-troubleshooting
		// Event Add-Ons    tribe-app-shop
		// Setup Guide      first-time-setup
		
		$parents = array(
			'edit.php?post_type=tribe_events',
			'tribe-common',
		);
		
		$remove_pages = array(
			'tec-events-help-hub',
			'tec-troubleshooting',
			'tribe-app-shop',
			'first-time-setup',
		);
		
		foreach( $remove_pages as $key ) {
			// remove_menu_page( $key );
			foreach( $parents as $p ) {
				remove_submenu_page( $p, $key );
			}
		}
		
	}
	
	/**
	 * Register a rewrite rule used to display a form for an event product while it is in the cart
	 *
	 * @return void
	 */
	public function register_rewrite_rule() {
		add_rewrite_rule(
			"^event/?",
			'index.php?sfe_cart_item_key=$matches[1]'
		);
		
		/*
		add_rewrite_rule(
			"^cart/register-event/([^/]+)/?",
			'index.php?sfe_cart_item_key=$matches[1]',
			'top'
		);
		*/
	}
	
	/**
	 * Add a custom query variable to store the Cart Item Key from the URL
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'sfe_cart_item_key';
		return $vars;
	}
	
	/**
	 * If viewing the event product registration page, load our custom page template
	 *
	 * @param WP $wp
	 * @return void
	 */
	public function maybe_display_product_registration_template( $wp ) {
		$cart_item_key = get_query_var( 'sfe_cart_item_key' );
		if ( ! $cart_item_key ) return;

		if ( ! WC()->cart ) {
			wp_die( 'Could not display the event product registration page: The cart is not available.', 'Cart Not Available', array( 'response' => 500, 'back_link' => true ) );
			exit;
		}
		
		$cart_item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! $cart_item ) {
			wp_die( 'Could not display the event product registration page: Invalid cart item specified.', 'Cart Not Available', array( 'response' => 500, 'back_link' => true ) );
			exit;
		}
		
		echo '<pre>';
		var_dump($cart_item_key, $cart_item);
		exit;

		include SFE_PATH . '/templates/product-registration.php';
		exit;
	}
	
}

SFE_Settings::get_instance();