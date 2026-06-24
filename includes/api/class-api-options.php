<?php
/**
 * Class Api_Options
 *
 * Provides dynamic option lists needed by the React settings UI.
 * All data is fetched from live WooCommerce / WordPress state so the
 * UI always reflects the current store configuration.
 *
 * Routes registered:
 *   GET /wp-json/cos-pro/v1/options              → get_all_options()
 *   GET /wp-json/cos-pro/v1/options/products     → search_products()   (?search=...)
 *
 * @package Custom_Order_Status\API
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WC_Product_Query;
use WC_Shipping_Zone;
use WC_Shipping_Zones;

defined( 'ABSPATH' ) || exit;

class Api_Options extends Api_Base {

	protected $rest_base = 'options';

	// ─── Route registration ────────────────────────────────────────────────────

	public function register_routes(): void {

		// GET /options  – all option lists in one call
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_options' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'schema'              => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /options/products?search=...
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/products',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search_products' ],
				'permission_callback' => [ $this, 'check_permission' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => __( 'Product search keyword.', 'custom-order-statuses-woocommerce' ),
					],
				],
			]
		);
	}

	// ─── Handlers ─────────────────────────────────────────────────────────────

	/**
	 * GET /options
	 *
	 * Returns all dynamic option lists in one response so the React app
	 * only needs a single HTTP request on page load.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_all_options( WP_REST_Request $request ): WP_REST_Response {
		return $this->success( [
			'order_statuses'         => $this->get_all_order_statuses(),
			'custom_order_statuses'  => $this->get_custom_order_statuses(),
			'default_order_statuses' => $this->get_core_order_statuses(),
			'payment_methods'        => $this->get_payment_methods(),
			'shipping_methods'       => $this->get_shipping_methods(),
			'product_categories'     => $this->get_product_categories(),
			'user_roles'             => $this->get_user_roles(),
			'countries'              => $this->get_countries(),
		] );
	}

	/**
	 * GET /options/products?search=...
	 *
	 * Returns up to 20 products matching the search term.
	 * Used by the AsyncProductSelect component which calls this endpoint
	 * on every keystroke (debounced at 350ms in JS).
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function search_products( WP_REST_Request $request ): WP_REST_Response {
		$search  = $request->get_param( 'search' );
		$options = [];

		$args = [
			'limit'   => 20,
			'orderby' => 'relevance',
			'order'   => 'DESC',
			'return'  => 'objects',
			'status'  => 'publish',
		];

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query    = new WC_Product_Query( $args );
		$products = $query->get_products();

		foreach ( $products as $product ) {
			$options[] = [
				'value' => (string) $product->get_id(),
				'label' => wp_strip_all_tags( $product->get_name() ),
			];
		}

		return $this->success( $options );
	}

	// ─── Data helpers ──────────────────────────────────────────────────────────

	/**
	 * All registered WooCommerce order statuses, including custom plugin statuses.
	 * Each item: { value: 'wc-processing', label: 'Processing' }
	 *
	 * @return array
	 */
	private function get_all_order_statuses(): array {
		$statuses = wc_get_order_statuses();
		$options  = [];

		foreach ( $statuses as $value => $label ) {
			$options[] = [
				'value' => str_replace( 'wc-', '', $value ),
				'label' => $label,
			];
		}

		return $options;
	}

	/**
	 * Only custom order statuses created by this plugin.
	 *
	 * @return array
	 */
	private function get_custom_order_statuses(): array {
		$statuses = alg_get_custom_order_statuses_from_cpt();
		$options  = [];

		foreach ( $statuses as $value => $label ) {
			$options[] = [
				'value' => str_replace( 'wc-', '', $value ),
				'label' => $label,
			];
		}

		return $options;
	}

	

	/**
	 * Only the core WooCommerce statuses (excluding any custom ones).
	 * Used for the "default order status" dropdown in settings.
	 *
	 * @return array
	 */
	private function get_core_order_statuses(): array {
		$all_statuses = wc_get_order_statuses(); // includes all statuses (core + custom)
		$custom_keys  = array_keys( alg_get_custom_order_statuses_from_cpt() ); // keys like 'wc-...'

		// Remove custom statuses from the list
		$core_statuses = array_diff_key( $all_statuses, array_flip( $custom_keys ) );

		$options = [];
		foreach ( $core_statuses as $key => $label ) {
			$options[] = [
				'value' => str_replace( 'wc-', '', $key ),
				'label' => $label,
			];
		}

		return $options;
	}

	/**
	 * All enabled payment gateways.
	 * Each item: { value: 'cod', label: 'Cash on Delivery' }
	 *
	 * @return array
	 */
	private function get_payment_methods(): array {
		// Ensure WC is initialised before accessing gateways
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways ) {
			return [];
		}

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$options  = [];

		foreach ( $gateways as $id => $gateway ) {
			$options[] = [
				'value' => sanitize_key( $id ),
				'label' => wp_strip_all_tags( $gateway->get_title() ),
			];
		}

		return $options;
	}

	/**
	 * All shipping method instances across all shipping zones.
	 * Each item: { value: 'flat_rate:1', label: 'Flat Rate (Zone: Domestic)' }
	 *
	 * @return array
	 */
	private function get_shipping_methods(): array {
		$options = [];
		$seen    = [];

		// Iterate all shipping zones
		$zones = WC_Shipping_Zones::get_zones();
		foreach ( $zones as $zone_data ) {
			$zone = new WC_Shipping_Zone( $zone_data['zone_id'] );
			foreach ( $zone->get_shipping_methods( true ) as $method ) {
				$instance_id = $method->get_instance_id();
				if ( isset( $seen[ $instance_id ] ) ) {
					continue;
				}
				$options[] = [
					'value' => (string) $instance_id,  // Only instance ID
					'label' => sprintf(
						__( '%1$s (%2$s)', 'custom-order-statuses-woocommerce' ),
						wp_strip_all_tags( $method->get_title() ),
						$zone_data['zone_name']
					),
				];
				$seen[ $instance_id ] = true;
			}
		}

		// Also include "Rest of World" zone (zone id 0)
		$rest_of_world = new WC_Shipping_Zone( 0 );
		foreach ( $rest_of_world->get_shipping_methods( true ) as $method ) {
			$instance_id = $method->get_instance_id();
			if ( ! isset( $seen[ $instance_id ] ) ) {
				$options[] = [
					'value' => (string) $instance_id,
					'label' => sprintf(
						__( '%1$s (Rest of World)', 'custom-order-statuses-woocommerce' ),
						wp_strip_all_tags( $method->get_title() )
					),
				];
				$seen[ $instance_id ] = true;
			}
		}

		return $options;
	}

	/**
	 * All product categories.
	 * Each item: { value: '15', label: 'Clothing' }
	 *
	 * @return array
	 */
	private function get_product_categories(): array {
		$terms = get_terms( [
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$options = [];
		foreach ( $terms as $term ) {
			$options[] = [
				'value' => (string) $term->term_id,
				'label' => $term->name,
			];
		}

		return $options;
	}

	/**
	 * All WordPress user roles.
	 * Each item: { value: 'customer', label: 'Customer' }
	 *
	 * @return array
	 */
	private function get_user_roles(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return [];
		}

		$options = array();
		$options[] = array(
			'value' => 'guest',
			'label' => __( 'Guest', 'custom-order-statuses-woocommerce' ),
		);
		foreach ( $wp_roles->get_names() as $role => $label ) {
			$options[] = [
				'value' => $role,
				'label' => translate_user_role( $label ),
			];
		}

		return $options;
	}

	/**
	 * WooCommerce countries list.
	 * Each item: { value: 'IN', label: 'India' }
	 *
	 * @return array
	 */
	private function get_countries(): array {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return [];
		}

		$countries = WC()->countries->get_countries();
		$options   = [];

		foreach ( $countries as $code => $name ) {
			$options[] = [
				'value' => $code,
				'label' => $name,
			];
		}

		return $options;
	}

	// ─── Schema ───────────────────────────────────────────────────────────────

	public function get_item_schema(): array {
		return [
			'$schema' => 'http://json-schema.org/draft-04/schema#',
			'title'   => 'cos-options',
			'type'    => 'object',
			'properties' => [
				'order_statuses'         => [ 'type' => 'array' ],
				'custom_order_statuses'  => [ 'type' => 'array' ],
				'default_order_statuses' => [ 'type' => 'array' ],
				'payment_methods'        => [ 'type' => 'array' ],
				'shipping_methods'       => [ 'type' => 'array' ],
				'product_categories'     => [ 'type' => 'array' ],
				'user_roles'             => [ 'type' => 'array' ],
				'countries'              => [ 'type' => 'array' ],
			],
		];
	}
}
