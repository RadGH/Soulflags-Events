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
		
		// Add a message to the top of the Checkout page to explain users can also use punch cards
		add_shortcode( 'soulflags_above_checkout', array( $this, 'shortcode_soulflags_above_checkout' ) );
		
		// Disable order notes
		add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
		
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
		if ( function_exists('acf_add_options_sub_page') ) {
			acf_add_options_sub_page( array(
				'parent_slug' => 'edit.php?post_type=tribe_events',
				'menu_title' => 'Class Settings',
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
	 * Add a message to the top of the Checkout page to explain users can also use punch cards
	 *
	 * @param array $atts
	 * @param string $content
	 * @param string $shortcode_name
	 *
	 * @return string
	 */
	public function shortcode_soulflags_above_checkout( $atts, $content = '', $shortcode_name = 'soulflags_above_checkout' ) {
		$message = __( 'If you have a punch card when you arrive we can credit you back for the payment at the time of the event.', 'soulflags-events' );
		$message = wpautop($message);
		return '<div class="woocommerce-info sfe-punchcard-notice">' . $message . '</div>';
	}
	
}

SFE_Settings::get_instance();