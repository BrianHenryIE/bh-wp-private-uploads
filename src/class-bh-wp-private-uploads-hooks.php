<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Menu;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Meta_Boxes;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\CLI;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Media;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Post_Type;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite;
use Psr\Log\LoggerInterface;
use WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * frontend-facing site hooks.
 *
 * @since      1.0.0
 * @package    brianhenryie/bh-wp-private-uploads
 *
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */
class BH_WP_Private_Uploads_Hooks {

	protected LoggerInterface $logger;

	protected Private_Uploads_Settings_Interface $settings;

	protected API_Interface $api;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 *
	 * @param API_Interface                      $api
	 * @param Private_Uploads_Settings_Interface $settings
	 * @param LoggerInterface                    $logger
	 *
	 * @since    1.0.0
	 */
	public function __construct( API_Interface $api, Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {

		$this->logger   = $logger;
		$this->settings = $settings;
		$this->api      = $api;

		$this->define_api_hooks();
		$this->define_admin_notices_hooks();
		$this->define_frontend_hooks();
		$this->define_cron_job_hooks();
		$this->define_cli_hooks();
		$this->define_post_hooks();
		$this->define_rewrite_hooks();

		$this->define_meta_box_hooks();
		$this->define_media_library_hooks();
		new Upload( $settings );

		$this->define_admin_menu_hooks();
	}

	protected function define_api_hooks(): void {
		add_action( 'init', array( $this->api, 'create_directory' ) );
	}

	/**
	 * This also registers the REST API.
	 *
	 * @since    2.0.0
	 */
	protected function define_post_hooks(): void {

		$post = new Post_Type( $this->settings );

		add_action( 'init', array( $post, 'register_post_type' ) );
	}

	/**
	 * Register hooks for handling admin notices: display and dismissal.
	 *
	 * @since    3.0.0
	 */
	protected function define_admin_notices_hooks(): void {

		$admin_notices = new Admin_Notices( $this->api, $this->settings, $this->logger );

		// Generate the notices from wp_options.
		add_action( 'admin_init', array( $admin_notices, 'admin_notices' ), 9 );
		// Add the notice.
		add_action( 'admin_notices', array( $admin_notices, 'the_notices' ) );
	}

	/**
	 * Register hooks for handling frontend delivery of the files.
	 *
	 * @since    1.0.0
	 */
	protected function define_frontend_hooks(): void {

		$serve_private_file = new Serve_Private_File( $this->settings, $this->logger );

		add_action( 'init', array( $serve_private_file, 'init' ) );
	}

	/**
	 * Define hooks for a cron job to regularly check the folder is private.
	 */
	protected function define_cron_job_hooks(): void {

		$cron = new Cron( $this->api, $this->settings, $this->logger );

		add_action( 'init', array( $cron, 'register_cron_job' ) );

		$cron_job_hook_name = "private_uploads_check_url_{$this->settings->get_post_type_name()}";
		add_action( $cron_job_hook_name, array( $cron, 'check_is_url_public' ) );

		add_action( "{$this->settings->get_post_type_name()}_unsnooze_dismissed_private_uploads_notice", array( $cron, 'unsnooze_dismissed_notice' ) );
	}

	/**
	 * Register CLI commands: `download`.
	 *
	 * @since    2.0.0
	 */
	protected function define_cli_hooks(): void {

		if ( is_null( $this->settings->get_cli_base() ) ) {
			return;
		}

		$cli = new CLI( $this->api, $this->settings, $this->logger );

		add_action( 'cli_init', array( $cli, 'register_commands' ) );
	}

	/**
	 * Define hooks for adding .htaccess rules to make the folder private.
	 */
	protected function define_rewrite_hooks(): void {

		$rewrite = new WP_Rewrite( $this->settings, $this->logger );

		add_action( 'init', array( $rewrite, 'register_rewrite_rule' ) );
	}

	/**
	 * Define hooks which redirect attachments uploaded through the media library to the private uploads directory.
	 */
	protected function define_media_library_hooks(): void {

		$media = new Media( $this->settings );

		add_action( 'wp_ajax_query-attachments', array( $media, 'on_query_attachments' ), 1 );
		add_action( 'admin_init', array( $media, 'on_upload_attachment' ), 1 );

		$admin_assets = new Admin_Assets( $this->settings );

		add_action( 'admin_init', array( $admin_assets, 'register_script' ), 1 );
	}

	protected function define_meta_box_hooks(): void {
		$admin_meta_boxes = new Admin_Meta_Boxes( $this->settings, $this->logger );

		add_action( 'add_meta_boxes', array( $admin_meta_boxes, 'add_meta_box' ), 10, 2 );
	}

	protected function define_admin_menu_hooks(): void {

		$admin_menu_hooks = new Admin_Menu( $this->settings );

		add_action( 'admin_menu', array( $admin_menu_hooks, 'add_private_media_library_menu' ) );
		add_filter( 'submenu_file', array( $admin_menu_hooks, 'highlight_menu' ), 10, 2 );
		add_filter( 'admin_menu', array( $admin_menu_hooks, 'remove_top_level_menu' ), 1000 );
	}
}
