<?php
/**
 * Custom Order Statuses for WooCommerce - Functions
 *
 * @version 1.4.0
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! function_exists( 'alg_get_order_statuses' ) ) {

	/**
	 * Alg_get_order_statuses.
	 *
	 * @version 1.1.0
	 * @since   1.0.0
	 */
	function alg_get_order_statuses() {
		$result   = array();
		$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		foreach ( $statuses as $status => $status_name ) {
			$result[ substr( $status, 3 ) ] = $status_name;
		}
		return $result;
	}
}

if ( ! function_exists( 'alg_get_custom_order_statuses' ) ) {
	/**
	 * Alg_get_custom_order_statuses.
	 *
	 * @param bool $cut_prefix - Whether the prefix should be added or no.
	 *
	 * @version 1.3.5
	 * @since   1.2.0
	 */
	function alg_get_custom_order_statuses( $cut_prefix = false ) {
		$custom_order_statuses = ( '' == get_option( 'alg_orders_custom_statuses_array', array() ) ) ? array() : get_option( 'alg_orders_custom_statuses_array', array() ); // phpcs:ignore
		if ( $cut_prefix ) {
			$custom_order_statuses_no_prefix = array();
			foreach ( $custom_order_statuses as $key => $value ) {
				$custom_order_statuses_no_prefix[ substr( $key, 3 ) ] = $value;
			}
			$custom_order_statuses = $custom_order_statuses_no_prefix;
		}
		return $custom_order_statuses;
	}
}

if ( ! function_exists( 'alg_get_table_html' ) ) {
	/**
	 * Alg_get_table_html.
	 *
	 * @param array $data - Data.
	 * @param array $args - List of arguments.
	 * @version 1.4.0
	 * @since   1.3.0
	 */
	function alg_get_table_html( $data, $args = array() ) {
		$args       = array_merge(
			array(
				'table_class'        => '',
				'table_style'        => '',
				'row_styles'         => '',
				'table_heading_type' => 'horizontal',
				'columns_classes'    => array(),
				'columns_styles'     => array(),
			),
			$args
		);
		$row_styles = ( '' == $args['row_styles'] ? '' : ' style="' . $args['row_styles'] . '"' ); // phpcs:ignore
		$html       = '';
		$html      .= '<table' .
			( '' == $args['table_class'] ? '' : ' class="' . $args['table_class'] . '"' ) . // phpcs:ignore
			( '' == $args['table_style'] ? '' : ' style="' . $args['table_style'] . '"' ) . '>'; // phpcs:ignore
		$html      .= '<tbody>';
		foreach ( $data as $row_number => $row ) {
			$html .= '<tr' . $row_styles . '>';
			foreach ( $row as $column_number => $value ) {
				$th_or_td = ( ( 0 === $row_number && 'horizontal' === $args['table_heading_type'] ) || ( 0 === $column_number && 'vertical' === $args['table_heading_type'] ) ? 'th' : 'td' );
				$html    .= '<' . $th_or_td .
					( ! empty( $args['columns_classes'][ $column_number ] ) ? ' class="' . $args['columns_classes'][ $column_number ] . '"' : '' ) .
					( ! empty( $args['columns_styles'][ $column_number ] ) ? ' style="' . $args['columns_styles'][ $column_number ] . '"' : '' ) . '>';
				$html    .= $value;
				$html    .= '</' . $th_or_td . '>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody>';
		$html .= '</table>';
		return $html;
	}
}

if ( ! function_exists( 'alg_get_custom_order_statuses_from_cpt' ) ) {
	/**
	 * Function alg_get_custom_order_statuses_from_cpt.
	 *
	 * @param bool $cut_prefix - Whether the prefix should be added or no.
	 * @param bool $get_post_ids - Whether it will return post name or ID.
	 *
	 * @version 1.3.5
	 * @since   1.3.5
	 */
	function alg_get_custom_order_statuses_from_cpt( $cut_prefix = false, $get_post_ids = false ) {
		if ( class_exists( 'ActiveMember360' ) ) {
			require_once ABSPATH . 'wp-includes/pluggable.php';
		}
		// Get the order statues.
		$arg = array(
			'numberposts' => -1,
			'post_type'   => 'custom_order_status',
		);

		// Allow third party to change the arguments.
		$arg = apply_filters( 'alg_fetch_custom_order_status_arg', $arg );

		$custom_order_statuses = get_posts( $arg );

		$custom_order_statuses_no_prefix = array();

		$prefix = ! $cut_prefix ? 'wc-' : '';

		// Check array is not empty.
		if ( ! empty( $custom_order_statuses ) ) {

			if ( $get_post_ids ) {

				foreach ( $custom_order_statuses as $post ) {

					$status_slug = get_post_meta( $post->ID, 'status_slug', true );
					if ( $status_slug ) {
						$custom_order_statuses_no_prefix[ $prefix . $status_slug ] = $post->ID;
					}
				}
			} else {
				foreach ( $custom_order_statuses as $post ) {
					$status_slug = get_post_meta( $post->ID, 'status_slug', true );
					if ( $status_slug ) {
						$custom_order_statuses_no_prefix[ $prefix . $status_slug ] = $post->post_title;
					}
				}
			}
		}
		// Filter the order status results.
		$custom_order_statuses_no_prefix = apply_filters( 'alg_resuts_order_statues', $custom_order_statuses_no_prefix );

		if ( empty( $custom_order_statuses_no_prefix ) ) {
			$custom_order_statuses_no_prefix = alg_get_custom_order_statuses();
		}

		return $custom_order_statuses_no_prefix;
	}
}
if ( ! function_exists( 'cos_wc_hpos_enabled' ) ) {
	/**
	 * Check if HPOS is enabled or not.
	 *
	 * @since 2.2.0
	 * return boolean true if enabled else false
	 */
	function cos_wc_hpos_enabled() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			if ( OrderUtil::custom_orders_table_usage_is_enabled() ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'cos_get_custom_statuses' ) ) {
	/**
	 * Get custom order status slugs.
	 *
	 * @since 2.2.0
	 * return boolean true if enabled else false
	 */
	function cos_get_custom_statuses() {
		$custom_post_slug      = array();
		$query                 = array(
			'post_type'      => 'custom_order_status',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);
		$custom_order_statuses = get_posts( $query );
		foreach ( $custom_order_statuses as $custom_order_status ) {
			$post_id            = $custom_order_status->ID;
			$custom_post_meta   = get_post_meta( $post_id );
			$custom_post_slug[] = $custom_post_meta['status_slug'][0];
		}

		return $custom_post_slug;
	}
}
