<?php // phpcs:ignore
/**
 * Custom Order Statuses Importing Into Custom Post Type Class
 *
 * @version 1.4.0
 * @since   1.4.0
 * @author  Tyche Softwares
 * @package Custom-Order-Statuses
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( __NAMESPACE__ . '\\CPT' ) ) {

	/**
	 * Used for importing the the custom order status into Custom Post type.
	 */
	class CPT {

		/**
		 * Constructor.
		 *
		 * @version 1.4.0
		 * @since   1.4.0
		 * @author  Tyche Softwares
		 */
		public function __construct() {
			add_action( 'admin_init', array( $this, 'cos_custom_order_script_status_check' ) );
			add_action( 'admin_notices', array( $this, 'cos_migration_admin_notice' ) );
			add_action( 'admin_notices', array( $this, 'cos_migration_success_admin_notice' ) );
			add_action( 'init', array( $this, 'cos_custom_post_registeration' ) );
			add_action( 'admin_init', array( $this, 'cos_update_status_slug_if_empty' ) );
		}

		/**
		 * Function to Add admin notice to Import the order status to Custom Order Status.
		 */
		public static function cos_migration_admin_notice() {
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
				<div class="cos-pro-message notice notice-info" style="position: relative;">
					<p style="margin: 10px 0 10px 10px; font-size: medium;">
						<?php
							echo esc_html_e( 'Custom Order Status plugin has been updated to use custom post types. We highly recommend you to update the database so you can take advantage of the new features like setting a different email for each custom status.', 'custom-order-statuses-woocommerce' );
						?>
					</p>
					<p class="submit" style="margin: -10px 0 10px 10px;">
						<a class="button-primary button button-large" id="cos-pro-import" href="edit.php?post_type=custom_order_status&action=custom_post_type"><?php esc_html_e( 'Update Now', 'custom-order-statuses-woocommerce' ); ?></a>
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
		public function cos_custom_order_script_status_check() {
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
		public function cos_migration_success_admin_notice() {
            if ( isset( $_GET['imported'] ) && 'succesfully' == $_GET['imported'] ) { // phpcs:ignore
				echo ( '<div class="updated"><p>' . wp_kses_post( 'All custom statuses has been successfully converted to custom post types. You can edit them from the list below & access them from the <a href="' . esc_url( 'edit.php?post_type=custom_order_status' ) . '">WooCommerce -> Custom Order Status page</a>', 'custom-order-statuses-woocommerce' ) . '</p></div>' );
			}
		}

		/**
		 * Function to update the slug field of the custom order status if it is empty.
		 */
		public function cos_update_status_slug_if_empty() {
			$is_migrated         = get_option( 'is_statuses_migrated' );
			$no_empty_slug_field = get_option( 'alg_custom_order_status_no_empty_slug_field' );
			if ( $is_migrated && 'true' !== $no_empty_slug_field ) {
				$arg                   = array(
					'numberposts' => -1,
					'post_type'   => 'custom_order_status',
				);
				$custom_order_statuses = get_posts( $arg );
				if ( ! empty( $custom_order_statuses ) ) {
					foreach ( $custom_order_statuses as $post ) {
						$status_slug = get_post_meta( $post->ID, 'status_slug', true );
						if ( ! $status_slug || '' === $status_slug ) {
							$post_status_slug = get_post_meta( $post->ID, 'slug', true );
							if ( ! $post_status_slug || '' === $post_status_slug ) {
								$post_status_slug = substr( get_post_field( 'post_name', $post->ID ), 0, 17 );
							}
							update_post_meta( $post->ID, 'status_slug', $post_status_slug );
						}
					}
					update_option( 'alg_custom_order_status_no_empty_slug_field', 'true' );
				}
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
		public function cos_custom_post_registeration() {
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
		public function cos_insert_custom_status( $name, $custom_slug, $data ) {
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
	}
}

return new CPT();
