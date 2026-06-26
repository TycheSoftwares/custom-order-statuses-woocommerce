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

use Tyche_Plugin_Deactivation;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( __NAMESPACE__ . '\\Deactivation' ) ) {

	/** Declaration of Class */
	class Deactivation {

		/** Constructor */
		public function __construct() {
			require_once __DIR__ . '/tyche/components/plugin-deactivation/class-tyche-plugin-deactivation.php';
			new Tyche_Plugin_Deactivation(
				array(
					'plugin_name'       => 'Custom Order Status for WooCommerce',
					'plugin_base'       => 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php',
					'script_file'       => COS_PLUGIN_URL . 'includes/tyche/assets/js/plugin-deactivation.js',
					'plugin_short_name' => 'cos_lite',
					'version'           => COS_VERSION,
					'plugin_locale'     => 'custom-order-statuses-woocommerce',
				)
			);
		}
	}

	// Initialize the license class.
	new Deactivation();
}
