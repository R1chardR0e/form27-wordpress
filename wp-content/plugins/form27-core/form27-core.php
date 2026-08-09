<?php
/**
 * Plugin Name: FORM 27 Core
 * Description: Product data, interactive specification tools, requests and demo content for FORM 27.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Author: Andrey Digital
 * Text Domain: form27
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'F27_CORE_VERSION', '1.0.0' );
define( 'F27_CORE_FILE', __FILE__ );
define( 'F27_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'F27_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once F27_CORE_DIR . 'includes/class-f27-product-schema.php';
require_once F27_CORE_DIR . 'includes/class-f27-settings.php';
require_once F27_CORE_DIR . 'includes/class-f27-content.php';
require_once F27_CORE_DIR . 'includes/class-f27-seeder.php';
require_once F27_CORE_DIR . 'includes/class-f27-rest.php';
require_once F27_CORE_DIR . 'includes/class-f27-blocks.php';
require_once F27_CORE_DIR . 'includes/class-f27-plugin.php';

register_activation_hook( __FILE__, array( 'F27_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'F27_Plugin', 'deactivate' ) );

F27_Plugin::instance()->boot();
