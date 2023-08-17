<?php
/**
 * Custom Order Status for WooCommerce - Data Tracking Functions
 *
 * @version 1.0.0
 * @since   1.5.0
 * @package Custom Order Numbers/Data Tracking
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {

	exit; // Exit if accessed directly.

}

if ( ! class_exists( 'Cos_Tracking_Functions' ) ) :

	/**
	 * Custom Order Status Data Tracking Functions.
	 */
	class Cos_Tracking_Functions {
		/**
		 * Construct.
		 *
		 * @since 1.5.0
		 */
		public function __construct() {

		}

		/**
		 * Returns plugin data for tracking.
		 *
		 * @param array $data - Generic data related to WP, WC, Theme, Server and so on.
		 * @return array $data - Plugin data included in the original data received.
		 * @since 1.5.0
		 */
		public static function cos_lite_plugin_tracking_data( $data ) {
			$plugin_data = array(
				'ts_meta_data_table_name'   => 'ts_tracking_cos_meta_data',
				'ts_plugin_name'            => 'Custom Order Status for WooCommerce',
				'global_settings'           => self::cos_get_global_settings(),
				'orders_count'              => self::cos_get_order_count(),
				'email_settings'            => self::cos_get_email_settings(),
				'payment_gateways_settings' => self::cos_get_payment_gateways_settings(),
			);
			$data['plugin_data'] = $plugin_data;
			return $data;
		}

		/**
		 * Send the global settings for tracking.
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_global_settings() {
			$global_settings = array(
				'alg_orders_custom_statuses_add_to_bulk_actions'    => get_option( 'alg_orders_custom_statuses_add_to_bulk_actions' ),
				'alg_orders_custom_statuses_add_to_reports' 	    => get_option( 'alg_orders_custom_statuses_add_to_reports' ),
				'alg_orders_custom_statuses_default_status' 	    => get_option( 'alg_orders_custom_statuses_default_status' ),
				'alg_orders_custom_statuses_default_status_bacs'    => get_option( 'alg_orders_custom_statuses_default_status_bacs' ),
				'alg_orders_custom_statuses_default_status_cheque'  => get_option( 'alg_orders_custom_statuses_default_status_cheque' ),
				'alg_orders_custom_statuses_default_status_cod'     => get_option( 'alg_orders_custom_statuses_default_status_cod' ),
				'alg_orders_custom_statuses_default_status_paypal'  => get_option( 'alg_orders_custom_statuses_default_status_paypal' ),
				'alg_orders_custom_statuses_fallback_delete_status' => get_option( 'alg_orders_custom_statuses_fallback_delete_status' ),
				'alg_orders_custom_statuses_enable_column_colored'  => get_option( 'alg_orders_custom_statuses_enable_column_colored' ),

			);
			return wp_json_encode( $global_settings );
		}

		/**
		 * Sends an array where the status is the key and the count of orders is the array value
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_order_count() {
			$order_count = array();
			$statuses    = get_option( 'alg_orders_custom_statuses_array', array() );
			foreach ( $statuses as $status => $status_name ) {
				$count                  = self::cos_get_orders_status_count( $status );
				$order_count[ $status ] = $count;
			}
			return wp_json_encode( $order_count );
		}

		/**
		 * This function will take the status and return the count of orders belonging to the status. Will be called from cos_get_order_count()
		 *
		 * @param string $status Custom status Slug-name.
		 * @since 1.5.0
		 */
		public static function cos_get_orders_status_count( $status = '' ) {
			if ( '' !== $status ) {
				global $wpdb;
                $count = $wpdb->get_var( $wpdb->prepare( "SELECT count(id) FROM `" . $wpdb->prefix . "posts` WHERE post_type = 'shop_order' AND post_status = %s", trim( $status ) ) ); // phpcs:ignore
				return $count;
			}
			return 0;
		}

		/**
		 * Send the Statuses for which emails are sent(Email-settings).
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_email_settings() {
			$email_settings = array(
				'alg_orders_custom_statuses_emails_enabled'     => get_option( 'alg_orders_custom_statuses_emails_enabled' ),
				'alg_orders_custom_statuses_emails_statuses'    => get_option( 'alg_orders_custom_statuses_emails_statuses' ),
				'alg_orders_custom_statuses_emails_address'     => get_option( 'alg_orders_custom_statuses_emails_address' ),
				'alg_orders_custom_statuses_bcc_emails_address' => get_option( 'alg_orders_custom_statuses_bcc_emails_address' ),
				'alg_orders_custom_statuses_emails_subject'     => get_option( 'alg_orders_custom_statuses_emails_subject' ),
				'alg_orders_custom_statuses_emails_heading'     => get_option( 'alg_orders_custom_statuses_emails_heading' ),
				'alg_orders_custom_statuses_emails_content'     => get_option( 'alg_orders_custom_statuses_emails_content' ),
			);
			return wp_json_encode( $email_settings );
		}

		/**
		 * Send the Statuses for which payment gateway are enabled( Default Status for payment gateways -settings).
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_payment_gateways_settings() {
			$available_payment_gateways = WC()->payment_gateways->payment_gateways();
			foreach ( $available_payment_gateways as $key => $gateway ) {
				$payment_gateways[] = array(
					'alg_orders_custom_statuses_default_status_' . $key  => get_option( 'alg_orders_custom_statuses_default_status_' . $key ),
				);
			}
			return wp_json_encode( $payment_gateways );
		}
	}

endif;

$cos_tracking_functions = new Cos_Tracking_Functions();
