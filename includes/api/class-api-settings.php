<?php
/**
 * Class Api_Settings
 *
 * Handles reading and writing the plugin's main settings object.
 *
 * Routes registered:
 *   GET  /wp-json/cos-pro/v1/settings          → get_settings()
 *   POST /wp-json/cos-pro/v1/settings          → update_settings()
 *   POST /wp-json/cos-pro/v1/settings/reset    → reset_section()
 *
 * All settings are stored in a single WordPress option so a single
 * database row holds the full settings tree. Individual sections can
 * be reset without touching the others.
 *
 * @package Custom_Order_Status\API
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class Api_Settings extends Api_Base {

	/** @var string WordPress option key */
	const OPTION_KEY = 'cos_pro_settings';

	protected $rest_base = 'settings';

	// ─── Route registration ────────────────────────────────────────────────────

	/**
	 * Register routes with the WP REST API.
	 * Called from Api_Router::register_all_routes().
	 */
	public function register_routes(): void {

		// GET/POST /settings
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_update_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// POST /settings/reset
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reset',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reset_section' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'section' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'description'       => __( 'Settings section key to reset.', 'custom-order-statuses-woocommerce' ),
					],
				],
			]
		);

		register_rest_route(
            $this->namespace,
            '/tracking/reset',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'reset_tracking' ),
                'permission_callback' => array( $this, 'check_permission' ),
            )
        );
	}

	/**
     * POST /tracking/reset – returns true.
     *
     * @return WP_REST_Response
     */
    public function reset_tracking() {
        delete_option( 'cos_lite_allow_tracking' );
        delete_option( 'ts_tracker_last_send' );
        return $this->success( array( 'reset' => true ) );
    }

	// ─── Handlers ─────────────────────────────────────────────────────────────

	/**
	 * GET /settings
	 * Retrieve the full saved settings object, merged with plugin defaults.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$saved    = get_option( self::OPTION_KEY, [] );
		$defaults = $this->get_defaults();

		// Deep-merge: defaults provide structure, saved values override
		$merged = $this->deep_merge( $defaults, $saved );

		return $this->success( $merged );
	}

	/**
	 * POST /settings
	 * Persist the full settings object after sanitisation.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$body     = $request->get_json_params();
		$incoming = $this->get_param( $body, 'settings', [] );

		if ( ! is_array( $incoming ) ) {
			return $this->error(
				'cos_invalid_settings',
				__( 'Settings must be a valid object.', 'custom-order-statuses-woocommerce' ),
				400
			);
		}

		// Sanitise before storage
		$sanitised = $this->sanitize_settings( $incoming );

		// Merge with existing so we never lose unrelated sections
		$existing = get_option( self::OPTION_KEY, [] );
		$merged   = $this->smart_merge( $existing, $sanitised );

		update_option( self::OPTION_KEY, $merged, false );

		/**
		 * Action: fired after settings are saved via REST.
		 *
		 * @param array $merged   The full merged settings array.
		 * @param array $incoming The raw incoming payload (unsanitised).
		 */
		do_action( 'cos_pro_settings_saved', $merged, $incoming );

		return $this->success( $merged );
	}

	/**
	 * POST /settings/reset
	 * Reset a single section to plugin defaults.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function reset_section( WP_REST_Request $request ): WP_REST_Response {
		$section  = $request->get_param( 'section' );
		$defaults = $this->get_defaults();

		if ( ! array_key_exists( $section, $defaults ) ) {
			return $this->error(
				'cos_unknown_section',
				/* translators: %s: section key */
				sprintf( __( 'Unknown settings section: %s', 'custom-order-statuses-woocommerce' ), $section ),
				404
			);
		}

		// Load current settings, replace only the requested section
		$settings             = get_option( self::OPTION_KEY, [] );
		$settings[ $section ] = $defaults[ $section ];

		update_option( self::OPTION_KEY, $settings, false );

		do_action( 'cos_pro_section_reset', $section, $defaults[ $section ] );

		return $this->success( $defaults[ $section ] );
	}

	// ─── Sanitisation ─────────────────────────────────────────────────────────

	/**
	 * Sanitise a full settings array section by section.
	 * Each section gets its own sanitisation logic so field-level
	 * rules (e.g. cast to bool, sanitize_key, wc_format_decimal) are applied.
	 *
	 * @param array $data Raw incoming settings array.
	 * @return array Sanitised settings array.
	 */
	private function sanitize_settings( array $data ): array {
		$clean = [];

		// ── general ───────────────────────────────────────────────────────────
		// Field names match the migrated keys (snake_case, no alg_ prefix).
		if ( isset( $data['general'] ) ) {
			$g = $data['general'];
			$clean['general'] = [
				'add_to_bulk_actions'          => (bool) ( $g['add_to_bulk_actions']          ?? true  ),
				'add_to_reports'               => (bool) ( $g['add_to_reports']               ?? true  ),
				'default_status'               => sanitize_key( $g['default_status']          ?? ''    ),
				'fallback_delete_status'       => sanitize_key( $g['fallback_delete_status']  ?? 'on-hold' ),
				'add_to_order_list_actions'    => (bool) ( $g['add_to_order_list_actions']    ?? false ),
				'list_actions_colored'         => (bool) ( $g['list_actions_colored']         ?? false ),
				'enable_column_colored'        => (bool) ( $g['enable_column_colored']        ?? false ),
				'enable_column_icons'          => (bool) ( $g['enable_column_icons']          ?? true  ),
				'add_to_order_preview_actions' => (bool) ( $g['add_to_order_preview_actions'] ?? false ),
				'enable_editable'              => (bool) ( $g['enable_editable']              ?? false ),
				'enable_paid'                  => (bool) ( $g['enable_paid']                  ?? false ),
				'enable_fallback'              => (bool) ( $g['enable_fallback']              ?? false ),
				'filters_priority'             => absint(  $g['filters_priority']             ?? 0     ),
			];
		}

		// ── emails ────────────────────────────────────────────────────────────
		if ( isset( $data['emails'] ) ) {
			$e = $data['emails'];
			$clean['emails'] = [
				'enabled'  => (bool) ( $e['enabled'] ?? false ),
				'statuses' => array_map( 'sanitize_key', (array) ( $e['statuses'] ?? [] ) ),
				'address'  => sanitize_textarea_field( $e['address'] ?? '' ),
				'bcc'      => sanitize_textarea_field( $e['bcc']     ?? '' ),
				'subject'  => sanitize_text_field(     $e['subject'] ?? '' ),
				'heading'  => sanitize_text_field(     $e['heading'] ?? '' ),
				'content'  => wp_kses_post(            $e['content'] ?? '' ),
			];
		}

		// ── admin_email ───────────────────────────────────────────────────────
		if ( isset( $data['admin_email'] ) ) {
			$ae = $data['admin_email'];
			$clean['admin_email'] = [
				'enabled'       => (bool) ( $ae['enabled']       ?? false  ),
				'statuses'      => array_map( 'sanitize_key', (array) ( $ae['statuses'] ?? [] ) ),
				'interval_time' => absint(              $ae['interval_time'] ?? 1     ),
				'interval'      => sanitize_key(        $ae['interval']      ?? 'days' ),
				'address'       => sanitize_textarea_field( $ae['address']   ?? ''    ),
				'subject'       => sanitize_text_field( $ae['subject']       ?? ''    ),
				'heading'       => sanitize_text_field( $ae['heading']       ?? ''    ),
				'content'       => wp_kses_post(        $ae['content']       ?? ''    ),
			];
		}

		// ── sms ───────────────────────────────────────────────────────────────
		if ( isset( $data['sms'] ) ) {
			$s = $data['sms'];
			$clean['sms'] = [
				'enabled'     => (bool) ( $s['enabled']     ?? false ),
				'from_num'    => sanitize_text_field( $s['from_num']    ?? '' ),
				'account_sid' => sanitize_text_field( $s['account_sid'] ?? '' ),
				'auth_token'  => sanitize_text_field( $s['auth_token']  ?? '' ),
				'statuses'    => array_map( 'sanitize_key', (array) ( $s['statuses'] ?? [] ) ),
				'content'     => sanitize_textarea_field( $s['content'] ?? '' ),
			];
		}

		// ── gateways: keyed by gateway_id → status slug ───────────────────────
		if ( isset( $data['gateways'] ) && is_array( $data['gateways'] ) ) {
			$clean['gateways'] = [];
			foreach ( $data['gateways'] as $gateway_id => $status ) {
				$clean['gateways'][ sanitize_key( $gateway_id ) ] = sanitize_key( $status );
			}
		}

		// ── labels: keyed by wc status slug → custom label ────────────────────
		if ( isset( $data['labels'] ) && is_array( $data['labels'] ) ) {
			$clean['labels'] = [];
			foreach ( $data['labels'] as $slug => $label ) {
				$clean['labels'][ sanitize_key( $slug ) ] = sanitize_text_field( $label );
			}
		}

		// ── advanced ──────────────────────────────────────────────────────────
		if ( isset( $data['advanced'] ) ) {
			$clean['advanced'] = [];
		}

		// ── license ───────────────────────────────────────────────────────────
		if ( isset( $data['license'] ) ) {
			$lic = $data['license'];
			$clean['license'] = [
				'key'    => sanitize_text_field( $lic['key']    ?? '' ),
				'status' => sanitize_key(        $lic['status'] ?? 'inactive' ),
			];
		}

		return $clean;
	}

	// ─── Defaults ─────────────────────────────────────────────────────────────

	/**
	 * Full default settings tree. Used for:
	 *   1. Deep-merging with saved data so missing keys always have a value.
	 *   2. Providing defaults when a section is reset.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		return [
			// ── General ───────────────────────────────────────────────────────
			// Keys match the old alg_orders_custom_statuses_* option names
			// (minus the prefix) so the migration map is 1-to-1.
			'general' => [
				'add_to_bulk_actions'          => true,   // was: alg_orders_custom_statuses_add_to_bulk_actions
				'add_to_reports'               => true,   // was: alg_orders_custom_statuses_add_to_reports
				'default_status'               => '',     // was: alg_orders_custom_statuses_default_status ('alg_disabled')
				'fallback_delete_status'       => 'on-hold', // was: alg_orders_custom_statuses_fallback_delete_status
				'add_to_order_list_actions'    => false,  // was: alg_orders_custom_statuses_add_to_order_list_actions
				'list_actions_colored'         => false,  // was: alg_orders_custom_statuses_add_to_order_list_actions_colored
				'enable_column_colored'        => false,  // was: alg_orders_custom_statuses_enable_column_colored
				'enable_column_icons'          => true,   // was: alg_orders_custom_statuses_enable_column_icons
				'add_to_order_preview_actions' => false,  // was: alg_orders_custom_statuses_add_to_order_preview_actions
				'enable_editable'              => false,  // was: alg_orders_custom_statuses_enable_editable
				'enable_paid'                  => false,  // was: alg_orders_custom_statuses_enable_paid
				'enable_fallback'              => false,  // was: alg_orders_custom_statuses_enable_fallback
				'filters_priority'             => 0,      // was: alg_orders_custom_statuses_filters_priority (moved from advanced)
			],

			// ── Order Status Emails ───────────────────────────────────────────
			'emails' => [
				'enabled'  => false,  // was: alg_orders_custom_statuses_emails_enabled ('no')
				'statuses' => [],     // was: alg_orders_custom_statuses_emails_statuses
				'address'  => '',     // was: alg_orders_custom_statuses_emails_address
				'bcc'      => '',     // was: alg_orders_custom_statuses_bcc_emails_address
				'subject'  => "[{site_title}] Order #{order_number} status changed to {status_to} - {order_date}",
				'heading'  => 'Order status changed to {status_to}',
				'content'  => 'Order #{order_number} status changed from {status_from} to {status_to}',
			],

			// ── Admin (Pending Order) Email ───────────────────────────────────
			// was section: cos_po_emails, prefix: alg_cos_po_notify_emails_
			'admin_email' => [
				'enabled'       => false,   // was: alg_cos_po_notify_emails_enabled
				'statuses'      => [],      // was: alg_cos_po_notify_emails_statuses
				'interval_time' => 1,       // was: alg_cos_po_notify_emails_interval_time
				'interval'      => 'days',  // was: alg_cos_po_notify_emails_interval
				'address'       => '',      // was: alg_cos_po_notify_emails_address
				'subject'       => "[{site_title}] Order #{order_number} action required {order_status} - {order_date}",
				'heading'       => 'Order action required to {order_status} order',
				'content'       => 'Order #{order_number} action required to {order_status} status',
			],

			// ── SMS ───────────────────────────────────────────────────────────
			'sms' => [
				'enabled'     => false,  // was: alg_orders_custom_statuses_enable_sms
				'from_num'    => '',     // was: alg_orders_custom_statuses_enable_from_num
				'account_sid' => '',     // was: alg_orders_custom_statuses_enable_acc_sid
				'auth_token'  => '',     // was: alg_orders_custom_statuses_enable_acc_token
				'statuses'    => [],     // was: alg_orders_custom_statuses_sms_statuses
				'content'     => 'Order #{order_number} status changed from {status_from} to {status_to}',
			],

			// ── Gateways: keyed by gateway_id → status slug ───────────────────
			// was: alg_orders_custom_statuses_default_status_{gateway_id} per gateway
			'gateways' => [],

			// ── Labels: keyed by wc status slug → custom label ────────────────
			// was: alg_wc_cos_{slug_with_underscores} per status
			'labels' => [],

			// ── Advanced ──────────────────────────────────────────────────────
			'advanced' => [],

			// ── License ───────────────────────────────────────────────────────
			'license' => [
				'key'    => '',
				'status' => 'inactive',
			],
		];
	}

	// ─── Schema ───────────────────────────────────────────────────────────────

	/**
	 * JSON Schema for the settings object.
	 * Used by the WordPress REST API for validation and documentation.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cos-settings',
			'type'       => 'object',
			'properties' => [
				'settings' => [
					'description' => __( 'Plugin settings object.', 'custom-order-statuses-woocommerce' ),
					'type'        => 'object',
					'context'     => [ 'view', 'edit' ],
				],
			],
		];
	}

	// ─── Request args ─────────────────────────────────────────────────────────

	/**
	 * Argument schema for POST /settings.
	 *
	 * @return array
	 */
	private function get_update_args(): array {
		return [
			'settings' => [
				'required'    => true,
				'type'        => 'object',
				'description' => __( 'Full settings object to persist.', 'custom-order-statuses-woocommerce' ),
			],
		];
	}

	// ─── Utility ──────────────────────────────────────────────────────────────

	/**
	 * Recursively merge two arrays, with $override values taking precedence.
	 * Unlike array_merge_recursive, scalar values in $override replace $base.
	 *
	 * @param array $base
	 * @param array $override
	 * @return array
	 */
	private function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				// If the incoming array is an indexed list (e.g. statuses, skip_days)
				// replace it entirely — merging by index would preserve removed items.
				if ( array_is_list( $value ) || array_is_list( $base[ $key ] ) ) {
					$base[ $key ] = $value;
				} else {
					$base[ $key ] = $this->deep_merge( $base[ $key ], $value );
				}
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	/**
	 * Merge $sanitised into $existing settings, replacing indexed arrays
	 * wholesale and deep-merging associative sections.
	 * Used by update_settings() instead of array_replace_recursive.
	 *
	 * @param array $existing  Currently saved settings.
	 * @param array $sanitised Incoming sanitised settings to merge in.
	 * @return array
	 */
	private function smart_merge( array $existing, array $sanitised ): array {
		return $this->deep_merge( $existing, $sanitised );
	}
}
