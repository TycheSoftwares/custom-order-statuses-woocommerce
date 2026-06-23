<?php
/**
 * Class Setting
 *
 * Registers the COS Pro tab in WooCommerce → Settings and renders the React app.
 *
 * @package Custom_Order_Status
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

defined( 'ABSPATH' ) || exit;

class Setting {

    const TAB_ID = 'custom-order-statuses-for-woocommerce';

    public function __construct() {
        add_filter( 'woocommerce_settings_tabs_array',        [ $this, 'add_settings_tab'    ], 50 );
        add_action( 'woocommerce_settings_' . self::TAB_ID,   [ $this, 'render_settings_tab' ] );
        add_action( 'woocommerce_settings_save_' . self::TAB_ID, '__return_false' );
        add_action( 'admin_enqueue_scripts',                  [ $this, 'enqueue_assets'      ] );
    }

    public function add_settings_tab( array $tabs ): array {
        $tabs[ self::TAB_ID ] = __( 'Custom Order Status', 'custom-order-statuses-woocommerce' );
        return $tabs;
    }

    public function render_settings_tab(): void {
        echo '<div id="cos-settings-root"></div>';
    }

    public function enqueue_assets( string $hook_suffix ): void {
        if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:gnore
        if ( self::TAB_ID !== $current_tab ) {
            return;
        }

        $asset_file = COS_PLUGIN_PATH . 'build/settings.asset.php';

        if ( ! file_exists( $asset_file ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-error"><p>' .
                    sprintf(
                        esc_html__( 'COS Pro: run %s to build the settings UI.', 'custom-order-statuses-woocommerce' ),
                        '<code>npm run build</code>'
                    ) .
                    '</p></div>';
            } );
            return;
        }

        $asset = require $asset_file;

        /**
         * WHY we register this handle manually:
         *
         * When @wordpress/scripts sees `import '@wordpress/components/build-style/style.css'`
         * in JS, it extracts the CSS into a separate file AND injects the string
         * 'wp-components/build-style/style.css' into the dependencies array of
         * settings.asset.php.
         *
         * This is NOT a real WordPress handle — it is a generated placeholder that
         * @wordpress/scripts expects the plugin to register manually in PHP.
         * WordPress's wp_enqueue_script() checks every handle in the dependencies
         * array. If any handle is not registered, the script is silently dropped
         * and never loads — no error, just a blank page.
         *
         * The fix is to register this fake handle pointing to the actual
         * wp-components stylesheet (already bundled by WordPress core).
         * This tells WordPress "yes this dependency exists, it's satisfied by
         * the wp-components stylesheet you already have".
         */
        wp_register_style(
            'wp-components/build-style/style.css', // the fake handle from asset.php
            false,                                  // no separate URL — wp-components covers it
            [ 'wp-components' ],                    // depends on the real wp-components style
            $asset['version']
        );

        // Also register the extracted CSS file that @wordpress/scripts generated
        // (build/settings.css contains our app.scss + any other imported CSS)
        wp_enqueue_style(
            'cos-pro-settings',
            COS_PLUGIN_URL . 'build/settings.css',
            [ 'wp-components', 'wp-components/build-style/style.css', 'woocommerce_admin_styles' ],
            $asset['version']
        );

        wp_enqueue_style( 'dashicons' );

        // Font Awesome — needed for icon picker (same icons.json the old plugin uses)
        wp_enqueue_style(
            'cos-pro-font-awesome',
            COS_PLUGIN_URL . 'assets/css/all.min.css',
            array(),
            '6.5.1'
        );

        // Enqueue the main JS bundle — now all dependencies resolve correctly
        wp_enqueue_script(
            'cos-pro-settings',
            COS_PLUGIN_URL . 'build/settings.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        // Inject the REST nonce into wp.apiFetch
        wp_add_inline_script(
            'wp-api-fetch',
            sprintf(
                'wp.apiFetch.use( wp.apiFetch.createNonceMiddleware( %s ) );',
                wp_json_encode( wp_create_nonce( 'wp_rest' ) )
            ),
            'after'
        );

        wp_localize_script(
            'cos-pro-settings',
            'cosProData',
            [
                'restUrl'  => esc_url_raw( rest_url( 'cos-pro/v1/' ) ),
                'adminUrl' => esc_url_raw( admin_url() ),
                'version'  => COS_VERSION,
                'iconsJsonUrl'=> esc_url_raw( COS_PLUGIN_URL . 'assets/js/icons.json' ),
            ]
        );
    }
}
