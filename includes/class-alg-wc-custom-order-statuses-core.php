<?php
/**
 * Custom Order Statuses for WooCommerce - Core Class
 *
 * @version 1.4.4
 * @since   1.0.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Core' ) ) :

	/**
	 * Core Functionality.
	 */
	class Alg_WC_Custom_Order_Statuses_Core {

		/**
		 * Constructor.
		 *
		 * @version 1.4.4
		 * @since   1.0.0
		 * @todo    [feature] "Processing" and "Complete" action buttons (list & preview)
		 */
		public function __construct() {

			// Filters priority.
			$filters_priority = get_option( 'alg_orders_custom_statuses_filters_priority', 0 );
			if ( 0 === $filters_priority ) {
				$filters_priority = PHP_INT_MAX;
			}

			// Custom Status: Filter, Register, Icons.
			add_filter( 'wc_order_statuses', array( $this, 'add_custom_statuses_to_filter' ), $filters_priority );
			add_action( 'init', array( $this, 'register_custom_post_statuses' ) );
			add_action( 'admin_head', array( $this, 'hook_statuses_icons_css' ), 11 );

			// Default Status.
			add_filter( 'woocommerce_thankyou', array( $this, 'set_default_order_status' ), $filters_priority );

			// Reports.
			if ( 'yes' === get_option( 'alg_orders_custom_statuses_add_to_reports', 'yes' ) ) {
				add_filter( 'woocommerce_reports_order_statuses', array( $this, 'add_custom_order_statuses_to_reports' ), $filters_priority );
			}

			// Bulk Actions.
			if ( 'yes' === get_option( 'alg_orders_custom_statuses_add_to_bulk_actions', 'yes' ) ) {
				if ( version_compare( get_bloginfo( 'version' ), '4.7' ) >= 0 ) {
					add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_order_custom_status_bulk_actions' ), $filters_priority );
				} else {
					add_action( 'admin_footer', array( $this, 'bulk_admin_footer' ), 11 );
				}
			}

			// Admin Order List Actions.
			if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions' ) ) {
				add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_custom_status_actions_buttons' ), $filters_priority, 2 );
				add_action( 'admin_head', array( $this, 'add_custom_status_actions_buttons_css' ) );
			}

			// Column Colors.
			if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_column_colored' ) ) {
				add_action( 'admin_head', array( $this, 'add_custom_status_column_css' ) );
			}

			// Order preview actions.
			if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_preview_actions' ) ) {
				add_filter( 'woocommerce_admin_order_preview_actions', array( $this, 'add_custom_status_to_order_preview' ), PHP_INT_MAX, 2 );
			}

			// Editable orders.
			if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_editable' ) ) {
				add_filter( 'wc_order_is_editable', array( $this, 'add_custom_order_statuses_to_order_editable' ), PHP_INT_MAX, 2 );
			}

			// Paid order statuses.
			if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_paid' ) ) {
				add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_custom_order_statuses_to_order_paid' ), PHP_INT_MAX );
			}

			// Emails.
			if ( 'yes' === get_option( 'alg_orders_custom_statuses_emails_enabled', 'no' ) ) {
				add_action( 'woocommerce_order_status_changed', array( $this, 'send_email_on_order_status_changed' ), PHP_INT_MAX, 4 );
			}

		}

		/**
		 * Get_custom_order_statuses_actions.
		 *
		 * @param object $_order - WC Order object.
		 * @version 1.4.1
		 * @since   1.4.1
		 */
		public function get_custom_order_statuses_actions( $_order ) {
			$status_actions        = array();
			$custom_order_statuses = alg_get_custom_order_statuses_from_cpt( true );
			foreach ( $custom_order_statuses as $custom_order_status => $label ) {
				if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status.
					$status_actions[ $custom_order_status ] = $label;
				}
			}
			return $status_actions;
		}

		/**
		 * Get_custom_order_statuses_action_url.
		 *
		 * @param string $status - Order Status.
		 * @param int    $order_id - Order ID.
		 *
		 * @version 1.4.1
		 * @since   1.4.1
		 */
		public function get_custom_order_statuses_action_url( $status, $order_id ) {
			return wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=' . $status . '&order_id=' . $order_id ), 'woocommerce-mark-order-status' );
		}

		/**
		 * Add_custom_status_to_order_preview.
		 *
		 * @param array  $actions - Actions available for the order.
		 * @param object $_order - WC Order object.
		 * @return array $actions - Actions available for the order.
		 *
		 * @version 1.4.1
		 * @since   1.4.1
		 */
		public function add_custom_status_to_order_preview( $actions, $_order ) {
			$status_actions  = array();
			$_status_actions = $this->get_custom_order_statuses_actions( $_order );
			if ( ! empty( $_status_actions ) ) {
				$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );
				foreach ( $_status_actions as $custom_order_status => $label ) {
					$status_actions[ $custom_order_status ] = array(
						'url'    => $this->get_custom_order_statuses_action_url( $custom_order_status, $order_id ),
						'name'   => $label,
						// translators: Custom Order status.
						'title'  => sprintf( __( 'Change order status to %s', 'custom-order-statuses-woocommerce' ), $custom_order_status ),
						'action' => $custom_order_status,
					);
				}
			}
			if ( $status_actions ) {
				if ( ! empty( $actions['status']['actions'] ) && is_array( $actions['status']['actions'] ) ) {
					$actions['status']['actions'] = array_merge( $actions['status']['actions'], $status_actions );
				} else {
					$actions['status'] = array(
						'group'   => __( 'Change status: ', 'woocommerce' ),
						'actions' => $status_actions,
					);
				}
			}
			return $actions;
		}

		/**
		 * Add_custom_order_statuses_to_order_paid.
		 *
		 * @param array $statuses - List of statuses.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 * @todo    [feature] separate option for each custom status
		 */
		public function add_custom_order_statuses_to_order_paid( $statuses ) {
			return array_merge( $statuses, array_keys( alg_get_custom_order_statuses_from_cpt( true ) ) );
		}

		/**
		 * Send_email_on_order_status_changed.
		 *
		 * @param integer $order_id - WC Order ID.
		 * @param string  $status_from - Old Status.
		 * @param string  $status_to - New Status.
		 * @param object  $order - WC Order.
		 *
		 * @version 1.4.4
		 * @since   1.4.0
		 * @todo    [dev] maybe use `woocommerce_order_status_ . $status_transition['to']` action instead of `woocommerce_order_status_changed`
		 * @todo    [dev] recheck - email from
		 * @todo    [feature] add more replaced values
		 * @todo    [feature] optional `wrap_in_wc_email_template()`
		 * @todo    [feature] separate content, subject etc. for each custom status
		 */
		public function send_email_on_order_status_changed( $order_id, $status_from, $status_to, $order ) {

			$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();

			$emails_statuses = get_option( 'alg_orders_custom_statuses_emails_statuses', array() );
			if ( in_array( 'wc-' . $status_to, $emails_statuses, true ) || ( empty( $emails_statuses ) && in_array( 'wc-' . $status_to, array_keys( $alg_orders_custom_statuses_array ), true ) ) ) {
				// Options.
				$email_address = get_option( 'alg_orders_custom_statuses_emails_address', '' );
				$email_subject = get_option(
					'alg_orders_custom_statuses_emails_subject',
					// translators: WC Order Number, New Status & Date on which the order was placed.
					sprintf( __( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' )
				);
				$email_heading = get_option(
					'alg_orders_custom_statuses_emails_heading',
					// translators: New Order status.
					sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' )
				);
				$email_content = nl2br(
					get_option(
						'alg_orders_custom_statuses_emails_content',
						// translators: WC Order Number, Old status, new status.
						sprintf( __( 'Order #%1$s status changed from %2$s to %3$s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' )
					)
				);

				$woo_statuses        = wc_get_order_statuses();
				$replace_status_from = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_from ] : $woo_statuses[ 'wc-' . $status_from ];
				$replace_status_to   = isset( $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] ) ? $alg_orders_custom_statuses_array[ 'wc-' . $status_to ] : $woo_statuses[ 'wc-' . $status_to ];

				// Replaced values.
				$replaced_values       = array(
					'{order_id}'      => $order_id,
					'{order_number}'  => $order->get_order_number(),
					'{order_date}'    => date( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ), // phpcs:ignore
					'{order_details}' => ( false !== strpos( $email_content, '{order_details}' ) ? $this->get_wc_order_details_template( $order ) : '' ),
					'{site_title}'    => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
					'{status_from}'   => $replace_status_from,
					'{status_to}'     => $replace_status_to,
					'{first_name}'    => $order->get_billing_first_name(),
					'{last_name}'     => $order->get_billing_last_name(),
				);
				$email_replaced_values = array(
					'{customer_email}' => $order->get_billing_email(),
					'{admin_email}'    => get_option( 'admin_email' ),
				);
				// Final processing.
				$email_address = ( '' === $email_address ? get_option( 'admin_email' ) : str_replace( array_keys( $email_replaced_values ), $email_replaced_values, $email_address ) );
				$email_subject = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_subject ) );
				$email_heading = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_heading ) );
				$email_content = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $this->wrap_in_wc_email_template( $email_content, $email_heading ) ) );
				// Send mail.
				wc_mail( $email_address, $email_subject, $email_content );
			}
		}

		/**
		 * Get_wc_order_details_template.
		 *
		 * @param object $order - WC Order object.
		 * @version 1.4.4
		 * @since   1.4.4
		 */
		public function get_wc_order_details_template( $order ) {
			ob_start();
			wc_get_template(
				'emails/email-order-details.php',
				array(
					'order'         => $order,
					'sent_to_admin' => false,
					'plain_text'    => false,
					'email'         => '',
				)
			);
			return ob_get_clean();
		}

		/**
		 * Wrap_in_wc_email_template.
		 *
		 * @param string $content - Email content.
		 * @param string $email_heading - Email heading.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function wrap_in_wc_email_template( $content, $email_heading = '' ) {
			return $this->get_wc_email_part( 'header', $email_heading ) . $content . $this->get_wc_email_part( 'footer' );
		}

		/**
		 * Get_wc_email_part.
		 *
		 * @param string $part - Email part (header or footer).
		 * @param string $email_heading - Email heading.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function get_wc_email_part( $part, $email_heading = '' ) {
			ob_start();
			switch ( $part ) {
				case 'header':
					wc_get_template( 'emails/email-header.php', array( 'email_heading' => $email_heading ) );
					break;
				case 'footer':
					wc_get_template( 'emails/email-footer.php' );
					break;
			}
			return ob_get_clean();
		}

		/**
		 * Add_custom_order_statuses_to_order_editable.
		 *
		 * @param boolean $is_editable - Order is editable or no.
		 * @param object  $_order - WC Order object.
		 *
		 * @version 1.3.5
		 * @since   1.3.5
		 * @todo    [feature] separate option for each custom status
		 */
		public function add_custom_order_statuses_to_order_editable( $is_editable, $_order ) {
			return ( in_array( 'wc-' . $_order->get_status(), array_keys( alg_get_custom_order_statuses_from_cpt() ), true ) ? true : $is_editable );
		}

		/**
		 * Add_custom_status_column_css.
		 *
		 * @version 1.3.3
		 * @since   1.3.2
		 */
		public function add_custom_status_column_css() {
			$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
			if ( empty( $statuses ) ) {
				$statuses = alg_get_custom_order_statuses();
			}
			foreach ( $statuses as $status => $status_id ) {
				$content    = get_post_meta( $status_id, 'content', true );
				$icon_color = get_post_meta( $status_id, 'color', true );
				$text_color = get_post_meta( $status_id, 'text_color', true );
				if ( ! $content ) {
					$content = 'e011';
				}
				if ( ! $text_color ) {
					$text_color = '#000000';
				}
				if ( ! $icon_color ) {
					$icon_color = '#999999';
				}

				if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
					$status      = substr( $status, 3 );
					$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );
					if ( $status_data['content'] ) {
						$content = $status_data['content'];
					}
					if ( $status_data['color'] ) {
						$icon_color = $status_data['color'];
					}
					if ( $status_data['text_color'] ) {
						$text_color = $status_data['text_color'];
					}
				}
				echo '<style>mark.order-status.status-' . esc_attr( $status ) . ' { color: ' . esc_attr( $text_color ) . '; background-color: ' . esc_attr( $icon_color ) . ' }</style>';
			}
		}

		/**
		 * Add_custom_status_actions_buttons.
		 *
		 * @param array  $actions - List of actions for the order.
		 * @param object $_order - WC Order object.
		 *
		 * @version 1.4.1
		 * @since   1.2.0
		 */
		public function add_custom_status_actions_buttons( $actions, $_order ) {
			$statuses = alg_get_custom_order_statuses_from_cpt();

			$_order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );

			// if the complete order action is not present in the array, add it (happens when the order is set to a custom status).
			if ( ! in_array( 'complete', $actions, true ) ) {
				$actions['complete'] = array(
					'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $_order_id ), 'woocommerce-mark-order-status' ),
					'name'   => __( 'Complete', 'woocommerce' ),
					'action' => 'complete',
				);
			}

			foreach ( $statuses as $slug => $label ) {
				$custom_order_status = substr( $slug, 3 );
				if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status.
					$actions[ $custom_order_status ] = array(
						'url'    => $this->get_custom_order_statuses_action_url( $custom_order_status, $_order_id ),
						'name'   => $label,
						'action' => 'view ' . $custom_order_status, // setting "view" for proper button CSS.
					);
				}
			}
			return $actions;
		}

		/**
		 * Add_custom_status_actions_buttons_css.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		public function add_custom_status_actions_buttons_css() {
			$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
			if ( empty( $statuses ) ) {
				$statuses = alg_get_custom_order_statuses();
			}
			foreach ( $statuses as $status => $status_id ) {
				$content    = get_post_meta( $status_id, 'content', true );
				$icon_color = get_post_meta( $status_id, 'color', true );
				$text_color = get_post_meta( $status_id, 'text_color', true );
				if ( ! $content ) {
					$content = 'e011';
				}
				if ( ! $text_color ) {
					$text_color = '#000000';
				}
				if ( ! $icon_color ) {
					$icon_color = '#999999';
				}

				if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
					$status      = substr( $status, 3 );
					$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );
					if ( $status_data['content'] ) {
						$content = $status_data['content'];
					}
					if ( $status_data['color'] ) {
						$icon_color = $status_data['color'];
					}
					if ( $status_data['text_color'] ) {
						$text_color = $status_data['text_color'];
					}
				}
				$color_style = ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions_colored' ) ) ? ' color: ' . esc_attr( $icon_color ) . ' !important;' : '';
				echo '<style>.view.' . esc_attr( $status ) . '::after { font-family: WooCommerce !important;' . esc_attr( $color_style ) . ' content: "\\' . esc_attr( $content ) . '" !important; }</style>';
			}
		}

		/**
		 * Add_custom_order_statuses_to_reports.
		 *
		 * @param array $order_statuses - List of order statuses.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function add_custom_order_statuses_to_reports( $order_statuses ) {
			if ( is_array( $order_statuses ) && in_array( 'completed', $order_statuses, true ) ) {
				return array_merge( $order_statuses, array_keys( alg_get_custom_order_statuses_from_cpt( true ) ) );
			}
			return $order_statuses;
		}

		/**
		 * Set_default_order_status.
		 *
		 * @param array $order_id - Order id.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function set_default_order_status( $order_id ) {
			if ( ! $order_id ) {
				return;
			}
			$order          = wc_get_order( $order_id );
			$payment_method = $order->get_payment_method();
			if ( 'yes' !== get_post_meta( $order_id, 'alg_cos_updated', true ) ) {
				if ( 'alg_disabled' !== get_option( 'alg_orders_custom_statuses_default_status_' . $payment_method, 'alg_disabled' ) ) {
					$order->update_status( get_option( 'alg_orders_custom_statuses_default_status_' . $payment_method, 'alg_disabled' ) );
					update_post_meta( $order_id, 'alg_cos_updated', 'yes' );
				} elseif ( 'alg_disabled' !== get_option( 'alg_orders_custom_statuses_default_status', 'alg_disabled' ) ) {
					$order->update_status( get_option( 'alg_orders_custom_statuses_default_status', 'alg_disabled' ) );
					update_post_meta( $order_id, 'alg_cos_updated', 'yes' );
				}
			}
		}

		/**
		 * Register_custom_post_statuses.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function register_custom_post_statuses() {
			$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
			foreach ( $alg_orders_custom_statuses_array as $slug => $label ) {
				register_post_status(
					$slug,
					array(
						'label'                     => $label,
						'public'                    => true,
						'exclude_from_search'       => false,
						'show_in_admin_all_list'    => true,
						'show_in_admin_status_list' => true,
						// translators: Count of orders with the custom status.
						'label_count'               => _n_noop( "$label <span class='count'>(%s)</span>", "$label <span class='count'>(%s)</span>" ), // phpcs:ignore
					)
				);
			}
		}

		/**
		 * Add_custom_statuses_to_filter.
		 *
		 * @param array $order_statuses - Order Statuses.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function add_custom_statuses_to_filter( $order_statuses ) {
			$alg_orders_custom_statuses_array = alg_get_custom_order_statuses_from_cpt();
			$order_statuses                   = ( '' === $order_statuses ) ? array() : $order_statuses;
			return array_merge( $order_statuses, $alg_orders_custom_statuses_array );
		}

		/**
		 * Hook_statuses_icons_css.
		 *
		 * @version 1.2.0
		 * @since   1.0.0
		 */
		public function hook_statuses_icons_css() {
			$output   = '<style>';
			$statuses = alg_get_custom_order_statuses_from_cpt( true, true );
			if ( empty( $statuses ) ) {
				$statuses = alg_get_custom_order_statuses();
			}
			foreach ( $statuses as $status => $status_id ) {
				$content    = get_post_meta( $status_id, 'content', true );
				$icon_color = get_post_meta( $status_id, 'color', true );
				$text_color = get_post_meta( $status_id, 'text_color', true );
				if ( ! $content ) {
					$content = 'e011';
				}
				if ( ! $text_color ) {
					$text_color = '#000000';
				}
				if ( ! $icon_color ) {
					$icon_color = '#999999';
				}

				if ( strpos( $status, 'wc-' ) > -1 && ! empty( alg_get_custom_order_statuses() ) ) {
					$status      = substr( $status, 3 );
					$status_data = get_option( 'alg_orders_custom_status_icon_data_' . $status );
					if ( $status_data['content'] ) {
						$content = $status_data['content'];
					}
					if ( $status_data['color'] ) {
						$icon_color = $status_data['color'];
					}
					if ( $status_data['text_color'] ) {
						$text_color = $status_data['text_color'];
					}
				}

				$output .= '.status-' . $status . ' { position: relative; color: ' . $text_color . '; }';
				$output .= 'mark.status-' . $status . ':after { content: "\\' . $content . '"; color: ' . $text_color . '; }';
				$output .= 'mark.status-' . $status . ':after { font-family: WooCommerce; speak: none; font-weight: 400; font-variant: normal; text-transform: none; line-height: 1; -webkit-font-smoothing: antialiased; margin: 0; text-indent: 0; position: absolute; top: 0; left: 0; width: 100%; height: 100%; text-align: center }';
			}
			$output .= '</style>';
			echo wp_kses( $output, array( 'style' => array() ) );
		}

		/**
		 * Register_order_custom_status_bulk_actions.
		 *
		 * @param array $bulk_actions - List of bulk actions.
		 *
		 * @version 1.4.0
		 * @since   1.1.0
		 * @see     https://make.wordpress.org/core/2016/10/04/custom-bulk-actions/
		 */
		public function register_order_custom_status_bulk_actions( $bulk_actions ) {
			$custom_order_statuses = alg_get_custom_order_statuses_from_cpt( true );
			foreach ( $custom_order_statuses as $slug => $label ) {
				// translators: New Status.
				$bulk_actions[ 'mark_' . $slug ] = sprintf( __( 'Change status to %s', 'custom-order-statuses-woocommerce' ), $label );
			}
			return $bulk_actions;
		}

		/**
		 * Add extra bulk action options to mark orders as complete or processing.
		 *
		 * Using Javascript until WordPress core fixes: http://core.trac.wordpress.org/ticket/16031
		 * Fixed in WordPress v4.7
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function bulk_admin_footer() {
			global $post_type;
			if ( 'shop_order' === $post_type ) {
				?><script type="text/javascript">
				<?php
				foreach ( alg_get_order_statuses() as $key => $order_status ) {
					if ( in_array( $key, array( 'processing', 'on-hold', 'completed' ), true ) ) {
						continue;
					}
					?>
				jQuery(function() {
					// translators: custom status.
					jQuery('<option>').val('mark_<?php echo esc_attr( $key ); ?>').text('<?php sprintf( __( 'Mark %s', 'custom-order-statuses-woocommerce' ), esc_attr( $order_status ) ); ?>').appendTo('select[name="action"]');
					// translators: custom status.
					jQuery('<option>').val('mark_<?php echo esc_attr( $key ); ?>').text('<?php sprintf( __( 'Mark %s', 'custom-order-statuses-woocommerce' ), esc_attr( $order_status ) ); ?>').appendTo('select[name="action2"]');
				});
					<?php
				}
				?>
			</script>
				<?php
			}
		}

	}

endif;

return new Alg_WC_Custom_Order_Statuses_Core();
