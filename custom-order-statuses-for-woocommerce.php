<?php
/**
 * Plugin Name: Custom Order Status for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/
 * Description: Create and manage custom order statuses for WooCommerce.
 * Version: 3.0.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-statuses-woocommerce
 * Domain Path: /languages
 * Copyright: © 2021 Tyche Softwares
 * WC tested up to: 10.9.1
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0.0
 * Requires Plugins: woocommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 *
 * @package Custom-Order-Statuses
 */

defined( 'ABSPATH' ) || exit;

use TycheSoftwares\CustomOrderStatus\Lite\Custom_Order_Status;

if ( ! defined( 'COS_PLUGIN_FILE' ) ) {
	define( 'COS_PLUGIN_FILE', __FILE__ );
}

// Include the Product Input Fields class.
if ( ! class_exists( Custom_Order_Status::class, false ) ) {
	include_once dirname( COS_PLUGIN_FILE ) . '/includes/class-custom-order-status.php';
}

/**
 * Returns the instance of COS.
 *
 * @since  1.0
 */
function COS_Lite() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return Custom_Order_Status::instance();
}

COS_Lite();
