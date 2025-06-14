<?php

class SFE_Cleanup {
	
	public function __construct() {
		
		// Customize the admin menu pages
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
		
		/*
		foreach( $parents as $p ) {
			if ( isset( $menu[ $p ] ) ) {
				foreach( $menu[ $p ] as $key => $item ) {
					if ( in_array( $item[2], $remove_pages ) ) {
						unset( $menu[ $p ][ $key ] );
					}
				}
			}
			if ( isset( $submenu[ $p ] ) ) {
				foreach( $submenu[ $p ] as $key => $item ) {
					if ( in_array( $item[2], $remove_pages ) ) {
						unset( $submenu[ $p ][ $key ] );
					}
				}
			}
		}
		*/
		
		foreach( $remove_pages as $key ) {
			// remove_menu_page( $key );
			foreach( $parents as $p ) {
				remove_submenu_page( $p, $key );
			}
		}
		
	}
	
}

SFE_Cleanup::get_instance();