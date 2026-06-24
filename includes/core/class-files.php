<?php
/**
 * Custom Order Status for WooCommerce - Admin Files Class
 *
 * Class for including files for the Admin.
 *
 * @author      Tyche Softwares
 * @package     COS/Admin/Files
 * @category    Classes
 * @since       1.0
 */

namespace TycheSoftwares\CustomOrderStatus\Lite;

defined( 'ABSPATH' ) || exit;

/**
 * COS Admin Files.
 *
 * @since 1.0
 */
class Files {

	public function __construct() {
		$this->include_files();
		add_action( 'plugins_loaded', array( $this, 'cos_pro_init' ) );
	}

	/**
	 * Include files.
	 *
	 * @since 1.0
	 */
	public function include_files() {

		COS_Lite()::include_file( 'functions/functions.php' );
		// Register Custom Post Type for custom order status.
		COS_Lite()::include_file( 'admin/class-cpt.php' );

		$tyche_files = array(
			'class-tracking.php',
			'class-deactivation.php',
		);

		foreach ( $tyche_files as $tyche_file ) {
			if ( file_exists( COS_PLUGIN_PATH . '/includes/' . $tyche_file ) ) {
				COS_Lite()::include_file( $tyche_file );
			}
		}
	}

	public function cos_pro_init() {
		// Run settings migration (old options → new unified structure)
		require_once COS_PLUGIN_PATH . 'includes/admin/class-migration.php';
		Migration::run();

		// Load the REST API layer (router requires all controllers)
		require_once COS_PLUGIN_PATH . 'includes/api/class-api-router.php';
		Api_Router::init();

		// Load the WC settings tab + asset enqueuing
		require_once COS_PLUGIN_PATH . 'includes/admin/class-settings.php';
		new Setting();
	}

	/**
	 * Loads Dependency Files.
	 * If there are required files needed ( to be included before ) for the execution of the view file, those dependencies can be added here.
	 *
	 * @param string $section Section Directory.
	 * @param string $filename File in the section Directory to be loaded.
	 * @since 5.19.0
	 */
	public static function load_dependencies( $section, $filename ) {

		if ( '' === $section || '' === $filename ) {
			return;
		}
	}
}