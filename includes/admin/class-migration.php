<?php
/**
 * Class Migration
 *
 * Migrates all settings from the old WooCommerce Settings API format
 * (individual wp_options rows) into the new unified JSON structure
 * stored under the single option key `cos_pro_settings`.
 *
 * OLD FORMAT (one option per setting):
 *   get_option( 'alg_orders_custom_statuses_add_to_bulk_actions' ) → "yes" / "no"
 *   get_option( 'alg_orders_custom_statuses_emails_enabled' )      → "yes" / "no"
 *   get_option( 'alg_orders_custom_statuses_rules_options' )       → array of rules
 *   ... (30+ individual option rows)
 *
 * NEW FORMAT (single option key):
 *   get_option( 'cos_pro_settings' ) → [
 *       'general'    => [ 'add_to_bulk_actions' => true, 'filters_priority' => 0, ... ],
 *       'emails'     => [ 'enabled' => false, 'subject' => '...', ... ],
 *       'admin_email'=> [ 'enabled' => false, ... ],
 *       'sms'        => [ 'enabled' => false, ... ],
 *       'gateways'   => [ 'cod' => 'wc-processing', ... ],
 *       'labels'     => [ 'wc-pending' => 'Pending Payment', ... ],
 *       'license'    => [ 'key' => '...', 'status' => 'active' ],
 *   ]
 *
 * Rules are stored separately under `cos_pro_rules` (unchanged key,
 * but field names are normalised — see migrate_rules()).
 *
 * @package Custom_Order_Status
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

class Migration {

	/** New unified settings option key */
	const NEW_OPTION_KEY = 'cos_pro_settings';

	/** Rules option key (separate from main settings) */
	const RULES_OPTION_KEY = 'cos_pro_rules';

	/** Old rules option key */
	const OLD_RULES_KEY = 'alg_orders_custom_statuses_rules_options';

	/** Migration version stored to prevent re-running */
	const MIGRATION_FLAG = 'cos_pro_migration_version';

	/** Current migration version */
	const MIGRATION_VERSION = '2.0.0';

	/**
	 * Run migration. Called from the plugin's activation hook and on
	 * admin_init (for existing installs that update without re-activating).
	 * Safe to call multiple times — exits immediately if already run.
	 */
	public static function run(): void {
		// Already migrated — do nothing.
		if ( get_option( self::MIGRATION_FLAG ) === self::MIGRATION_VERSION ) {
			return;
		}

		// If new settings already exist (manually set) — do not overwrite,
		// just stamp the migration flag.
		if ( false !== get_option( self::NEW_OPTION_KEY ) ) {
			update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
			return;
		}

		// Check if there is any old data to migrate.
		// If no old option exists either, this is a fresh install — seed defaults.
		$has_old_data = false !== get_option( 'alg_orders_custom_statuses_add_to_bulk_actions' );

		if ( ! $has_old_data ) {
			// Fresh install: seed defaults via the settings controller.
			self::seed_defaults();
		} else {
			// Existing install: migrate all old options.
			self::migrate();
		}

		update_option( self::MIGRATION_FLAG, self::MIGRATION_VERSION );
	}

	/**
	 * Read all old individual options and write them into the new
	 * unified `cos_pro_settings` structure.
	 */
	private static function migrate(): void {
		$new_settings = [
			'general'    => self::migrate_general(),
			'emails'     => self::migrate_emails(),
			'admin_email'=> self::migrate_admin_email(),
			'sms'        => self::migrate_sms(),
			'gateways'   => self::migrate_gateways(),
			'labels'     => self::migrate_labels(),
			'advanced'   => self::migrate_advanced(),
			'license'    => self::migrate_license(),
		];

		update_option( self::NEW_OPTION_KEY, $new_settings, false );

		// Migrate rules to the new option key with normalised field names.
		self::migrate_rules();

		/**
		 * Action fired after migration completes.
		 * Use this to run any additional data transforms needed by other
		 * parts of the plugin.
		 *
		 * @param array $new_settings The migrated settings array.
		 */
		do_action( 'cos_pro_migration_complete', $new_settings );
	}

	/**
	 * Migrate General settings.
	 *
	 * Type changes:
	 *   "yes" / "no"  → true / false   (all checkbox fields)
	 *   "alg_disabled" → ""             (no-change sentinel value)
	 *
	 * @return array
	 */
	private static function migrate_general(): array {
		// Helper: convert "yes"/"no" string to boolean.
		$yes = static fn( string $key, bool $default = false ): bool =>
			get_option( $key, $default ? 'yes' : 'no' ) === 'yes';

		// Helper: resolve status slug — old code uses "alg_disabled" sentinel.
		$status = static fn( string $key, string $default = '' ): string => (function() use ( $key, $default ) {
			$val = get_option( $key, $default );
			return ( 'alg_disabled' === $val || 'alg_none' === $val ) ? $default : (string) $val;
		})();

		return array(
			'add_to_bulk_actions'         => $yes( 'alg_orders_custom_statuses_add_to_bulk_actions', true ), // Old: alg_orders_custom_statuses_add_to_bulk_actions  default: 'yes'
			'add_to_reports'              => $yes( 'alg_orders_custom_statuses_add_to_reports', true ), // Old: alg_orders_custom_statuses_add_to_reports  default: 'yes'
			'default_status'              => $status( 'alg_orders_custom_statuses_default_status', '' ), // Old: alg_orders_custom_statuses_default_status  default: 'alg_disabled'
			'fallback_delete_status'      => $status( 'alg_orders_custom_statuses_fallback_delete_status', 'on-hold' ), // Old: alg_orders_custom_statuses_fallback_delete_status  default: 'on-hold'
			'add_to_order_list_actions'   => $yes( 'alg_orders_custom_statuses_add_to_order_list_actions' ), // Old: alg_orders_custom_statuses_add_to_order_list_actions  default: 'no'
			'list_actions_colored'        => $yes( 'alg_orders_custom_statuses_add_to_order_list_actions_colored' ), // Old: alg_orders_custom_statuses_add_to_order_list_actions_colored  default: 'no'
			'enable_column_colored'       => $yes( 'alg_orders_custom_statuses_enable_column_colored' ), // Old: alg_orders_custom_statuses_enable_column_colored  default: 'no'
			'enable_column_icons'         => $yes( 'alg_orders_custom_statuses_enable_column_icons', true ), // Old: alg_orders_custom_statuses_enable_column_icons  default: 'yes'
			'add_to_order_preview_actions'=> $yes( 'alg_orders_custom_statuses_add_to_order_preview_actions' ), // Old: alg_orders_custom_statuses_add_to_order_preview_actions  default: 'no'
			'enable_editable'             => $yes( 'alg_orders_custom_statuses_enable_editable' ), // Old: alg_orders_custom_statuses_enable_editable  default: 'no'
			'enable_paid'                 => $yes( 'alg_orders_custom_statuses_enable_paid' ), // Old: alg_orders_custom_statuses_enable_paid  default: 'no'
			'enable_fallback'             => $yes( 'alg_orders_custom_statuses_enable_fallback' ), // Old: alg_orders_custom_statuses_enable_fallback  default: 'no'
			'filters_priority'            => (int) get_option( 'alg_orders_custom_statuses_filters_priority', 0 ), // Old: alg_orders_custom_statuses_filters_priority  default: 0. NOTE: moved from 'advanced' section to 'general' in v2.0
		);
	}

	/**
	 * Migrate Order Status Emails settings.
	 *
	 * @return array
	 */
	private static function migrate_emails(): array {
		$yes = static fn( string $key ): bool => get_option( $key, 'no' ) === 'yes';

		return [
			// Old: alg_orders_custom_statuses_emails_enabled  default: 'no'
			'enabled'  => $yes( 'alg_orders_custom_statuses_emails_enabled' ),

			// Old: alg_orders_custom_statuses_emails_statuses  default: array()
			// Type: array of status slugs (unchanged)
			'statuses' => (array) get_option( 'alg_orders_custom_statuses_emails_statuses', [] ),

			// Old: alg_orders_custom_statuses_emails_address  default: ''
			'address'  => (string) get_option( 'alg_orders_custom_statuses_emails_address', '' ),

			// Old: alg_orders_custom_statuses_bcc_emails_address  default: ''
			// NOTE: old key name uses 'bcc_emails_address', new key is just 'bcc'
			'bcc'      => (string) get_option( 'alg_orders_custom_statuses_bcc_emails_address', '' ),

			// Old: alg_orders_custom_statuses_emails_subject
			'subject'  => (string) get_option(
				'alg_orders_custom_statuses_emails_subject',
				"[{site_title}] Order #{order_number} status changed to {status_to} - {order_date}"
			),

			// Old: alg_orders_custom_statuses_emails_heading
			'heading'  => (string) get_option(
				'alg_orders_custom_statuses_emails_heading',
				'Order status changed to {status_to}'
			),

			// Old: alg_orders_custom_statuses_emails_content
			'content'  => (string) get_option(
				'alg_orders_custom_statuses_emails_content',
				'Order #{order_number} status changed from {status_from} to {status_to}'
			),
		];
	}

	/**
	 * Migrate Admin (Pending Order) Email settings.
	 * Old section id: cos_po_emails  Old prefix: alg_cos_po_notify_emails_
	 *
	 * @return array
	 */
	private static function migrate_admin_email(): array {
		$yes = static fn( string $key ): bool => get_option( $key, 'no' ) === 'yes';

		return [
			// Old: alg_cos_po_notify_emails_enabled  default: 'no'
			'enabled'       => $yes( 'alg_cos_po_notify_emails_enabled' ),

			// Old: alg_cos_po_notify_emails_statuses  default: array()
			'statuses'      => (array) get_option( 'alg_cos_po_notify_emails_statuses', [] ),

			// Old: alg_cos_po_notify_emails_interval_time  default: 1
			'interval_time' => (int) get_option( 'alg_cos_po_notify_emails_interval_time', 1 ),

			// Old: alg_cos_po_notify_emails_interval  default: 'days'
			'interval'      => (string) get_option( 'alg_cos_po_notify_emails_interval', 'days' ),

			// Old: alg_cos_po_notify_emails_address  default: ''
			'address'       => (string) get_option( 'alg_cos_po_notify_emails_address', '' ),

			// Old: alg_cos_po_notify_emails_subject
			'subject'       => (string) get_option(
				'alg_cos_po_notify_emails_subject',
				"[{site_title}] Order #{order_number} action required {order_status} - {order_date}"
			),

			// Old: alg_cos_po_notify_emails_heading
			'heading'       => (string) get_option(
				'alg_cos_po_notify_emails_heading',
				'Order action required to {order_status} order'
			),

			// Old: alg_cos_po_notify_emails_content
			'content'       => (string) get_option(
				'alg_cos_po_notify_emails_content',
				'Order #{order_number} action required to {order_status} status'
			),
		];
	}

	/**
	 * Migrate SMS settings.
	 *
	 * @return array
	 */
	private static function migrate_sms(): array {
		$yes = static fn( string $key ): bool => get_option( $key, 'no' ) === 'yes';

		return [
			// Old: alg_orders_custom_statuses_enable_sms  default: 'no'
			'enabled'     => $yes( 'alg_orders_custom_statuses_enable_sms' ),

			// Old: alg_orders_custom_statuses_enable_from_num  default: ''
			// NOTE: old key prefix is 'enable_' which was misleading — new key is 'from_num'
			'from_num'    => (string) get_option( 'alg_orders_custom_statuses_enable_from_num', '' ),

			// Old: alg_orders_custom_statuses_enable_acc_sid  default: ''
			'account_sid' => (string) get_option( 'alg_orders_custom_statuses_enable_acc_sid', '' ),

			// Old: alg_orders_custom_statuses_enable_acc_token  default: ''
			'auth_token'  => (string) get_option( 'alg_orders_custom_statuses_enable_acc_token', '' ),

			// Old: alg_orders_custom_statuses_sms_statuses  default: array()
			'statuses'    => (array) get_option( 'alg_orders_custom_statuses_sms_statuses', [] ),

			// Old: alg_orders_custom_statuses_sms_content
			'content'     => (string) get_option(
				'alg_orders_custom_statuses_sms_content',
				'Order #{order_number} status changed from {status_from} to {status_to}'
			),
		];
	}

	/**
	 * Migrate Status by Payment Gateways settings.
	 *
	 * Old format: one option per gateway — alg_orders_custom_statuses_default_status_{gateway_id}
	 * New format: single 'gateways' array keyed by gateway_id → status slug
	 *
	 * @return array
	 */
	private static function migrate_gateways(): array {
		$gateways = [];

		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			foreach ( WC()->payment_gateways->payment_gateways() as $gateway_id => $gateway ) {
				$old_key = 'alg_orders_custom_statuses_default_status_' . $gateway_id;
				$val     = get_option( $old_key, 'alg_disabled' );

				// Only store if the user had explicitly set a value (not the default sentinel).
				if ( 'alg_disabled' !== $val && '' !== $val ) {
					$gateways[ sanitize_key( $gateway_id ) ] = sanitize_key( $val );
				}
			}
		}

		return $gateways;
	}

	/**
	 * Migrate Order Status Labels settings.
	 *
	 * Old format: alg_wc_cos_{slug_with_underscores}  e.g. alg_wc_cos_wc_pending
	 * New format: 'labels' array keyed by original slug  e.g. wc-pending → "Custom Label"
	 *
	 * @return array
	 */
	private static function migrate_labels(): array {
		$labels = array();

		// The old settings file iterates wc-pending, wc-processing, etc.
		// and converts dashes to underscores to form the option key.
		$wc_statuses = array(
			'pending'    => _x( 'Pending Payment', 'Order status', 'woocommerce' ),
			'processing' => _x( 'Processing',      'Order status', 'woocommerce' ),
			'on-hold'    => _x( 'On Hold',          'Order status', 'woocommerce' ),
			'completed'  => _x( 'Completed',        'Order status', 'woocommerce' ),
			'cancelled'  => _x( 'Cancelled',        'Order status', 'woocommerce' ),
			'refunded'   => _x( 'Refunded',         'Order status', 'woocommerce' ),
			'failed'     => _x( 'Failed',           'Order status', 'woocommerce' ),
			'draft'      => _x( 'Draft',           'Order status', 'woocommerce' ),
		);

		foreach ( $wc_statuses as $slug => $default_label ) {
			// Old key: replace '-' with '_', prefix with 'alg_wc_cos_'
			// e.g. wc-pending → alg_wc_cos_wc_pending
			$old_key       = 'alg_wc_cos_wc_' . str_replace( '-', '_', $slug );
			$custom_label  = get_option( $old_key, $default_label );

			// Only store if the user actually changed the label.
			if ( (string) $custom_label !== (string) $default_label ) {
				$labels[ $slug ] = sanitize_text_field( $custom_label );
			}
		}

		return $labels;
	}

	/**
	 * Migrate Advanced settings.
	 *
	 * @return array
	 */
	private static function migrate_advanced(): array {
		return [];
	}

	/**
	 * Migrate License settings.
	 *
	 * Old storage: EDD Software Licensing used these option keys:
	 *   edd_license_key_cos        → the license key string
	 *   edd_license_key_cos_status → 'valid' | 'invalid' | 'expired' | ''
	 *
	 * New storage: cos_pro_settings['license']
	 *   key    → the key string (unchanged)
	 *   status → 'active' | 'inactive'  ('valid' maps to 'active', anything else to 'inactive')
	 *
	 * @return array
	 */
	private static function migrate_license(): array {
		$old_key    = (string) get_option( 'edd_license_key_cos',        '' );
		$old_status = (string) get_option( 'edd_license_key_cos_status', '' );

		// No old key — nothing to migrate.
		if ( empty( $old_key ) ) {
			return [
				'key'              => '',
				'status'           => 'inactive',
				'expires'          => '',
				'activations_left' => '',
			];
		}

		if ( 'valid' === $old_status ) {
			$base_file       = COS_PLUGIN_PATH . 'includes/api/class-api-base.php';
			$controller_file = COS_PLUGIN_PATH . 'includes/api/class-api-license.php';

			if ( file_exists( $base_file ) && ! class_exists( __NAMESPACE__ . '\\Api_Base' ) ) {
				require_once $base_file;
			}
			if ( file_exists( $controller_file ) && ! class_exists( __NAMESPACE__ . '\\Api_License' ) ) {
				require_once $controller_file;
			}

			if ( class_exists( __NAMESPACE__ . '\\Api_License' ) ) {
				$request = new WP_REST_Request( 'POST', '/cos-pro/v1/license/activate' );
				$request->set_param( 'key', $old_key );

				$controller = new Api_License();
				$response   = $controller->activate( $request );

				$data = $response->get_data();

				if ( ! empty( $data['data']['key'] ) ) {
					return array(
						'key'              => $data['data']['key']              ?? $old_key,
						'status'           => $data['data']['status']           ?? 'inactive',
						'expires'          => $data['data']['expires']          ?? '',
						'activations_left' => $data['data']['activations_left'] ?? '',
					);
				}
			}

			return array(
				'key'              => $old_key,
				'status'           => 'active',
				'expires'          => '',
				'activations_left' => '',
			);
		}

		// Key exists but was not active — store it so the user can re-activate
		// from the License tab without re-entering the key.
		return array(
			'key'              => $old_key,
			'status'           => 'inactive',
			'expires'          => '',
			'activations_left' => '',
		);
	}

	/**
	 * Migrate Rules.
	 *
	 * Old storage: alg_orders_custom_statuses_rules_options
	 * Old field names inside each rule:
	 *   rule_name, status_from, status_to,
	 *   time_trigger_value, time_trigger_unit,
	 *   skip_days, skip_dates, payment_gateways, shipping_methods,
	 *   products, categories, user_roles, countries,
	 *   min_order_amount, min_order_quantity, status
	 *
	 * New storage: cos_pro_rules
	 * New field names (normalised for REST API consistency):
	 *   name, status_from, status_to,
	 *   time_trigger, time_unit,
	 *   skip_days, skip_dates, payment_methods, shipping_methods,
	 *   products, categories, user_roles, countries,
	 *   min_amount, min_qty, enabled
	 *
	 * Key changes:
	 *   rule_name          → name
	 *   time_trigger_value → time_trigger
	 *   time_trigger_unit  → time_unit
	 *   payment_gateways   → payment_methods   (more consistent with WC naming)
	 *   min_order_amount   → min_amount
	 *   min_order_quantity → min_qty
	 *   status (0/1 int)   → enabled (bool)
	 */
	private static function migrate_rules(): void {
		$old_rules = get_option( self::OLD_RULES_KEY, [] );

		if ( empty( $old_rules ) ) {
			return;
		}

		$new_rules = [];

		foreach ( $old_rules as $old_id => $old_rule ) {
			$new_id = (string) $old_id;

			$new_rules[ $new_id ] = [
				'id'              => $new_id,
				'name'            => sanitize_text_field( $old_rule['rule_name']    ?? '' ),
				'status_from'     => sanitize_key( $old_rule['status_from']         ?? '' ),
				'status_to'       => sanitize_key( $old_rule['status_to']           ?? '' ),
				'time_trigger'    => absint( $old_rule['time_trigger_value']         ?? 0 ), // time_trigger: was time_trigger_value
				'time_unit'       => sanitize_key( $old_rule['time_trigger_unit']   ?? 'minutes' ), // time_unit: was time_trigger_unit
				'skip_days'       => array_map( 'intval',              (array) ( $old_rule['skip_days']       ?? [] ) ),
				// skip_dates: old format was an array of date strings, new format is
				// a single comma-separated string e.g. "03-24-2026,03-27-2026"
				'skip_dates'      => implode( ',', array_filter( array_map(
					'sanitize_text_field',
					(array) ( $old_rule['skip_dates'] ?? [] )
				) ) ),

				'payment_methods' => array_map( 'sanitize_key',        (array) ( $old_rule['payment_gateways']  ?? [] ) ), // payment_methods: was payment_gateways
				'shipping_methods'=> array_map( 'sanitize_text_field', (array) ( $old_rule['shipping_methods'] ?? [] ) ),
				'products'        => array_map( 'absint',              (array) ( $old_rule['products']         ?? [] ) ),
				'categories'      => array_map( 'absint',              (array) ( $old_rule['categories']       ?? [] ) ),
				'user_roles'      => array_map( 'sanitize_key',        (array) ( $old_rule['user_roles']       ?? [] ) ),
				'countries'       => array_map( 'sanitize_text_field', (array) ( $old_rule['countries']        ?? [] ) ),
				'min_amount'      => wc_format_decimal( $old_rule['min_order_amount'] ?? 0 ), // min_amount: was min_order_amount
				'min_qty'         => absint( $old_rule['min_order_quantity'] ?? 0 ), // min_qty: was min_order_quantity
				'enabled'         => (bool) ( $old_rule['status'] ?? 0 ), // enabled: was status (stored as 0/1 int)
			];
		}

		update_option( self::RULES_OPTION_KEY, $new_rules, false );
	}

	/**
	 * Seed default settings for a fresh install.
	 * Reads defaults from the REST settings controller.
	 */
	private static function seed_defaults(): void {
		$controller_file = COS_PLUGIN_PATH . 'includes/api/class-api-settings.php';

		if ( ! file_exists( $controller_file ) ) {
			return;
		}

		// Load base class if not already loaded.
		$base_file = COS_PLUGIN_PATH . 'includes/api/class-api-base.php';
		if ( file_exists( $base_file ) && ! class_exists( __NAMESPACE__ . '\\Api_Base' ) ) {
			require_once $base_file;
		}

		if ( ! class_exists( __NAMESPACE__ . '\\Api_Settings' ) ) {
			require_once $controller_file;
		}

		$controller = new Api_Settings();
		add_option( self::NEW_OPTION_KEY, $controller->get_defaults(), '', false );
	}

	/**
	 * Helper for any plugin code that still reads individual old options.
	 * Returns the migrated value from the new structure, falling back to
	 * the old option if migration hasn't run yet.
	 *
	 * Usage (in other plugin files, during transition period):
	 *   Migration::get( 'general', 'add_to_bulk_actions', true );
	 *
	 * @param string $section  Top-level section key.
	 * @param string $key      Field key within the section.
	 * @param mixed  $default  Default value if not found.
	 * @return mixed
	 */
	public static function get( string $section, string $key, $default = null ) {
		$settings = get_option( self::NEW_OPTION_KEY, null );

		if ( $settings && isset( $settings[ $section ][ $key ] ) ) {
			return $settings[ $section ][ $key ];
		}

		return $default;
	}
}
