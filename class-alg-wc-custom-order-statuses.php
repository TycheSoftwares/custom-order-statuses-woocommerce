<?php
/**
 * Performs the basic functions for the plugin.
 *
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Automattic\WooCommerce\Utilities\OrderUtil;

// Check if WooCommerce is active.
$plugin_name = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin_name, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) &&
	! ( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
}

if ( 'custom-order-statuses-for-woocommerce.php' === basename( __FILE__ ) ) {
	// Check if Pro is active, if so then return.
	$plugin_file = 'custom-order-statuses-for-woocommerce-pro/custom-order-statuses-for-woocommerce-pro.php';
	if (
		in_array( $plugin_file, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
		( is_multisite() && array_key_exists( $plugin_file, get_site_option( 'active_sitewide_plugins', array() ) ) )
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
		public $version = '2.5.0';

		/**
		 * Setting.
		 *
		 * @var $setting
		 */
		public $settings = '';

		/**
		 * Plugin instance.
		 *
		 * @var   Alg_WC_Custom_Order_Statuses The single instance of the class
		 * @since 1.0.0
		 */
		protected static $instance = null;

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
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Alg_WC_Custom_Order_Statuses Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 * @access  public
		 */
		public function __construct() {

			// Set up localisation.
			load_plugin_textdomain( 'custom-order-statuses-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );

			if ( is_admin() ) {
				// The Filter.
				add_filter( 'alg_orders_custom_statuses', array( $this, 'alg_orders_custom_statuses' ), PHP_INT_MAX, 3 );
				add_action( 'before_woocommerce_init', array( &$this, 'cos_lite_custom_order_tables_compatibility' ), 999 );
				add_action( 'admin_footer', array( $this, 'ts_admin_notices_scripts' ) );
				add_action( 'admin_init', array( $this, 'ts_reset_tracking_setting' ) );
				add_action( 'cos_lite_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );
				add_filter( 'ts_tracker_data', array( 'Cos_Tracking_Functions', 'cos_lite_plugin_tracking_data' ), 10, 1 );
			}

			// Include required files.
			$this->includes();

			// Admin.
			if ( is_admin() ) {
				add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_woocommerce_settings_tab' ) );
				// Register Custom Post Type for custom order status.
				require_once 'includes/class-alg-wc-custom-post-type-for-order-statuses.php';
				// Tool.
				require_once 'includes/class-alg-wc-custom-order-statuses-tool.php';
				// Settings.
				require_once 'includes/settings/class-alg-wc-custom-order-statuses-settings-section.php';
				$this->settings             = array();
				$this->settings['general']  = require_once 'includes/settings/class-alg-wc-custom-order-statuses-settings-general.php';
				$this->settings['emails']   = require_once 'includes/settings/class-alg-wc-custom-order-statuses-settings-emails.php';
				$this->settings['advanced'] = require_once 'includes/settings/class-alg-wc-custom-order-statuses-settings-advanced.php';
				if ( get_option( 'alg_custom_order_statuses_version', '' ) !== $this->version ) {
					add_action( 'admin_init', array( $this, 'version_updated' ) );
				}
			}
		}

		/**
		 * Function alg_orders_custom_statuses.
		 *
		 * @param string $value - string value.
		 * @param string $type  - string value for type.
		 * @param array  $args  - array of arguments.
		 * @version 1.4.1
		 * @since   1.2.0
		 */
		public function alg_orders_custom_statuses( $value, $type, $args = '' ) {
			switch ( $type ) {
				case 'settings':
					return $value;
				case 'value_column_colored':
					return get_option( 'alg_orders_custom_statuses_enable_column_colored', 'no' );
			}
			return $value;
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function includes() {
			$cos_plugin_url = plugins_url() . '/custom-order-statuses-woocommerce';
			// Functions.
			require_once 'includes/alg-wc-custom-order-statuses-functions.php';
			// Core.
			require_once 'includes/class-alg-wc-custom-order-statuses-core.php';
			// plugin deactivation.
			require_once 'includes/component/plugin-deactivation/class-tyche-plugin-deactivation.php';
			new Tyche_Plugin_Deactivation(
				array(
					'plugin_name'       => 'Custom Order Status for WooCommerce',
					'plugin_base'       => 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php',
					'script_file'       => $cos_plugin_url . '/includes/js/plugin-deactivation.js',
					'plugin_short_name' => 'cos_lite',
					'version'           => $this->version,
					'plugin_locale'     => 'custom-order-statuses-woocommerce',
				)
			);

			$doc_link = 'https://www.tychesoftwares.com/docs/docs/custom-order-status-for-woocommerce/custom-order-status-usage-tracking';
			require_once 'includes/component/plugin-tracking/class-tyche-plugin-tracking.php';
			require_once 'includes/class-cos-tracking-functions.php';
			new Tyche_Plugin_Tracking(
				array(
					'plugin_name'       => 'Custom Order Status for WooCommerce',
					'plugin_locale'     => 'custom-order-statuses-woocommerce',
					'plugin_short_name' => 'cos_lite',
					'version'           => $this->version,
					'blog_link'         => $doc_link,
				)
			);
		}

		/**
		 * Version_updated.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function version_updated() {
			foreach ( $this->settings as $section ) {
				foreach ( $section->get_settings() as $value ) {
					if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
						$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
						add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
					}
				}
			}
			update_option( 'alg_custom_order_statuses_version', $this->version );

			// get the email send to address option as it needs to be updated.
			$email_send_to = get_option( 'alg_orders_custom_statuses_emails_address', '' );
			if ( '' !== $email_send_to && in_array( $email_send_to, array( '%customer%', '%admin%', 'min%' ), true ) ) { // contains old values.
				switch ( $email_send_to ) {
					case 'min%':
					case '%admin%':
						update_option( 'alg_orders_custom_statuses_emails_address', '{admin_email}' );
						break;
					case '%customer%':
						update_option( 'alg_orders_custom_statuses_emails_address', '{customer_email}' );
						break;
					default:
						break;
				}
			}
		}

		/**
		 * Add Custom Order Statuses settings tab to WooCommerce settings.
		 *
		 * @param array $settings - Settings File.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function add_woocommerce_settings_tab( $settings ) {
			$settings[] = require_once 'includes/settings/class-alg-wc-settings-custom-order-statuses.php';
			return $settings;
		}

		/**
		 * Get the plugin url.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( __FILE__ ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( __FILE__ ) );
		}
		/**
		 * Sets the compatibility with Woocommerce HPOS.
		 *
		 * @since 2.2.0
		 */
		public function cos_lite_custom_order_tables_compatibility() {

			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'custom-order-statuses-for-woocommerce/custom-order-statuses-for-woocommerce.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', 'custom-order-statuses-for-woocommerce/custom-order-statuses-for-woocommerce.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', 'custom-order-statuses-for-woocommerce/custom-order-statuses-for-woocommerce.php', true );
			}
		}

		/**
		 * This function includes js files required for admin side.
		 */
		public function ts_admin_notices_scripts() {
			$nonce = wp_create_nonce( 'tracking_notice' );
			wp_enqueue_script(
				'cos_lite_ts_dismiss_notice',
				plugins_url() . '/custom-order-statuses-woocommerce/includes/js/tyche-dismiss-tracking-notice.js',
				'',
				$this->version,
				false
			);

			wp_localize_script(
				'cos_lite_ts_dismiss_notice',
				'cos_lite_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'cos_lite',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
					'tracking_notice'     => $nonce,
				)
			);
		}

		/**
		 * Function remove query argument when click on allow button on opt banner.
		 */
		public static function ts_reset_tracking_setting() {
			if ( isset( $_GET ['ts_action'] ) && 'reset_tracking' === $_GET ['ts_action'] ) {// phpcs:ignore WordPress.Security.NonceVerification
				Tyche_Plugin_Tracking::reset_tracker_setting( 'cos_lite' );
				$ts_url = remove_query_arg( 'ts_action' );
				wp_safe_redirect( $ts_url );
			}
		}

		/**
		 * Function located on settings page after tracking completed.
		 */
		public static function init_tracker_completed() {
			header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_statuses' ) );
			exit;
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
