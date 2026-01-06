<?php
/**
 * Plugin Name: Custom Order Status for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/
 * Description: Add custom order statuses to WooCommerce.
 * Version: 2.9.0
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-statuses-woocommerce
 * Domain Path: /langs
 * Copyright: © 2021 Tyche Softwares
 * WC tested up to: 10.4.3
 * Tested up to: 6.9.0
 * Requires PHP: 7.4
 * WC requires at least: 5.0.0
 * Requires Plugins: woocommerce
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses' ) ) {
	include_once 'class-alg-wc-custom-order-statuses.php';
}

/**
 * Show action links on the plugin screen.
 *
 * @param mixed $links - Links to be displayed for the plugin in WP Dashboard->Plugins.
 * @return  array
 *
 * @version 1.3.5
 * @since   1.0.0
 */
function cos_action_links( $links ) {
	$custom_links   = array();
	$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_statuses' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
	if ( 'custom-order-statuses-for-woocommerce.php' === basename( __FILE__ ) ) {
		$custom_links[] = '<a href="https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=unlockall&utm_campaign=CustomOrderStatusLite">' . __( 'Unlock All', 'custom-order-statuses-woocommerce' ) . '</a>';
	}
	return array_merge( $custom_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'cos_action_links' );
