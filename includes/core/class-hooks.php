<?php
/**
 * COS Hooks Class
 *
 * Handles the hooks for the COS Pro plugin.
 *
 * @author  Tyche Softwares
 * @package COS/Hooks
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use Automattic\WooCommerce\Utilities\OrderUtil;
use DateTime;
use Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class Hooks {

	/**
	 * Plugin Settings Cache.
	 *
	 * @var   array
	 * @since 2.6.0
	 */
	private static $cos_settings_cache = null;

	public function __construct() {

		add_action( 'before_woocommerce_init', array( $this, 'cos_custom_order_tables_compatibility' ), 999 );
		// The Filter.
		add_filter( 'alg_orders_custom_statuses', array( $this, 'alg_orders_custom_statuses' ), PHP_INT_MAX, 3 );

		// Admin.
		if ( is_admin() ) {
			// Woocommerce default order statuses label customization filters.
			add_filter( 'wc_order_statuses', array( $this, 'alg_wc_order_statuses' ), PHP_INT_MAX, 1 );
			add_filter( 'bulk_actions-edit-shop_order', array( $this, 'alg_default_bulk_actions' ), 1000, 1 );
			add_filter( 'views_edit-shop_order', array( $this, 'alg_default_views' ), PHP_INT_MAX, 1 );

			if ( get_option( 'alg_custom_order_statuses_version', '' ) !== COS_VERSION ) {
				add_action( 'admin_init', array( $this, 'version_updated' ), 4 );
			}

			// Add custom order statuses in list for PDF invoice & packing slip.
			add_filter( 'wpo_wcpdf_wc_emails', array( $this, 'custom_status_for_pdf_invoices' ), 10, 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_script' ) );
		}

		add_action( 'cos_pro_settings_saved', array( $this, 'alg_cos_order_status_save_options' ), PHP_INT_MAX );

		add_action( 'cos_pro_settings_saved', array( $this, 'reset_settings_cache' ) );

		// Filters priority.
		$filters_priority = self::cos_get_setting('general', 'filters_priority', 0);
		if ( 0 === $filters_priority ) {
			$filters_priority = PHP_INT_MAX;
		}
		// Schedule the action custom_change_order_status.
		add_action( 'init', array( $this, 'register_schedule_custom_hooks' ) );
		add_action( 'woocommerce_order_status_changed', array( $this, 'schedule_order_status_change' ), 10, 4 );

		// Custom Status: Filter, Register, Icons.
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses_to_filter' ), $filters_priority );

		add_filter( 'woocommerce_order_is_download_permitted', array( &$this, 'custom_download_access' ), 10, 2 );

		add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_custom_statuses_to_paid' ), $filters_priority );

		add_action( 'init', array( $this, 'register_custom_post_statuses' ) );
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_column_colored' ) || 'yes' === self::cos_get_yes_no('general', 'enable_column_icons', true) ) {
			add_action( 'admin_head', array( $this, 'hook_statuses_icons_css' ), 11 );
			// add_action( 'wp_head', array( $this, 'hook_statuses_icons_css' ), 11 );
		}
		add_action( 'woocommerce_order_status_changed', array( $this, 'alg_cos_paid_status_and_update_stock_levels' ), 20, 4 );
		add_action( 'woocommerce_saved_order_items', array( $this, 'alg_cos_adjust_stock_on_order_item_update' ), 20, 1 ); // stock adjustment on order item update.

		// Default Status.
		add_filter( 'woocommerce_thankyou', array( $this, 'set_default_order_status' ), $filters_priority );

		// Reports.
		if ( 'yes' === self::cos_get_yes_no( 'general', 'add_to_reports', true ) ) {
			add_filter( 'woocommerce_reports_order_statuses', array( $this, 'add_custom_order_statuses_to_reports' ), $filters_priority );
		}

		// Bulk Actions.
		if ( 'yes' === self::cos_get_yes_no( 'general', 'add_to_bulk_actions', true ) ) {
			if ( version_compare( get_bloginfo( 'version' ), '4.7' ) >= 0 ) {
				add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'register_order_custom_status_bulk_actions' ), $filters_priority );
				add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_order_custom_status_bulk_actions' ), $filters_priority );
			} else {
				add_action( 'admin_footer', array( $this, 'bulk_admin_footer' ), 11 );
			}
		}

		// Admin Order List Actions.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions' ) ) {
			add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_custom_status_actions_buttons' ), $filters_priority, 2 );
			add_action( 'admin_head', array( $this, 'add_custom_status_actions_buttons_css' ) );
		}

		// Column Colors.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_column_colored' ) ) {
			add_action( 'admin_head', array( $this, 'add_custom_status_column_css' ) );
		}

		// Order preview actions.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_preview_actions' ) ) {
			add_filter( 'woocommerce_admin_order_preview_actions', array( $this, 'add_custom_status_to_order_preview' ), PHP_INT_MAX, 2 );
		}

		// Editable orders.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_editable' ) ) {
			add_filter( 'wc_order_is_editable', array( $this, 'add_custom_order_statuses_to_order_editable' ), PHP_INT_MAX, 2 );
		}

		// Paid order statuses.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_paid' ) ) {
			add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_custom_order_statuses_to_order_paid' ), PHP_INT_MAX );
		}

		// Emails.
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_emails', array( 'return' => 'true' ) ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_email_on_order_status_changed' ), PHP_INT_MAX, 4 );
		}
		add_filter( 'wc_order_is_editable', array( $this, 'alg_paid_status_order_not_editable' ), PHP_INT_MAX, 2 );

		// User Can Cancel option.
		add_filter( 'woocommerce_valid_order_statuses_for_cancel', array( $this, 'alg_cos_add_statuses_for_cancel' ) );
		add_action( 'wp_trash_post', array( $this, 'update_order_status_on_trash' ), 10, 1 );

	}

	/**
	 * Function version_updated.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	public function version_updated() {
		/* if ( ! is_array( $this->settings ) ) {
			$this->settings = array();
		}
		foreach ( $this->settings as $section ) {
			$settings = $section->get_settings();
			if ( is_array( $settings ) ) {
				foreach ( $settings as $value ) {
					if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
						$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
						add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
					}
				}
			}
		} */
		update_option( 'alg_custom_order_statuses_version', COS_VERSION );

		// get the email send to address option as it needs to be updated.
		$email_send_to = self::cos_get_setting( 'emails', 'address', '' );
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
	 * Read a value from the unified cos_pro_settings option.
	 * Caches the settings array for the life of the request.
	 *
	 * @param string $section  Top-level key e.g. 'general', 'labels', 'admin_email'.
	 * @param string $key      Field key within that section.
	 * @param mixed  $default  Fallback when the key is absent.
	 * @return mixed
	 */
	public static function cos_get_setting( string $section, string $key, $default = null ) {
		
		if ( null === self::$cos_settings_cache ) {
			self::$cos_settings_cache = get_option( 'cos_pro_settings', [] );
		}

		return self::$cos_settings_cache[ $section ][ $key ] ?? $default;
	}

	/**
	 * Resetting the static settings variable to null when updated it.
	 */
	public static function reset_settings_cache() {
		self::$cos_settings_cache = null;
	}

	/**
	 * Helper: return 'yes' or 'no' from a boolean stored in cos_pro_settings.
	 *
	 * @param string $section
	 * @param string $key
	 * @param bool   $default
	 * @return string 'yes'|'no'
	 */
	private static function cos_get_yes_no( string $section, string $key, bool $default = false ): string {
		return self::cos_get_setting( $section, $key, $default ) ? 'yes' : 'no';
	}

	/**
	 * This function includes js files required for admin side.
	 *
	 * @hook admin_enqueue_scripts
	 *
	 * * @since 2.9.0
	 */
	public function enqueue_script() {

		wp_register_script(
			'tyche',
			plugins_url() . '/custom-order-statuses-woocommerce/assets/js/tyche.js',
			array( 'jquery' ),
			COS_VERSION,
			true
		);
		wp_enqueue_script( 'tyche' );
	}

	/**
	 * Function custom_status_for_pdf_invoices.
	 *
	 * @version 2.2.0
	 * @since   2.2.0
	 * @param array $emails - WooCommmerce and custom order status array.
	 */
	public function custom_status_for_pdf_invoices( $emails ) {

		$args = array(
			'post_type'      => 'custom_order_status',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		);
		$ids  = get_posts( $args );
		if ( ! empty( $ids ) ) {
			foreach ( $ids as $id ) {
				$slug            = get_post_meta( $id, 'status_slug', true );
				$name            = get_the_title( $id );
				$emails[ $slug ] = $name;
			}
		}
		return $emails;
	}

	/**
	 * Add or Update woocommerce settings options.
	 *
	 * @version 1.3.5
	 * @since   1.0.0
	 */
	public function alg_cos_order_status_save_options() {

		global $current_tab;
		// Save activation date when enabling Order Status Email Notification for Admin.
		// Save activation start date when admin email is enabled.
		// Now triggered by REST save (cos_pro_settings) rather than WC form POST.
		$admin_email_enabled = self::cos_get_setting( 'admin_email', 'enabled', false );
		if ( $admin_email_enabled && ! get_option( 'alg_cos_po_notify_emails_start_date' ) ) {
			update_option( 'alg_cos_po_notify_emails_start_date', gmdate( 'Y-m-d' ) );
		}

		// Admin email interval is now saved via REST to cos_pro_settings.
			// Re-schedule cron whenever settings are saved on our tab.
			$settings   = get_option( 'cos_pro_settings', [] );
			$int_time   = $settings['admin_email']['interval_time'] ?? 1;
			$int_period = $settings['admin_email']['interval']      ?? 'days';
			if ( ! empty( $int_time ) && ! empty( $int_period ) ) { //phpcs:ignore
			$schedule_key = 'every_' . strtolower( cos_lite_convert_number( $int_time ) ) . '_' . $int_period;
			$interval     = 0;
			switch ( $int_period ) {
				case 'minutes':
					$interval = $int_time * 60;
					break;
				case 'hours':
					$interval = $int_time * 3600;
					break;
				case 'days':
					$interval = $int_time * 3600 * 24;
					break;
				case 'weeks':
					$interval = $int_time * 3600 * 24 * 7;
					break;
				case 'months':
					$interval = $int_time * 3600 * 24 * 30;
					break;
				default:
					$interval = 24 * 3600;
			}
			
			// Cron job actions.
			if ( ! as_next_scheduled_action( 'alg_cos_order_status_notify' ) ) {
				as_schedule_recurring_action( time(), $interval, 'alg_cos_order_status_notify' );
			} else {
				wp_clear_scheduled_hook( 'alg_cos_order_status_notify' );
				as_unschedule_action( 'alg_cos_order_status_notify' );
				as_schedule_recurring_action( time(), $interval, 'alg_cos_order_status_notify' );
			}

			// Update scheduled event from day to hourly.
			if ( ! wp_next_scheduled( 'alg_cos_order_status_notify' ) ) {
				wp_schedule_event( time(), $interval, 'alg_cos_order_status_notify' );
			} else {
				wp_clear_scheduled_hook( 'alg_cos_order_status_notify' );
				wp_schedule_event( time(), $interval, 'alg_cos_order_status_notify' );
			}
		}
	}

	/**
	 * Adding to admin order list views name.
	 * Function alg_default_views.
	 *
	 * @param array $views - array of arguments.
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	public function alg_default_views( $views ) {
	
		foreach ( $views as $key => $view ) {
			switch ( $key ) {
				case 'wc-pending':
					$field_val           = self::cos_get_setting( 'labels', 'pending', '' );
					$new_val             = ( $field_val ) ? $field_val : 'Pending payment';
					$views['wc-pending'] = str_replace( 'Pending payment', __( 'woocommerce' ), $views['wc-pending'] );
					break;
				case 'wc-processing':
					$field_val              = self::cos_get_setting( 'labels', 'processing', '' );
					$new_val                = ( $field_val ) ? $field_val : 'Processing';
					$views['wc-processing'] = str_replace( 'Processing', __( $new_val, 'woocommerce' ), $views['wc-processing'] ); // phpcs:ignore
					break;
				case 'wc-on-hold':
					$field_val           = self::cos_get_setting( 'labels', 'on-hold', '' );
					$new_val             = ( $field_val ) ? $field_val : 'On hold';
					$views['wc-on-hold'] = str_replace( 'On hold', __( $new_val, 'woocommerce' ), $views['wc-on-hold'] ); // phpcs:ignore
					break;
				case 'wc-completed':
					$field_val             = self::cos_get_setting( 'labels', 'completed', '' );
					$new_val               = ( $field_val ) ? $field_val : 'Completed';
					$views['wc-completed'] = str_replace( 'Completed', __( $new_val, 'woocommerce' ), $views['wc-completed'] ); // phpcs:ignore
					break;
				case 'wc-cancelled':
					$field_val             = self::cos_get_setting( 'labels', 'cancelled', '' );
					$new_val               = ( $field_val ) ? $field_val : 'Cancelled';
					$views['wc-cancelled'] = str_replace( 'Cancelled', __( $new_val, 'woocommerce' ), $views['wc-cancelled'] ); // phpcs:ignore
					break;
				case 'wc-refunded':
					$field_val            = self::cos_get_setting( 'labels', 'refunded', '' );
					$new_val              = ( $field_val ) ? $field_val : 'Refunded';
					$views['wc-refunded'] = str_replace( 'Refunded', __( $new_val, 'woocommerce' ), $views['wc-refunded'] ); // phpcs:ignore
					break;
				case 'wc-failed':
					$field_val          = self::cos_get_setting( 'labels', 'failed', '' );
					$new_val            = ( $field_val ) ? $field_val : 'Failed';
					$views['wc-failed'] = str_replace( 'Failed', __( $new_val, 'woocommerce' ), $views['wc-failed'] ); // phpcs:ignore
					break;
			}
		}
		return $views;
	}

	/**
	 * Adding to admin order list bulk dropdown a change action name.
	 * Function alg_default_bulk_actions.
	 *
	 * @param array $actions - array of arguments.
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	public function alg_default_bulk_actions( $actions ) {

		foreach ( $actions as $key => $action ) {
			switch ( $key ) {
				case 'mark_processing':
					$field_val                  = self::cos_get_setting( 'labels', 'processing', '' );
					$new_val                    = ( $field_val ) ? 'Change status to ' . $field_val : $action;
					$actions['mark_processing'] = __( $new_val, 'woocommerce' ); // phpcs:ignore
					break;
				case 'mark_on-hold':
					$field_val               = self::cos_get_setting( 'labels', 'on-hold', '' );
					$new_val                 = ( $field_val ) ? 'Change status to ' . $field_val : $action;
					$actions['mark_on-hold'] = __( $new_val, 'woocommerce' ); // phpcs:ignore
					break;
				case 'mark_completed':
					$field_val                 = self::cos_get_setting( 'labels', 'completed', '' );
					$new_val                   = ( $field_val ) ? 'Change status to ' . $field_val : $action;
					$actions['mark_completed'] = __( $new_val, 'woocommerce' ); // phpcs:ignore
					break;
				case 'mark_cancelled':
					$field_val                 = self::cos_get_setting( 'labels', 'cancelled', '' );
					$new_val                   = ( $field_val ) ? 'Change status to ' . $field_val : $action;
					$actions['mark_cancelled'] = __( $new_val, 'woocommerce' ); // phpcs:ignore
					break;
			}
		}
		return $actions;
	}

	/**
	 * Function alg_wc_order_statuses.
	 *
	 * @param array $order_statuses - array of arguments.
	 * @version 2.3.0
	 * @since   2.3.0
	 */
	public function alg_wc_order_statuses( $order_statuses ) {
		foreach ( $order_statuses as $key => $status ) {
			switch ( $key ) {
				case 'wc-pending':
					$field_val                    = self::cos_get_setting( 'labels', 'pending', '' );
					$new_val                      = ( $field_val ) ? $field_val : 'Pending Payment';
					$order_statuses['wc-pending'] = $new_val;
					break;
				case 'wc-processing':
					$field_val                       = self::cos_get_setting( 'labels', 'processing', '' );
					$new_val                         = ( $field_val ) ? $field_val : 'Processing';
					$order_statuses['wc-processing'] = $new_val;
					break;
				case 'wc-on-hold':
					$field_val                    = self::cos_get_setting( 'labels', 'on-hold', '' );
					$new_val                      = ( $field_val ) ? $field_val : 'On Hold';
					$order_statuses['wc-on-hold'] = $new_val;
					break;
				case 'wc-completed':
					$field_val                      = self::cos_get_setting( 'labels', 'completed', '' );
					$new_val                        = ( $field_val ) ? $field_val : 'Completed';
					$order_statuses['wc-completed'] = $new_val;
					break;
				case 'wc-cancelled':
					$field_val                      = self::cos_get_setting( 'labels', 'cancelled', '' );
					$new_val                        = ( $field_val ) ? $field_val : 'Cancelled';
					$order_statuses['wc-cancelled'] = $new_val;
					break;
				case 'wc-refunded':
					$field_val                     = self::cos_get_setting( 'labels', 'refunded', '' );
					$new_val                       = ( $field_val ) ? $field_val : 'Refunded';
					$order_statuses['wc-refunded'] = $new_val;
					break;
				case 'wc-failed':
					$field_val                   = self::cos_get_setting( 'labels', 'failed', '' );
					$new_val                     = ( $field_val ) ? $field_val : 'Failed';
					$order_statuses['wc-failed'] = $new_val;
					break;
			}
		}
		return $order_statuses;
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
				return '';
			case 'value_order_list_actions':
				return self::cos_get_yes_no( 'general', 'add_to_order_list_actions' );
			case 'value_order_list_actions_colored':
				return self::cos_get_yes_no( 'general', 'list_actions_colored' );
			case 'value_column_colored':
				return self::cos_get_yes_no( 'general', 'enable_column_colored' );
			case 'value_order_preview_actions':
				return self::cos_get_yes_no( 'general', 'add_to_order_preview_actions' );
			case 'value_is_editable':
				return self::cos_get_yes_no( 'general', 'enable_editable' );
			case 'value_is_paid':
				return self::cos_get_yes_no( 'general', 'enable_paid' );
			case 'value_emails':
				if ( 'true' === $args['return'] ) {
					return 'yes';
				} else {
					return self::cos_get_yes_no( 'emails', 'enabled', true );
				}
		}
		return $value;
	}

    /**
     * Sets the compatibility with Woocommerce HPOS.
     *
     * @since 2.5.0
     */
    public function cos_custom_order_tables_compatibility() {

        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php', true );
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php', true );
        }
    }

	/**
	 * Adjusts future time considering skip days and skip dates.
	 *
	 * @param int   $future_time Timestamp of the future time.
	 * @param array $skip_days   Array of days (1-7) to skip.
	 * @param array $skip_dates  Array of dates (MM-DD-YYYY) to skip.
	 *
	 * @return int  Adjusted future time after skipping days and dates.
	 */
	public function adjust_for_skip_days( $future_time, $skip_days = array(), $skip_dates = array() ) {
		// Convert skip dates to a trimmed array of MM-DD-YYYY format.
		if ( ! is_array( $skip_dates ) ) {
			$skip_dates = explode( ',', $skip_dates );
		}
		$skip_dates = array_map( 'trim', $skip_dates );
		// Convert skip dates to an array of DateTime objects.
		$skip_dates_objects = array();
		foreach ( $skip_dates as $date_str ) {
			try {
				$date = DateTime::createFromFormat( 'm-d-Y', $date_str );
				if ( false !== $date ) {
					$skip_dates_objects[] = $date;
				}
			} catch ( Exception $e ) { // phpcs:ignore
				// errors in date creation.
			}
		}
		$current_time = time();
		$final_time   = $future_time;
		// Calculate the number of days to loop through.
		$days_to_check = ( $future_time - $current_time ) / DAY_IN_SECONDS;
		for ( $i = 0; $i < $days_to_check; $i++ ) {
			$check_time   = $current_time + ( $i * DAY_IN_SECONDS );
			$current_day  = gmdate( 'N', $check_time );
			$current_date = gmdate( 'm-d-Y', $check_time );
			// Create a DateTime object for the current date.
			$current_date_obj = DateTime::createFromFormat( 'm-d-Y', $current_date );
			$should_skip      = false;
			if ( in_array( $current_day, $skip_days ) ) { // phpcs:ignore
				$should_skip = true;
			}
			// Check if the current date is in the list of skip dates.
			foreach ( $skip_dates_objects as $skip_date_obj ) {
				if ( $skip_date_obj->format( 'm-d-Y' ) === $current_date ) {
					$should_skip = true;
					break;
				}
			}
			// If we need to skip this day, extend the final time by one day.
			if ( $should_skip ) {
				$final_time += DAY_IN_SECONDS;
			}
		}
		return $final_time;
	}

	/**
	 * Function for showing the Paid statment on the edit order page for the paid statement and for updating the stock levels on status change.
	 *
	 * @param array  $order_id - order ID.
	 * @param string $old_status - Old status.
	 * @param string $new_status - New status.
	 * @param object $order - Order object.
	 * @version 1.4.9
	 * @since   1.4.8
	 */
	public function schedule_order_status_change( $order_id, $old_status, $new_status, $order ) {

		// Get the saved rules.
		$rules = get_option( 'cos_pro_rules', array() );
		
		foreach ( $rules as $rule ) {
			// Check if the new status matches the rule's status_from.
			if ( $new_status === $rule['status_from'] && ! empty( $rule['enabled'] ) ) {
				// Check if payment gateways are defined in the rule and if the order's payment gateway matches.
				$payment_gateway_match     = empty( $rule['payment_methods'] ) || in_array( $order->get_payment_method(), $rule['payment_methods'] ); // phpcs:ignore
				$order_shipping_methods    = $order->get_shipping_methods();
				$order_shipping_method_ids = array();
				foreach ( $order_shipping_methods as $shipping_method ) {
					$method_id = $shipping_method->get_instance_id();
					if ( $method_id ) {
						$order_shipping_method_ids[] = $method_id;
					}
				}
				// Check if shipping methods are defined in the rule and if the order's shipping methods matches.
				$shipping_method_match = empty( $rule['shipping_methods'] ) || array_intersect( $order_shipping_method_ids, $rule['shipping_methods'] );
				// --- Check: Products ---
				$products_match = true;
				if ( ! empty( $rule['products'] ) ) {
					$products_match = false;
					foreach ( $order->get_items() as $item ) {
						if ( in_array( $item->get_product_id(), $rule['products'], true ) ) {
							$products_match = true;
							break;
						}
					}
				}
				// --- Check: Categories ---
				$categories_match = true;
				if ( ! empty( $rule['categories'] ) ) {
					$categories_match = false;
					foreach ( $order->get_items() as $item ) {
						$product_id   = $item->get_product_id();
						$product_cats = wc_get_product_term_ids( $product_id, 'product_cat' );
						if ( array_intersect( $product_cats, $rule['categories'] ) ) {
							$categories_match = true;
							break;
						}
					}
				}
				// --- Check: Min Order Amount ---
				$order_amount_match = true;
				if ( isset( $rule['min_amount'] ) && is_numeric( $rule['min_amount'] ) && $rule['min_amount'] > 0 ) {
					$order_amount_match = floatval( $order->get_total() ) >= floatval( $rule['min_amount'] );
				}
				// --- Check: Min Order Quantity ---
				$order_quantity_match = true;
				if ( isset( $rule['min_qty'] ) && is_numeric( $rule['min_qty'] ) && $rule['min_qty'] > 0 ) {
					$total_quantity       = 0;
					foreach ( $order->get_items() as $item ) {
						$total_quantity += $item->get_quantity();
					}
					$order_quantity_match = $total_quantity >= intval( $rule['min_qty'] );
				}
				// --- Check: User Roles ---
				$user_roles_match = true;
				if ( ! empty( $rule['user_roles'] ) ) {
					$user = $order->get_user();
					if ( ! $user || 0 === $user->ID ) {
						$user_role_keys = array( 'guest' );
					} else {
						$user_role_keys = $user->roles;
					}
					$user_roles_match = array_intersect( $rule['user_roles'], $user_role_keys );
				}
				// --- Check: Countries ---
				$country_match = true;
				if ( ! empty( $rule['countries'] ) ) {
					$billing_country = $order->get_billing_country();
					$country_match   = in_array( $billing_country, $rule['countries'], true );
				}
				if ( $payment_gateway_match && $shipping_method_match && $products_match && $categories_match && $order_amount_match && $order_quantity_match && $user_roles_match && $country_match ) {
					// Calculate the delay in seconds based on time_trigger and time_unit.
					$delay = 0;
					switch ( $rule['time_unit'] ) {
						case 'minutes':
							$delay = $rule['time_trigger'] * MINUTE_IN_SECONDS;
							break;
						case 'hours':
							$delay = $rule['time_trigger'] * HOUR_IN_SECONDS;
							break;
						case 'days':
							$delay = $rule['time_trigger'] * DAY_IN_SECONDS;
							break;
						case 'weeks':
							$delay = $rule['time_trigger'] * WEEK_IN_SECONDS;
							break;
					}
					if ( ! empty( $rule['skip_days'] ) || ! empty( $rule['skip_dates'] ) ) {
						$current_time = time();
						$future_time  = $current_time + $delay;
						// Adjust for skip days and dates.
						$adjusted_time = $this->adjust_for_skip_days( $future_time, $rule['skip_days'], $rule['skip_dates'] );
						// Recalculate delay based on adjusted time.
						$delay = $adjusted_time - $current_time;
					}
					// Schedule the action to change the status, only if not already scheduled.
					if ( ! as_has_scheduled_action( 'custom_change_order_status', array( $order_id, $rule['status_to'], $new_status ) ) ) {
						as_schedule_single_action( time() + $delay, 'custom_change_order_status', array( $order_id, $rule['status_to'], $new_status ) );
						// ✅ Add order note to let admin know status will be changed later.
						$order->add_order_note( sprintf( // phpcs:ignore
							__( 'Order status will be changed automatically to "%s" in %s %s.', 'custom-order-statuses-woocommerce' ), // phpcs:ignore
							$rule['status_to'],
							$rule['time_trigger'],
							$rule['time_unit']
						) ); // phpcs:ignore
					}
				}
			}
		}
	}

	/**
	 * Callback fucntion for Schedule the action custom_change_order_status.
	 *
	 * @param array  $order_id - order ID.
	 * @param string $new_status - New status.
	 */
	public function process_scheduled_order_status_change( $order_id, $new_status, $expected_current_status ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			if ( $order->get_status() === $expected_current_status ) {
				$order->update_status( $new_status, __( 'Order status changed via scheduled action.', 'custom-order-statuses-woocommerce' ) );
			} else {
				// Optional: Add an order note for skipped update.
				$order->add_order_note( sprintf( // phpcs:ignore
					__( 'Scheduled status change to "%s" skipped. Current status is "%s", expected "%s".', 'custom-order-statuses-woocommerce' ), // phpcs:ignore
					$new_status,
					$order->get_status(),
					$expected_current_status
				) ); // phpcs:ignore
			}
		}
	}

	/**
	 * Register hook.
	 */
	public function register_schedule_custom_hooks() {
		add_action( 'custom_change_order_status', array( $this, 'process_scheduled_order_status_change' ), 10, 3 );
	}

	/**
	 * This function updates the custom order status to the fallback status when we move the custom order status to trash.
	 *
	 * @param int $post_id - Post Id.
	 */
	public function update_order_status_on_trash( $post_id ) {
		$slug = get_post_meta( $post_id, 'status_slug', true );

		if ( ! empty( $slug ) ) {
			$orders = wc_get_orders(
				array(
					'return' => 'ids',
					'status' => $slug,
				)
			);
			if ( ! empty( $orders ) ) {
				foreach ( $orders as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( $order && $order instanceof WC_Order ) {
						$fallback_status = self::cos_get_setting( 'general', 'fallback_delete_status', 'on-hold' );
						$order->update_status( $fallback_status );
					}
				}
			}
		}
	}

	/**
	 * Get_custom_order_statuses_actions.
	 *
	 * @param Object $_order - Order object.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	public function get_custom_order_statuses_actions( $_order ) {
		$status_actions        = array();
		$custom_order_statuses = alg_get_custom_order_statuses_from_cpt( true );
		foreach ( $custom_order_statuses as $custom_order_status => $label ) {
			if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status.
				$status_actions[ $custom_order_status ] = $label;
			}
		}
		return $status_actions;
	}

	/**
	 * Get_custom_order_statuses_action_url.
	 *
	 * @param string $status - Order status.
	 * @param string $order_id - Order id.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	public function get_custom_order_statuses_action_url( $status, $order_id ) {
		return wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=' . $status . '&order_id=' . $order_id ), 'woocommerce-mark-order-status' );
	}

	/**
	 * Function add_custom_status_to_order_preview.
	 *
	 * @param array  $actions - array of actions.
	 * @param object $_order - object of Order.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	public function add_custom_status_to_order_preview( $actions, $_order ) {
		$status_actions  = array();
		$_status_actions = $this->get_custom_order_statuses_actions( $_order );
		if ( ! empty( $_status_actions ) ) {
			$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );
			foreach ( $_status_actions as $custom_order_status => $label ) {
				$status_actions[ $custom_order_status ] = array(
					'url'    => $this->get_custom_order_statuses_action_url( $custom_order_status, $order_id ),
					'name'   => $label,
					/* translators: %s: order status */
					'title'  => sprintf( __( 'Change order status to %s', 'custom-order-statuses-woocommerce' ), $custom_order_status ),
					'action' => $custom_order_status,
				);
			}
		}
		if ( $status_actions ) {
			if ( ! empty( $actions['status']['actions'] ) && is_array( $actions['status']['actions'] ) ) {
				$actions['status']['actions'] = array_merge( $actions['status']['actions'], $status_actions );
			} else {
				$actions['status'] = array(
					'group'   => __( 'Change status: ', 'woocommerce' ),
					'actions' => $status_actions,
				);
			}
		}
		return $actions;
	}

	/**
	 * Function add_custom_order_statuses_to_order_paid.
	 *
	 * @param array $statuses - array of statuses.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 * @todo    [feature] separate option for each custom status
	 */
	public function add_custom_order_statuses_to_order_paid( $statuses ) {
		return array_merge( $statuses, array_keys( alg_get_custom_order_statuses_from_cpt( true ) ) );
	}

	/**
	 * It will give the translated text from the WPML.
	 *
	 * @param string $get_translated_text Id of the message.
	 * @param string $message Message.
	 * @param string $language Selected language.
	 * @global mixed $wpdb.
	 * @return $message Message.
	 * @since 2.6
	 */
	public function cos_get_translated_texts( $get_translated_text, $message, $language ) {
		if ( function_exists( 'icl_register_string' ) ) {
			$translated = apply_filters( 'wpml_translate_single_string', $message, 'admin_texts_' . $get_translated_text, $get_translated_text, $language );
			return $translated;

		} else {
			return $message;
		}
	}

	/**
	 * Function send_email_on_order_status_changed.
	 *
	 * @param int    $order_id - order id.
	 * @param string $status_from - status from text.
	 * @param string $status_to - status to text.
	 * @param object $order - order object.
	 *
	 * @version 1.4.4
	 * @since   1.4.0
	 * @todo    [dev] maybe use `woocommerce_order_status_ . $status_transition['to']` action instead of `woocommerce_order_status_changed`
	 * @todo    [dev] recheck - email from
	 * @todo    [feature] add more replaced values
	 * @todo    [feature] optional `wrap_in_wc_email_template()`
	 * @todo    [feature] separate content, subject etc. for each custom status
	 */
	public function send_email_on_order_status_changed( $order_id, $status_from, $status_to, $order ) {

		$alg_orders_custom_statuses_array          = alg_get_custom_order_statuses_from_cpt();
		$alg_orders_custom_statuses_with_id_array  = alg_get_custom_order_statuses_from_cpt( true, true );
		$email_address                             = '';
		$bcc_email_address                         = '';
		$email_subject                             = '';
		$email_heading                             = '';
		$email_content                             = '';
		$emails_statuses                           = self::cos_get_setting( 'emails', 'statuses', array() );
		$is_global_emails_enabled                  = self::cos_get_yes_no( 'emails', 'enabled', '' );
		$alg_send_emails                           = false;
		$alg_orders_custom_statuses_emails_enabled = '';
		$products_with_title_link                  = array();
		$woo_statuses                              = wc_get_order_statuses();
		$lang                                      = get_post_meta( $order_id, 'wpml_language', true );

		global $sitepress;
		if ( empty( $lang ) && ! is_null( $sitepress ) ) {
			$lang = $sitepress->get_current_language();
		}

		// For the emails set at a global level.
		if ( 'yes' === $is_global_emails_enabled ) {
			if ( in_array( 'wc-' . $status_to, $emails_statuses, true ) || ( empty( $emails_statuses ) && in_array( 'wc-' . $status_to, array_keys( $alg_orders_custom_statuses_array ), true ) ) && in_array( 'wc-' . $status_to, array_keys( $woo_statuses ), true ) ) { // phpcs:ignore
				$alg_send_emails = true;
				// Options.
				$email_address     = self::cos_get_setting( 'emails', 'address', '' );
				$bcc_email_address = self::cos_get_setting( 'emails', 'bcc', '' );
				$email_subject     = self::cos_get_setting(
					'emails',
					'subject',
					/* translators: 1$s: site title, 2$s: order number, 3$s: status to, 4$s: order date */
					sprintf( __( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' )
				);
				$email_subject = $this->cos_get_translated_texts( 'alg_orders_custom_statuses_emails_subject', $email_subject, $lang );
				$email_heading = self::cos_get_setting(
					'emails',
					'heading',
					/* translators: $s: status to */
					sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' )
				);
				$email_heading = $this->cos_get_translated_texts( 'alg_orders_custom_statuses_emails_subject', $email_heading, $lang );
				$email_content = nl2br(
					self::cos_get_setting(
						'emails',
						'content',
						/* translators: 1$s: order number, 2$s: status from, 3$s: status to */
						sprintf( __( 'Order #%1$s status changed from %2$s to %3$s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' )
					)
				);
			}
		}
		// For the emails set at custom status level(Individual level).
		if ( ! empty( $alg_orders_custom_statuses_with_id_array ) ) {
			// Get custom meta box values of custom post status.
			if ( isset( $alg_orders_custom_statuses_with_id_array[ $status_to ] ) ) {
				$status_post_id                            = $alg_orders_custom_statuses_with_id_array[ $status_to ];
				$alg_orders_custom_statuses_emails_enabled = get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_enabled', true );
				if ( $status_post_id > 0 && 'yes' === $alg_orders_custom_statuses_emails_enabled ) {
					$alg_send_emails = true;
					if ( ! empty( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_address', true ) ) ) {
						$email_address = get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_address', true );
					}
					if ( ! empty( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_bcc_emails_address', true ) ) ) {
						$bcc_email_address = get_post_meta( $status_post_id, 'alg_orders_custom_statuses_bcc_emails_address', true );
					}
					if ( ! empty( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_subject', true ) ) ) {
						$email_subject = get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_subject', true );
						$email_subject = $this->cos_get_translated_texts( 'alg_orders_custom_statuses_emails_subject', $email_subject, $lang );
					}
					if ( ! empty( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_heading', true ) ) ) {
						$email_heading = get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_heading', true );
						$email_heading = $this->cos_get_translated_texts( 'alg_orders_custom_statuses_emails_subject', $email_heading, $lang );
					}
					if ( ! empty( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_content', true ) ) ) {
						$email_content = nl2br( get_post_meta( $status_post_id, 'alg_orders_custom_statuses_emails_content', true ) );
					}
				}
			}
		}

		if ( $alg_send_emails ) {

			$woo_statuses = wc_get_order_statuses();

			$replace_status_from = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] : $woo_statuses[ 'wc-' . $status_from ];

			$replace_status_to = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] : $woo_statuses[ 'wc-' . $status_to ];

			// if product_title is in email content.
			if ( strpos( $email_content, '{product_titles}' ) !== false ) {
				$items = $order->get_items();
				foreach ( $items as $item ) {
					$product                    = $item->get_product();
					$product_id                 = $product->get_id();
					$products_with_title_link[] = '<a href="' . get_permalink( $product_id ) . '">' . get_the_title( $product_id ) . '</a>';
				}
			}

			// {delivery_date}
			if ( strpos( $email_content, '{delivery_date}' ) !== false ) {
				$delivery_date = $order->get_meta( '_orddd_delivery_date' ); // adjust meta key if needed
			}

			// {delivery_time}
			if ( strpos( $email_content, '{delivery_time}' ) !== false ) {
				$delivery_time = $order->get_meta( '_orddd_time_slot' );
			}

			// {pickup_location}
			if ( strpos( $email_content, '{pickup_location}' ) !== false ) {
				$pickup_location = $order->get_meta( '_pickup_location' );
			}

			// {order_type}
			if ( strpos( $email_content, '{order_type}' ) !== false ) {
				$order_type = $order->get_meta( '_orddd_order_type' ); // delivery / pickup
			}
			// Replaced values.
			$replaced_values = array(
				'{order_id}'         => $order_id,
				'{order_number}'     => $order->get_order_number(),
				'{order_date}'       => gmdate( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ),
				'{order_details}'    => ( false !== strpos( $email_content, '{order_details}' ) ? $this->get_wc_order_details_template( $order ) : '' ),
				'{site_title}'       => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
				'{status_from}'      => $replace_status_from,
				'{status_to}'        => $replace_status_to,
				'{first_name}'       => $order->get_billing_first_name(),
				'{last_name}'        => $order->get_billing_last_name(),
				'{billing_address}'  => $order->get_formatted_billing_address(),
				'{shipping_address}' => $order->get_formatted_shipping_address(),
				'{product_titles}'   => ( 0 < count( $products_with_title_link ) ? implode( '<br>', $products_with_title_link ) : '' ),
				'{delivery_date}'    => ! empty( $delivery_date ) ? $delivery_date : '',
				'{delivery_time}'    => ! empty( $delivery_time ) ? $delivery_time : '',
				'{pickup_location}'  => ! empty( $pickup_location ) ? $pickup_location : '',
				'{order_type}'       => ! empty( $order_type ) ? ucfirst( $order_type ) : '',
			);

			if ( strpos( $email_content, '{custom_field_' ) !== false ) {
				foreach ( $order->get_meta_data() as $key => $value ) {
					$order_meta_data = $value->get_data();
					$meta_key        = $order_meta_data['key'];
					$meta_data[]     = array(
						'{custom_field_' . $meta_key . '}' => get_post_meta( $order_id, $meta_key, true ),
					);
				}
				if ( ! empty( $meta_data ) && is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta_key ) {
						// Will replace the meta key shortcode with their respective meta values.
						$replaced_values = array_merge( $replaced_values, $meta_key );
					}
				}
			}
			$email_replaced_values = array(
				'{customer_email}' => $order->get_billing_email(),
				'{admin_email}'    => get_option( 'admin_email' ),
			);
			// Final processing.
			$email_address = ( '' === $email_address ? get_option( 'admin_email' ) : str_replace( array_keys( $email_replaced_values ), $email_replaced_values, $email_address ) );
			$email_subject = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_subject ) );
			$email_heading = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_heading ) );
			$email_content = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $this->wrap_in_wc_email_template( $email_content, $email_heading ) ) );
			$headers       = array();
			$headers[]     = 'Content-Type: text/html; charset=UTF-8';
			$from_name     = wp_specialchars_decode( get_option( 'woocommerce_email_from_name' ), ENT_QUOTES );
			$from_address  = sanitize_email( get_option( 'woocommerce_email_from_address' ) );
			$headers[]     = 'From: ' . $from_name . ' <' . $from_address . '>';
			$bcc_to        = array();
			if ( ! empty( $bcc_email_address ) ) {
				$bcc_to = explode( ', ', $bcc_email_address );
				if ( ! empty( $bcc_to ) ) {
					foreach ( $bcc_to as $email ) {
						$headers[] = 'Bcc: ' . $email;
					}
				}
			}

			if ( in_array( 'woocommerce-pdf-invoices-packing-slips/woocommerce-pdf-invoices-packingslips.php', (array) get_option( 'active_plugins', array() ), true ) ) {
				$upload_dir  = trailingslashit( WPO_WCPDF()->main->get_tmp_path( 'attachments' ) );
				$attachments = $upload_dir . '/invoice-' . $order_id . '.pdf';
			} else {
				$attachments = '';
			}
			// Send mail.
			wc_mail( $email_address, strval( $email_subject ), $email_content, $headers, $attachments );
		}
	}

	/**
	 * Function get_wc_order_details_template.
	 *
	 * @param object $order - order object.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	public static function get_wc_order_details_template( $order ) {
		ob_start();
		wc_get_template(
			'emails/email-order-details.php',
			array(
				'order'         => $order,
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => '',
			)
		);
		$address_details = apply_filters( 'alg_orders_custom_statuses_address_details_in_emails', true );
		if ( $address_details ) {
			wc_get_template(
				'emails/email-addresses.php',
				array(
					'order'         => $order,
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => '',
				)
			);
		}
		return ob_get_clean();
	}

	/**
	 * Function wrap_in_wc_email_template.
	 *
	 * @param string $content - email content.
	 * @param string $email_heading - email heading.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	public function wrap_in_wc_email_template( $content, $email_heading = '' ) {
		return $this->get_wc_email_part( 'header', $email_heading ) . $content . $this->get_wc_email_part( 'footer' );
	}

	/**
	 * Function get_wc_email_part.
	 *
	 * @param string $part - part value.
	 * @param string $email_heading - email heading.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	public function get_wc_email_part( $part, $email_heading = '' ) {
		ob_start();
		switch ( $part ) {
			case 'header':
				wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
				break;
			case 'footer':
				wc_get_template( 'emails/email-footer.php' );
				break;
		}
		return ob_get_clean();
	}

	/**
	 * Function add_custom_order_statuses_to_order_editable.
	 *
	 * @param string $is_editable - value for is_editable.
	 * @param object $_order - order object.
	 *
	 * @version 1.3.5
	 * @since   1.3.5
	 * @todo    [feature] separate option for each custom status
	 */
	public function add_custom_order_statuses_to_order_editable( $is_editable, $_order ) {
		return ( in_array( 'wc-' . $_order->get_status(), array_keys( alg_get_custom_order_statuses_from_cpt() ), true ) ? true : $is_editable );
	}

	/**
	 * Function add_custom_status_column_css.
	 *
	 * @version 1.3.3
	 * @since   1.3.2
	 */
	public function add_custom_status_column_css() {
		$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
		if ( empty( $statuses ) ) {
			$statuses = alg_get_custom_order_statuses();
		}
		foreach ( $statuses as $status => $status_id ) {
			$content    = get_post_meta( $status_id, 'content', true );
			$icon_color = get_post_meta( $status_id, 'color', true );
			$text_color = get_post_meta( $status_id, 'text_color', true );
			if ( ! $text_color ) {
				$text_color = '#000000';
			}
			if ( ! $icon_color ) {
				$icon_color = '#999999';
			}

			if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
				$status      = substr( $status, 3 );
				$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );
				if ( $status_data['content'] ) {
					$content = $status_data['content'];
				}
				if ( $status_data['color'] ) {
					$icon_color = $status_data['color'];
				}
				if ( $status_data['text_color'] ) {
					$text_color = $status_data['text_color'];
				}
			}
			echo '<style>mark.order-status.status-' . esc_attr( $status ) . ' { color: ' . esc_attr( $text_color ) . '; background-color: ' . esc_attr( $icon_color ) . ' }</style>';
		}
	}

	/**
	 * Function for showing the Paid statment on the edit order page for the paid statement and for updating the stock levels on status change.
	 *
	 * @param array  $order_id - order ID.
	 * @param string $old_status - Old status.
	 * @param string $new_status - New status.
	 * @param object $order - Order object.
	 * @version 1.4.9
	 * @since   1.4.8
	 */
	public function alg_cos_paid_status_and_update_stock_levels( $order_id, $old_status, $new_status, $order ) {
		global $wpdb;
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
		if ( in_array( 'wc-' . $new_status, array_keys( $alg_orders_custom_statuses_array ), true ) ) {
			$post_id             = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='status_slug' AND meta_value=%s LIMIT 1", $new_status ) ); //phpcs:ignore
			if ( cos_wc_hpos_enabled() ) {
				$individual_paid_cos = $order->get_meta( 'alg_orders_individual_custom_status_enable_paid' );
			} else {
				$individual_paid_cos = get_post_meta( $post_id, 'alg_orders_individual_custom_status_enable_paid', true );
			}
			if ( 'yes' === $individual_paid_cos || ( '' === $individual_paid_cos && self::cos_get_yes_no( 'general', 'enable_paid', false ) === 'yes' ) ) {
				// Add the paid time in the order object to show the the Paid statement.
				$order->set_date_paid( time() );
				$order->save();
			}
		}
		$statuses = alg_get_custom_order_statuses_from_cpt();
		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			if ( $new_status === $custom_order_status ) {
				$stock_reduced = get_post_meta( $order_id, '_order_stock_reduced', true );
				$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='status_slug' AND meta_value='%s' LIMIT 1", $custom_order_status ) ); // phpcs:ignore
				$reduce_stock  = get_post_meta( $post_id, 'reduce_stock', true );
				if ( 'decrease' === $reduce_stock && 'no' === $stock_reduced ) {
					wc_maybe_reduce_stock_levels( $order_id );
				} elseif ( 'decrease' === $reduce_stock ) {
					wc_maybe_reduce_stock_levels( $order_id );
				} elseif ( 'increase' === $reduce_stock && 'yes' === $stock_reduced ) {
					wc_maybe_increase_stock_levels( $order_id );
				}
			}
		}
	}

	/**
	 * Function for updating the stock levels on order item update.
	 *
	 * @param int $order_id - order ID.
	 * @version 1.4.9
	 * @since   1.4.9
	 */
	public function alg_cos_adjust_stock_on_order_item_update( $order_id ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$current_status  = $order->get_status();
		$custom_statuses = alg_get_custom_order_statuses_from_cpt();
		if ( ! in_array( 'wc-' . $current_status, array_keys( $custom_statuses ), true ) ) {
			return;
		}
		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'status_slug' AND meta_value = %s LIMIT 1", $current_status ) ); // phpcs:ignore
		if ( ! $post_id ) {
			return;
		}
		$reduce_stock = get_post_meta( $post_id, 'reduce_stock', true );
		if ( 'decrease' !== $reduce_stock ) {
			return;
		}
		$is_hpos = function_exists( 'cos_wc_hpos_enabled' ) && cos_wc_hpos_enabled();
		if ( $is_hpos ) {
			$stock_reduced = $order->get_meta( '_order_stock_reduced' );
			if ( $stock_reduced ) {
				wc_maybe_increase_stock_levels( $order );
				$order->delete_meta_data( '_order_stock_reduced' );
			}
			wc_maybe_reduce_stock_levels( $order );
			$order->save();
		} else {
			$stock_reduced = get_post_meta( $order_id, '_order_stock_reduced', true );
			if ( $stock_reduced ) {
				wc_maybe_increase_stock_levels( $order_id );
				delete_post_meta( $order_id, '_order_stock_reduced' );
			}
			wc_maybe_reduce_stock_levels( $order_id );
		}
	}

	/**
	 * Function for making order uneditabale for the paid custom order status.
	 *
	 * @param string $is_editable - value for is_editable.
	 * @param object $_order - order object.
	 *
	 * @version 2.0.4
	 * @since   2.0.4
	 */
	public function alg_paid_status_order_not_editable( $is_editable, $_order ) {
		global $wpdb;
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
		$custom_order_status              = $_order->get_status();
		if ( in_array( 'wc-' . $custom_order_status, array_keys( $alg_orders_custom_statuses_array ), true ) ) {
			$post_id             = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='status_slug' AND meta_value=%s LIMIT 1", $custom_order_status ) ); // phpcs:ignore
			$individual_paid_cos = get_post_meta( $post_id, 'alg_orders_individual_custom_status_enable_paid', true );
			if ( 'yes' === $individual_paid_cos || ( '' === $individual_paid_cos && 'yes' === self::cos_get_yes_no( 'general', 'enable_paid', false ) ) ) {
				return false;
			}
		}
		return $is_editable;
	}

	/**
	 * Function add_custom_status_actions_buttons.
	 *
	 * @param array  $actions - array of actions.
	 * @param object $_order - order object.
	 * @version 1.4.1
	 * @since   1.2.0
	 */
	public function add_custom_status_actions_buttons( $actions, $_order ) {
		$statuses = alg_get_custom_order_statuses_from_cpt();

		$_order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );

		// if the complete order action is not present in the array, add it (happens when the order is set to a custom status).
		if ( ! in_array( 'complete', $actions, true ) ) {
			$actions['complete'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $_order_id ), 'woocommerce-mark-order-status' ),
				'name'   => __( 'Complete', 'woocommerce' ),
				'action' => 'complete',
			);
		}

		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status.
				$actions[ $custom_order_status ] = array(
					'url'    => $this->get_custom_order_statuses_action_url( $custom_order_status, $_order_id ),
					'name'   => $label,
					'action' => 'view ' . $custom_order_status, // setting "view" for proper button CSS.
				);
			}
		}
		return $actions;
	}

	/**
	 * Function add_custom_status_actions_buttons_css.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	public function add_custom_status_actions_buttons_css() {
		$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
		if ( empty( $statuses ) ) {
			$statuses = alg_get_custom_order_statuses();
		}
		foreach ( $statuses as $status => $status_id ) {
			$content    = get_post_meta( $status_id, 'content', true );
			$icon_color = get_post_meta( $status_id, 'color', true );
			$text_color = get_post_meta( $status_id, 'text_color', true );
			if ( ! $text_color ) {
				$text_color = '#000000';
			}
			if ( ! $icon_color ) {
				$icon_color = '#999999';
			}
			if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
				$status      = substr( $status, 3 );
				$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );

				if ( ! empty( $status_data['content'] ) ) {
					$content = $status_data['content'];
				}
				if ( ! empty( $status_data['color'] ) ) {
					$icon_color = $status_data['color'];
				}
				if ( ! empty( $status_data['text_color'] ) ) {
					$text_color = $status_data['text_color'];
				}
			}

			$font_family = '"Font Awesome 7 Free", "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome"';
			
			// Build CSS string.
			$color_style = ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions_colored' ) )
				? ' color: ' . $icon_color . ' !important;' : '';
			$css         = '.view.' . sanitize_html_class( $status ) . '::after { ' .
				'font-family: ' . $font_family . ' !important; ' .
				$color_style .
				'content: "\\' . $content . '" !important; }';
			// Output inline CSS.
			echo '<style>' . $css . '</style>'; // phpcs:ignore
		}
	}

	/**
	 * Function add_custom_order_statuses_to_reports.
	 *
	 * @param array $order_statuses - array of order status.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	public function add_custom_order_statuses_to_reports( $order_statuses ) {
		if ( is_array( $order_statuses ) && in_array( 'completed', $order_statuses, true ) ) {
			return array_merge( $order_statuses, array_keys( alg_get_custom_order_statuses_from_cpt( true ) ) );
		}
		return $order_statuses;
	}

	/**
	 * Set_default_order_status.
	 *
	 * @param string $order_id - Order id.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public function set_default_order_status( $order_id ) {
		if ( ! $order_id ) {
			return;
		}
		$order           = wc_get_order( $order_id );
		$payment_method  = $order->get_payment_method();
		$alg_cos_updated = cos_wc_hpos_enabled() ? $order->get_meta( 'alg_cos_updated' ) : get_post_meta( $order_id, 'alg_cos_updated', true );
		if ( 'yes' !== $alg_cos_updated ) {
			if ( '' !== self::cos_get_setting( 'gateways', $payment_method, '' ) ) {
				$order->update_status( self::cos_get_setting( 'gateways', $payment_method, '' ) );
				if ( cos_wc_hpos_enabled() ) {
					$order->update_meta_data( 'alg_cos_updated', 'yes' );
					$order->save();
				} else {
					update_post_meta( $order_id, 'alg_cos_updated', 'yes' );
				}
			} elseif ( '' !== self::cos_get_setting( 'general', 'default_status', '' ) ) {
				$order->update_status( self::cos_get_setting( 'general', 'default_status', '' ) );
				if ( cos_wc_hpos_enabled() ) {
					$order->update_meta_data( 'alg_cos_updated', 'yes' );
					$order->save();
				} else {
					update_post_meta( $order_id, 'alg_cos_updated', 'yes' );
				}
			}
		}
	}

	/**
	 * Function register_custom_post_statuses.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	public function register_custom_post_statuses() {
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
		foreach ( $alg_orders_custom_statuses_array as $slug => $label ) {
			register_post_status(
				$slug,
				array(
					'label'                     => $label,
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					// translators: Count of orders with the custom status.
					'label_count'               => _n_noop( "$label <span class='count'>(%s)</span>", "$label <span class='count'>(%s)</span>" ), // phpcs:ignore
				)
			);
		}
	}

	/**
	 * Function add_custom_statuses_to_filter.
	 *
	 * @param array $order_statuses - Order status.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	public function add_custom_statuses_to_filter( $order_statuses ) {
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
		$order_statuses                   = ( '' === $order_statuses ) ? array() : $order_statuses;
		return array_merge( $order_statuses, $alg_orders_custom_statuses_array );
	}

	/**
	 * If custom status allow download.
	 *
	 * @param array  $data - array of data.
	 * @param object $order - order object.
	 * @since 1.4.7
	 */
	public function custom_download_access( $data, $order ) {

		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
		if ( is_array( $alg_orders_custom_statuses_array ) && count( $alg_orders_custom_statuses_array ) > 0 ) {
			$custom_status = array();
			foreach ( $alg_orders_custom_statuses_array as $status_slug => $status_name ) {
				$status_slug2 = 'wc-' === substr( $status_slug, 0, 3 ) ? substr( $status_slug, 3 ) : $status_slug;
				array_push( $custom_status, $status_slug2 );
			}

			if ( in_array( $order->get_status(), $custom_status, true ) ) {
				return true;
			}
		}

		return $data;
	}

	/**
	 * Add custom statuses for paid status
	 *
	 * @param array $paid_statuses - array of status.
	 * @since 1.4.7
	 */
	public function add_custom_statuses_to_paid( $paid_statuses ) {

		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses();
		$paid_statuses                    = ( '' === $paid_statuses ) ? array() : $paid_statuses;
		if ( is_array( $alg_orders_custom_statuses_array ) && count( $alg_orders_custom_statuses_array ) > 0 ) {
			foreach ( $alg_orders_custom_statuses_array as $status_slug => $status_name ) {
				$status_slug2 = 'wc-' === substr( $status_slug, 0, 3 ) ? substr( $status_slug, 3 ) : $status_slug;
				array_push( $paid_statuses, $status_slug2 );
			}
		}

		return $paid_statuses;
	}

	/**
	 * Add the statuses in which the order can be cancelled by user.
	 *
	 * @param array $statuses Array of cancel statuses.
	 */
	public function alg_cos_add_statuses_for_cancel( $cancel_statuses ) { // phpcs:ignore
		global $wpdb;
		$custom_order_statuses   = alg_get_custom_order_statuses_from_cpt();
		foreach ( $custom_order_statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			$post_id             = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='status_slug' AND meta_value=%s LIMIT 1", $custom_order_status ) ); //phpcs:ignore
			$user_can_cancel     = get_post_meta( $post_id, 'alg_orders_individual_custom_status_user_cancel', true );
			if ( 'yes' === $user_can_cancel ) {
				$cancel_statuses[] = substr( $slug, 3 );
			}
		}
		return $cancel_statuses;
	}

	/**
	 * Function hook_statuses_icons_css.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	public function hook_statuses_icons_css() {
		global $post;

		if ( ( isset( $post->post_type ) && 'shop_order' === $post->post_type ) || ( isset( $post->post_name ) && 'my-account' === $post->post_name ) || ( isset( $_GET['page'] ) == 'wc-orders' ) ) { //phpcs:ignore
			$output   = '<style>';
			$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
			if ( empty( $statuses ) ) {
				$statuses = alg_get_custom_order_statuses();
			}
			foreach ( $statuses as $status => $status_id ) {
				$content    = get_post_meta( $status_id, 'content', true );
				$icon_color = get_post_meta( $status_id, 'color', true );
				$text_color = get_post_meta( $status_id, 'text_color', true );
				// Determine font-family based on the content format.
				$font_family = '"Font Awesome 7 Free", "Font Awesome 6 Free", "Font Awesome 5 Free", "FontAwesome"';
				if ( ! $text_color ) {
					$text_color = '#000000';
				}
				if ( ! $icon_color ) {
					$icon_color = '#999999';
				}

				if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
					$status      = substr( $status, 3 );
					$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );
					if ( $status_data['content'] ) {
						$content = $status_data['content'];
					}
					if ( $status_data['color'] ) {
						$icon_color = $status_data['color'];
					}
					if ( $status_data['text_color'] ) {
						$text_color = $status_data['text_color'];
					}
				}

				if ( 'yes' !== self::cos_get_yes_no( 'general', 'enable_column_colored', false ) ) {
					$text_color = '';
				}
				$output .= '.status-' . $status . ' { position: relative; color: ' . $text_color . '; }';
				$output .= '.woocommerce-orders-table__row--status-' . esc_attr( $status ) . ' .woocommerce-orders-table__cell-order-status { position: relative; }';
				if ( 'yes' === self::cos_get_yes_no('general', 'enable_column_icons', true) ) {
					$output .= 'mark.status-' . $status . ':before { 
						content: "\\' . $content . '"; 
						color: ' . $text_color . '; 
						margin: 10px -8px 8px 8px; 
						font-family: ' . $font_family . ';
						line-height: 1; 
						-webkit-font-smoothing: antialiased; 
						text-indent: 0; 
					}';
				}
				$output         .= '.woocommerce-orders-table__row--status-' . esc_attr( $status ) . ' .woocommerce-orders-table__cell-order-status:after { content: "\\' . $content . '"; }';
				$output         .= 'mark.status-' . $status . ':after { font-family: WooCommerce; speak: none; font-weight: 400; font-variant: normal; text-transform: none; line-height: 1; -webkit-font-smoothing: antialiased; margin: 0; text-indent: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; text-align: center }';
				$user_can_cancel = get_post_meta( $status_id, 'alg_orders_individual_custom_status_user_cancel', true );
				if ( 'yes' === $user_can_cancel ) {
					$output .= '.woocommerce-orders-table__row--status-' . esc_attr( $status ) . ' .woocommerce-orders-table__cell-order-status:after { font-family: WooCommerce; speak: none; font-weight: 400; font-variant: normal; text-transform: none; line-height: 1; -webkit-font-smoothing: antialiased; margin: 0; text-indent: 0; position: absolute; top: 20%; left: 0; width: 100%; height: 100%; text-align: left }';
				} else {
					$output .= '.woocommerce-orders-table__row--status-' . esc_attr( $status ) . ' .woocommerce-orders-table__cell-order-status:after { font-family: WooCommerce; speak: none; font-weight: 400; font-variant: normal; text-transform: none; line-height: 1; -webkit-font-smoothing: antialiased; margin: 0; text-indent: 0; position: absolute; top: 30%; left: 0; width: 100%; height: 100%; text-align: left }';
				}
			}
			$output .= '</style>';
			echo wp_kses( $output, array( 'style' => array() ) );

			wp_enqueue_style(
				'cos-pro-font-awesome',
				COS_PLUGIN_URL . 'assets/css/all.min.css',
				array(),
				'6.5.1'
			);
		}
	}

	/**
	 * Function register_order_custom_status_bulk_actions.
	 *
	 * @param array $bulk_actions - array of actions.
	 *
	 * @version 1.4.0
	 * @since   1.1.0
	 * @see     https://make.wordpress.org/core/2016/10/04/custom-bulk-actions/
	 */
	public function register_order_custom_status_bulk_actions( $bulk_actions ) {
		$custom_order_statuses = alg_get_custom_order_statuses_from_cpt( true );
		foreach ( $custom_order_statuses as $slug => $label ) {
			// translators: Name of the custom status.
			$bulk_actions[ 'mark_' . $slug ] = sprintf( __( 'Change status to %s', 'custom-order-statuses-woocommerce' ), $label );
		}
		return $bulk_actions;
	}

	/**
	 * Add extra bulk action options to mark orders as complete or processing.
	 *
	 * Using Javascript until WordPress core fixes: http://core.trac.wordpress.org/ticket/16031
	 * Fixed in WordPress v4.7
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	public function bulk_admin_footer() {
		global $post_type;
		if ( 'shop_order' === $post_type ) {
			?><script type="text/javascript">
			<?php
			foreach ( alg_get_order_statuses() as $key => $order_status ) {
				if ( in_array( $key, array( 'processing', 'on-hold', 'completed' ), true ) ) {
					continue;
				}
				?>
				jQuery(function() {
					jQuery('<option>').val('mark_<?php echo esc_attr( $key ); ?>').text('<?php echo esc_attr__( 'Mark', 'custom-order-statuses-woocommerce' ) . ' ' . esc_attr( $order_status ); ?>').appendTo('select[name="action"]');
					jQuery('<option>').val('mark_<?php echo esc_attr( $key ); ?>').text('<?php echo esc_attr__( 'Mark', 'custom-order-statuses-woocommerce' ) . ' ' . esc_attr( $order_status ); ?>').appendTo('select[name="action2"]');
				});
				<?php
			}
			?>
			</script>
			<?php
		}
	}

	/**
	 * Returns an array with mapped Country codes with ISD codes.
	 *
	 * @return array Mapped Array
	 */
	public function cos_country_code_map() {

		return array(
			'IL' => array(
				'name'      => 'Israel',
				'dial_code' => '+972',
			),
			'AF' => array(
				'name'      => 'Afghanistan',
				'dial_code' => '+93',
			),
			'AL' => array(
				'name'      => 'Albania',
				'dial_code' => '+355',
			),
			'DZ' => array(
				'name'      => 'Algeria',
				'dial_code' => '+213',
			),
			'AS' => array(
				'name'      => 'AmericanSamoa',
				'dial_code' => '+1684',
			),
			'AD' => array(
				'name'      => 'Andorra',
				'dial_code' => '+376',
			),
			'AO' => array(
				'name'      => 'Angola',
				'dial_code' => '+244',
			),
			'AI' => array(
				'name'      => 'Anguilla',
				'dial_code' => '+1264',
			),
			'AG' => array(
				'name'      => 'Antigua and Barbuda',
				'dial_code' => '+1268',
			),
			'AR' => array(
				'name'      => 'Argentina',
				'dial_code' => '+54',
			),
			'AM' => array(
				'name'      => 'Armenia',
				'dial_code' => '+374',
			),
			'AW' => array(
				'name'      => 'Aruba',
				'dial_code' => '+297',
			),
			'AU' => array(
				'name'      => 'Australia',
				'dial_code' => '+61',
			),
			'AT' => array(
				'name'      => 'Austria',
				'dial_code' => '+43',
			),
			'AZ' => array(
				'name'      => 'Azerbaijan',
				'dial_code' => '+994',
			),
			'BS' => array(
				'name'      => 'Bahamas',
				'dial_code' => '+1 242',
			),
			'BH' => array(
				'name'      => 'Bahrain',
				'dial_code' => '+973',
			),
			'BD' => array(
				'name'      => 'Bangladesh',
				'dial_code' => '+880',
			),
			'BB' => array(
				'name'      => 'Barbados',
				'dial_code' => '+1 246',
			),
			'BY' => array(
				'name'      => 'Belarus',
				'dial_code' => '+375',
			),
			'BE' => array(
				'name'      => 'Belgium',
				'dial_code' => '+32',
			),
			'BZ' => array(
				'name'      => 'Belize',
				'dial_code' => '+501',
			),
			'BJ' => array(
				'name'      => 'Benin',
				'dial_code' => '+229',
			),
			'BM' => array(
				'name'      => 'Bermuda',
				'dial_code' => '+1 441',
			),
			'BT' => array(
				'name'      => 'Bhutan',
				'dial_code' => '+975',
			),
			'BA' => array(
				'name'      => 'Bosnia and Herzegovina',
				'dial_code' => '+387',
			),
			'BW' => array(
				'name'      => 'Botswana',
				'dial_code' => '+267',
			),
			'BR' => array(
				'name'      => 'Brazil',
				'dial_code' => '+55',
			),
			'IO' => array(
				'name'      => 'British Indian Ocean Territory',
				'dial_code' => '+246',
			),
			'BG' => array(
				'name'      => 'Bulgaria',
				'dial_code' => '+359',
			),
			'BF' => array(
				'name'      => 'Burkina Faso',
				'dial_code' => '+226',
			),
			'BI' => array(
				'name'      => 'Burundi',
				'dial_code' => '+257',
			),
			'KH' => array(
				'name'      => 'Cambodia',
				'dial_code' => '+855',
			),
			'CM' => array(
				'name'      => 'Cameroon',
				'dial_code' => '+237',
			),
			'CA' => array(
				'name'      => 'Canada',
				'dial_code' => '+1',
			),
			'CV' => array(
				'name'      => 'Cape Verde',
				'dial_code' => '+238',
			),
			'KY' => array(
				'name'      => 'Cayman Islands',
				'dial_code' => '+ 345',
			),
			'CF' => array(
				'name'      => 'Central African Republic',
				'dial_code' => '+236',
			),
			'TD' => array(
				'name'      => 'Chad',
				'dial_code' => '+235',
			),
			'CL' => array(
				'name'      => 'Chile',
				'dial_code' => '+56',
			),
			'CN' => array(
				'name'      => 'China',
				'dial_code' => '+86',
			),
			'CX' => array(
				'name'      => 'Christmas Island',
				'dial_code' => '+61',
			),
			'CO' => array(
				'name'      => 'Colombia',
				'dial_code' => '+57',
			),
			'KM' => array(
				'name'      => 'Comoros',
				'dial_code' => '+269',
			),
			'CG' => array(
				'name'      => 'Congo',
				'dial_code' => '+242',
			),
			'CK' => array(
				'name'      => 'Cook Islands',
				'dial_code' => '+682',
			),
			'CR' => array(
				'name'      => 'Costa Rica',
				'dial_code' => '+506',
			),
			'HR' => array(
				'name'      => 'Croatia',
				'dial_code' => '+385',
			),
			'CU' => array(
				'name'      => 'Cuba',
				'dial_code' => '+53',
			),
			'CY' => array(
				'name'      => 'Cyprus',
				'dial_code' => '+537',
			),
			'CZ' => array(
				'name'      => 'Czech Republic',
				'dial_code' => '+420',
			),
			'DK' => array(
				'name'      => 'Denmark',
				'dial_code' => '+45',
			),
			'DJ' => array(
				'name'      => 'Djibouti',
				'dial_code' => '+253',
			),
			'DM' => array(
				'name'      => 'Dominica',
				'dial_code' => '+1 767',
			),
			'DO' => array(
				'name'      => 'Dominican Republic',
				'dial_code' => '+1849',
			),
			'EC' => array(
				'name'      => 'Ecuador',
				'dial_code' => '+593',
			),
			'EG' => array(
				'name'      => 'Egypt',
				'dial_code' => '+20',
			),
			'SV' => array(
				'name'      => 'El Salvador',
				'dial_code' => '+503',
			),
			'GQ' => array(
				'name'      => 'Equatorial Guinea',
				'dial_code' => '+240',
			),
			'ER' => array(
				'name'      => 'Eritrea',
				'dial_code' => '+291',
			),
			'EE' => array(
				'name'      => 'Estonia',
				'dial_code' => '+372',
			),
			'ET' => array(
				'name'      => 'Ethiopia',
				'dial_code' => '+251',
			),
			'FO' => array(
				'name'      => 'Faroe Islands',
				'dial_code' => '+298',
			),
			'FJ' => array(
				'name'      => 'Fiji',
				'dial_code' => '+679',
			),
			'FI' => array(
				'name'      => 'Finland',
				'dial_code' => '+358',
			),
			'FR' => array(
				'name'      => 'France',
				'dial_code' => '+33',
			),
			'GF' => array(
				'name'      => 'French Guiana',
				'dial_code' => '+594',
			),
			'PF' => array(
				'name'      => 'French Polynesia',
				'dial_code' => '+689',
			),
			'GA' => array(
				'name'      => 'Gabon',
				'dial_code' => '+241',
			),
			'GM' => array(
				'name'      => 'Gambia',
				'dial_code' => '+220',
			),
			'GE' => array(
				'name'      => 'Georgia',
				'dial_code' => '+995',
			),
			'DE' => array(
				'name'      => 'Germany',
				'dial_code' => '+49',
			),
			'GH' => array(
				'name'      => 'Ghana',
				'dial_code' => '+233',
			),
			'GI' => array(
				'name'      => 'Gibraltar',
				'dial_code' => '+350',
			),
			'GR' => array(
				'name'      => 'Greece',
				'dial_code' => '+30',
			),
			'GL' => array(
				'name'      => 'Greenland',
				'dial_code' => '+299',
			),
			'GD' => array(
				'name'      => 'Grenada',
				'dial_code' => '+1 473',
			),
			'GP' => array(
				'name'      => 'Guadeloupe',
				'dial_code' => '+590',
			),
			'GU' => array(
				'name'      => 'Guam',
				'dial_code' => '+1 671',
			),
			'GT' => array(
				'name'      => 'Guatemala',
				'dial_code' => '+502',
			),
			'GN' => array(
				'name'      => 'Guinea',
				'dial_code' => '+224',
			),
			'GW' => array(
				'name'      => 'Guinea-Bissau',
				'dial_code' => '+245',
			),
			'GY' => array(
				'name'      => 'Guyana',
				'dial_code' => '+595',
			),
			'HT' => array(
				'name'      => 'Haiti',
				'dial_code' => '+509',
			),
			'HN' => array(
				'name'      => 'Honduras',
				'dial_code' => '+504',
			),
			'HU' => array(
				'name'      => 'Hungary',
				'dial_code' => '+36',
			),
			'IS' => array(
				'name'      => 'Iceland',
				'dial_code' => '+354',
			),
			'IN' => array(
				'name'      => 'India',
				'dial_code' => '+91',
			),
			'ID' => array(
				'name'      => 'Indonesia',
				'dial_code' => '+62',
			),
			'IQ' => array(
				'name'      => 'Iraq',
				'dial_code' => '+964',
			),
			'IE' => array(
				'name'      => 'Ireland',
				'dial_code' => '+353',
			),
			'IT' => array(
				'name'      => 'Italy',
				'dial_code' => '+39',
			),
			'JM' => array(
				'name'      => 'Jamaica',
				'dial_code' => '+1876',
			),
			'JP' => array(
				'name'      => 'Japan',
				'dial_code' => '+81',
			),
			'JO' => array(
				'name'      => 'Jordan',
				'dial_code' => '+962',
			),
			'KZ' => array(
				'name'      => 'Kazakhstan',
				'dial_code' => '+77',
			),
			'KE' => array(
				'name'      => 'Kenya',
				'dial_code' => '+254',
			),
			'KI' => array(
				'name'      => 'Kiribati',
				'dial_code' => '+686',
			),
			'KW' => array(
				'name'      => 'Kuwait',
				'dial_code' => '+965',
			),
			'KG' => array(
				'name'      => 'Kyrgyzstan',
				'dial_code' => '+996',
			),
			'LV' => array(
				'name'      => 'Latvia',
				'dial_code' => '+371',
			),
			'LB' => array(
				'name'      => 'Lebanon',
				'dial_code' => '+961',
			),
			'LS' => array(
				'name'      => 'Lesotho',
				'dial_code' => '+266',
			),
			'LR' => array(
				'name'      => 'Liberia',
				'dial_code' => '+231',
			),
			'LI' => array(
				'name'      => 'Liechtenstein',
				'dial_code' => '+423',
			),
			'LT' => array(
				'name'      => 'Lithuania',
				'dial_code' => '+370',
			),
			'LU' => array(
				'name'      => 'Luxembourg',
				'dial_code' => '+352',
			),
			'MG' => array(
				'name'      => 'Madagascar',
				'dial_code' => '+261',
			),
			'MW' => array(
				'name'      => 'Malawi',
				'dial_code' => '+265',
			),
			'MY' => array(
				'name'      => 'Malaysia',
				'dial_code' => '+60',
			),
			'MV' => array(
				'name'      => 'Maldives',
				'dial_code' => '+960',
			),
			'ML' => array(
				'name'      => 'Mali',
				'dial_code' => '+223',
			),
			'MT' => array(
				'name'      => 'Malta',
				'dial_code' => '+356',
			),
			'MH' => array(
				'name'      => 'Marshall Islands',
				'dial_code' => '+692',
			),
			'MQ' => array(
				'name'      => 'Martinique',
				'dial_code' => '+596',
			),
			'MR' => array(
				'name'      => 'Mauritania',
				'dial_code' => '+222',
			),
			'MU' => array(
				'name'      => 'Mauritius',
				'dial_code' => '+230',
			),
			'YT' => array(
				'name'      => 'Mayotte',
				'dial_code' => '+262',
			),
			'MX' => array(
				'name'      => 'Mexico',
				'dial_code' => '+52',
			),
			'MC' => array(
				'name'      => 'Monaco',
				'dial_code' => '+377',
			),
			'MN' => array(
				'name'      => 'Mongolia',
				'dial_code' => '+976',
			),
			'ME' => array(
				'name'      => 'Montenegro',
				'dial_code' => '+382',
			),
			'MS' => array(
				'name'      => 'Montserrat',
				'dial_code' => '+1664',
			),
			'MA' => array(
				'name'      => 'Morocco',
				'dial_code' => '+212',
			),
			'MM' => array(
				'name'      => 'Myanmar',
				'dial_code' => '+95',
			),
			'NA' => array(
				'name'      => 'Namibia',
				'dial_code' => '+264',
			),
			'NR' => array(
				'name'      => 'Nauru',
				'dial_code' => '+674',
			),
			'NP' => array(
				'name'      => 'Nepal',
				'dial_code' => '+977',
			),
			'NL' => array(
				'name'      => 'Netherlands',
				'dial_code' => '+31',
			),
			'AN' => array(
				'name'      => 'Netherlands Antilles',
				'dial_code' => '+599',
			),
			'NC' => array(
				'name'      => 'New Caledonia',
				'dial_code' => '+687',
			),
			'NZ' => array(
				'name'      => 'New Zealand',
				'dial_code' => '+64',
			),
			'NI' => array(
				'name'      => 'Nicaragua',
				'dial_code' => '+505',
			),
			'NE' => array(
				'name'      => 'Niger',
				'dial_code' => '+227',
			),
			'NG' => array(
				'name'      => 'Nigeria',
				'dial_code' => '+234',
			),
			'NU' => array(
				'name'      => 'Niue',
				'dial_code' => '+683',
			),
			'NF' => array(
				'name'      => 'Norfolk Island',
				'dial_code' => '+672',
			),
			'MP' => array(
				'name'      => 'Northern Mariana Islands',
				'dial_code' => '+1670',
			),
			'NO' => array(
				'name'      => 'Norway',
				'dial_code' => '+47',
			),
			'OM' => array(
				'name'      => 'Oman',
				'dial_code' => '+968',
			),
			'PK' => array(
				'name'      => 'Pakistan',
				'dial_code' => '+92',
			),
			'PW' => array(
				'name'      => 'Palau',
				'dial_code' => '+680',
			),
			'PA' => array(
				'name'      => 'Panama',
				'dial_code' => '+507',
			),
			'PG' => array(
				'name'      => 'Papua New Guinea',
				'dial_code' => '+675',
			),
			'PY' => array(
				'name'      => 'Paraguay',
				'dial_code' => '+595',
			),
			'PE' => array(
				'name'      => 'Peru',
				'dial_code' => '+51',
			),
			'PH' => array(
				'name'      => 'Philippines',
				'dial_code' => '+63',
			),
			'PL' => array(
				'name'      => 'Poland',
				'dial_code' => '+48',
			),
			'PT' => array(
				'name'      => 'Portugal',
				'dial_code' => '+351',
			),
			'PR' => array(
				'name'      => 'Puerto Rico',
				'dial_code' => '+1939',
			),
			'QA' => array(
				'name'      => 'Qatar',
				'dial_code' => '+974',
			),
			'RO' => array(
				'name'      => 'Romania',
				'dial_code' => '+40',
			),
			'RW' => array(
				'name'      => 'Rwanda',
				'dial_code' => '+250',
			),
			'WS' => array(
				'name'      => 'Samoa',
				'dial_code' => '+685',
			),
			'SM' => array(
				'name'      => 'San Marino',
				'dial_code' => '+378',
			),
			'SA' => array(
				'name'      => 'Saudi Arabia',
				'dial_code' => '+966',
			),
			'SN' => array(
				'name'      => 'Senegal',
				'dial_code' => '+221',
			),
			'RS' => array(
				'name'      => 'Serbia',
				'dial_code' => '+381',
			),
			'SC' => array(
				'name'      => 'Seychelles',
				'dial_code' => '+248',
			),
			'SL' => array(
				'name'      => 'Sierra Leone',
				'dial_code' => '+232',
			),
			'SG' => array(
				'name'      => 'Singapore',
				'dial_code' => '+65',
			),
			'SK' => array(
				'name'      => 'Slovakia',
				'dial_code' => '+421',
			),
			'SI' => array(
				'name'      => 'Slovenia',
				'dial_code' => '+386',
			),
			'SB' => array(
				'name'      => 'Solomon Islands',
				'dial_code' => '+677',
			),
			'ZA' => array(
				'name'      => 'South Africa',
				'dial_code' => '+27',
			),
			'GS' => array(
				'name'      => 'South Georgia and the South Sandwich Islands',
				'dial_code' => '+500',
			),
			'ES' => array(
				'name'      => 'Spain',
				'dial_code' => '+34',
			),
			'LK' => array(
				'name'      => 'Sri Lanka',
				'dial_code' => '+94',
			),
			'SD' => array(
				'name'      => 'Sudan',
				'dial_code' => '+249',
			),
			'SR' => array(
				'name'      => 'Suriname',
				'dial_code' => '+597',
			),
			'SZ' => array(
				'name'      => 'Swaziland',
				'dial_code' => '+268',
			),
			'SE' => array(
				'name'      => 'Sweden',
				'dial_code' => '+46',
			),
			'CH' => array(
				'name'      => 'Switzerland',
				'dial_code' => '+41',
			),
			'TJ' => array(
				'name'      => 'Tajikistan',
				'dial_code' => '+992',
			),
			'TH' => array(
				'name'      => 'Thailand',
				'dial_code' => '+66',
			),
			'TG' => array(
				'name'      => 'Togo',
				'dial_code' => '+228',
			),
			'TK' => array(
				'name'      => 'Tokelau',
				'dial_code' => '+690',
			),
			'TO' => array(
				'name'      => 'Tonga',
				'dial_code' => '+676',
			),
			'TT' => array(
				'name'      => 'Trinidad and Tobago',
				'dial_code' => '+1868',
			),
			'TN' => array(
				'name'      => 'Tunisia',
				'dial_code' => '+216',
			),
			'TR' => array(
				'name'      => 'Turkey',
				'dial_code' => '+90',
			),
			'TM' => array(
				'name'      => 'Turkmenistan',
				'dial_code' => '+993',
			),
			'TC' => array(
				'name'      => 'Turks and Caicos Islands',
				'dial_code' => '+1649',
			),
			'TV' => array(
				'name'      => 'Tuvalu',
				'dial_code' => '+688',
			),
			'UG' => array(
				'name'      => 'Uganda',
				'dial_code' => '+256',
			),
			'UA' => array(
				'name'      => 'Ukraine',
				'dial_code' => '+380',
			),
			'AE' => array(
				'name'      => 'United Arab Emirates',
				'dial_code' => '+971',
			),
			'GB' => array(
				'name'      => 'United Kingdom',
				'dial_code' => '+44',
			),
			'US' => array(
				'name'      => 'United States',
				'dial_code' => '+1',
			),
			'UY' => array(
				'name'      => 'Uruguay',
				'dial_code' => '+598',
			),
			'UZ' => array(
				'name'      => 'Uzbekistan',
				'dial_code' => '+998',
			),
			'VU' => array(
				'name'      => 'Vanuatu',
				'dial_code' => '+678',
			),
			'WF' => array(
				'name'      => 'Wallis and Futuna',
				'dial_code' => '+681',
			),
			'YE' => array(
				'name'      => 'Yemen',
				'dial_code' => '+967',
			),
			'ZM' => array(
				'name'      => 'Zambia',
				'dial_code' => '+260',
			),
			'ZW' => array(
				'name'      => 'Zimbabwe',
				'dial_code' => '+263',
			),
			'BO' => array(
				'name'      => 'Bolivia, Plurinational State of',
				'dial_code' => '+591',
			),
			'BN' => array(
				'name'      => 'Brunei Darussalam',
				'dial_code' => '+673',
			),
			'CC' => array(
				'name'      => 'Cocos (Keeling) Islands',
				'dial_code' => '+61',
			),
			'CD' => array(
				'name'      => 'Congo, The Democratic Republic of the',
				'dial_code' => '+243',
			),
			'CI' => array(
				'name'      => 'Cote dIvoire',
				'dial_code' => '+225',
			),
			'FK' => array(
				'name'      => 'Falkland Islands (Malvinas)',
				'dial_code' => '+500',
			),
			'GG' => array(
				'name'      => 'Guernsey',
				'dial_code' => '+44',
			),
			'VA' => array(
				'name'      => 'Holy See (Vatican City State)',
				'dial_code' => '+379',
			),
			'HK' => array(
				'name'      => 'Hong Kong',
				'dial_code' => '+852',
			),
			'IR' => array(
				'name'      => 'Iran, Islamic Republic of',
				'dial_code' => '+98',
			),
			'IM' => array(
				'name'      => 'Isle of Man',
				'dial_code' => '+44',
			),
			'JE' => array(
				'name'      => 'Jersey',
				'dial_code' => '+44',
			),
			'KP' => array(
				'name'      => 'Korea, Democratic Peoples Republic of',
				'dial_code' => '+850',
			),
			'KR' => array(
				'name'      => 'Korea, Republic of',
				'dial_code' => '+82',
			),
			'LA' => array(
				'name'      => 'Lao Peoples Democratic Republic',
				'dial_code' => '+856',
			),
			'LY' => array(
				'name'      => 'Libyan Arab Jamahiriya',
				'dial_code' => '+218',
			),
			'MO' => array(
				'name'      => 'Macao',
				'dial_code' => '+853',
			),
			'MK' => array(
				'name'      => 'Macedonia, The Former Yugoslav Republic of',
				'dial_code' => '+389',
			),
			'FM' => array(
				'name'      => 'Micronesia, Federated States of',
				'dial_code' => '+691',
			),
			'MD' => array(
				'name'      => 'Moldova, Republic of',
				'dial_code' => '+373',
			),
			'MZ' => array(
				'name'      => 'Mozambique',
				'dial_code' => '+258',
			),
			'PS' => array(
				'name'      => 'Palestinian Territory, Occupied',
				'dial_code' => '+970',
			),
			'PN' => array(
				'name'      => 'Pitcairn',
				'dial_code' => '+872',
			),
			'RE' => array(
				'name'      => 'R�union',
				'dial_code' => '+262',
			),
			'RU' => array(
				'name'      => 'Russia',
				'dial_code' => '+7',
			),
			'BL' => array(
				'name'      => 'Saint Barth�lemy',
				'dial_code' => '+590',
			),
			'SH' => array(
				'name'      => 'Saint Helena, Ascension and Tristan Da Cunha',
				'dial_code' => '+290',
			),
			'KN' => array(
				'name'      => 'Saint Kitts and Nevis',
				'dial_code' => '+1 869',
			),
			'LC' => array(
				'name'      => 'Saint Lucia',
				'dial_code' => '+1758',
			),
			'MF' => array(
				'name'      => 'Saint Martin',
				'dial_code' => '+590',
			),
			'PM' => array(
				'name'      => 'Saint Pierre and Miquelon',
				'dial_code' => '+508',
			),
			'VC' => array(
				'name'      => 'Saint Vincent and the Grenadines',
				'dial_code' => '+1784',
			),
			'ST' => array(
				'name'      => 'Sao Tome and Principe',
				'dial_code' => '+239',
			),
			'SO' => array(
				'name'      => 'Somalia',
				'dial_code' => '+252',
			),
			'SJ' => array(
				'name'      => 'Svalbard and Jan Mayen',
				'dial_code' => '+47',
			),
			'SY' => array(
				'name'      => 'Syrian Arab Republic',
				'dial_code' => '+963',
			),
			'TW' => array(
				'name'      => 'Taiwan, Province of China',
				'dial_code' => '+886',
			),
			'TZ' => array(
				'name'      => 'Tanzania, United Republic of',
				'dial_code' => '+255',
			),
			'TL' => array(
				'name'      => 'Timor-Leste',
				'dial_code' => '+670',
			),
			'VE' => array(
				'name'      => 'Venezuela, Bolivarian Republic of',
				'dial_code' => '+58',
			),
			'VN' => array(
				'name'      => 'Viet Nam',
				'dial_code' => '+84',
			),
			'VG' => array(
				'name'      => 'Virgin Islands, British',
				'dial_code' => '+1284',
			),
			'VI' => array(
				'name'      => 'Virgin Islands, U.S.',
				'dial_code' => '+1340',
			),
		);
	}
	
}