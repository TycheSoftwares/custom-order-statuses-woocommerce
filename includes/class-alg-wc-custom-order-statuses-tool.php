<?php
/**
 * Custom Order Statuses for WooCommerce - Tool Class
 *
 * @version 1.4.0
 * @since   1.2.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses-Lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Order_Statuses_Tool' ) ) :

	/**
	 * Custom Order Statuses Tool Class.
	 */
	class Alg_WC_Custom_Order_Statuses_Tool {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.2.0
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'add_tool' ), PHP_INT_MAX );
		}

		/**
		 * Add menu item
		 *
		 * @version 1.3.0
		 * @since   1.0.0
		 * @todo    [dev] replace this with "Tool" settings section
		 */
		public function add_tool() {
			add_submenu_page(
				'woocommerce',
				__( 'Custom Order Status', 'custom-order-statuses-woocommerce' ),
				__( 'Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'manage_woocommerce',
				'alg-custom-order-statuses-tool',
				array( $this, 'create_custom_statuses_tool' )
			);
			$alg_orders_custom_statuses_array = alg_get_custom_order_statuses();
			$is_migrated                      = get_option( 'is_statuses_migrated' );
			if ( $is_migrated || empty( $alg_orders_custom_statuses_array ) ) {
				remove_submenu_page( 'woocommerce', 'alg-custom-order-statuses-tool' );
			}
		}

		/**
		 * Maybe_execute_actions.
		 *
		 * @version 1.3.3
		 * @since   1.3.0
		 * @todo    [dev] add `wp_safe_redirect`
		 */
		public function maybe_execute_actions() {
			$result_message = '';
			if ( isset( $_POST['alg_add_custom_status'], $_POST['new_status'], $_POST['new_status_label'], $_POST['new_status_icon_content'], $_POST['new_status_icon_color'], $_POST['new_status_text_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$result_message = $this->add_custom_status(
					sanitize_key( wp_unslash( $_POST['new_status'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_label'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_icon_content'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_icon_color'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_text_color'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
				);
			} elseif ( isset( $_POST['alg_edit_custom_status'], $_POST['new_status'], $_POST['new_status_label'], $_POST['new_status_icon_content'], $_POST['new_status_icon_color'], $_POST['new_status_text_color'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$result_message = $this->edit_custom_status(
					sanitize_key( wp_unslash( $_POST['new_status'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_label'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_icon_content'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_icon_color'] ) ), // phpcs:ignore WordPress.Security.NonceVerification
					sanitize_text_field( wp_unslash( $_POST['new_status_text_color'] ) ) // phpcs:ignore WordPress.Security.NonceVerification
				);
			} elseif ( isset( $_GET['delete'] ) && ( '' !== $_GET['delete'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$result_message = $this->delete_custom_status( sanitize_text_field( wp_unslash( $_GET['delete'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
			echo wp_kses_post( $result_message );
		}

		/**
		 * Get_status_table_html.
		 *
		 * @version 1.4.0
		 * @since   1.3.0
		 */
		public function get_status_table_html() {
			$table_data       = array();
			$table_data[]     = array(
				__( 'Slug', 'custom-order-statuses-woocommerce' ),
				__( 'Label', 'custom-order-statuses-woocommerce' ),
				__( 'Icon Code', 'custom-order-statuses-woocommerce' ),
				__( 'Color', 'custom-order-statuses-woocommerce' ),
				__( 'Text Color', 'custom-order-statuses-woocommerce' ),
				__( 'Actions', 'custom-order-statuses-woocommerce' ),
			);
			$statuses         = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
			$default_statuses = $this->get_default_order_statuses();
			$plugin_statuses  = alg_get_custom_order_statuses();
			foreach ( $statuses as $status => $status_name ) {
				if ( array_key_exists( $status, $default_statuses ) || ! array_key_exists( $status, $plugin_statuses ) ) {
					$icon_and_actions = array( '', '', '', '' );
				} else {
					$icon_data = get_option( 'alg_orders_custom_status_icon_data_' . substr( $status, 3 ), '' );
					if ( '' !== $icon_data ) {
						$content    = $icon_data['content'];
						$color      = $icon_data['color'];
						$text_color = ( isset( $icon_data['text_color'] ) ? $icon_data['text_color'] : '#000000' );
					} else {
						$content    = 'e011';
						$color      = '#999999';
						$text_color = '#000000';
					}
					$fallback_status_without_wc_prefix  = get_option( 'alg_orders_custom_statuses_fallback_delete_status', 'on-hold' );
					$delete_button_ending               = ' href="' . add_query_arg( 'delete', $status, remove_query_arg( array( 'edit', 'fallback' ) ) ) .
					'" onclick="return confirm(\'' . __( 'Are you sure?', 'custom-order-statuses-woocommerce' ) . '\')">';
					$delete_with_fallback_button_ending = ( substr( $status, 3 ) !== $fallback_status_without_wc_prefix ?
					' href="' . add_query_arg(
						array(
							'delete'   => $status,
							'fallback' => 'yes',
						),
						remove_query_arg( 'edit' )
					) .
					'" onclick="return confirm(\'' . __( 'Are you sure?', 'custom-order-statuses-woocommerce' ) . '\')" title="' .
					sprintf(
						// translators: New status.
						__( 'Status for orders with this status will be changed to \'%s\'.' ),
						$this->get_status_title( 'wc-' . $fallback_status_without_wc_prefix )
					)
					. '">'
					:
					' disabled title="' .
					__( 'This status can not be deleted as it\'s set to be the fallback status. Change \'Fallback Delete Order Status\' to some other value in plugin\'s settings to delete this status.', 'custom-order-statuses-woocommerce' )
					. '">'
					);
					$edit_button_ending          = ' href="' . add_query_arg( 'edit', $status, remove_query_arg( array( 'delete', 'fallback' ) ) ) . '">';
					$delete_button               = '<a class="button-primary"' . $delete_button_ending . __( 'Delete', 'custom-order-statuses-woocommerce' ) . '</a>';
					$delete_with_fallback_button = '<a class="button-primary"' . $delete_with_fallback_button_ending . __( 'Delete with fallback', 'custom-order-statuses-woocommerce' ) . '</a>';
					$edit_button                 = '<a class="button-primary"' . $edit_button_ending . __( 'Edit', 'custom-order-statuses-woocommerce' ) . '</a>';
					$icon_and_actions            = array(
						$content,
						'<input disabled type="color" value="' . $color . '">',
						'<input disabled type="color" value="' . $text_color . '">',
						$delete_button . ' ' . $delete_with_fallback_button . ' ' . $edit_button,
					);
				}
				$table_data[] = array_merge( array( esc_attr( $status ), esc_html( $status_name ) ), $icon_and_actions );
			}
			return '<h2>' . __( 'Status Table', 'custom-order-statuses-woocommerce' ) . '</h2>' .
			alg_get_table_html( $table_data, array( 'table_class' => 'wc_status_table widefat striped' ) ) .
			'<p style="font-style:italic;">* ' . sprintf(
				// translators: plugin settings link.
				__( '"Delete with fallback" button will delete custom status and change status for every order with that status to "fallback status". Fallback status can be set in <a href="%s">plugin\'s general settings</a>. Please note - if you have large number of orders this may take longer.', 'custom-order-statuses-woocommerce' ),
				admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_statuses' )
			) .
				'</p>';
		}

		/**
		 * Get_actions_box_html.
		 *
		 * @version 1.4.0
		 * @since   1.3.0
		 * @todo    [dev] wp_nonce_url / wp_create_nonce / wp_verify_nonce - https://codex.wordpress.org/Function_Reference/wp_create_nonce
		 * @todo    [feature] "delete all custom statuses" and "delete all custom statuses with fallback" button
		 */
		public function get_actions_box_html() {
			$is_editing = ( isset( $_GET['edit'] ) ) ? true : false; // phpcs:ignore WordPress.Security.NonceVerification
			if ( $is_editing ) {
				$edit_slug             = sanitize_text_field( wp_unslash( $_GET['edit'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
				$custom_order_statuses = alg_get_custom_order_statuses();
				$edit_label            = isset( $custom_order_statuses[ $edit_slug ] ) ? $custom_order_statuses[ $edit_slug ] : '';
				$edit_icon_data        = get_option( 'alg_orders_custom_status_icon_data_' . substr( $edit_slug, 3 ), '' );
				if ( '' !== $edit_icon_data ) {
					$edit_content    = $edit_icon_data['content'];
					$edit_color      = $edit_icon_data['color'];
					$edit_text_color = ( isset( $edit_icon_data['text_color'] ) ? $edit_icon_data['text_color'] : '#000000' );
				} else {
					$edit_content    = 'e011';
					$edit_color      = '#999999';
					$edit_text_color = '#000000';
				}
			}
			$title            = ( $is_editing ? __( 'Edit', 'custom-order-statuses-woocommerce' ) : __( 'Add', 'custom-order-statuses-woocommerce' ) );
			$slug_input       = '<input required type="text" name="new_status" maxlength="17" style="width:100%;"' .
			( $is_editing ? ' value="' . substr( $edit_slug, 3 ) . '" readonly' : '' ) . '>';
			$label_input      = '<input required type="text" name="new_status_label" style="width:100%;"' . ( $is_editing ? ' value="' . $edit_label . '"' : '' ) . '>';
			$icon_input       = '<input required type="text" name="new_status_icon_content" maxlength="4" pattern="[e]{1,1}[a-fA-F\d]{3,3}" value="' .
			( $is_editing ? $edit_content : 'e011' ) . '">';
			$icon_color_input = '<input required type="color" name="new_status_icon_color" value="' . ( $is_editing ? $edit_color : '#999999' ) . '">';
			$text_color_input = '<input required type="color" name="new_status_text_color" value="' . ( $is_editing ? $edit_text_color : '#000000' ) . '">';
			$icon_desc        = sprintf(
				// translators: Icon page Link.
				'* ' . __( 'You can check icon codes <a target="_blank" href="%s">here</a>', 'custom-order-statuses-woocommerce' ),
				'https://rawgit.com/woothemes/woocommerce-icons/master/demo.html'
			);
			// translators: WC status prefix.
			$slug_desc       = '* ' . sprintf( __( 'Without %s prefix', 'custom-order-statuses-woocommerce' ), '<code>wc-</code>' );
			$icon_input     .= "<br><em>$icon_desc</em>";
			$slug_input     .= "<br><em>$slug_desc</em>";
			$add_edit_button = '<input class="button-primary" type="submit" ' .
				'name="' . ( $is_editing ? 'alg_edit_custom_status' : 'alg_add_custom_status' ) . '" ' .
				'value="' . ( $is_editing ? __( 'Edit custom status', 'custom-order-statuses-woocommerce' ) : __( 'Add new custom status', 'custom-order-statuses-woocommerce' ) ) .
				'">';
			$clear_button    = ( $is_editing ?
				'<a href="' . remove_query_arg( array( 'delete', 'fallback', 'edit' ) ) . '">' . __( 'Clear form', 'custom-order-statuses-woocommerce' ) . '</a>' : '' );

			$table_data = array(
				array( __( 'Slug', 'custom-order-statuses-woocommerce' ), $slug_input ),
				array( __( 'Label', 'custom-order-statuses-woocommerce' ), $label_input ),
				array( __( 'Icon Code', 'custom-order-statuses-woocommerce' ), $icon_input ),
				array( __( 'Color', 'custom-order-statuses-woocommerce' ), $icon_color_input ),
				array( __( 'Text Color', 'custom-order-statuses-woocommerce' ), $text_color_input ),
				array( $add_edit_button, '' ),
			);
			if ( '' !== $clear_button ) {
				$table_data[] = array( $clear_button, '' );
			}
			return '<form method="post" action="' . remove_query_arg( array( 'delete', 'fallback' ) ) . '">' .
			'<h2>' . $title . ' ' . __( 'Status', 'custom-order-statuses-woocommerce' ) . '</h2>' .
			alg_get_table_html(
				$table_data,
				array(
					'table_style'        => 'width:50%;min-width:300px;',
					'table_class'        => 'widefat striped',
					'table_heading_type' => 'vertical',
				)
			) .
			'</form>';
		}

		/**
		 * Create_custom_statuses_tool.
		 *
		 * @version 1.4.0
		 * @since   1.0.0
		 */
		public function create_custom_statuses_tool() {
			$html = '';
			?>
			<div class="wrap">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=alg_wc_custom_order_statuses' ) ); ?>">
			<?php esc_html_e( '<<Plugin settings', 'custom-order-statuses-woocommerce' ); ?></a></p>
			<h1><?php esc_html_e( 'Custom Order Status Tool', 'custom-order-statuses-woocommerce' ); ?></h1>
			<p><?php esc_html_e( 'The tool lets you add or delete any WooCommerce orders custom status.', 'custom-order-statuses-woocommerce' ); ?></p>
			<?php
			$this->maybe_execute_actions();
			$html .= $this->get_actions_box_html();
			$html .= $this->get_status_table_html();
			echo $html;
			?>
			</div>

			<?php
		}

		/**
		 * Add new custom status to alg_orders_custom_statuses_array.
		 *
		 * @param string $new_status - New status code.
		 * @param string $new_status_label - New status label.
		 * @param string $new_status_icon_content - New status icon code.
		 * @param string $new_status_icon_color - New status icon color.
		 * @param string $new_status_text_color - New status text color.
		 *
		 * @version 1.3.3
		 * @since   1.0.0
		 */
		public function add_custom_status( $new_status, $new_status_label, $new_status_icon_content, $new_status_icon_color, $new_status_text_color ) {

			// Checking function arguments.
			if ( '' === $new_status ) {
				return '<div class="error"><p>' . __( 'Status slug is empty. Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			} else {
				global $wpdb;
				$terms_list = $wpdb->get_col( 'SELECT DISTINCT slug FROM `' . $wpdb->prefix . 'terms`' ); //phpcs:ignore
				if ( is_array( $terms_list ) && in_array( $new_status, $terms_list, true ) ) {
					return '<div class="error"><p>' . __( 'Status slug is already present. Please use another slug name.', 'custom-order-statuses-woocommerce' ) . '</p></div>';
				}
			}
			if ( strlen( $new_status ) > 17 ) {
				return '<div class="error"><p>' . __( 'The length of status slug must be 17 or less characters. Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			}
			if ( ! isset( $new_status_label ) || '' === $new_status_label ) {
				return '<div class="error"><p>' . __( 'Status label is empty. Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			}

			// Checking status.
			$statuses_updated = alg_get_custom_order_statuses();
			$new_key          = 'wc-' . $new_status;
			if ( isset( $statuses_updated[ $new_key ] ) ) {
				return '<div class="error"><p>' . __( 'Duplicate slug. Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			}
			$default_statuses = $this->get_default_order_statuses();
			if ( isset( $default_statuses[ $new_key ] ) ) {
				return '<div class="error"><p>' . __( 'Duplicate slug (default WooCommerce status). Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			}
			$statuses_updated[ $new_key ] = $new_status_label;

			// Adding custom status.
			$result = update_option( 'alg_orders_custom_statuses_array', $statuses_updated );
			$result = update_option(
				'alg_orders_custom_status_icon_data_' . $new_status,
				array(
					'content'    => $new_status_icon_content,
					'color'      => $new_status_icon_color,
					'text_color' => $new_status_text_color,
				)
			);
			return ( true === $result ) ?
				'<div class="updated"><p>' . __( 'New status has been successfully added!', 'custom-order-statuses-woocommerce' ) . '</p></div>' :
				'<div class="error"><p>' . __( 'Status was not added!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
		}

		/**
		 * Edit_custom_status.
		 *
		 * @param string $new_status - Edit status code.
		 * @param string $new_status_label - Edit status label.
		 * @param string $new_status_icon_content - Edit status icon code.
		 * @param string $new_status_icon_color - Edit status icon color.
		 * @param string $new_status_text_color - Edit status text color.
		 *
		 * @version 1.3.3
		 * @since   1.2.0
		 * @todo    [feature] make slug editable (and if slug is changed - change all orders to new status)
		 */
		public function edit_custom_status( $new_status, $new_status_label, $new_status_icon_content, $new_status_icon_color, $new_status_text_color ) {
			if ( '' === $new_status_label ) {
				$result_message = '<div class="error"><p>' . __( 'Status label is empty. Status was not edited!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			} else {
				$statuses_updated                        = alg_get_custom_order_statuses();
				$statuses_updated[ 'wc-' . $new_status ] = $new_status_label;
				$result                                  = update_option( 'alg_orders_custom_statuses_array', $statuses_updated );
				$result_icon_data                        = update_option(
					'alg_orders_custom_status_icon_data_' . $new_status,
					array(
						'content'    => $new_status_icon_content,
						'color'      => $new_status_icon_color,
						'text_color' => $new_status_text_color,
					)
				);
				if ( $result || $result_icon_data ) {
					$result_message = '<div class="updated"><p>' . __( 'Status has been successfully edited!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
				} else {
					$result_message = '<div class="error"><p>' . __( 'Status was not edited!', 'custom-order-statuses-woocommerce' ) . '</p></div>';
				}
			}
			return $result_message;
		}

		/**
		 * Delete_custom_status.
		 *
		 * @param string $delete_status - Slug of the status being deleted.
		 *
		 * @version 1.3.0
		 * @since   1.2.0
		 * @todo    [dev] check (e.g. temporary remove) emails (and possibly other triggers) on fallback status
		 * @todo    [dev] fix "Order Notes"
		 */
		public function delete_custom_status( $delete_status ) {
			// Statuses data.
			$statuses_updated = alg_get_custom_order_statuses();
			if ( isset( $statuses_updated[ $delete_status ] ) ) {
				// Fallback.
				$new_status_without_wc_prefix = get_option( 'alg_orders_custom_statuses_fallback_delete_status', 'on-hold' );
				if ( isset( $_GET['fallback'] ) && 'alg_none' !== $new_status_without_wc_prefix ) { // phpcs:ignore WordPress.Security.NonceVerification
					$total_orders_changed = $this->change_orders_status( $delete_status, $new_status_without_wc_prefix );
				} else {
					$total_orders_changed = 0;
				}
				// Delete status.
				unset( $statuses_updated[ $delete_status ] );
				$result = update_option( 'alg_orders_custom_statuses_array', $statuses_updated );
				// Delete icon data.
				$result_icon_data = delete_option( 'alg_orders_custom_status_icon_data_' . substr( $delete_status, 3 ) );
				// Result message.
				if ( true === $result && true === $result_icon_data ) {
					$result_message = '<div class="updated"><p>' . __( 'Status has been successfully deleted.', 'custom-order-statuses-woocommerce' ) . '</p></div>';
					if ( $total_orders_changed > 0 ) {
						// translators: number of orders for which status has been changed.
						$result_message .= '<div class="updated"><p>' . sprintf( __( 'Status has been changed for %d orders.', 'custom-order-statuses-woocommerce' ), $total_orders_changed ) . '</p></div>';
					}
				} else {
					$result_message = '<div class="error"><p>' . __( 'Delete failed.', 'custom-order-statuses-woocommerce' ) . '</p></div>';
				}
			} else {
				$result_message = '<div class="error"><p>' . __( 'Delete failed (status not found).', 'custom-order-statuses-woocommerce' ) . '</p></div>';
			}
			return $result_message;
		}

		/**
		 * Change_orders_status.
		 *
		 * @param string $old_status - Old custom status.
		 * @param string $new_status_without_wc_prefix - New status.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		public function change_orders_status( $old_status, $new_status_without_wc_prefix ) {
			$total_orders_changed = 0;
			$offset               = 0;
			$block_size           = 1024;
			while ( true ) {
				$args_orders = array(
					'post_type'      => 'shop_order',
					'post_status'    => $old_status,
					'posts_per_page' => $block_size,
					'offset'         => $offset,
					'fields'         => 'ids',
				);
				$loop_orders = new WP_Query( $args_orders );
				if ( ! $loop_orders->have_posts() ) {
					break;
				}
				foreach ( $loop_orders->posts as $order_id ) {
					$order = wc_get_order( $order_id );
					$order->update_status( $new_status_without_wc_prefix );
					$total_orders_changed++;
				}
				$offset += $block_size;
			}
			return $total_orders_changed;
		}

		/**
		 * Get_status_title.
		 *
		 * @param string $slug - Custom status slug.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		public function get_status_title( $slug ) {
			$statuses = $this->get_all_order_statuses();
			return ( isset( $statuses[ $slug ] ) ) ? $statuses[ $slug ] : '';
		}

		/**
		 * Get_all_order_statuses.
		 *
		 * @version 1.2.0
		 * @since   1.2.0
		 */
		public function get_all_order_statuses() {
			return array_merge( $this->get_default_order_statuses(), alg_get_custom_order_statuses() );
		}

		/**
		 * Get_default_order_statuses.
		 *
		 * @version 1.0.0
		 * @since   1.0.0
		 */
		public function get_default_order_statuses() {
			return array(
				'wc-pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
				'wc-processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
				'wc-on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
				'wc-completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
				'wc-cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
				'wc-refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
				'wc-failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
			);
		}

	}

endif;

return new Alg_WC_Custom_Order_Statuses_Tool();
