<?php
/*
Plugin Name: Custom Order Status for WooCommerce
Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/
Description: Add custom order statuses to WooCommerce.
Version: 1.4.6
Author: Tyche Softwares
Author URI: https://www.tychesoftwares.com/
Text Domain: custom-order-statuses-woocommerce
Domain Path: /langs
Copyright: © 2018 Tyche Softwares
WC tested up to: 3.5.0
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Check if WooCommerce is active
$plugin = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) &&
	! ( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'custom-order-statuses-for-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return
	$plugin = 'custom-order-statuses-for-woocommerce-pro/custom-order-statuses-for-woocommerce-pro.php';
	if (
		in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ) ) ||
		( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses' ) ) :

/**
 * Main Alg_WC_Custom_Order_Statuses Class
 *
 * @class   Alg_WC_Custom_Order_Statuses
 * @version 1.4.1
 * @since   1.0.0
 */
final class Alg_WC_Custom_Order_Statuses {

	/**
	 * Plugin version.
	 *
	 * @var   string
	 * @since 1.0.0
	 */
	public $version = '1.4.6';

	/**
	 * @var   Alg_WC_Custom_Order_Statuses The single instance of the class
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Main Alg_WC_Custom_Order_Statuses Instance
	 *
	 * Ensures only one instance of Alg_WC_Custom_Order_Statuses is loaded or can be loaded.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @static
	 * @return  Alg_WC_Custom_Order_Statuses - Main instance
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Alg_WC_Custom_Order_Statuses Constructor.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 * @access  public
	 */
	function __construct() {

		// Set up localisation
		load_plugin_textdomain( 'custom-order-statuses-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

		// Include required files
		$this->includes();

		// Admin
		if ( is_admin() ) {
			add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
			// Tool
			require_once( 'includes/class-alg-wc-custom-order-statuses-tool.php' );
			// Settings
			require_once( 'includes/settings/class-alg-wc-custom-order-statuses-settings-section.php' );
			$this->settings = array();
			$this->settings['general']    = require_once( 'includes/settings/class-alg-wc-custom-order-statuses-settings-general.php' );
			$this->settings['emails']     = require_once( 'includes/settings/class-alg-wc-custom-order-statuses-settings-emails.php' );
			$this->settings['advanced']   = require_once( 'includes/settings/class-alg-wc-custom-order-statuses-settings-advanced.php' );
			if ( get_option( 'alg_custom_order_statuses_version', '' ) !== $this->version ) {
				add_action( 'admin_init', array( $this, 'version_updated' ) );
			}
		}
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @version 1.3.5
	 * @since   1.0.0
	 * @param   mixed $links
	 * @return  array
	 */
	function action_links( $links ) {
		$custom_links = array();
		$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_statuses' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
		if ( 'custom-order-statuses-for-woocommerce.php' === basename( __FILE__ ) ) {
			$custom_links[] = '<a href="https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/">' . __( 'Unlock All', 'custom-order-statuses-woocommerce' ) . '</a>';
		}
		return array_merge( $custom_links, $links );
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function includes() {
		// Functions
		require_once( 'includes/alg-wc-custom-order-statuses-functions.php' );
		// Core
		require_once( 'includes/class-alg-wc-custom-order-statuses-core.php' );
	}

	/**
	 * version_updated.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function version_updated() {
		foreach ( $this->settings as $section ) {
			foreach ( $section->get_settings() as $value ) {
				if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
					$autoload = isset( $value['autoload'] ) ? ( bool ) $value['autoload'] : true;
					add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
				}
			}
		}
		update_option( 'alg_custom_order_statuses_version', $this->version );
	}

	/**
	 * Add Custom Order Statuses settings tab to WooCommerce settings.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function add_woocommerce_settings_tab( $settings ) {
		$settings[] = require_once( 'includes/settings/class-alg-wc-settings-custom-order-statuses.php' );
		return $settings;
	}

	/**
	 * Get the plugin url.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function plugin_url() {
		return untrailingslashit( plugin_dir_url( __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  string
	 */
	function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

}

endif;

if ( ! function_exists( 'alg_wc_custom_order_statuses' ) ) {
	/**
	 * Returns the main instance of Alg_WC_Custom_Order_Statuses to prevent the need to use globals.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 * @return  Alg_WC_Custom_Order_Statuses
	 */
	function alg_wc_custom_order_statuses() {
		return Alg_WC_Custom_Order_Statuses::instance();
	}
}

alg_wc_custom_order_statuses();
