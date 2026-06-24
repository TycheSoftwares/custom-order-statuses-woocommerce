<?php
/**
 * Custom Order Status for WooCommerce - Deactivation Class
 *
 * @version 1.1.7
 * @since   1.1.3
 * @author  Tyche Softwares
 * @package Custom Order Status
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use Tyche_Plugin_Tracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( __NAMESPACE__ . '\\Tracking' ) ) {

	/** Declaration of Class */
	class Tracking {

		const TRACKING_KEY          = 'cos_pro_allow_tracking';
		const TRACKER_LAST_SEND_KEY = 'ts_tracker_last_send';

		/** Constructor */
		public function __construct() {
			if ( is_admin() ) {
				require_once __DIR__ . '/tyche/components/plugin-tracking/class-tyche-cos-data-tracking.php';
				require_once __DIR__ . '/tyche/components/plugin-tracking/class-tyche-plugin-tracking.php';
				new Tyche_Plugin_Tracking(
					array(
						'plugin_name'       => 'Custom Order Status for WooCommerce',
						'plugin_locale'     => 'custom-order-statuses-woocommerce',
						'plugin_short_name' => 'cos_lite',
						'version'           => COS_VERSION,
						'blog_link'         => 'https://www.tychesoftwares.com/docs/docs/custom-order-status-for-woocommerce/custom-order-status-usage-tracking',
					)
				);
			}
			//self::register_settings();
		}

		public static function register_settings() {
			$default_args = array(
				'type'         => 'string',
				'default'      => '',
				'show_in_rest' => ['schema' => ['type' => 'string']]
			);

	
			register_setting( 'options', self::TRACKING_KEY, $default_args );
			register_setting( 'options', self::TRACKER_LAST_SEND_KEY, $default_args );
		}
	}

	// Initialize the license class.
	new Tracking();
}
