<?php
/**
 * Custom Order Statuses for WooCommerce - Settings
 *
 * @version 1.4.0
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Settings_Custom_Order_Statuses' ) ) :

	/**
	 * Inherits the WC Settings Class.
	 */
	class Alg_WC_Settings_Custom_Order_Statuses extends WC_Settings_Page {

		/**
		 * Constructor.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function __construct() {
			$this->id    = 'alg_wc_custom_order_statuses';
			$this->label = __( 'Custom Order Status', 'custom-order-statuses-woocommerce' );
			parent::__construct();
			add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_unsanitize_option' ), PHP_INT_MAX, 3 );
		}

		/**
		 * Maybe_unsanitize_option.
		 *
		 * @param string $value - Setting Value.
		 * @param array  $option -  List of settings.
		 * @param string $raw_value - Unsanitized Value.
		 *
		 * @since   1.4.0
		 * @version 1.4.0
		 */
		public function maybe_unsanitize_option( $value, $option, $raw_value ) {
			return ( ! empty( $option['alg_wc_ocs_raw'] ) ? $raw_value : $value );
		}

		/**
		 * Get_settings.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function get_settings() {
			global $current_section;
			return array_merge(
				apply_filters( 'woocommerce_get_settings_' . $this->id . '_' . $current_section, array() ),
				array(
					array(
						'title' => __( 'Reset Settings', 'custom-order-statuses-woocommerce' ),
						'type'  => 'title',
						'id'    => $this->id . '_' . $current_section . '_reset_options',
					),
					array(
						'title'   => __( 'Reset section settings', 'custom-order-statuses-woocommerce' ),
						'desc'    => '<strong>' . __( 'Reset', 'custom-order-statuses-woocommerce' ) . '</strong>',
						'id'      => $this->id . '_' . $current_section . '_reset',
						'default' => 'no',
						'type'    => 'checkbox',
					),
					array(
						'type' => 'sectionend',
						'id'   => $this->id . '_' . $current_section . '_reset_options',
					),
				)
			);
		}

		/**
		 * Maybe_reset_settings.
		 *
		 * @version 1.2.1
		 * @since   1.2.1
		 */
		public function maybe_reset_settings() {
			global $current_section;
			if ( 'yes' === get_option( $this->id . '_' . $current_section . '_reset', 'no' ) ) {
				foreach ( $this->get_settings() as $value ) {
					if ( isset( $value['default'] ) && isset( $value['id'] ) ) {
						delete_option( $value['id'] );
						$autoload = isset( $value['autoload'] ) ? (bool) $value['autoload'] : true;
						add_option( $value['id'], $value['default'], '', ( $autoload ? 'yes' : 'no' ) );
					}
				}
			}
		}

		/**
		 * Save settings.
		 *
		 * @version 1.2.1
		 * @since   1.2.1
		 */
		public function save() {
			parent::save();
			$this->maybe_reset_settings();
		}

	}

endif;

return new Alg_WC_Settings_Custom_Order_Statuses();
