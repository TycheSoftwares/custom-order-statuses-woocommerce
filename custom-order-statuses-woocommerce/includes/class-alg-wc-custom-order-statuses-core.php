<?php
/**
 * Custom Order Statuses for WooCommerce - Core Class
 *
 * @version 1.4.4
 * @since   1.0.0
 * @author  Tyche Softwares
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Core' ) ) :

class Alg_WC_Custom_Order_Statuses_Core {

	/**
	 * Constructor.
	 *
	 * @version 1.4.4
	 * @since   1.0.0
	 * @todo    [feature] "Processing" and "Complete" action buttons (list & preview)
	 */
	function __construct() {

		// Filters priority
		if ( 0 == ( $filters_priority = get_option( 'alg_orders_custom_statuses_filters_priority', 0 ) ) ) {
			$filters_priority = PHP_INT_MAX;
		}

		// Custom Status: Filter, Register, Icons
		add_filter( 'wc_order_statuses',  array( $this, 'add_custom_statuses_to_filter' ), $filters_priority );
		add_action( 'init',               array( $this, 'register_custom_post_statuses' ) );
		add_action( 'admin_head',         array( $this, 'hook_statuses_icons_css' ), 11 );

		// Default Status
		if ( 'alg_disabled' != get_option( 'alg_orders_custom_statuses_default_status', 'alg_disabled' ) ) {
			add_filter( 'woocommerce_default_order_status',              array( $this, 'set_default_order_status' ), $filters_priority );
		}
		if ( 'alg_disabled' != get_option( 'alg_orders_custom_statuses_default_status_bacs', 'alg_disabled' ) ) {
			add_filter( 'woocommerce_bacs_process_payment_order_status', array( $this, 'set_default_order_status_bacs' ), $filters_priority );
		}
		if ( 'alg_disabled' != get_option( 'alg_orders_custom_statuses_default_status_cod', 'alg_disabled' ) ) {
			add_filter( 'woocommerce_cod_process_payment_order_status',  array( $this, 'set_default_order_status_cod' ), $filters_priority );
		}

		// Reports
		if ( 'yes' === get_option( 'alg_orders_custom_statuses_add_to_reports', 'yes' ) ) {
			add_filter( 'woocommerce_reports_order_statuses', array( $this, 'add_custom_order_statuses_to_reports' ), $filters_priority );
		}

		// Bulk Actions
		if ( 'yes' === get_option( 'alg_orders_custom_statuses_add_to_bulk_actions', 'yes' ) ) {
			if ( version_compare( get_bloginfo( 'version' ), '4.7' ) >= 0 ) {
				add_filter( 'bulk_actions-edit-shop_order', array( $this, 'register_order_custom_status_bulk_actions' ), $filters_priority );
			} else {
				add_action( 'admin_footer', array( $this, 'bulk_admin_footer' ), 11 );
			}
		}

		// Admin Order List Actions
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions' ) ) {
			add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_custom_status_actions_buttons' ), $filters_priority, 2 );
			add_action( 'admin_head',                      array( $this, 'add_custom_status_actions_buttons_css' ) );
		}

		// Column Colors
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_column_colored' ) ) {
			add_action( 'admin_head', array( $this, 'add_custom_status_column_css' ) );
		}

		// Order preview actions
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_preview_actions' ) ) {
			add_filter( 'woocommerce_admin_order_preview_actions', array( $this, 'add_custom_status_to_order_preview' ), PHP_INT_MAX, 2 );
		}

		// Editable orders
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_editable' ) ) {
			add_filter( 'wc_order_is_editable', array( $this, 'add_custom_order_statuses_to_order_editable' ), PHP_INT_MAX, 2 );
		}

		// Paid order statuses
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_is_paid' ) ) {
			add_filter( 'woocommerce_order_is_paid_statuses', array( $this, 'add_custom_order_statuses_to_order_paid' ), PHP_INT_MAX );
		}

		// Emails
		if ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_emails' ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'send_email_on_order_status_changed' ), PHP_INT_MAX, 4 );
		}

	}

	/**
	 * get_custom_order_statuses_actions.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	function get_custom_order_statuses_actions( $_order ) {
		$status_actions        = array();
		$custom_order_statuses = alg_get_custom_order_statuses( true );
		foreach ( $custom_order_statuses as $custom_order_status => $label ) {
			if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status
				$status_actions[ $custom_order_status ] = $label;
			}
		}
		return $status_actions;
	}

	/**
	 * get_custom_order_statuses_action_url.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	function get_custom_order_statuses_action_url( $status, $order_id ) {
		return wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=' . $status . '&order_id=' . $order_id ), 'woocommerce-mark-order-status' );
	}

	/**
	 * add_custom_status_to_order_preview.
	 *
	 * @version 1.4.1
	 * @since   1.4.1
	 */
	function add_custom_status_to_order_preview( $actions, $_order ) {
		$status_actions  = array();
		$_status_actions = $this->get_custom_order_statuses_actions( $_order );
		if ( ! empty( $_status_actions ) ) {
			$order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );
			foreach ( $_status_actions as $custom_order_status => $label ) {
				$status_actions[ $custom_order_status ] = array(
					'url'       => $this->get_custom_order_statuses_action_url( $custom_order_status, $order_id ),
					'name'      => $label,
					'title'     => sprintf( __( 'Change order status to %s', 'custom-order-statuses-woocommerce' ), $custom_order_status ),
					'action'    => $custom_order_status,
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
	 * add_custom_order_statuses_to_order_paid.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 * @todo    [feature] separate option for each custom status
	 */
	function add_custom_order_statuses_to_order_paid( $statuses ) {
		return array_merge( $statuses, array_keys( alg_get_custom_order_statuses( true ) ) );
	}

	/**
	 * send_email_on_order_status_changed.
	 *
	 * @version 1.4.4
	 * @since   1.4.0
	 * @todo    [dev] maybe use `woocommerce_order_status_ . $status_transition['to']` action instead of `woocommerce_order_status_changed`
	 * @todo    [dev] recheck - email from
	 * @todo    [feature] add more replaced values
	 * @todo    [feature] optional `wrap_in_wc_email_template()`
	 * @todo    [feature] separate content, subject etc. for each custom status
	 */
	function send_email_on_order_status_changed( $order_id, $status_from, $status_to, $order ) {
		$emails_statuses = get_option( 'alg_orders_custom_statuses_emails_statuses', array() );
		if ( in_array( 'wc-' . $status_to, $emails_statuses ) || ( empty( $emails_statuses ) && in_array( 'wc-' . $status_to, array_keys( alg_get_custom_order_statuses() ) ) ) ) {
			// Options
			$email_address = get_option( 'alg_orders_custom_statuses_emails_address', '' );
			$email_subject = get_option( 'alg_orders_custom_statuses_emails_subject',
				sprintf( __( '[%s] Order #%s status changed to %s - %s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' ) );
			$email_heading = get_option( 'alg_orders_custom_statuses_emails_heading',
				sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' ) );
			$email_content = get_option( 'alg_orders_custom_statuses_emails_content',
				sprintf( __( 'Order #%s status changed from %s to %s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' ) );
			// Replaced values
			$replaced_values = array(
				'{order_id}'        => $order_id,
				'{order_number}'    => $order->get_order_number(),
				'{order_date}'      => date( get_option( 'date_format' ), strtotime( $order->get_date_created() ) ),
				'{order_details}'   => ( false !== strpos( $email_content, '{order_details}' ) ? $this->get_wc_order_details_template( $order ) : '' ),
				'{site_title}'      => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
				'{status_from}'     => $status_from,
				'{status_to}'       => $status_to,
			);
			$email_replaced_values = array(
				'%customer%' => $order->get_billing_email(),
				'%admin%'    => get_option( 'admin_email' ),
			);
			// Final processing
			$email_address = ( '' == $email_address ? get_option( 'admin_email' ) : str_replace( array_keys( $email_replaced_values ), $email_replaced_values, $email_address ) );
			$email_subject = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_subject ) );
			$email_heading = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $email_heading ) );
			$email_content = do_shortcode( str_replace( array_keys( $replaced_values ), $replaced_values, $this->wrap_in_wc_email_template( $email_content, $email_heading ) ) );
			// Send mail
			wc_mail( $email_address, $email_subject, $email_content );
		}
	}

	/**
	 * get_wc_order_details_template.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function get_wc_order_details_template( $order ) {
		ob_start();
		wc_get_template(
			'emails/email-order-details.php', array(
				'order'         => $order,
				'sent_to_admin' => false,
				'plain_text'    => false,
				'email'         => '',
			)
		);
		return ob_get_clean();
	}

	/**
	 * wrap_in_wc_email_template.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function wrap_in_wc_email_template( $content, $email_heading = '' ) {
		return $this->get_wc_email_part( 'header', $email_heading ) . $content . $this->get_wc_email_part( 'footer' );
	}

	/**
	 * get_wc_email_part.
	 *
	 * @version 1.4.0
	 * @since   1.4.0
	 */
	function get_wc_email_part( $part, $email_heading = '' ) {
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
	 * add_custom_order_statuses_to_order_editable.
	 *
	 * @version 1.3.5
	 * @since   1.3.5
	 * @todo    [feature] separate option for each custom status
	 */
	function add_custom_order_statuses_to_order_editable( $is_editable, $_order ) {
		return ( in_array( 'wc-' . $_order->get_status(), array_keys( alg_get_custom_order_statuses() ) ) ? true : $is_editable );
	}

	/**
	 * add_custom_status_column_css.
	 *
	 * @version 1.3.3
	 * @since   1.3.2
	 */
	function add_custom_status_column_css() {
		$statuses = alg_get_custom_order_statuses();
		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			if ( '' != ( $icon_data = get_option( 'alg_orders_custom_status_icon_data_' . $custom_order_status, '' ) ) ) {
				$color      = $icon_data['color'];
				$text_color = ( isset( $icon_data['text_color'] ) ? $icon_data['text_color'] : '#000000' );
			} else {
				$color      = '#999999';
				$text_color = '#000000';
			}
			echo '<style>mark.order-status.status-' . $custom_order_status . ' { color: ' . $text_color . '; background-color: ' . $color . ' }</style>';
		}
	}

	/**
	 * add_custom_status_actions_buttons.
	 *
	 * @version 1.4.1
	 * @since   1.2.0
	 */
	function add_custom_status_actions_buttons( $actions, $_order ) {
		$statuses = alg_get_custom_order_statuses();
		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			if ( ! $_order->has_status( array( $custom_order_status ) ) ) { // if order status is not $custom_order_status
				$_order_id = ( version_compare( get_option( 'woocommerce_version', null ), '3.0.0', '<' ) ? $_order->id : $_order->get_id() );
				$actions[ $custom_order_status ] = array(
					'url'    => $this->get_custom_order_statuses_action_url( $custom_order_status, $_order_id ),
					'name'   => $label,
					'action' => "view " . $custom_order_status, // setting "view" for proper button CSS
				);
			}
		}
		return $actions;
	}

	/**
	 * add_custom_status_actions_buttons_css.
	 *
	 * @version 1.2.0
	 * @since   1.2.0
	 */
	function add_custom_status_actions_buttons_css() {
		$statuses = alg_get_custom_order_statuses();
		foreach ( $statuses as $slug => $label ) {
			$custom_order_status = substr( $slug, 3 );
			if ( '' != ( $icon_data = get_option( 'alg_orders_custom_status_icon_data_' . $custom_order_status, '' ) ) ) {
				$content = $icon_data['content'];
				$color   = $icon_data['color'];
			} else {
				$content = 'e011';
				$color   = '#999999';
			}
			$color_style = ( 'yes' === apply_filters( 'alg_orders_custom_statuses', 'no', 'value_order_list_actions_colored' ) ) ? ' color: ' . $color . ' !important;' : '';
			echo '<style>.view.' . $custom_order_status . '::after { font-family: WooCommerce !important;' . $color_style . ' content: "\\' . $content . '" !important; }</style>';
		}
	}

	/**
	 * add_custom_order_statuses_to_reports.
	 *
	 * @version 1.4.0
	 * @since   1.0.0
	 */
	function add_custom_order_statuses_to_reports( $order_statuses ) {
		if ( is_array( $order_statuses ) && in_array( 'completed', $order_statuses ) ) {
			return array_merge( $order_statuses, array_keys( alg_get_custom_order_statuses( true ) ) );
		}
		return $order_statuses;
	}

	/**
	 * set_default_order_status.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function set_default_order_status() {
		return get_option( 'alg_orders_custom_statuses_default_status', 'alg_disabled' );
	}

	/**
	 * set_default_order_status_bacs.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function set_default_order_status_bacs() {
		return get_option( 'alg_orders_custom_statuses_default_status_bacs', 'alg_disabled' );
	}

	/**
	 * set_default_order_status_cod.
	 *
	 * @version 1.4.4
	 * @since   1.4.4
	 */
	function set_default_order_status_cod() {
		return get_option( 'alg_orders_custom_statuses_default_status_cod', 'alg_disabled' );
	}

	/**
	 * register_custom_post_statuses.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function register_custom_post_statuses() {
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses();
		foreach ( $alg_orders_custom_statuses_array as $slug => $label )
			register_post_status( $slug, array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>' ),
			) );
	}

	/**
	 * add_custom_statuses_to_filter.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function add_custom_statuses_to_filter( $order_statuses ) {
		$alg_orders_custom_statuses_array = alg_get_custom_order_statuses();
		$order_statuses = ( '' == $order_statuses ) ? array() : $order_statuses;
		return array_merge( $order_statuses, $alg_orders_custom_statuses_array );
	}

	/**
	 * hook_statuses_icons_css.
	 *
	 * @version 1.2.0
	 * @since   1.0.0
	 */
	function hook_statuses_icons_css() {
		$output = '<style>';
		$statuses = alg_get_custom_order_statuses();
		foreach( $statuses as $status => $status_name ) {
			if ( '' != ( $icon_data = get_option( 'alg_orders_custom_status_icon_data_' . substr( $status, 3 ), '' ) ) ) {
				$content = $icon_data['content'];
				$color   = $icon_data['color'];
			} else {
				$content = 'e011';
				$color   = '#999999';
			}
			$output .= 'mark.' . substr( $status, 3 ) . '::after { content: "\\' . $content . '"; color: ' . $color . '; }';
			$output .= 'mark.' . substr( $status, 3 ) . ':after {font-family:WooCommerce;speak:none;font-weight:400;font-variant:normal;text-transform:none;line-height:1;-webkit-font-smoothing:antialiased;margin:0;text-indent:0;position:absolute;top:0;left:0;width:100%;height:100%;text-align:center}';
		}
		$output .= '</style>';
		echo $output;
	}

	/**
	 * register_order_custom_status_bulk_actions.
	 *
	 * @version 1.4.0
	 * @since   1.1.0
	 * @see     https://make.wordpress.org/core/2016/10/04/custom-bulk-actions/
	 */
	function register_order_custom_status_bulk_actions( $bulk_actions ) {
		$custom_order_statuses = alg_get_custom_order_statuses( true );
		foreach ( $custom_order_statuses as $slug => $label ) {
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
	function bulk_admin_footer() {
		global $post_type;
		if ( 'shop_order' == $post_type ) {
			?><script type="text/javascript"><?php
			foreach( alg_get_order_statuses() as $key => $order_status ) {
				if ( in_array( $key, array( 'processing', 'on-hold', 'completed', ) ) ) {
					continue;
				}
				?>jQuery(function() {
					jQuery('<option>').val('mark_<?php echo $key; ?>').text('<?php echo __( 'Mark', 'custom-order-statuses-woocommerce' ) . ' ' . $order_status; ?>').appendTo('select[name="action"]');
					jQuery('<option>').val('mark_<?php echo $key; ?>').text('<?php echo __( 'Mark', 'custom-order-statuses-woocommerce' ) . ' ' . $order_status; ?>').appendTo('select[name="action2"]');
				});<?php
			}
			?></script><?php
		}
	}

}

endif;

return new Alg_WC_Custom_Order_Statuses_Core();
