<?php
/**
 * Custom Order Status for WooCommerce - Data Tracking Class
 *
 * @version 1.0.0
 * @since   1.5.0
 * @package Custom Order Status/Data Tracking
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Tyche_COS_Data_Tracking' ) ) :

	/**
	 * Custom Order Status Data Tracking Core.
	 */
	class Tyche_COS_Data_Tracking {

		/**
		 * Construct.
		 *
		 * @since 1.5.0
		 */
		public function __construct() {
			// Include JS script for the notice.
			add_filter( 'cos_lite_ts_tracker_data', array( __CLASS__, 'cos_pro_ts_add_plugin_tracking_data' ), 10, 1 );
			add_action( 'admin_footer', array( __CLASS__, 'ts_admin_notices_scripts' ) );
			// Send Tracker Data.
			add_action( 'cos_lite_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );
			add_filter( 'cos_lite_ts_tracker_display_notice', array( __CLASS__, 'cos_pro_ts_tracker_display_notice' ), 10, 1 );

			add_filter( 'woocommerce_reset_settings_alg_wc_custom_order_statuses', array( $this, 'ts_tracking_reset_option' ), 10, 2 );
		}

		/**
		 * Add reset tracking option on general settings.
		 *
		 * @param array  $settings Settings.
		 * @param string $current_section Current section.
		 *
		 * @return array
		 */
		public function ts_tracking_reset_option( $settings, $current_section ) {

			if ( ! isset( $_GET['page'], $_GET['tab'] ) || $_GET['page'] !== 'wc-settings' || $_GET['tab'] !== 'custom-order-statuses-for-woocommerce' || ( isset( $_GET['section'] ) && $_GET['section'] !== '' ) ) { // phpcs:ignore
				return $settings;
			}

			$reset_usage_tracking = array(
				'title'   => __( 'Reset Usage Tracking', 'custom-order-statuses-for-woocommerce' ),
				'desc'    => __( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data.', 'custom-order-statuses-woocommerce' ),
				'id'      => $current_section . '_reset_usage_tracking',
				'default' => 'no',
				'type'    => 'checkbox',
			);
			array_splice( $settings, 2, 0, array( $reset_usage_tracking ) );

			return $settings;
		}

		/**
		 * Send the plugin data when the user has opted in
		 *
		 * @hook ts_tracker_data
		 * @param array $data All data to send to server.
		 *
		 * @return array $plugin_data All data to send to server.
		 */
		public static function cos_pro_ts_add_plugin_tracking_data( $data ) {
			$plugin_short_name = 'cos_lite';
			if ( ! isset( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ) {
				return $data;
			}

			$tracker_option = isset( $_GET[ $plugin_short_name . '_tracker_optin' ] ) ? $plugin_short_name . '_tracker_optin' : ( isset( $_GET[ $plugin_short_name . '_tracker_optout' ] ) ? $plugin_short_name . '_tracker_optout' : '' ); // phpcs:ignore
			if ( '' === $tracker_option || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET[ $plugin_short_name . '_tracker_nonce' ] ) ), $tracker_option ) ) {
				return $data;
			}

			$data = self::cos_pro_plugin_tracking_data( $data );
			return $data;
		}

		/**
		 * Add admin notice script.
		 */
		public static function ts_admin_notices_scripts() {
			$plugin_url = plugins_url() . '/custom-order-statuses-woocommerce';
			$version    = get_option( 'alg_custom_order_statuses_version' );
			$nonce      = wp_create_nonce( 'tracking_notice' );
			wp_enqueue_script(
				'cos_pro_ts_dismiss_notice',
				$plugin_url . '/includes/tyche/assets/js/tyche-dismiss-tracking-notice.js',
				'',
				$version,
				false
			);

			wp_localize_script(
				'cos_pro_ts_dismiss_notice',
				'cos_pro_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'cos_lite',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
					'tracking_notice'     => $nonce,
				)
			);
		}

		/**
		 * Add tracker completed.
		 */
		public static function init_tracker_completed() {
			header( 'Location: ' . admin_url( 'admin.php?page=wc-settings&tab=custom-order-statuses-for-woocommerce' ) );
			exit;
		}

		/**
		 * Display admin notice on specific page.
		 *
		 * @param array $is_flag Is Flag defailt value true.
		 */
		public static function cos_pro_ts_tracker_display_notice( $is_flag ) {
			global $current_section;
			if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] ) { // phpcs:ignore
				$is_flag = false;
				if ( isset( $_GET['tab'] ) && 'custom-order-statuses-for-woocommerce' === $_GET['tab'] && empty( $current_section ) ) { // phpcs:ignore
					$is_flag = true;
				}
			}
			return $is_flag;
		}

		/**
		 * Returns plugin data for tracking.
		 *
		 * @param array $data - Generic data related to WP, WC, Theme, Server and so on.
		 * @return array $data - Plugin data included in the original data received.
		 * @since 1.5.0
		 */
		public static function cos_pro_plugin_tracking_data( $data ) {
			$plugin_data         = array(
				'ts_meta_data_table_name'   => 'ts_tracking_cos_lite_meta_data',
				'ts_plugin_name'            => 'Custom Order Status for WooCommerce',
				'global_settings'           => self::cos_get_global_settings(),
				'license_data'              => self::cos_get_license_data(),
				'orders_count'              => self::cos_get_order_count(),
				'status_setup'              => self::cos_get_statuses_setup(),
				'email_settings'            => self::cos_get_email_settings(),
				'sms_settings'              => self::cos_get_sms_settings(),
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
				'alg_orders_custom_statuses_add_to_bulk_actions' => get_option( 'alg_orders_custom_statuses_add_to_bulk_actions' ),
				'alg_orders_custom_statuses_add_to_reports' => get_option( 'alg_orders_custom_statuses_add_to_reports' ),
				'alg_orders_custom_statuses_default_status' => get_option( 'alg_orders_custom_statuses_default_status' ),
				'alg_orders_custom_statuses_default_status_bacs' => get_option( 'alg_orders_custom_statuses_default_status_bacs' ),
				'alg_orders_custom_statuses_default_status_cod' => get_option( 'alg_orders_custom_statuses_default_status_cod' ),
				'alg_orders_custom_statuses_fallback_delete_status' => get_option( 'alg_orders_custom_statuses_fallback_delete_status' ),
				'alg_orders_custom_statuses_add_to_order_list_actions' => get_option( 'alg_orders_custom_statuses_add_to_order_list_actions' ),
				'alg_orders_custom_statuses_add_to_order_list_actions_colored' => get_option( 'alg_orders_custom_statuses_add_to_order_list_actions_colored' ),
				'alg_orders_custom_statuses_enable_column_colored' => get_option( 'alg_orders_custom_statuses_enable_column_colored' ),
				'alg_orders_custom_statuses_add_to_order_preview_actions' => get_option( 'alg_orders_custom_statuses_add_to_order_preview_actions' ),
				'alg_orders_custom_statuses_enable_editable' => get_option( 'alg_orders_custom_statuses_enable_editable' ),
				'alg_orders_custom_statuses_enable_paid' => get_option( 'alg_orders_custom_statuses_enable_paid' ),
				'alg_orders_custom_statuses_default_status_cheque' => get_option( 'alg_orders_custom_statuses_default_status_cheque' ),
				'alg_orders_custom_statuses_default_status_paypal' => get_option( 'alg_orders_custom_statuses_default_status_paypal' ),
				'alg_orders_custom_statuses_enable_fallback' => get_option( 'alg_orders_custom_statuses_enable_fallback' ),
				'alg_orders_custom_statuses_filters_priority' => get_option( 'alg_orders_custom_statuses_filters_priority' ),

			);
			return $global_settings;
		}

		/**
		 * Send the license data for data tracking.
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_license_data() {
			$settings = get_option( 'cos_pro_settings', array() );
			
			return array(
				'license_key'    => $settings['license']['key'] ?? '',
				'license_status' => $settings['license']['status'] ?? 'inactive',
			);
		}

		/**
		 * Send the statuses which have been setup.
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_statuses_setup() {
			$statuses = get_option( 'alg_orders_custom_statuses_array', array() );
			return $statuses;
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
			return $order_count;
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
			return $email_settings;
		}

		/**
		 * Send the Statuses for which SMS are sent(SMS-settings).
		 *
		 * @since 1.5.0
		 */
		public static function cos_get_sms_settings() {
			$email_settings = array(
				'alg_orders_custom_statuses_enable_sms'   => get_option( 'alg_orders_custom_statuses_enable_sms' ),
				'alg_orders_custom_statuses_enable_from_num' => get_option( 'alg_orders_custom_statuses_enable_from_num' ),
				'alg_orders_custom_statuses_enable_acc_sid' => get_option( 'alg_orders_custom_statuses_enable_acc_sid' ),
				'alg_orders_custom_statuses_enable_acc_token' => get_option( 'alg_orders_custom_statuses_enable_acc_token' ),
				'alg_orders_custom_statuses_sms_statuses' => get_option( 'alg_orders_custom_statuses_sms_statuses' ),
				'alg_orders_custom_statuses_sms_content'  => get_option( 'alg_orders_custom_statuses_sms_content' ),
			);
			return $email_settings;
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
			return $payment_gateways;
		}
	}

endif;

$tyche_cos_data_tracking = new Tyche_COS_Data_Tracking();
