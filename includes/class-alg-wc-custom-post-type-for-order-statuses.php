<?php // phpcs:ignore
/**
 * Custom Order Statuses Importing Into Custom Post Type Class
 *
 * @version 1.4.0
 * @since   1.4.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Alg_WC_Custom_Post_Type_For_Order_Statuses' ) ) {

	/**
	 * Used for importing the the custom order status into Custom Post type.
	 */
	class Alg_WC_Custom_Post_Type_For_Order_Statuses {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'alg_add_cpt_menu' ), PHP_INT_MAX );
			add_action( 'admin_init', array( $this, 'alg_custom_order_script_status_check' ) );
			add_action( 'admin_notices', array( $this, 'alg_migration_admin_notice' ) );
			add_action( 'admin_notices', array( $this, 'alg_migration_success_admin_notice' ) );
			add_action( 'init', array( $this, 'alg_custom_post_registeration' ) );
			add_action( 'add_meta_boxes', array( $this, 'alg_register_meta_boxes' ) );
			add_action( 'save_post_custom_order_status', array( $this, 'alg_save_meta_box' ) );
			add_filter( 'manage_custom_order_status_posts_columns', array( $this, 'alg_custom_post_type_columns' ) );
			add_action( 'manage_custom_order_status_posts_custom_column', array( $this, 'alg_add_data_custom_post_type_columns' ), 10, 2 );
			add_filter( 'post_updated_messages', array( $this, 'alg_change_status_post_messages' ) );
			add_action( 'parent_file', array( $this, 'alg_make_menu_active' ) );
			add_action( 'admin_footer', array( $this, 'alg_status_name_check' ), 11 );
		}

		/**
		 * This function is used to check custom order slug length and trim it if length is greater than 17.
		 */
		public function alg_status_name_check() {
			if ( 'custom_order_status' === get_post_type() ) { // phpcs:ignore
				?>
				<script>
				function check_status_slug( elem ) {
					var text = jQuery(elem).val();
					if ( text.length > 17 ) {
						jQuery('.status_warning').remove();
						jQuery(elem).after( '<span class="status_warning" style="color: red; margin-left: 10px;">17 characters max</span>' );
						jQuery(elem).val( text.substring( 0, 17 ) );
					} else {
						jQuery('.status_warning').remove();
					}
				}
				</script>
				<?php
			}
		}

		/**
		 * This function is used for expanding WooCommerce menu while editing post of Custom Status.
		 *
		 * @name alg_make_menu_active
		 * @version 1.4.0
		 * @since   1.4.0
		 * @param string $parent_file - parent file of submenu.
		 * @author  Tyche Softwares
		 */
		public function alg_make_menu_active( $parent_file ) {
			global $post_type;
			if ( 'custom_order_status' === $post_type ) {
				$parent_file = 'woocommerce';
			}
			return $parent_file;
		}

		/**
		 * This function is used for the get all data of the order staus
		 *
		 * @name alg_change_status_post_messages
		 * @version 1.4.0
		 * @since   1.4.0
		 * @param array $messages - default array of messages for post.
		 * @author  Tyche Softwares
		 */
		public function alg_change_status_post_messages( $messages ) {
			global $post, $post_ID;
			$messages['custom_order_status'][1] = __( 'Custom status has been updated.', 'custom-order-statuses-for-woocommerce' );
			return $messages;
		}

		/**
		 * Function to add the CPT in WooCommerce menu.
		 */
		public function alg_add_cpt_menu() {
			$is_migrated                      = get_option( 'is_statuses_migrated' );
			$alg_orders_custom_statuses_array = alg_get_custom_order_statuses();
			if ( $is_migrated || empty( $alg_orders_custom_statuses_array ) ) {
				add_submenu_page(
					'woocommerce',
					'Custom Order Status',
					'Custom Order Status',
					'manage_options',
					'edit.php?post_type=custom_order_status'
				);
			}
		}

		/**
		 * Function to Add admin notice to Import the order status to Custom Order Status.
		 */
		public static function alg_migration_admin_notice() {
			global $current_screen;
			$ts_current_screen               = get_current_screen();
			$custom_order_statuses_no_prefix = alg_get_custom_order_statuses();
			$is_migrated                     = get_option( 'is_statuses_migrated' );

			// Return if custom statuses were already migrated.
			if ( ! empty( $is_migrated ) ) {
				return;
			}

			// Return if there is no custom order status registered.
			if ( empty( $custom_order_statuses_no_prefix ) ) {
				return;
			}

			// Return when we're on any edit screen, as notices are distracting in there.
			if ( ( method_exists( $ts_current_screen, 'is_block_editor' ) && $ts_current_screen->is_block_editor() ) || ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) ) {
				return;
			}
			?>
			<div class=''>
				<div class="cos-lite-message notice notice-info" style="position: relative;">
					<p style="margin: 10px 0 10px 10px; font-size: medium;">
						<?php
							echo esc_html_e( 'Custom Order Status Lite plugin has been updated to use custom post types. We highly recommend you to update the database so you can take advantage of the new features like setting a different email for each custom status.', 'custom-order-statuses-for-woocommerce' );
						?>
					</p>
					<p class="submit" style="margin: -10px 0 10px 10px;">
						<a class="button-primary button button-large" id="cos-lite-import" href="edit.php?post_type=custom_order_status&action=custom_post_type"><?php esc_html_e( 'Update Now', 'custom-order-statuses-for-woocommerce' ); ?></a>
					</p>
				</div>
			</div>
			<?php
		}

		/**
		 * Function to check status of the script
		 *
		 * @name alg_custom_order_script_status_check
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_custom_order_script_status_check() {
			if ( isset( $_REQUEST['action'] ) && 'custom_post_type' === $_REQUEST['action'] ) { // phpcs:ignore
				// Get all custom order statues.
				$statuses = function_exists( 'alg_get_custom_order_statuses' ) ? alg_get_custom_order_statuses() : array();

				// Check is status is not empty.
				if ( ! empty( $statuses ) && is_array( $statuses ) ) {
					foreach ( $statuses as $key => $value ) {
						// Check data is not empty.
						$custom_status = substr( $key, 3 );
						$data          = $this->get_data_order_status( $custom_status );
						if ( ! empty( $data ) ) {
							$this->alg_insert_custom_status( $value, $custom_status, $data );
						}
					}
				}
				update_option( 'is_statuses_migrated', 'yes' );
				wp_redirect( admin_url( 'edit.php?post_type=custom_order_status&imported=succesfully' ) ); // phpcs:ignore
				exit;

			}

			$is_statuses_migrated_to_slug = get_option( 'is_statuses_migrated_to_slug' );

			if ( ! $is_statuses_migrated_to_slug ) {
				// Get the order statues.
				$arg = array(
					'numberposts' => -1,
					'post_type'   => 'custom_order_status',
				);

				$custom_order_statuses = get_posts( $arg );
				if ( ! empty( $custom_order_statuses ) ) {
					foreach ( $custom_order_statuses as $post ) {
						$status_slug = get_post_meta( $post->ID, 'status_slug', true );
						if ( ! $status_slug ) {
							$post_status_slug = substr( get_post_field( 'post_name', $post->ID ), 0, 17 );
							$post_status_slug = rtrim( $post_status_slug, '-' );
							update_post_meta( $post->ID, 'status_slug', $post_status_slug );
						}
					}
				}
				update_option( 'is_statuses_migrated_to_slug', 'yes' );
			}

		}

		/**
		 * Function to show the success notice on import
		 */
		public function alg_migration_success_admin_notice() {
            if ( isset( $_GET['imported'] ) && 'succesfully' == $_GET['imported'] ) { // phpcs:ignore
				echo ( '<div class="updated"><p>' . wp_kses_post( 'All custom statuses has been successfully converted to custom post types. You can edit them from the list below & access them from the <a href="' . esc_url( 'edit.php?post_type=custom_order_status' ) . '">WooCommerce -> Custom Order Status page</a>', 'custom-order-statuses-woocommerce' ) . '</p></div>' );
			}
		}

		/**
		 * Function used for registering the custom post type.
		 *
		 * @name alg_custom_post_registeration
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_custom_post_registeration() {
			$labels = array(
				'name'               => _x( 'Custom Order Status', 'custom_order_status', 'custom-order-statuses-woocommerce' ),
				'singular_name'      => _x( 'Custom Order Status', 'post type singular name', 'custom-order-statuses-woocommerce' ),
				'menu_name'          => _x( 'Custom Order Status', 'admin menu', 'custom-order-statuses-woocommerce' ),
				'name_admin_bar'     => _x( 'Custom Order Status', 'add new on admin bar', 'custom-order-statuses-woocommerce' ),
				'add_new'            => _x( 'Add New', 'custom_order_status', 'custom-order-statuses-woocommerce' ),
				'add_new_item'       => __( 'Add New Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'new_item'           => __( 'New Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'edit_item'          => __( 'Edit Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'view_item'          => __( 'View Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'all_items'          => __( 'All Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'search_items'       => __( 'Search Custom Order Status', 'custom-order-statuses-woocommerce' ),
				'parent_item_colon'  => __( 'Parent Custom Order Status:', 'custom-order-statuses-woocommerce' ),
				'not_found'          => __( 'No Custom Order Status found.', 'custom-order-statuses-woocommerce' ),
				'not_found_in_trash' => __( 'No Custom Order Status found in Trash.', 'custom-order-statuses-woocommerce' ),
			);

			$args = array(
				'labels'             => $labels,
				'description'        => __( 'Description.', 'custom-order-statuses-woocommerce' ),
				'public'             => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'query_var'          => true,
				'rewrite'            => array( 'slug' => 'custom_order_status' ),
				'capability_type'    => 'post',
				'has_archive'        => true,
				'hierarchical'       => false,
				'menu_position'      => null,
				'supports'           => array( 'title' ),
			);

			register_post_type( 'custom_order_status', $args );

		}

		/**
		 * This function is used for the get all data of the order staus
		 *
		 * @name get_data_order_status
		 * @version 1.4.0
		 * @since   1.4.0
		 * @param string $slug  Slug of the custom order status.
		 * @author  Tyche Softwares
		 */
		public function get_data_order_status( $slug ) {
			$get_data_custom_status = get_option( 'alg_orders_custom_status_icon_data_' . $slug, array() );
			return $get_data_custom_status;
		}

		/**
		 * This function is used for inserting the post into the database.
		 *
		 * @name alg_insert_custom_status
		 * @param string $name  Name of the post.
		 * @param string $custom_slug Slug of the post.
		 * @param array  $data  Data that will inserted.
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_insert_custom_status( $name, $custom_slug, $data ) {
			global $wpdb;
			$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='slug' AND meta_value='%s' LIMIT 1", $custom_slug ) ); // phpcs:ignore
			if ( ! $post_id ) {
				$post    = array(
					'post_content' => '',
					'post_status'  => 'publish',
					'post_title'   => $name,
					'post_parent'  => '',
					'post_type'    => 'custom_order_status',
					'post_name'    => $custom_slug,
				);
				$post_id = wp_insert_post( $post, true );

				update_post_meta( $post_id, 'slug', $custom_slug );
				update_post_meta( $post_id, 'color', $data['color'] );
				update_post_meta( $post_id, 'content', $data['content'] );
				update_post_meta( $post_id, 'text_color', $data['text_color'] );
			}
		}

		/**
		 * Register meta box.
		 *
		 * @name alg_register_meta_boxes.
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_register_meta_boxes() {
			add_meta_box( 'custom_box', __( 'Status details', 'custom-order-statuses-woocommerce' ), array( $this, 'alg_my_display_callback' ), 'custom_order_status' );
			add_meta_box( 'custom_email_box', __( 'Email Settings', 'custom-order-statuses-woocommerce' ), array( $this, 'alg_email_setting_display_callback' ), 'custom_order_status' );
		}

		/**
		 * Meta box display callback.
		 *
		 * @param WP_Post $post Current post object.
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_my_display_callback( $post ) {
			if ( '' !== get_post_meta( $post->ID, 'status_slug', true ) ) {
				$slug_readonly = 'readonly';
			} else {
				$slug_readonly = '';
			}
			?>
				<table class="form-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Slug', 'custom-order-statuses-woocommerce' ); ?></th>
							<td><input required="" type="text" onkeyup="check_status_slug(this);" name="new_status_slug" value="<?php echo esc_attr( get_post_meta( $post->ID, 'status_slug', true ) ); ?>" <?php echo esc_attr( $slug_readonly ); ?>>
							<br><em><?php /* translators: $s: wc string */ printf( esc_attr__( '* Without %s prefix,', 'custom-order-statuses-woocommerce' ), '<code>wc-</code>' ); ?>
							<?php esc_html_e( '17 characters max.', 'custom-order-statuses-woocommerce' ); ?></em>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Icon Code', 'custom-order-statuses-woocommerce' ); ?></th>
							<td><input required="" type="text" name="new_status_icon_content" maxlength="4" pattern="[e]{1,1}[a-fA-F\d]{3,3}" value="<?php echo esc_attr( get_post_meta( $post->ID, 'content', true ) ); ?>">
							<br><em><?php esc_html_e( '* You can check icon codes', 'custom-order-statuses-woocommerce' ); ?> <a target="_blank" href="https://rawgit.com/woothemes/woocommerce-icons/master/demo.html"><?php esc_html_e( 'here', 'custom-order-statuses-woocommerce' ); ?></a></em>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Color', 'custom-order-statuses-woocommerce' ); ?></th>
							<td><input required="" type="color" name="new_status_icon_color" value="<?php echo esc_attr( get_post_meta( $post->ID, 'color', true ) ); ?>"></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Text Color', 'custom-order-statuses-woocommerce' ); ?></th>
							<td><input required="" type="color" name="new_status_text_color" value="<?php echo esc_attr( get_post_meta( $post->ID, 'text_color', true ) ); ?>"></td>
						</tr>
					</tbody>
				</table>
			<?php
			wp_nonce_field( 'custom_post_type_nonce', 'custom_nonce' );
		}

		/**
		 * Meta box display callback.
		 *
		 * @param WP_Post $post Current post object.
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function alg_email_setting_display_callback( $post ) {
			?>
			<b><i>Upgrade to <a href='https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=cosupgradetopro&utm_medium=link&utm_campaign=CustomOrderStatusLite' target="_blank">Custom Order Status for WooCommerce Pro</a> to enable this option.</i></b>
				<table class="form-table" style="opacity:0.5">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Emails', 'custom-order-statuses-woocommerce' ); ?></th>
							<td>
								<fieldset readonly>
									<legend class="screen-reader-text"><span>Emails</span></legend>
									<label for="alg_orders_custom_statuses_emails_enabled">
										<input name="alg_orders_custom_statuses_emails_enabled" id="alg_orders_custom_statuses_emails_enabled" type="checkbox" class="" disabled="disabled" checked="checked"  value="yes" <?php echo esc_attr( get_post_meta( $post->ID, 'alg_orders_custom_statuses_emails_enabled', true ) ) === 'yes' ? 'checked=checked' : ''; ?>> <strong><?php esc_html_e( 'Enable section', 'custom-order-statuses-woocommerce' ); ?></strong>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email address', 'custom-order-statuses-woocommerce' ); ?></th>
							<td>
								<input name="alg_orders_custom_statuses_emails_address" id="alg_orders_custom_statuses_emails_address" type="text" value="<?php echo esc_attr( get_post_meta( $post->ID, 'alg_orders_custom_statuses_emails_address', true ) ); ?>" class="large-text" placeholder="" readonly/> 
								<p class="description"><?php /* translators: $s: admin email */ printf( esc_attr__( 'Comma separated list of emails. Leave blank to send emails to admin (%s).', 'custom-order-statuses-woocommerce' ), esc_attr( get_option( 'admin_email' ) ) ); ?></p>
								<p class="description"><?php /* translators: 1$s: customer email, 2$s admin email */ printf( esc_attr__( 'Use %1$s to send email to the customer\'s billing email; %2$s to the admin\'s email.', 'custom-order-statuses-woocommerce' ), '<code>{customer_email}</code>', '<code>{admin_email}</code>' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email subject', 'custom-order-statuses-woocommerce' ); ?></th>
							<td>
								<?php
								/* translators: $s: default values for the field */
								$emails_replaced_values_desc               = sprintf( __( 'Replaced values: %s.', 'custom-order-statuses-woocommerce' ), '<code>' . implode( '</code>, <code>', array( '{order_id}', '{order_number}', '{order_date}', '{order_details}', '{first_name}', '{last_name}', '{site_title}', '{status_from}', '{status_to}' ) ) . '</code>' ) . ' ' . __( 'You can also use shortcodes here.', 'custom-order-statuses-woocommerce' );
								$alg_orders_custom_statuses_emails_subject = esc_attr( get_post_meta( $post->ID, 'alg_orders_custom_statuses_emails_subject', true ) );
								if ( ! $alg_orders_custom_statuses_emails_subject ) {
									/* translators: 1$s: site title, 2$s: order number, 3$s: status to, 4$s: order date */
									$alg_orders_custom_statuses_emails_subject = sprintf( __( '[%1$s] Order #%2$s status changed to %3$s - %4$s', 'custom-order-statuses-woocommerce' ), '{site_title}', '{order_number}', '{status_to}', '{order_date}' );
								}
								?>
								<input name="alg_orders_custom_statuses_emails_subject" id="alg_orders_custom_statuses_emails_subject" type="text" value="<?php echo esc_attr( $alg_orders_custom_statuses_emails_subject ); ?>" class="large-text" placeholder="" readonly/>
								<p class="description"><?php echo wp_kses_post( str_replace( ', <code>{order_details}</code>, <code>{first_name}</code>, <code>{last_name}</code>', '', $emails_replaced_values_desc ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email heading', 'custom-order-statuses-woocommerce' ); ?></th>
							<td>
								<?php
								/* translators: $s: default values for the field */
								$emails_replaced_values_desc               = sprintf( __( 'Replaced values: %s.', 'custom-order-statuses-woocommerce' ), '<code>' . implode( '</code>, <code>', array( '{order_id}', '{order_number}', '{order_date}', '{order_details}', '{first_name}', '{last_name}', '{site_title}', '{status_from}', '{status_to}' ) ) . '</code>' ) . ' ' . __( 'You can also use shortcodes here.', 'custom-order-statuses-woocommerce' );
								$alg_orders_custom_statuses_emails_heading = get_post_meta( $post->ID, 'alg_orders_custom_statuses_emails_heading', true );
								if ( ! $alg_orders_custom_statuses_emails_heading ) {
									/* translators: $s: status to */
									$alg_orders_custom_statuses_emails_heading = sprintf( __( 'Order status changed to %s', 'custom-order-statuses-woocommerce' ), '{status_to}' );
								}
								?>
								<input name="alg_orders_custom_statuses_emails_heading" id="alg_orders_custom_statuses_emails_heading" type="text" value="<?php echo esc_attr( $alg_orders_custom_statuses_emails_heading ); ?>" class="large-text" placeholder="" readonly/>
								<p class="description"><?php echo wp_kses_post( str_replace( ', <code>{order_details}</code>, <code>{first_name}</code>, <code>{last_name}</code>', '', $emails_replaced_values_desc ) ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Email content', 'custom-order-statuses-woocommerce' ); ?></th>
							<td>
								<?php
								/* translators: 1$s: default value of this field */
								$emails_replaced_values_desc               = sprintf( __( 'Replaced values: %s.', 'custom-order-statuses-woocommerce' ), '<code>' . implode( '</code>, <code>', array( '{order_id}', '{order_number}', '{order_date}', '{order_details}', '{first_name}', '{last_name}', '{site_title}', '{status_from}', '{status_to}' ) ) . '</code>' ) . ' ' . __( 'You can also use shortcodes here.', 'custom-order-statuses-woocommerce' );
								$alg_orders_custom_statuses_emails_content = get_post_meta( $post->ID, 'alg_orders_custom_statuses_emails_content', true );
								if ( ! $alg_orders_custom_statuses_emails_content ) {
									/* translators: 1$s: order number, 2$s: status from, 3$s: status to */
									$alg_orders_custom_statuses_emails_content = sprintf( __( 'Order #%1$s status changed from %2$s to %3$s', 'custom-order-statuses-woocommerce' ), '{order_number}', '{status_from}', '{status_to}' );
								}
								?>
								<textarea name="alg_orders_custom_statuses_emails_content" id="alg_orders_custom_statuses_emails_content" type="text" class="large-text" placeholder="" rows="10" readonly><?php echo esc_attr( $alg_orders_custom_statuses_emails_content ); ?></textarea>
								<p class="description"><?php echo wp_kses_post( '<em>' . $emails_replaced_values_desc . '</em>' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			<?php
			wp_nonce_field( 'custom_post_type_nonce', 'custom_nonce' );
		}

		/**
		 * Save meta box content.
		 *
		 * @param int $post_id Post id of the custom order status.
		 * @version 1.4.0
		 * @since   1.4.0
		 */
		public function alg_save_meta_box( $post_id ) {
			$_nonce = isset( $_POST['custom_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_nonce'] ) ) : '';
			if ( wp_verify_nonce( $_nonce, 'custom_post_type_nonce' ) ) {
				$new_status_slug = ( isset( $_POST['new_status_slug'] ) && ! empty( $_POST['new_status_slug'] ) ) ? sanitize_key( wp_unslash( $_POST['new_status_slug'] ) ) : '';
				if ( empty( $new_status_slug ) ) {
					$new_status_slug = substr( get_post_field( 'post_name', $post_id ), 0, 17 );
				}
				$_icon_content = ( isset( $_POST['new_status_icon_content'] ) && ! empty( $_POST['new_status_icon_content'] ) ) ? sanitize_text_field( wp_unslash( $_POST['new_status_icon_content'] ) ) : 'e011';
				$_text_color   = ( isset( $_POST['new_status_text_color'] ) && ! empty( $_POST['new_status_text_color'] ) ) ? sanitize_text_field( wp_unslash( $_POST['new_status_text_color'] ) ) : '#000000';
				$_color        = ( isset( $_POST['new_status_icon_color'] ) && ! empty( $_POST['new_status_icon_color'] ) ) ? sanitize_text_field( wp_unslash( $_POST['new_status_icon_color'] ) ) : '#999999';
				update_post_meta( $post_id, 'status_slug', $new_status_slug );
				update_post_meta( $post_id, 'color', $_color );
				update_post_meta( $post_id, 'content', $_icon_content );
				update_post_meta( $post_id, 'text_color', $_text_color );
			}
		}

		/**
		 * Function to add columns in CPT table.
		 *
		 * @param array $columns Array of the columns in the CPT table.
		 */
		public static function alg_custom_post_type_columns( $columns ) {
			return array(
				'cb'         => '<input type="checkbox" />',
				'title'      => __( 'Title', 'custom-order-statuses-woocommerce' ),
				'icon_code'  => __( 'Icon Code', 'custom-order-statuses-woocommerce' ),
				'color'      => __( 'Color', 'custom-order-statuses-woocommerce' ),
				'text_color' => __( 'Text Color', 'custom-order-statuses-woocommerce' ),
				'date'       => __( 'Date', 'custom-order-statuses-woocommerce' ),
			);
		}

		/**
		 * Function to add the data in the newly added column in CPT table.
		 *
		 * @param string $column Name of column in the CPT table.
		 * @param int    $post_id Individual Post ID of the custom status.
		 */
		public static function alg_add_data_custom_post_type_columns( $column, $post_id ) {
			switch ( $column ) {
				case 'icon_code':
					$icon_code = get_post_meta( $post_id, 'content', true );
					echo esc_attr( $icon_code );
					break;
				case 'color':
					$_color = get_post_meta( $post_id, 'color', true );
					$color  = '<input disabled type="color" value="' . $_color . '">';
					echo ( $color ); // phpcs:ignore
					break;
				case 'text_color':
					$_text_color = get_post_meta( $post_id, 'text_color', true );
					$text_color  = '<input disabled type="color" value="' . $_text_color . '">';
					echo ( $text_color ); // phpcs:ignore
					break;
			}
		}
	}
}

return new Alg_WC_Custom_Post_Type_For_Order_Statuses();
