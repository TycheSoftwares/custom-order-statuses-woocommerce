<?php
/**
 * Class Api_Base
 *
 * Shared base for every REST controller in this plugin.
 * Extends WP_REST_Controller so we get proper WordPress conventions:
 *   - Standard response envelope via $this->prepare_item_for_response()
 *   - Schema support
 *   - Built-in namespace / rest_base handling
 *
 * @package Custom_Order_Status\API
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

abstract class Api_Base extends WP_REST_Controller {

	/**
	 * REST namespace shared across all plugin routes.
	 *
	 * @var string
	 */
	protected $namespace = 'cos-pro/v1';

	/**
	 * Plugin text domain, used in translatable error messages.
	 *
	 * @var string
	 */
	protected $text_domain = 'custom-order-statuses-woocommerce';

	/**
	 * Default permission callback: require manage_woocommerce capability.
	 * All controllers use this unless they override it.
	 *
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'cos_rest_forbidden',
				__( 'You do not have permission to manage Custom Order Status settings.', 'custom-order-statuses-woocommerce' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}
		return true;
	}

	/**
	 * Wrap data in a standard success envelope and return a REST response.
	 *
	 * @param mixed $data
	 * @param int   $status  HTTP status code (default 200).
	 * @return WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Return a standard error response.
	 *
	 * @param string $code    Machine-readable error code.
	 * @param string $message Human-readable message.
	 * @param int    $status  HTTP status code (default 400).
	 * @return WP_REST_Response
	 */
	protected function error( string $code, string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'success' => false,
				'code'    => $code,
				'message' => $message,
			],
			$status
		);
	}

	/**
	 * Recursively sanitise an arbitrary nested array for database storage.
	 * Booleans, integers, and floats are preserved with proper casting.
	 * All string values are run through sanitize_textarea_field().
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	protected function deep_sanitize( $data ) {
		if ( is_array( $data ) ) {
			return array_map( [ $this, 'deep_sanitize' ], $data );
		}
		if ( is_bool( $data ) ) {
			return (bool) $data;
		}
		if ( is_int( $data ) ) {
			return (int) $data;
		}
		if ( is_float( $data ) ) {
			return (float) $data;
		}
		return sanitize_textarea_field( (string) $data );
	}

	/**
	 * Helper to safely read a value from a request body with a fallback.
	 *
	 * @param array  $body
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	protected function get_param( array $body, string $key, $default = '' ) {
		return $body[ $key ] ?? $default;
	}
}
