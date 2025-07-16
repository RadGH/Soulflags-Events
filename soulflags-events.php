<?php
/*
Plugin Name: Soulflags Events
Description: Adds an integration between The Events Calendar, Gravity Forms, and Advanced Custom Fields, to create and manage classes with registration.
Version: 1.2.6
Author: ZingMap, Radley Sustaire
Author URI: https://zingmap.com
Date Created: 6/9/2025
GitHub Plugin URI: https://github.com/RadGH/Soulflags-Events
GitHub Branch: master
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'SFE_PATH', __DIR__ );
define( 'SFE_URL', untrailingslashit(plugin_dir_url(__FILE__)) );
define( 'SFE_VERSION', '1.2.6' );

class SFE_Plugin {
	
	/**
	 * Checks that required plugins are loaded before continuing
	 * @return void
	 */
	public static function load_plugin() {
		
		// Check for required plugins
		$missing_plugins = array();
		
		if ( ! class_exists( 'ACF' ) ) {
			$missing_plugins[] = 'Advanced Custom Fields Pro';
		}
		
		if ( ! class_exists( 'WooCommerce' ) ) {
			$missing_plugins[] = 'WooCommerce';
		}
		
		if ( ! class_exists( 'Tribe__Events__Main' ) ) {
			$missing_plugins[] = 'The Events Calendar';
		}
		
		if ( $missing_plugins ) {
			self::add_admin_notice( '<strong>Soulflags Events:</strong> The following plugins are required: ' . implode( ', ', $missing_plugins ) . '.', 'error' );
			
			return;
		}
		
		// Load ACF Fields
		require_once( SFE_PATH . '/fields/fields.php' );
		
		// Load plugin files
		require_once( SFE_PATH . '/includes/class-type.php' );
		require_once( SFE_PATH . '/includes/events.php' );
		require_once( SFE_PATH . '/includes/form.php' );
		require_once( SFE_PATH . '/includes/orders.php' );
		require_once( SFE_PATH . '/includes/registration.php' );
		require_once( SFE_PATH . '/includes/report.php' );
		require_once( SFE_PATH . '/includes/settings.php' );
		
		// After the plugin has been activated, flush rewrite rules, upgrade database, etc.
		add_action( 'admin_init', array( __CLASS__, 'after_plugin_activated' ) );
		
	}
	
	/**
	 * When the plugin is activated, set up the post types and refresh permalinks
	 */
	public static function on_plugin_activation() {
		update_option( 'sfe_plugin_activated', 1, true );
	}
	
	/**
	 * Flush rewrite rules if the option is set
	 * @return void
	 */
	public static function after_plugin_activated() {
		if ( get_option( 'sfe_plugin_activated' ) ) {
			
			/*
			// Flush rewrite rules
			flush_rewrite_rules();
			*/
			
			// Upgrade the database
			// require_once( SFE_PATH . '/includes/database.php' );
			// do_action( 'sfe/plugin_activated' );
			
			// Clear the option
			update_option( 'sfe_plugin_activated', 0, true );
			
		}
	}
	
	/**
	 * Adds an admin notice to the dashboard's "admin_notices" hook.
	 *
	 * @param string $message The message to display
	 * @param string $type    The type of notice: info, error, warning, or success. Default is "info"
	 * @param bool $format    Whether to format the message with wpautop()
	 *
	 * @return void
	 */
	public static function add_admin_notice( $message, $type = 'info', $format = true ) {
		add_action( 'admin_notices', function() use ( $message, $type, $format ) {
			?>
			<div class="notice notice-<?php
			echo $type; ?>">
				<?php
				echo $format ? wpautop( $message ) : $message; ?>
			</div>
			<?php
		} );
	}
	
	/**
	 * Add a link to the settings page
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public static function add_settings_link( $links ) {
		// array_unshift( $links, '<a href="admin.php?page=sfe-settings">Settings</a>' );
		return $links;
	}
	
}

// Add a link to the settings page
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'SFE_Plugin', 'add_settings_link' ) );

// When the plugin is activated, set up the post types and refresh permalinks
register_activation_hook( __FILE__, array( 'SFE_Plugin', 'on_plugin_activation' ) );

// Initialize the plugin
add_action( 'plugins_loaded', array( 'SFE_Plugin', 'load_plugin' ), 20 );

