<?php
/**
 * Class COS_REST_Router
 *
 * Central router for all Custom Order Status REST endpoints.
 *
 * Responsibilities:
 *   1. Require all controller class files.
 *   2. Instantiate each controller.
 *   3. Hook into `rest_api_init` to call register_routes() on each controller.
 *
 * Usage (from the main plugin bootstrap file):
 *   COS_REST_Router::init();
 *
 * @package Custom_Order_Status\API
 */

defined( 'ABSPATH' ) || exit;

class COS_REST_Router {

	/**
	 * Controller instances, keyed by a short identifier.
	 *
	 * @var COS_REST_Controller_Base[]
	 */
	private static array $controllers = [];

	/**
	 * Initialise the router.
	 * Call this once from the main plugin file (after plugins_loaded).
	 */
	public static function init(): void {
		self::load_controllers();
		add_action( 'rest_api_init', [ __CLASS__, 'register_all_routes' ] );
	}

	/**
	 * Require all controller files and store instances.
	 * Called from self::init() before the `rest_api_init` hook fires.
	 */
	private static function load_controllers(): void {
		$api_dir = COS_PLUGIN_PATH . 'includes/api/';

		// Base class must be loaded first
		require_once $api_dir . 'class-cos-rest-controller-base.php';

		// Individual controllers
		$controller_files = [
			'settings' => $api_dir . 'class-cos-rest-settings-controller.php',
			'rules'    => $api_dir . 'class-cos-rest-rules-controller.php',
			'options'  => $api_dir . 'class-cos-rest-options-controller.php',
			'license'  => $api_dir . 'class-cos-rest-license-controller.php',
			'statuses' => $api_dir . 'class-cos-rest-statuses-controller.php',
		];

		$controller_classes = [
			'settings' => 'COS_REST_Settings_Controller',
			'rules'    => 'COS_REST_Rules_Controller',
			'options'  => 'COS_REST_Options_Controller',
			'license'  => 'COS_REST_License_Controller',
			'statuses' => 'COS_REST_Statuses_Controller',
		];

		foreach ( $controller_files as $key => $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				self::$controllers[ $key ] = new $controller_classes[ $key ]();
			} else {
				// Non-fatal: log and continue so a missing file doesn't crash the plugin.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
					trigger_error(
						esc_html( "COS Pro: Controller file not found: $file" ),
						E_USER_WARNING
					);
				}
			}
		}
	}

	/**
	 * Called on `rest_api_init`.
	 * Delegates route registration to each controller.
	 */
	public static function register_all_routes(): void {
		foreach ( self::$controllers as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * Retrieve a specific controller instance (useful for unit tests).
	 *
	 * @param string $key  One of 'settings', 'rules', 'options', 'license'.
	 * @return COS_REST_Controller_Base|null
	 */
	public static function get_controller( string $key ): ?COS_REST_Controller_Base {
		return self::$controllers[ $key ] ?? null;
	}
}
