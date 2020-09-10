<?php
/**
 * Custom Order Status for WooCommerce - Lite
 *
 * Uninstalling Custom Order Status for WooCommerce Plugin delete settings.
 *
 * @author      Tyche Softwares
 * @category    Core
 * @version     1.4.6
 * @package     Custom-Order-Statuses-Lite
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// check if the Pro version file is present. If yes, do not delete any settings irrespective of whether the plugin is active or no.
if ( file_exists( WP_PLUGIN_DIR . '/custom-order-statuses-for-woocommerce-pro/custom-order-statuses-for-woocommerce-pro.php' ) ) {
	return;
}

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$cos_blog_list = get_sites();

	foreach ( $cos_blog_list as $cos_blog_list_key => $cos_blog_list_value ) {


		$cos_blog_id = $cos_blog_list_value->blog_id;

		/**
		 * It indicates the sub site id.
		 */
		$cos_multisite_prefix = $cos_blog_id > 1 ? $wpdb->prefix . "$cos_blog_id_" : $wpdb->prefix;

		// General Settings.
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_add_to_bulk_actions' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_add_to_reports' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_default_status' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_default_status_bacs' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_default_status_cod' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_fallback_delete_status' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_add_to_order_list_actions' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_add_to_order_list_actions_colored' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_enable_column_colored' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_add_to_order_preview_actions' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_enable_editable' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_enable_paid' );

		// Email Settings.
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_enabled' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_statuses' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_address' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_subject' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_heading' );
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_emails_content' );

		// Advanced Settings.
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_filters_priority' );

		// License.
		delete_blog_option( $cos_blog_id, 'edd_license_key_cos' );

		// custom status array.
		delete_blog_option( $cos_blog_id, 'alg_orders_custom_statuses_array' );

		// delete the custom order statuses.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM `%s` WHERE option_name LIKE %s', $cos_multisite_prefix . 'options', 'alg_orders_custom_status_icon_data_%' ) ); // phpcs:ignore
	}
} else {

	// General Settings.
	delete_option( 'alg_orders_custom_statuses_add_to_bulk_actions' );
	delete_option( 'alg_orders_custom_statuses_add_to_reports' );
	delete_option( 'alg_orders_custom_statuses_default_status' );
	delete_option( 'alg_orders_custom_statuses_default_status_bacs' );
	delete_option( 'alg_orders_custom_statuses_default_status_cod' );
	delete_option( 'alg_orders_custom_statuses_fallback_delete_status' );
	delete_option( 'alg_orders_custom_statuses_add_to_order_list_actions' );
	delete_option( 'alg_orders_custom_statuses_add_to_order_list_actions_colored' );
	delete_option( 'alg_orders_custom_statuses_enable_column_colored' );
	delete_option( 'alg_orders_custom_statuses_add_to_order_preview_actions' );
	delete_option( 'alg_orders_custom_statuses_enable_editable' );
	delete_option( 'alg_orders_custom_statuses_enable_paid' );

	// Email Settings.
	delete_option( 'alg_orders_custom_statuses_emails_enabled' );
	delete_option( 'alg_orders_custom_statuses_emails_statuses' );
	delete_option( 'alg_orders_custom_statuses_emails_address' );
	delete_option( 'alg_orders_custom_statuses_emails_subject' );
	delete_option( 'alg_orders_custom_statuses_emails_heading' );
	delete_option( 'alg_orders_custom_statuses_emails_content' );

	// Advanced Settings.
	delete_option( 'alg_orders_custom_statuses_filters_priority' );

	// License.
	delete_option( 'edd_license_key_cos' );

	// custom status array.
	delete_option( 'alg_orders_custom_statuses_array' );

	// delete the custom order statuses.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM `%s` WHERE option_name LIKE %s', $wpdb->prefix . 'options', 'alg_orders_custom_status_icon_data_%' ) ); // phpcs:ignore

}
// Clear any cached data that has been removed.
wp_cache_flush();
