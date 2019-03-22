<?php
/**
 * Custom Order Statuses for WooCommerce - Emails Section Settings
 *
 * @version 1.4.4
 * @since   1.4.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Settings_Emails' ) ) :

class Alg_WC_Custom_Order_Statuses_Settings_Emails extends Alg_WC_Custom_Order_Statuses_Settings_Section {

	/**
	 * Constructor.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function __construct() {
		$this->id   = 'emails';
		$this->desc = __( 'Emails', 'custom-order-statuses-woocommerce' );
		parent::__construct();
	}

	/**
	 * get_settings.
	 *
	 * @version 1.4.4
	 * @since   1.4.0
	 */
	function get_settings() {
		$emails_replaced_values_desc = sprintf( __( 'Replaced values: %s.', 'custom-order-statuses-woocommerce' ),
			'<code>' . implode( '</code>, <code>', array( '{order_id}', '{order_number}', '{order_date}', '{order_details}', '{site_title}', '{status_from}','{status_to}' ) ) . '</code>' ) . ' ' .
			__( 'You can also use shortcodes here.', 'custom-order-statuses-woocommerce' );
		return array(
			array(
				'title'    => __( 'Emails Options', 'custom-order-statuses-woocommerce' ),
				'type'     => 'title',
				'id'       => 'alg_orders_custom_statuses_emails_options',
			),
			array(
				'title'    => __( 'Emails', 'custom-order-statuses-woocommerce' ),
				'desc'     => '<strong>' . __( 'Enable section', 'custom-order-statuses-woocommerce' ) . '</strong>',
				'id'       => 'alg_orders_custom_statuses_emails_enabled',
				'default'  => 'no',
				'type'     => 'checkbox',
				'desc_tip' => apply_filters( 'alg_orders_custom_statuses',
					'Get <a href="https://wpfactory.com/item/custom-order-status-woocommerce/" target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this section.', 'settings' ),
				'custom_attributes' => apply_filters( 'alg_orders_custom_statuses', array( 'disabled' => 'disabled' ), 'settings' ),
			),
			array(
				'title'    => __( 'Statuses', 'custom-order-statuses-woocommerce' ),
				'desc_tip' => __( 'Custom statuses to send emails. Leave blank to send emails on all custom statuses.', 'custom-order-statuses-woocommerce' ),
				'id'       => 'alg_orders_custom_statuses_emails_statuses',
				'default'  => array(),
				'type'     => 'multiselect',
				'class'    => 'chosen_select',
				'options'  => alg_get_custom_order_statuses(),
			),
			array(
				'title'    => __( 'Email address', 'custom-order-statuses-woocommerce' ),
				'desc_tip' => sprintf( __( 'Comma separated list of emails. Leave blank to send emails to admin (%s).', 'custom-order-statuses-woocommerce' ), get_option( 'admin_email' ) ),
				'desc'     => sprintf( __( 'Use %s to send email to the customer\'s billing email; %s to the admin\'s email.', 'custom-order-statuses-woocommerce' ),
					'<code>%customer%</code>', '<code>%admin%</code>' ),
				'id'       => 'alg_orders_custom_statuses_emails_address',
				'default'  => '',
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Email subject', 'custom-order-statuses-woocommerce' ),
				'desc'     => str_replace( ', <code>{order_details}</code>', '', $emails_replaced_values_desc ),
				'id'       => 'alg_orders_custom_statuses_emails_subject',
				'default'  => sprintf( __( '[%s] Order #%s status changed to %s - %s', 'custom-order-statuses-woocommerce' ),
					'{site_title}', '{order_number}', '{status_to}', '{order_date}' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Email heading', 'custom-order-statuses-woocommerce' ),
				'desc'     => str_replace( ', <code>{order_details}</code>', '', $emails_replaced_values_desc ),
				'id'       => 'alg_orders_custom_statuses_emails_heading',
				'default'  => sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' ),
				'type'     => 'text',
				'css'      => 'width:100%',
			),
			array(
				'title'    => __( 'Email content', 'custom-order-statuses-woocommerce' ),
				'desc'     => '<em>' . $emails_replaced_values_desc . '</em>',
				'id'       => 'alg_orders_custom_statuses_emails_content',
				'default'  => sprintf( __( 'Order #%s status changed from %s to %s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' ),
				'type'     => 'textarea',
				'css'      => 'width:100%;height:400px',
				'alg_wc_ocs_raw' => true,
			),
			array(
				'type'     => 'sectionend',
				'id'       => 'alg_orders_custom_statuses_emails_options',
			),
		);
	}

}

endif;

return new Alg_WC_Custom_Order_Statuses_Settings_Emails();
