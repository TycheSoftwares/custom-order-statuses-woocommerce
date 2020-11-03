<?php
/**
 * Custom Order Statuses for WooCommerce - Emails Section Settings
 *
 * @version 1.4.4
 * @since   1.4.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Settings_Emails' ) ) :

	/**
	 * Email Settings.
	 */
	class Alg_WC_Custom_Order_Statuses_Settings_Emails extends Alg_WC_Custom_Order_Statuses_Settings_Section {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function __construct() {
			$this->id   = 'emails';
			$this->desc = __( 'Emails', 'custom-order-statuses-woocommerce' );
			parent::__construct();
		}

		/**
		 * Get_settings.
		 *
		 * @version 1.4.4
		 * @since   1.4.0
		 */
		public function get_settings() {
			$emails_replaced_values_desc = sprintf(
				// translators: Merge tags.
				__( 'Replaced values: %s.', 'custom-order-statuses-woocommerce' ),
				'<code>' . implode( '</code>, <code>', array( '{order_id}', '{order_number}', '{order_date}', '{order_details}', '{first_name}', '{last_name}', '{site_title}', '{status_from}', '{status_to}' ) ) . '</code>'
			) . ' ' .
				__( 'You can also use shortcodes here.', 'custom-order-statuses-woocommerce' );
			return array(
				array(
					'title' => __( 'Emails Options', 'custom-order-statuses-woocommerce' ),
					'type'  => 'title',
					'id'    => 'alg_orders_custom_statuses_emails_options',
				),
				array(
					'title'   => __( 'Emails', 'custom-order-statuses-woocommerce' ),
					'desc'    => '<strong>' . __( 'Enable section', 'custom-order-statuses-woocommerce' ) . '</strong>',
					'id'      => 'alg_orders_custom_statuses_emails_enabled',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				array(
					'title'    => __( 'Statuses', 'custom-order-statuses-woocommerce' ),
					'desc_tip' => __( 'Custom statuses to send emails. Leave blank to send emails on all custom statuses.', 'custom-order-statuses-woocommerce' ),
					'id'       => 'alg_orders_custom_statuses_emails_statuses',
					'default'  => array(),
					'type'     => 'multiselect',
					'class'    => 'chosen_select',
					'options'  => alg_get_custom_order_statuses_from_cpt(),
				),
				array(
					'title'    => __( 'Email address', 'custom-order-statuses-woocommerce' ),
					// translators: Comma seperated list of emails.
					'desc_tip' => sprintf( __( 'Comma separated list of emails. Leave blank to send emails to admin (%s).', 'custom-order-statuses-woocommerce' ), get_option( 'admin_email' ) ),
					'desc'     => sprintf(
						// translators: Merge tags for customer & admin emails.
						__( 'Use %1$s to send email to the customer\'s billing email; %2$s to the admin\'s email.', 'custom-order-statuses-woocommerce' ),
						'<code>{customer_email}</code>',
						'<code>{admin_email}</code>'
					),
					'id'       => 'alg_orders_custom_statuses_emails_address',
					'default'  => '',
					'type'     => 'text',
					'css'      => 'width:100%',
				),
				array(
					'title'   => __( 'Email subject', 'custom-order-statuses-woocommerce' ),
					'desc'    => str_replace( ', <code>{order_details}</code>, <code>{first_name}</code>, <code>{last_name}</code>', '', $emails_replaced_values_desc ),
					'id'      => 'alg_orders_custom_statuses_emails_subject',
					'default' => sprintf(
						// translators: merge tags - order number, new status, order date.
						__( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ),
						'{site_title}',
						'{order_number}',
						'{status_to}',
						'{order_date}'
					),
					'type'    => 'text',
					'css'     => 'width:100%',
				),
				array(
					'title'   => __( 'Email heading', 'custom-order-statuses-woocommerce' ),
					'desc'    => str_replace( ', <code>{order_details}</code>, <code>{first_name}</code>, <code>{last_name}</code>', '', $emails_replaced_values_desc ),
					'id'      => 'alg_orders_custom_statuses_emails_heading',
					// translators: Merge tags - new status.
					'default' => sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' ),
					'type'    => 'text',
					'css'     => 'width:100%',
				),
				array(
					'title'          => __( 'Email content', 'custom-order-statuses-woocommerce' ),
					'desc'           => '<em>' . $emails_replaced_values_desc . '</em>',
					'id'             => 'alg_orders_custom_statuses_emails_content',
					// translators: Merge tags - Order Number, old status, new status.
					'default'        => sprintf( __( 'Order #%1$s status changed from %2$s to %3$s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' ),
					'type'           => 'textarea',
					'css'            => 'width:100%;height:400px',
					'alg_wc_ocs_raw' => true,
				),
				array(
					'type' => 'sectionend',
					'id'   => 'alg_orders_custom_statuses_emails_options',
				),
			);
		}

	}

endif;

return new Alg_WC_Custom_Order_Statuses_Settings_Emails();
