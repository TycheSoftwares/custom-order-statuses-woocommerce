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

// If Pro version is installed (even if inactive), do not delete any data.
if ( file_exists( WP_PLUGIN_DIR . '/custom-order-statuses-for-woocommerce-pro/custom-order-statuses-for-woocommerce-pro.php' ) ) {
	return;
}

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once 'includes/functions/functions.php';

/**
 * Delete all plugin data for a specific blog (site) – handles both old and new formats.
 *
 * @param int    $blog_id Blog ID (0 for non‑multisite or main site).
 * @param string $prefix  Table prefix for the blog.
 */
function cos_delete_blog_data( $blog_id = 0, $prefix = '' ) {
	global $wpdb;

	$prefix = $prefix ? $prefix : $wpdb->prefix;

	// Read fallback settings from new or legacy options.
	$new_settings = $blog_id
		? get_blog_option( $blog_id, 'cos_pro_settings', array() )
		: get_option( 'cos_pro_settings', array() );

	$enable_fallback = isset( $new_settings['general']['enable_fallback'] )
		? (bool) $new_settings['general']['enable_fallback']
		: false;

	$fallback_status = isset( $new_settings['general']['fallback_delete_status'] )
		? $new_settings['general']['fallback_delete_status']
		: 'on-hold';

	// Fall back to legacy option if new setting is not set.
	if ( ! $enable_fallback ) {
		$legacy_fallback = $blog_id
			? get_blog_option( $blog_id, 'alg_orders_custom_statuses_fallback_delete_status', 'on-hold' )
			: get_option( 'alg_orders_custom_statuses_fallback_delete_status', 'on-hold' );

		$enable_fallback = ! empty( $legacy_fallback ) && 'on-hold' !== $legacy_fallback;
		$fallback_status = $legacy_fallback;
	}

	// Legacy options to delete.
	$legacy_options = array(
		'alg_orders_custom_statuses_add_to_bulk_actions',
		'alg_orders_custom_statuses_add_to_reports',
		'alg_orders_custom_statuses_default_status',
		'alg_orders_custom_statuses_default_status_bacs',
		'alg_orders_custom_statuses_default_status_cod',
		'alg_orders_custom_statuses_fallback_delete_status',
		'alg_orders_custom_statuses_add_to_order_list_actions',
		'alg_orders_custom_statuses_add_to_order_list_actions_colored',
		'alg_orders_custom_statuses_enable_column_colored',
		'alg_orders_custom_statuses_add_to_order_preview_actions',
		'alg_orders_custom_statuses_enable_editable',
		'alg_orders_custom_statuses_enable_paid',
		'is_statuses_migrated_to_slug',
		'alg_orders_custom_statuses_emails_enabled',
		'alg_orders_custom_statuses_emails_statuses',
		'alg_orders_custom_statuses_emails_address',
		'alg_orders_custom_statuses_emails_subject',
		'alg_orders_custom_statuses_emails_heading',
		'alg_orders_custom_statuses_emails_content',
		'alg_orders_custom_statuses_filters_priority',
		'edd_license_key_cos',
		'alg_orders_custom_statuses_array',
	);

	foreach ( $legacy_options as $opt ) {
		if ( $blog_id ) {
			delete_blog_option( $blog_id, $opt );
		} else {
			delete_option( $opt );
		}
	}

	// New consolidated settings.
	if ( $blog_id ) {
		delete_blog_option( $blog_id, 'cos_pro_settings' );
	} else {
		delete_option( 'cos_pro_settings' );
	}

	// Get all custom order statuses (posts).
	$get_all_status = $blog_id
		? get_blog_option( $blog_id, 'alg_orders_custom_statuses_array', array() )
		: get_option( 'alg_orders_custom_statuses_array', array() );

	if ( empty( $get_all_status ) ) {
		$get_all_status = alg_get_custom_order_statuses_from_cpt( false, true );
	}

	if ( ! empty( $get_all_status ) ) {
		// Update orders only if fallback is explicitly enabled.
		if ( $enable_fallback ) {
			foreach ( $get_all_status as $custom_status_key => $custom_status_id ) {
				$wpdb->update(
					$prefix . 'posts',
					array( 'post_status' => 'wc-' . $fallback_status ),
					array( 'post_status' => $custom_status_key )
				);
			}
		}
		// Always delete the custom status posts.
		foreach ( $get_all_status as $custom_status_id ) {
			wp_delete_post( $custom_status_id, true );
		}
	}

	// Delete any orphaned custom order status posts.
	$wpdb->delete( $prefix . 'posts', array( 'post_type' => 'custom_order_status' ) );

	// Delete icon data.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$prefix}options WHERE option_name LIKE 'alg_orders_custom_status_icon_data_%'" );
}

// Handle multisite.
if ( is_multisite() ) {
	$sites = get_sites();
	foreach ( $sites as $site ) {
		$blog_id = $site->blog_id;
		$prefix  = $blog_id > 1 ? $wpdb->prefix . "{$blog_id}_" : $wpdb->prefix;
		cos_delete_blog_data( $blog_id, $prefix );
	}
} else {
	cos_delete_blog_data();
}

wp_cache_flush();