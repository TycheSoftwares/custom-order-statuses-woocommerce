<?php
/**
 * Class Api_Rules
 *
 * Full CRUD for Order Status Rules.
 *
 * Routes registered:
 *   GET    /wp-json/cos-pro/v1/rules           → get_items()    List all rules
 *   POST   /wp-json/cos-pro/v1/rules           → create_item()  Create a rule
 *   GET    /wp-json/cos-pro/v1/rules/{id}      → get_item()     Get a single rule
 *   PUT    /wp-json/cos-pro/v1/rules/{id}      → update_item()  Update a rule
 *   DELETE /wp-json/cos-pro/v1/rules/{id}      → delete_item()  Delete a rule
 *
 * Rules are stored as an associative array keyed by rule ID
 * in the WordPress option `cos_pro_rules`.
 * Each rule ID is a string: "{timestamp}_{random}".
 *
 * @package Custom_Order_Status\API
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Server;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

class Api_Rules extends Api_Base {

	/** @var string WordPress option key */
	const OPTION_KEY = 'cos_pro_rules';

	protected $rest_base = 'rules';

	// ─── Route registration ────────────────────────────────────────────────────

	public function register_routes(): void {

		// Collection routes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_rule_args(),
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// Single-item routes
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\w_]+)',
			[
				'args' => [
					'id' => [
						'description'       => __( 'Unique rule identifier.', 'custom-order-statuses-woocommerce' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => 'PUT, PATCH',
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => $this->get_rule_args( false ),
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);
	}

	// ─── Collection handlers ───────────────────────────────────────────────────

	/**
	 * GET /rules
	 * Return all rules as an indexed array.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_items( $request ): WP_REST_Response {
		$rules = get_option( self::OPTION_KEY, [] );
		$rules = array_map( function( $rule ) {

			$rule['products'] = array_map( function( $id ) {

				$product = wc_get_product( $id );

				return $product ? [
					'value' => $id,
					'label' => $product->get_name(),
				] : null;

			}, $rule['products'] ?? [] );

			$rule['products'] = array_values(array_filter($rule['products']));

			return $rule;

		}, $rules );
		return $this->success( array_values( $rules ) );
	}

	/**
	 * POST /rules
	 * Create a new rule and return it with its generated ID.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function create_item( $request ): WP_REST_Response {
		$body  = $request->get_json_params();
		$rule  = $this->sanitize_rule( $body );

		

		// Generate a unique ID
		$id        = time() . '_' . wp_rand( 1000, 9999 );
		$rule['id'] = $id;

		$rules        = get_option( self::OPTION_KEY, [] );
		if ( '' === $rules ) {
			$rules = array();
		}

		$rules[ $id ] = $rule;

		update_option( self::OPTION_KEY, $rules, false );

		do_action( 'cos_pro_rule_created', $rule );

		return $this->success( $rule, 201 );
	}

	// ─── Single-item handlers ──────────────────────────────────────────────────

	/**
	 * GET /rules/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_item( $request ): WP_REST_Response {
		$id    = $request->get_param( 'id' );
		$rules = get_option( self::OPTION_KEY, [] );

		if ( ! isset( $rules[ $id ] ) ) {
			return $this->error(
				'cos_rule_not_found',
				__( 'Rule not found.', 'custom-order-statuses-woocommerce' ),
				404
			);
		}

		return $this->success( $rules[ $id ] );
	}

	/**
	 * PUT /rules/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function update_item( $request ): WP_REST_Response {
		$id    = $request->get_param( 'id' );
		$rules = get_option( self::OPTION_KEY, [] );

		if ( ! isset( $rules[ $id ] ) ) {
			return $this->error(
				'cos_rule_not_found',
				__( 'Rule not found.', 'custom-order-statuses-woocommerce' ),
				404
			);
		}

		$body     = $request->get_json_params();
		$existing = $rules[ $id ];
		if ( count( $body ) === 1 && array_key_exists( 'enabled', $body ) ) {
			// Partial update — only overwrite the fields that were sent
			$existing['enabled'] = (bool) $body['enabled'];
			$rule = $existing;
		} else {
			// Full update from the edit modal — sanitize and replace as before
			$rule         = $this->sanitize_rule( $body );
			$rule['id']   = $id;
		}

		$rules[ $id ]  = $rule;

		update_option( self::OPTION_KEY, $rules, false );

		do_action( 'cos_pro_rule_updated', $rule );

		return $this->success( $rule );
	}

	/**
	 * DELETE /rules/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ): WP_REST_Response {
		$id    = $request->get_param( 'id' );
		$rules = get_option( self::OPTION_KEY, [] );

		if ( ! isset( $rules[ $id ] ) ) {
			return $this->error(
				'cos_rule_not_found',
				__( 'Rule not found.', 'custom-order-statuses-woocommerce' ),
				404
			);
		}

		$deleted = $rules[ $id ];
		unset( $rules[ $id ] );

		update_option( self::OPTION_KEY, $rules, false );

		do_action( 'cos_pro_rule_deleted', $id, $deleted );

		return $this->success( [ 'deleted' => true, 'id' => $id ] );
	}

	// ─── Sanitisation ─────────────────────────────────────────────────────────

	/**
	 * Sanitise a raw rule array from the request body.
	 * Every field is explicitly sanitised; no raw user data is stored.
	 *
	 * @param array $data Raw request body.
	 * @return array Sanitised rule.
	 */
	private function sanitize_rule( array $data ): array {

		// products may come in two shapes:
		//   - [ { value: '123', label: 'Shirt' }, … ]  (from AsyncProductSelect)
		//   - [ '123', '456', … ]                       (plain IDs)
		$raw_products = (array) ( $data['products'] ?? [] );
		$products     = [];
		foreach ( $raw_products as $product ) {
			if ( is_array( $product ) && isset( $product['value'] ) ) {
				$products[] = absint( $product['value'] );
			} else {
				$products[] = absint( $product );
			}
		}

		return [
			'name'             => sanitize_text_field(    $data['name']              ?? '' ),
			'enabled'          => (bool) (                $data['enabled']           ?? true ),
			'status_from'      => sanitize_key(           $data['status_from']       ?? '' ),
			'status_to'        => sanitize_key(           $data['status_to']         ?? '' ),
			'time_trigger'     => absint(                 $data['time_trigger']      ?? 0  ),
			'time_unit'        => sanitize_key(           $data['time_unit']         ?? 'minutes' ),
			'skip_days'        => array_map( 'sanitize_key',        (array) ( $data['skip_days']         ?? [] ) ),
			'skip_dates'       => sanitize_textarea_field(           $data['skip_dates']                  ?? '' ),
			'payment_methods'  => array_map( 'sanitize_key',        (array) ( $data['payment_methods']   ?? [] ) ),
			'shipping_methods' => array_map( 'sanitize_text_field', (array) ( $data['shipping_methods']  ?? [] ) ),
			'products'         => $products,
			'categories'       => array_map( 'absint',              (array) ( $data['categories']        ?? [] ) ),
			'min_amount'       => wc_format_decimal(                 $data['min_amount']                  ?? 0   ),
			'min_qty'          => absint(                            $data['min_qty']                     ?? 0   ),
			'user_roles'       => array_map( 'sanitize_key',        (array) ( $data['user_roles']        ?? [] ) ),
			'countries'        => array_map( 'sanitize_text_field', (array) ( $data['countries']         ?? [] ) ),
		];
	}

	// ─── Schema ───────────────────────────────────────────────────────────────

	/**
	 * JSON Schema for a single rule item.
	 *
	 * @return array
	 */
	public function get_item_schema(): array {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cos-rule',
			'type'       => 'object',
			'properties' => [
				'id'              => [ 'type' => 'string',  'readonly' => true ],
				'name'            => [ 'type' => 'string',  'required' => true  ],
				'status_from'     => [ 'type' => 'string' ],
				'status_to'       => [ 'type' => 'string' ],
				'time_trigger'    => [ 'type' => 'integer' ],
				'time_unit'       => [ 'type' => 'string', 'enum' => [ 'minutes', 'hours', 'days', 'weeks' ] ],
				'enabled'         => [ 'type' => 'boolean' ],
				'skip_days'       => [ 'type' => 'array',  'items' => [ 'type' => 'string' ] ],
				'skip_dates'      => [ 'type' => 'string' ],
				'payment_methods' => [ 'type' => 'array',  'items' => [ 'type' => 'string' ] ],
				'shipping_methods'=> [ 'type' => 'array',  'items' => [ 'type' => 'string' ] ],
				'products'        => [ 'type' => 'array' ],
				'categories'      => [ 'type' => 'array',  'items' => [ 'type' => 'integer' ] ],
				'min_amount'      => [ 'type' => 'string' ],
				'min_qty'         => [ 'type' => 'integer' ],
				'user_roles'      => [ 'type' => 'array',  'items' => [ 'type' => 'string' ] ],
				'countries'       => [ 'type' => 'array',  'items' => [ 'type' => 'string' ] ],
			],
		];
	}

	// ─── Args ─────────────────────────────────────────────────────────────────

	/**
	 * REST endpoint argument schemas for rule creation/update.
	 *
	 * @param bool $require_name  Whether the name field is required (true for create).
	 * @return array
	 */
	private function get_rule_args( bool $require_name = true ): array {
		return [
			'name' => [
				'required'          => $require_name,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Human-readable rule name.', 'custom-order-statuses-woocommerce' ),
			],
			'status_from' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'status_to' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_key',
			],
			'time_trigger' => [
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
			],
			'time_unit' => [
				'type' => 'string',
				'enum' => [ 'minutes', 'hours', 'days', 'weeks' ],
			],
		];
	}
}
