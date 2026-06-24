<?php
/**
 * Class Api_License
 *
 * Manages plugin license key activation and deactivation.
 * Communicates with the Tyche Softwares licensing server.
 *
 * Routes registered:
 *   GET  /wp-json/cos-pro/v1/license            → get_status()
 *   POST /wp-json/cos-pro/v1/license/activate   → activate()
 *   POST /wp-json/cos-pro/v1/license/deactivate → deactivate()
 *
 * @package Custom_Order_Status\API
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Api_License extends Api_Base {

	protected $rest_base = 'license';

	/** Shared settings option — same store as all other plugin settings */
	const SETTINGS_KEY = 'cos_pro_settings';

	/**
	 * The remote licensing server endpoint.
	 * Replace with your actual EDD Software Licensing (or equivalent) URL.
	 */
	const LICENSE_SERVER_URL = 'https://www.tychesoftwares.com/';

	/** EDD product ID as configured on the licensing server */
	const PRODUCT_ID = 'Custom Order Status for WooCommerce';

	public function register_routes(): void {

		// GET /license
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// POST /license/activate
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'activate' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'key' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'License key to activate.', 'custom-order-statuses-woocommerce' ),
					],
				],
			]
		);

		// POST /license/deactivate
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/deactivate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deactivate' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		if ( class_exists( __NAMESPACE__ . '\\Tracking' ) ) {
			Tracking::register_settings();
		}
	}

	/**
	 * GET /license
	 * Return current license status without making a remote call.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( $this->build_status_payload() );
	}

	/**
	 * POST /license/activate
	 * Send the license key to the licensing server and store the result.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function activate( WP_REST_Request $request ): WP_REST_Response {
		$key = $request->get_param( 'key' );

		if ( empty( $key ) ) {
			return $this->error(
				'cos_license_empty_key',
				__( 'Please enter a license key.', 'custom-order-statuses-woocommerce' ),
				400
			);
		}

		// Call the remote licensing server
		$response = $this->remote_license_request( 'activate_license', $key );

		if ( is_wp_error( $response ) ) {
			return $this->error(
				'cos_license_server_error',
				$response->get_error_message(),
				502
			);
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $license_data ) ) {
			return $this->error(
				'cos_license_invalid_response',
				__( 'Invalid response from the licensing server. Please try again.', 'custom-order-statuses-woocommerce' ),
				502
			);
		}

		// EDD Software Licensing returns license->license = 'valid' on success
		if ( isset( $license_data->license ) && 'valid' === $license_data->license ) {
			$this->save_license( [
				'key'              => $key,
				'status'           => 'active',
				'expires'          => $license_data->expires          ?? '',
				'activations_left' => $license_data->activations_left ?? '',
			] );

			return $this->success(
				$this->build_status_payload(),
				200
			);
		}

		// Map EDD error codes to user-friendly messages
		$error_message = $this->get_activation_error_message( $license_data->error ?? '' );

		return $this->error( 'cos_license_activation_failed', $error_message, 422 );
	}

	/**
	 * POST /license/deactivate
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function deactivate( WP_REST_Request $request ): WP_REST_Response {
		$settings = get_option( self::SETTINGS_KEY, [] );
		$key      = $settings['license']['key'] ?? '';

		if ( empty( $key ) ) {
			return $this->error(
				'cos_license_no_key',
				__( 'No active license key found.', 'custom-order-statuses-woocommerce' ),
				400
			);
		}

		// Call the remote licensing server
		$response = $this->remote_license_request( 'deactivate_license', $key );

		// Even if the remote call fails, we clear the local status so the user
		// is not locked out. Log the error for debugging.
		if ( is_wp_error( $response ) ) {
			$this->log( 'License deactivation remote call failed: ' . $response->get_error_message() );
		}

		$this->save_license( [
			'status'           => 'inactive',
			'key'              => $key,
			'expires'          => '',
			'activations_left' => '',
		] );

		return $this->success( $this->build_status_payload() );
	}

	/**
	 * Build the license status payload returned to the React app.
	 *
	 * @return array
	 */
	private function build_status_payload(): array {
		$settings = get_option( self::SETTINGS_KEY, [] );
		$license  = $settings['license'] ?? [];
		return [
			'key'              => $license['key']              ?? '',
			'status'           => $license['status']           ?? 'inactive',
			'expires'          => $license['expires']          ?? '',
			'activations_left' => $license['activations_left'] ?? '',
		];
	}

	/**
	 * Write license data into the unified cos_pro_settings option.
	 *
	 * @param array $license_data  Associative array of license fields to set.
	 */
	private function save_license( array $license_data ): void {
		$settings            = get_option( self::SETTINGS_KEY, [] );
		$existing            = $settings['license'] ?? [];
		$settings['license'] = array_merge( $existing, $license_data );
		update_option( self::SETTINGS_KEY, $settings, false );
	}

	/**
	 * Make a wp_remote_get() request to the EDD Software Licensing endpoint.
	 *
	 * @param string $edd_action  'activate_license' or 'deactivate_license'.
	 * @param string $license_key
	 * @return array|WP_Error
	 */
	private function remote_license_request( string $edd_action, string $license_key ) {
		$api_params = [
			'edd_action' => $edd_action,
			'license'    => $license_key,
			'item_name'  => rawurlencode( self::PRODUCT_ID ),
			'url'        => home_url(),
		];

		return wp_remote_get(
			add_query_arg( $api_params, self::LICENSE_SERVER_URL ),
			[
				'timeout'   => 15,
				'sslverify' => true,
			]
		);
	}

	/**
	 * Map EDD license error codes to human-readable messages.
	 *
	 * @param string $error_code
	 * @return string
	 */
	private function get_activation_error_message( string $error_code ): string {
		$messages = [
			'expired'           => __( 'Your license key has expired. Please renew your license.', 'custom-order-statuses-woocommerce' ),
			'revoked'           => __( 'Your license key has been disabled. Please contact support.', 'custom-order-statuses-woocommerce' ),
			'missing'           => __( 'Invalid license key. Please check and try again.', 'custom-order-statuses-woocommerce' ),
			'invalid'           => __( 'License key does not match this product.', 'custom-order-statuses-woocommerce' ),
			'site_inactive'     => __( 'Your license key is not active for this URL.', 'custom-order-statuses-woocommerce' ),
			'item_name_mismatch'=> __( 'License key does not match this product name.', 'custom-order-statuses-woocommerce' ),
			'no_activations_left'=>__( 'Your license key has reached its activation limit.', 'custom-order-statuses-woocommerce' ),
		];

		return $messages[ $error_code ]
			?? __( 'License activation failed. Please check your license key or contact support.', 'custom-order-statuses-woocommerce' );
	}

	/**
	 * Conditionally log a message to the WooCommerce logger when debug mode is on.
	 *
	 * @param string $message
	 */
	private function log( string $message ): void {
		$settings = get_option( 'cos_pro_settings', [] );
		if ( ! empty( $settings['advanced']['debug_mode'] ) ) {
			$logger  = wc_get_logger();
			$context = [ 'source' => 'cos-pro-license' ];
			$logger->debug( $message, $context );
		}
	}
}
