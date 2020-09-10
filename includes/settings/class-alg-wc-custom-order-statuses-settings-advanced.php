<?php
/**
 * Custom Order Statuses for WooCommerce - Advanced Section Settings
 *
 * @version 1.4.0
 * @since   1.4.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Settings_Advanced' ) ) :

	/**
	 * Advanced Settings Section.
	 */
	class Alg_WC_Custom_Order_Statuses_Settings_Advanced extends Alg_WC_Custom_Order_Statuses_Settings_Section {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function __construct() {
			$this->id   = 'advanced';
			$this->desc = __( 'Advanced', 'custom-order-statuses-woocommerce' );
			parent::__construct();
		}

		/**
		 * Get_settings.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function get_settings() {
			return array(
				array(
					'title' => __( 'Advanced Options', 'custom-order-statuses-woocommerce' ),
					'type'  => 'title',
					'id'    => 'alg_orders_custom_statuses_advanced_options',
				),
				array(
					'title'    => __( 'Filters priority', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'This will set priority for WooCommerce filters used in plugin. Leave zero, if not sure.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_filters_priority',
					'default'  => 0,
					'type'     => 'number',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_orders_custom_statuses_advanced_options',
				),
			);
		}

	}

endif;

return new Alg_WC_Custom_Order_Statuses_Settings_Advanced();
