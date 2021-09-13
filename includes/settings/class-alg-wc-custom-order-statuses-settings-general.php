<?php
/**
 * Custom Order Statuses for WooCommerce - General Section Settings
 *
 * @version 1.4.3
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Settings_General' ) ) :

	/**
	 * General Settings.
	 */
	class Alg_WC_Custom_Order_Statuses_Settings_General extends Alg_WC_Custom_Order_Statuses_Settings_Section {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function __construct() {
			$this->id   = '';
			$this->desc = __( 'General', 'custom-order-statuses-woocommerce' );
			parent::__construct();
		}

		/**
		 * Get_settings.
		 *
		 * @version 1.4.3
		 * @since   1.0.0
		 */
		public function get_settings() {
			return array(
				array(
					'title' => __( 'General Options', 'custom-order-statuses-woocommerce' ),
					'type'  => 'title',
					'desc'  => sprintf(
						// translators: Link to the page to create custom statuses.
						__( 'Use %s to create, edit and delete custom statuses.', 'custom-order-statuses-woocommerce' ),
						'<a href="' . admin_url( 'edit.php?post_type=custom_order_status' ) . '">' .
						__( 'custom order statuses page', 'custom-order-statuses-woocommerce' ) . '</a>'
					),
					'id'    => 'alg_orders_custom_statuses_general_options',
				),
				array(
					'title'   => __( 'Add custom statuses to admin order bulk actions', 'custom-order-statuses-woocommerce' ),
					'desc'    => __( 'Add', 'custom-order-statuses-woocommerce' ),
					'id'      => 'alg_orders_custom_statuses_add_to_bulk_actions',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Add custom statuses to admin reports', 'custom-order-statuses-woocommerce' ),
					'desc'    => __( 'Add', 'custom-order-statuses-woocommerce' ),
					'id'      => 'alg_orders_custom_statuses_add_to_reports',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'    => __( 'Default order status', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'You can change the default order status here. However some payment gateways may change this status immediately on order creation. E.g. BACS gateway will change status to On-hold.', 'custom-order-statuses-woocommerce' ) . ' ' .
						__( 'Plugin must be enabled to add custom statuses to the list.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_default_status',
					'default'  => 'alg_disabled',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( array( 'alg_disabled' => __( 'No changes', 'custom-order-statuses-woocommerce' ) ), alg_get_order_statuses() ),
				),
				array(
					'title'    => __( 'Default order status for BACS (Direct bank transfer) payment method', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'Plugin must be enabled to add custom statuses to the list.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_default_status_bacs',
					'default'  => 'alg_disabled',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( array( 'alg_disabled' => __( 'No changes', 'custom-order-statuses-woocommerce' ) ), alg_get_order_statuses() ),
				),
				array(
					'title'    => __( 'Default order status for Check payment method', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'Plugin must be enabled to add custom statuses to the list.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_default_status_cheque',
					'default'  => 'alg_disabled',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( array( 'alg_disabled' => __( 'No changes', 'custom-order-statuses-woocommerce' ) ), alg_get_order_statuses() ),
				),
				array(
					'title'    => __( 'Default order status for COD (Cash on delivery) payment method', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'Plugin must be enabled to add custom statuses to the list.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_default_status_cod',
					'default'  => 'alg_disabled',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( array( 'alg_disabled' => __( 'No changes', 'custom-order-statuses-woocommerce' ) ), alg_get_order_statuses() ),
				),
				array(
					'title'    => __( 'Default order status for Paypal payment method', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'Plugin must be enabled to add custom statuses to the list.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_default_status_paypal',
					'default'  => 'alg_disabled',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( array( 'alg_disabled' => __( 'No changes', 'custom-order-statuses-woocommerce' ) ), alg_get_order_statuses() ),
				),
				array(
					'title'    => __( 'Fallback delete order status', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'When you delete some custom status with "Custom Order Statuses Tool", all orders with that status will be updated to this fallback status. Please note that all fallback status triggers (email etc.) will be activated.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_fallback_delete_status',
					'default'  => 'on-hold',
					'type'     => 'select',
					'class'    => 'wc-enhanced-select',
					'options'  => array_merge( alg_get_order_statuses(), array( 'alg_none' => __( 'No fallback', 'custom-order-statuses-woocommerce' ) ) ),
				),
				array(
					'title'             => __( 'Enable colors in status column', 'custom-order-statuses-woocommerce' ),
					'desc'              => __( 'Enable', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_enable_column_colored',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: Link to the Pro version.
					'desc_tip'          => apply_filters( 'alg_orders_custom_statuses', '', 'settings' ),
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', '', 'settings' ),
				),
				array(
					'title'             => __( 'Add custom statuses to admin order list action buttons', 'custom-order-statuses-woocommerce' ),
					'desc'              => __( 'Add', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_add_to_order_list_actions',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: Link to the Pro version.
					'desc_tip'          => apply_filters( 'alg_orders_custom_statuses', sprintf( __( 'Get <a href="%s" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.', 'custom-order-statuses-woocommerce' ), 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' ), 'settings' ),
					'checkboxgroup'     => 'start',
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'desc'              => __( 'Enable colors', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_add_to_order_list_actions_colored',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: Link to the Pro version.
					'desc_tip'          => apply_filters( 'alg_orders_custom_statuses', sprintf( __( 'Get <a href="%s" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.', 'custom-order-statuses-woocommerce' ), 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' ), 'settings' ),
					'checkboxgroup'     => 'end',
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'title'             => __( 'Add custom statuses to admin order preview action buttons', 'custom-order-statuses-woocommerce' ),
					'desc'              => __( 'Add', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_add_to_order_preview_actions',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: Link to the Pro version.
					'desc_tip'          => apply_filters( 'alg_orders_custom_statuses', sprintf( __( 'Get <a href="%s" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.', 'custom-order-statuses-woocommerce' ), 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' ), 'settings' ),
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'title'             => __( 'Make custom status orders editable', 'custom-order-statuses-woocommerce' ),
					'desc'              => __( 'Enable', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_enable_editable',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: Link to the Pro version.
					'desc_tip'          => apply_filters( 'alg_orders_custom_statuses', sprintf( __( 'Get <a href="%s" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.', 'custom-order-statuses-woocommerce' ), 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' ), 'settings' ),
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'title'             => __( 'Make custom status orders paid', 'custom-order-statuses-woocommerce' ),
					'desc'              => __( 'Enable', 'custom-order-statuses-woocommerce' ),
					'id'                => 'alg_orders_custom_statuses_enable_paid',
					'default'           => 'no',
					'type'              => 'checkbox',
					// translators: List of WC statuses which are paid.
					'desc_tip'          => sprintf( __( 'By default paid statuses are: %s.', 'custom-order-statuses-woocommerce' ), '<code>processing</code>, <code>completed</code>' ) .
					// translators: Link to the Pro version.
						apply_filters( 'alg_orders_custom_statuses', sprintf( '<br>' . __( 'Get <a href="%s" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.', 'custom-order-statuses-woocommerce' ), 'https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' ), 'settings' ),
					'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_orders_custom_statuses_general_options',
				),
			);
		}

	}

endif;

return new Alg_WC_Custom_Order_Statuses_Settings_General();
