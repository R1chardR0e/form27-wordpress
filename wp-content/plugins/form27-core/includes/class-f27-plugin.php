<?php
/**
 * Plugin orchestration.
 *
 * @package Form27
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class F27_Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function boot(): void {
		add_action( 'init', array( 'F27_Content', 'register' ), 5 );
		add_action( 'init', array( 'F27_Settings', 'register_shortcodes' ), 6 );
		add_action( 'init', array( 'F27_Blocks', 'register' ), 20 );
		add_action( 'admin_init', array( 'F27_Settings', 'register' ) );
		add_action( 'admin_menu', array( 'F27_Settings', 'add_page' ) );
		add_action( 'wp_loaded', array( 'F27_Seeder', 'maybe_seed' ), 20 );
		add_action( 'rest_api_init', array( 'F27_REST', 'register_routes' ) );
		add_action( 'add_meta_boxes_f27_product', array( 'F27_Content', 'add_product_metabox' ) );
		add_action( 'save_post_f27_product', array( 'F27_Content', 'save_product_metabox' ) );
		add_action( 'add_meta_boxes_f27_case', array( 'F27_Content', 'add_case_metabox' ) );
		add_action( 'save_post_f27_case', array( 'F27_Content', 'save_case_metabox' ) );
		add_action( 'add_meta_boxes_f27_request', array( 'F27_Content', 'add_request_metabox' ) );
		add_action( 'save_post_f27_request', array( 'F27_Content', 'save_request_metabox' ) );
		add_filter( 'manage_f27_request_posts_columns', array( 'F27_Content', 'request_columns' ) );
		add_action( 'manage_f27_request_posts_custom_column', array( 'F27_Content', 'render_request_column' ), 10, 2 );
		add_action( 'f27_cleanup_requests', array( 'F27_Content', 'cleanup_requests' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( 'F27_Content', 'privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( 'F27_Content', 'privacy_erasers' ) );
		add_filter( 'option_blogname', array( 'F27_Settings', 'filter_blogname' ) );
		add_filter( 'render_block', array( 'F27_Settings', 'filter_render_block' ), 10, 2 );
		add_action( 'phpmailer_init', array( 'F27_Settings', 'configure_mailer' ) );
		add_filter( 'wp_mail_from', array( 'F27_Settings', 'mail_from' ) );
		add_filter( 'wp_mail_from_name', array( 'F27_Settings', 'mail_from_name' ) );
		add_action( 'cli_init', array( 'F27_Seeder', 'register_cli' ) );
	}

	public static function activate(): void {
		F27_Content::register();
		F27_Seeder::seed( false, false );
		F27_Content::schedule_cleanup();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'f27_cleanup_requests' );
		flush_rewrite_rules();
	}
}
