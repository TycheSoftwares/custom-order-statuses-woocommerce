<?php
/**
 * Plugin Name: Custom Order Status for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/
 * Description: Add custom order statuses to WooCommerce.
 * Version: 2.0.3
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: custom-order-statuses-woocommerce
 * Domain Path: /langs
 * Copyright: © 2021 Tyche Softwares
 * WC tested up to: 5.6
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
