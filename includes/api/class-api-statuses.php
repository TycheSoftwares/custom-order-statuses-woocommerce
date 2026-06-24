<?php
/**
 * REST API controller for Custom Order Statuses CPT.
 *
 * Reads and writes the custom_order_status CPT posts and their meta.
 * Saving still goes through WordPress CPT — we just provide the REST
 * interface so the React screen can list, create, update and trash them.
 *
 * CPT meta key reference (from class-alg-wc-custom-post-type-for-order-statuses.php):
 *   post_title                                   → title
 *   status_slug                                  → slug (e.g. wc-new-arrivals)
 *   content                                      → icon_code (hex e.g. e02c)
 *   color                                        → background color (#rrggbb)
 *   text_color                                   → text color (#rrggbb)
 *   reduce_stock                                 → '' | 'increase' | 'decrease'
 *   alg_orders_individual_custom_status_enable_paid    → 'yes'|''
 *   alg_orders_individual_custom_status_user_cancel    → 'yes'|''
 *   alg_orders_custom_statuses_emails_enabled    → 'yes'|''
 *   alg_orders_custom_statuses_emails_address    → string
 *   alg_orders_custom_statuses_bcc_emails_address → string
 *   alg_orders_custom_statuses_emails_subject    → string
 *   alg_orders_custom_statuses_emails_heading    → string
 *   alg_orders_custom_statuses_emails_content    → string
 *   alg_orders_custom_statuses_sms_enabled       → 'yes'|''
 *   alg_orders_custom_statuses_sms_content       → string
 *
 * @package Custom_Order_Status
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

defined( 'ABSPATH' ) || exit;

class Api_Statuses extends Api_Base {

    public function register_routes(): void {
        // GET  /cos-pro/v1/statuses        — list all
        // POST /cos-pro/v1/statuses        — create new
        register_rest_route( $this->namespace, '/statuses', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_statuses' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_status' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );

        // GET    /cos-pro/v1/statuses/{id}  — single
        // PUT    /cos-pro/v1/statuses/{id}  — update
        // DELETE /cos-pro/v1/statuses/{id}  — trash
        register_rest_route( $this->namespace, '/statuses/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_status' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_status' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_status' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Convert a CPT post + meta into the shape React expects.
     */
    private function format_status( \WP_Post $post ): array {
        $id = $post->ID;
        return [
            'id'           => $id,
            'title'        => $post->post_title,
            'slug'         => get_post_meta( $id, 'status_slug', true ) ?: $post->post_name,
            'icon_code'    => get_post_meta( $id, 'content', true ),      // 'content' is the icon code field
            'color'        => get_post_meta( $id, 'color', true ) ?: '#000000',
            'text_color'   => get_post_meta( $id, 'text_color', true ) ?: '#ffffff',
            'post_status'  => $post->post_status,
            'date'         => get_the_date( 'Y/m/d \a\t g:i a', $post ),

            // Stock
            'reduce_stock' => get_post_meta( $id, 'reduce_stock', true ),

            // General
            'enable_paid'        => get_post_meta( $id, 'alg_orders_individual_custom_status_enable_paid', true ) === 'yes',
            'user_cancel'        => get_post_meta( $id, 'alg_orders_individual_custom_status_user_cancel', true ) === 'yes',

            // Email
            'email_enabled'  => get_post_meta( $id, 'alg_orders_custom_statuses_emails_enabled', true ) === 'yes',
            'email_address'  => get_post_meta( $id, 'alg_orders_custom_statuses_emails_address', true ),
            'email_bcc'      => get_post_meta( $id, 'alg_orders_custom_statuses_bcc_emails_address', true ),
            'email_subject'  => get_post_meta( $id, 'alg_orders_custom_statuses_emails_subject', true ),
            'email_heading'  => get_post_meta( $id, 'alg_orders_custom_statuses_emails_heading', true ),
            'email_content'  => get_post_meta( $id, 'alg_orders_custom_statuses_emails_content', true ),

            // SMS
            'sms_enabled'  => get_post_meta( $id, 'alg_orders_custom_statuses_sms_enabled', true ) === 'yes',
            'sms_content'  => get_post_meta( $id, 'alg_orders_custom_statuses_sms_content', true ),
        ];
    }

    /**
     * Save meta fields from the request body onto a post.
     */
    private function save_meta( int $post_id, array $data ): void {
        $map = [
            'icon_code'  => 'content',       // stored as 'content' in meta
            'color'      => 'color',
            'text_color' => 'text_color',
        ];

        foreach ( $map as $req_key => $meta_key ) {
            if ( isset( $data[ $req_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $data[ $req_key ] ) );
            }
        }

        // Slug
        if ( isset( $data['slug'] ) ) {
            update_post_meta( $post_id, 'status_slug', sanitize_key( $data['slug'] ) );
        }

        // Stock
        $reduce_stock = '';
        if ( ! empty( $data['reduce_stock'] ) && in_array( $data['reduce_stock'], [ 'increase', 'decrease' ], true ) ) {
            $reduce_stock = $data['reduce_stock'];
        }
        update_post_meta( $post_id, 'reduce_stock', $reduce_stock );

        // General
        update_post_meta( $post_id, 'alg_orders_individual_custom_status_enable_paid',
            ! empty( $data['enable_paid'] ) ? 'yes' : '' );
        update_post_meta( $post_id, 'alg_orders_individual_custom_status_user_cancel',
            ! empty( $data['user_cancel'] ) ? 'yes' : '' );

        // Email
        update_post_meta( $post_id, 'alg_orders_custom_statuses_emails_enabled',
            ! empty( $data['email_enabled'] ) ? 'yes' : '' );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_emails_address',
            sanitize_textarea_field( $data['email_address'] ?? '' ) );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_bcc_emails_address',
            sanitize_textarea_field( $data['email_bcc'] ?? '' ) );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_emails_subject',
            sanitize_text_field( $data['email_subject'] ?? '' ) );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_emails_heading',
            sanitize_text_field( $data['email_heading'] ?? '' ) );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_emails_content',
            wp_kses_post( $data['email_content'] ?? '' ) );

        // SMS
        update_post_meta( $post_id, 'alg_orders_custom_statuses_sms_enabled',
            ! empty( $data['sms_enabled'] ) ? 'yes' : '' );
        update_post_meta( $post_id, 'alg_orders_custom_statuses_sms_content',
            sanitize_textarea_field( $data['sms_content'] ?? '' ) );
    }

    // ── Endpoints ─────────────────────────────────────────────────────────────

    public function get_statuses( \WP_REST_Request $request ): \WP_REST_Response {
        $posts = get_posts( [
            'post_type'      => 'custom_order_status',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        return $this->success( array_map( [ $this, 'format_status' ], $posts ) );
    }

    public function get_status( \WP_REST_Request $request ): \WP_REST_Response {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'custom_order_status' !== $post->post_type ) {
            return $this->error( 'not_found', 'Status not found.', 404 );
        }
        return $this->success( $this->format_status( $post ) );
    }

    public function create_status( \WP_REST_Request $request ): \WP_REST_Response {
        $data  = $request->get_json_params();
        $title = sanitize_text_field( $data['title'] ?? '' );
        $slug  = sanitize_key( $data['slug'] ?? '' );

        if ( ! $title || ! $slug ) {
            return $this->error( 'missing_fields', 'Title and slug are required.', 400 );
        }

        // Get post_status from request, default to 'publish' (Active)
        $post_status = sanitize_key( $data['post_status'] ?? 'publish' );
        
        // Validate post_status - only allow publish or draft
        if ( ! in_array( $post_status, [ 'publish', 'draft' ], true ) ) {
            $post_status = 'publish';
        }

        // Ensure slug has wc- prefix
        if ( strpos( $slug, 'wc-' ) !== 0 ) {
            $slug = $slug;
        }

        $post_id = wp_insert_post( [
            'post_type'   => 'custom_order_status',
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => $post_status,
        ] );

        if ( is_wp_error( $post_id ) ) {
            return $this->error( 'create_failed', $post_id->get_error_message(), 500 );
        }

        update_post_meta( $post_id, 'status_slug', $slug );
        $this->save_meta( $post_id, $data );

        return $this->success( $this->format_status( get_post( $post_id ) ), 201 );
    }

    public function update_status( \WP_REST_Request $request ): \WP_REST_Response {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'custom_order_status' !== $post->post_type ) {
            return $this->error( 'not_found', 'Status not found.', 404 );
        }

        $data  = $request->get_json_params();
        $title = sanitize_text_field( $data['title'] ?? $post->post_title );
        $post_status = sanitize_key( $data['post_status'] ?? $post->post_status );
        if ( ! in_array( $post_status, [ 'publish', 'draft' ], true ) ) {
            $post_status = 'publish';
        }

        wp_update_post( [
            'ID'         => $post->ID,
            'post_title' => $title,
            'post_status' => $post_status,
        ] );

        $this->save_meta( $post->ID, $data );

        return $this->success( $this->format_status( get_post( $post->ID ) ) );
    }

    public function delete_status( \WP_REST_Request $request ): \WP_REST_Response {
        $post = get_post( (int) $request['id'] );
        if ( ! $post || 'custom_order_status' !== $post->post_type ) {
            return $this->error( 'not_found', 'Status not found.', 404 );
        }

        wp_trash_post( $post->ID );

        return $this->success( [ 'deleted' => true, 'id' => $post->ID ] );
    }
}
