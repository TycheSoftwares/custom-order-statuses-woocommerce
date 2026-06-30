<?php
/**
 * Custom Order Status for WooCommerce.
 *
 * Main Class.
 *
 * @author      Tyche Softwares
 * @package     COF_Pro/Main
 * @category    Classes
 * @since       1.0
 */


namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Check if WooCommerce is active.
/* $plugin_name = 'woocommerce/woocommerce.php';
if (
	! in_array( $plugin_name, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) &&
	! ( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
) {
	return;
} */

// Deactivate the Lite plugin if active. This is needed to avoid conflicts.
/* if ( in_array( 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php', (array) get_option( 'active_plugins', array() ) ) ) { //phpcs:ignore
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	deactivate_plugins( 'custom-order-statuses-woocommerce/custom-order-statuses-for-woocommerce.php' );
} */

/* if ( 'custom-order-statuses-for-woocommerce.php' === basename( COS_PLUGIN_FILE ) ) {
	// Check if Pro is active, if so then return.
	$plugin_name = 'custom-order-statuses-for-woocommerce/custom-order-statuses-for-woocommerce.php';
	if (
		in_array( $plugin_name, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
		( is_multisite() && array_key_exists( $plugin_name, get_site_option( 'active_sitewide_plugins', array() ) ) )
	) {
		return;
	}
} */

if ( ! class_exists( __NAMESPACE__ . '\\Custom_Order_Status' ) ) :

	/**
	 * Main Custom_Order_Status Class
	 *
	 * @class   Custom_Order_Status
	 * @version 1.4.1
	 * @since   1.0.0
	 */
	final class Custom_Order_Status {

		/**
		 * Plugin version.
		 *
		 * @var   string
		 * @since 1.0.0
		 */
		public $plugin_version = '3.0.0';

        /**
         * Minimum version of WordPress required.
         *
         * @var string
         */
        private static $wordpress_version = '5.2';

        /**
         * Minimum version of PHP required.
         *
         * @var string
         */
        private static $php_version = '7.0';

        /**
         * Slug.
         *
         * @var string
         */
        protected static $slug = 'cos';

        /**
         * Plugin slug.
         *
         * @var string
         */
        protected static $plugin_slug = 'custom-order-status-for-woocommerce';

        /**
         * Plugin Name.
         *
         * @var string
         */
        protected static $plugin_name = 'Custom Order Status for WooCommerce';

        /**
         * Plugin URL.
         *
         * @var string
         */
        protected static $plugin_url = 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-for-woocommerce/';

		/**
		 * Plugin settings.
		 *
		 * @var   array
		 * @since 2.6.0
		 */
		public $settings = '';

        /**
         * The single instance of the class.
         *
         * @var Custom_Order_Status
         */
        protected static $instance = null;

		/**
		 * Main Custom_Order_Status Instance
		 *
		 * Ensures only one instance of Custom_Order_Status is loaded or can be loaded.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @static
		 * @return  Custom_Order_Status - Main instance
		 */
		public static function instance() {
			if ( is_null( self::$instance ) && ! ( self::$instance instanceof Custom_Order_Status ) ) {
                self::$instance = new Custom_Order_Status();
                self::$instance->setup();
            }
			return self::$instance;
		}

        /**
         * A dummy constructor to prevent FAW from being loaded more than once.
         *
         * @since 1.0.0
         */
        private function __construct() {}

        /**
         * A dummy magic method to prevent FAW from being cloned.
         *
         * @since 1.0.0
         */
        public function __clone() {
            _doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'product-input-fields-for-woocommerce' ), '1.0' );
        }

        /**
         * A dummy magic method to prevent FAW from being unserialized.
         *
         * @since 1.0.0
         */
        public function __wakeup() {
            _doing_it_wrong( __FUNCTION__, esc_html__( 'Not allowed.', 'product-input-fields-for-woocommerce' ), '1.0' );
        }

        /**
         * Default constructor
         *
         * @since 1.0
         */
        private function setup() {

            add_action( 'alg_cos_order_status_notify', array( __CLASS__, 'alg_cos_order_status_notify_event_func' ), PHP_INT_MAX );

            self::handle_localization();

            /**
             * Define Constants.
             */
            self::define_constants();

            if ( ! self::check_requirements() ) {
                return;
            }

            self::init();

            /**
             * Include Files.
             */
            self::maybe_include_files();

            /**
             * Hooks.
             */
            self::init_hooks();
        }

        /**
         * Action Hooks.
         *
         * @since 1.0
         */
        private static function init_hooks() {
            register_activation_hook( COS_PLUGIN_FILE, array( __CLASS__, 'cos_pro_activate' ) );
            register_deactivation_hook( COS_PLUGIN_FILE, array( __CLASS__, 'cos_pro_deactivate' ) );

            // COS Hooks.
            self::include_file( 'core/class-hooks.php' );
            new Hooks();
        }

        /**
         * Checks whether to inlcude the plugin files.
         *
         * @since 1.0
         */
        public static function maybe_include_files() {
            self::include_file( 'core/class-files.php' );
            new Files();
        }

        /**
         * Include File.
         *
         * @param string $file File to be included.
         * @param bool   $is_plugin_include_file If it's a plugin file, then we can add the path.
         * @since 1.0
         */
        public static function include_file( $file, $is_plugin_include_file = true ) {
            $file = $is_plugin_include_file ? COS_PLUGIN_PATH . '/includes/' . $file : $file;

            if ( file_exists( $file ) ) {
                include_once $file;
            }
        }

        /**
         * Initializes
         *
         * @version 1.1.4
         * @since   1.1.4
         * @access  public
         */
        public function init() {

            add_action( 'alg_get_plugins_list', array( $this, 'cos_remove_plugin_name' ), PHP_INT_MAX );

            if ( is_admin() ) {
                add_filter( 'plugin_action_links_' . plugin_basename( COS_PLUGIN_FILE ), array( $this, 'action_links' ) );
            }
        }

        /**
         * Localization
         *
         * @version 1.1.3
         * @since   1.1.3
         */
        private function handle_localization() {
            $domain = 'custom-order-status-for-woocommerce';
            $locale = apply_filters( 'plugin_locale', get_locale(), $domain );
            $loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . 'plugins/' . $domain . '-pro/custom-order-statuses-woocommerce-' . $locale . '.mo' );
            if ( $loaded ) {
                return $loaded;
            } else {
                load_plugin_textdomain( $domain, false, dirname( plugin_basename( COS_PLUGIN_FILE ) ) . '/languages/' );
            }
        }

        /**
         * Checks that all requirements are met.
         *
         * @return bool
         */
        public static function check_requirements() {

            $messages = array();

            // Check WordPress version.
            if ( version_compare( get_bloginfo( 'version' ), self::$wordpress_version, '<' ) ) {
                /* translators: 1. Plugin Name, 2. WordPress Version */
                $messages[] = sprintf( esc_html__( 'You are using an outdated version of WordPress. %1$s requires WP version %2$s or higher.', 'product-input-fields-for-woocommerce' ), self::$plugin_name, self::$wordpress_version );
            }

            // Check PHP version.
            if ( version_compare( phpversion(), self::$php_version, '<' ) ) {
                /* translators: 1. Plugin Name, 2. PHP Version */
                $messages[] = sprintf( esc_html__( '%1$s requires PHP version %2$s or above. Please update PHP to run this plugin.', 'product-input-fields-for-woocommerce' ), self::$plugin_name, self::$php_version );
            }

            // Check WooCommerce.
            if ( ! self::is_woocommerce_active() ) {
                /* translators: Plugin Name */
                $messages[] = sprintf( esc_html__( 'WooCommerce not found. %s requires a minimum of WooCommerce v3.3.0.', 'product-input-fields-for-woocommerce' ), self::$plugin_name );
            }

            if ( empty( $messages ) ) {
                return true;
            }

            add_action( 'admin_init', array( __CLASS__, 'deactivate' ) );

            return false;
        }

        /**
         * Checks if WooCommerce is installed and active.
         *
         * @since 1.0
         */
        public static function is_woocommerce_active() {

            // WooCommerce is required.
            $woocommerce_path = 'woocommerce/woocommerce.php';
            $active_plugins   = (array) get_option( 'active_plugins', array() );
            $active           = false;

            if ( is_multisite() ) {
                $plugins = get_site_option( 'active_sitewide_plugins' );
                $active  = isset( $plugins[ $woocommerce_path ] );
            }

            return in_array( $woocommerce_path, $active_plugins, true ) || array_key_exists( $woocommerce_path, $active_plugins ) || $active;
        }

		/**
		 * Plugin activation:
		 *   - Flush rewrite rules so REST routes are available immediately.
		 *   - Seed default settings if this is a fresh install.
		 */
		public static function cos_pro_activate(): void {
			// Seed defaults only on fresh install (option doesn't exist yet)
			if ( false === get_option( 'cos_pro_settings' ) ) {
				// Migration handles seeding defaults on fresh install.
				// Require migration class.
				require_once plugin_dir_path( COS_PLUGIN_FILE ) . 'includes/admin/class-migration.php';
				Migration::run();

				// Load the controller to access get_defaults()
				require_once plugin_dir_path( COS_PLUGIN_FILE ) . 'includes/api/class-api-base.php';
				require_once plugin_dir_path( COS_PLUGIN_FILE ) . 'includes/api/class-api-settings.php';
				$controller = new Api_Settings();
				add_option( 'cos_pro_settings', $controller->get_defaults(), '', false );
				flush_rewrite_rules();
			}	
		}

		/**
		 * Added plugin text domain.
		 */
		/* public function cos_load_text_domain() {
			load_plugin_textdomain( 'custom-order-statuses-woocommerce', false, dirname( plugin_basename( COS_PLUGIN_FILE ) ) . '/langs/' );
		} */

		

		


		

		/**
		 * Adding cron job action.
		 * Function alg_cos_order_status_notify.
		 *
		 * @param array $schedules - array of arguments.
		 * @version 2.3.0
		 * @since   2.3.0
		 */
		public function alg_cos_order_status_notify( $schedules ) {
			// Interval time period options.
			$int_period   = Hooks::cos_get_setting( 'admin_email', 'interval',      'days' );
			$int_time     = Hooks::cos_get_setting( 'admin_email', 'interval_time', 1 );
			$schedule_key = 'every_' . cos_lite_convert_number( $int_time ) . '_' . $int_period;
			$sche_display = 'Every ' . cos_lite_convert_number( $int_time ) . ' ' . $int_period;
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
					$interval = $int_time * 3600 * 24 * 7 * 30;
					break;
				default:
					$interval = 24 * 3600;
			}
			$schedules[ $schedule_key ] = array(
				'interval' => $interval,
				'display'  => $sche_display,
			);
			return $schedules;
		}

		/**
		 * Hook into that action that'll fire every two days.
		 * Function alg_cos_order_status_notify_event_func.
		 *
		 * @version 1.4.4
		 * @since   1.4.0
		 * @todo    [feature] add more replaced values
		 * @todo    [feature] optional `wrap_in_wc_email_template()`
		 * @todo    [feature] separate content, subject etc. for each custom status
		 */
		public static function alg_cos_order_status_notify_event_func() {

			$cos_settings             = get_option( 'cos_pro_settings', [] );
			$admin_email              = $cos_settings['admin_email'] ?? [];
			$is_global_emails_enabled = ! empty( $admin_email['enabled'] ) ? 'yes' : 'no';
			$emails_statuses          = $admin_email['statuses'] ?? [];
			$initial_date             = get_option( 'alg_cos_po_notify_emails_start_date', '1972-01-01' );
			$final_date               = apply_filters( 'alg_admin_cos_notify_event_final_date', gmdate( 'Y-m-d' ) );
			if ( empty( $emails_statuses ) ) {
				$custom_statuses = alg_get_custom_order_statuses_from_cpt();
				foreach ( $custom_statuses as $status_slug => $status_title ) {
					$emails_statuses[] = $status_slug;
				}
			}
			$args   = array(
				'limit'        => -1,
				'status'       => $emails_statuses,
				'date_created' => $initial_date . '...' . $final_date,
			);
			$orders = wc_get_orders( $args );

			if ( ! empty( $orders ) && 'yes' === $is_global_emails_enabled ) {
				foreach ( $orders as $order ) {
					$email_address                    = '';
					$email_subject                    = '';
					$email_heading                    = '';
					$email_content                    = '';
					$alg_send_emails                  = false;
					$alg_cos_po_notify_emails_enabled = '';
					$products_with_title_link         = array();

					// For the emails set at a global level.
					$alg_send_emails = true;
					// Options.
					$email_address = $admin_email['address'] ?? '';
					$email_subject = ! empty( $admin_email['subject'] )
						? $admin_email['subject']
						: sprintf( __( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' );
					$email_heading = ! empty( $admin_email['heading'] )
						? $admin_email['heading']
						: sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' );
					$_default_content = sprintf( __( 'Order #%1$s status changed from %2$s to %3$s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' );
					$email_content    = nl2br( ! empty( $admin_email['content'] ) ? $admin_email['content'] : $_default_content );

					$order_id = $order->get_id();
					if ( cos_wc_hpos_enabled() ) {
						$is_send_raw = ( $order->get_meta( '_alg_cos_po_email_send' ) ) ? $order->get_meta( '_alg_cos_po_email_send' ) : false;
					} else {
						$is_send_raw = ( get_post_meta( $order_id, '_alg_cos_po_email_send', true ) ) ? get_post_meta( $order_id, '_alg_cos_po_email_send', true ) : false;
					}
					$is_send = ! empty( $is_send_raw ) && ( true === $is_send_raw || '1' === $is_send_raw || 'true' === $is_send_raw );
					
					if ( true === $is_send ) {
						continue;
					}

					if ( 'yes' !== $is_global_emails_enabled && true === $is_send ) {
						return;
					}

					if ( 'yes' === $is_global_emails_enabled && true !== $is_send ) {
						if ( cos_wc_hpos_enabled() ) {
							$order->update_meta_data( '_alg_cos_po_email_send', true );
							$order->save();
						} else {
							update_post_meta( $order_id, '_alg_cos_po_email_send', true );
						}
					}

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
						'{order_status}'     => wc_get_order_status_name( $order->get_status() ),
						'{order_date}'       => gmdate( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ),
						'{order_details}'    => ( false !== strpos( $email_content, '{order_details}' ) ? Hooks::get_wc_order_details_template( $order ) : '' ),
						'{site_title}'       => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
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
					$cos_core      = new Hooks();
					$email_content = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $cos_core->wrap_in_wc_email_template( $email_content, $email_heading ) ) );
					// Send mail.
					if ( $alg_send_emails ) {
						wc_mail( $email_address, $email_subject, $email_content );
					}
				}
			}
		}

		/**
		 * Show action links on the plugin screen.
		 *
		 * @version 1.3.5
		 * @since   1.0.0
		 * @param   mixed $links - plugin settings page link.
		 * @return  array
		 */
		public function action_links( $links ) {
			$custom_links   = array();
			$custom_links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=custom-order-statuses-for-woocommerce' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>';
			$custom_links[] = '<a href="https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=unlockall&utm_campaign=CustomOrderStatusLite">' . __( 'Unlock All', 'custom-order-statuses-woocommerce' ) . '</a>';

			return array_merge( $custom_links, $links );
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		/* public function includes() {
			// Functions.
			require_once COS_PLUGIN_PATH . 'includes/alg-wc-custom-order-statuses-functions.php';
			// Core.
			require_once COS_PLUGIN_PATH . 'includes/class-alg-wc-custom-order-statuses-core.php';
			
		} */

		/**
		 * Get the plugin url.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_url() {
			return untrailingslashit( plugin_dir_url( COS_PLUGIN_FILE ) );
		}

		/**
		 * Get the plugin path.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 * @return  string
		 */
		public function plugin_path() {
			return untrailingslashit( plugin_dir_path( COS_PLUGIN_FILE ) );
		}

		/**
		 * Defines constants.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		private function define_constants() {
			define( 'COS_VERSION', $this->plugin_version );
			define( 'COS_PLUGIN_BASENAME', plugin_basename( COS_PLUGIN_FILE ) );
			define( 'COS_PLUGIN_PATH', plugin_dir_path( COS_PLUGIN_FILE ) );
			define( 'COS_PLUGIN_URL', plugin_dir_url( COS_PLUGIN_FILE ) );
			define( 'COS_STORE_URL', 'https://www.tychesoftwares.com/' );
			define( 'COS_ITEM_NAME', 'Custom Order Status for WooCommerce' );
		}

		/**
		 * Function cos_remove_plugin_name.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function cos_remove_plugin_name() {

			$plugin_list = get_option( 'alg_wpcodefactory_helper_plugins' );

			if ( '' !== $plugin_list ) {
				$plugin_list = array_diff( $plugin_list, array( 'custom-order-statuses-woocommerce-pro' ) );
				update_option( 'alg_wpcodefactory_helper_plugins', $plugin_list );
			}
		}

		/**
		 * Actions to be performed when the plugin is deactivate.
		 *
		 * @since 1.4.9
		 */
		public static function cos_pro_deactivate() {
			if ( false !== as_next_scheduled_action( 'ts_send_data_tracking_usage' ) ) {
				as_unschedule_action( 'ts_send_data_tracking_usage' ); // Remove the scheduled action.
			}
			do_action( 'cos_deactivate' );
			if ( Hooks::cos_get_setting( 'general', 'enable_fallback', false ) ) {
				$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
				$fallback_status                  = Hooks::cos_get_setting( 'general', 'fallback_delete_status', 'on-hold' );
				foreach ( $alg_orders_custom_statuses_array as $slug => $alg_orders_custom_status ) {
					$custom_statuses_slug[] = $slug;
				}
				$st          = $custom_statuses_slug;
				$args_orders = array(
					'post_type'   => 'shop_order',
					'post_status' => $st,
					'offset'      => 0,
					'fields'      => 'ids',
				);
				$loop_orders = new WP_Query( $args_orders );
				if ( ! $loop_orders->have_posts() ) {
					return;
				}
				foreach ( $loop_orders->posts as $order_id ) {
					$order = wc_get_order( $order_id );
					$order->update_status( $fallback_status );
				}
			}
		}
	}

endif;
