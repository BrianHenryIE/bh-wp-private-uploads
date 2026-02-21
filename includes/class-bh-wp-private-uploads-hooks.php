<?php
/**
 * Add the hooks to WordPress.
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Menu;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Meta_Boxes;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\CLI;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Media;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Post_Type;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite;
use Psr\Log\LoggerInterface;

/**
 * Exclusively `add_action()` and `add_filter()` preceded with conditional checks.
 */
class BH_WP_Private_Uploads_Hooks {

	/**
	 * Constructor
	 *
	 * @param API_Interface                      $api The main functions.
	 * @param Private_Uploads_Settings_Interface $settings The configured settings.
	 * @param ?LoggerInterface                   $logger PSR logger to record errors.
	 *
	 * @since    1.0.0
	 */
	public function __construct(
		protected API_Interface $api,
		protected Private_Uploads_Settings_Interface $settings,
		protected ?LoggerInterface $logger = null
	) {
		$this->logger = $logger ?? new \Psr\Log\NullLogger();

		$this->define_api_hooks();
		$this->define_admin_notices_hooks();
		$this->define_frontend_hooks();
		$this->define_cron_job_hooks();
		$this->define_cli_hooks();
		$this->define_post_hooks();
		$this->define_rewrite_hooks();

		$this->define_meta_box_hooks();
		$this->define_media_library_hooks();
		new Upload( $this->settings, new Media_Request() );

		$this->define_admin_menu_hooks();
	}

	/**
	 * On every load, ensure the directory exists.
	 *
	 * TODO: Think about how this could be delayed. Let's avoid file-system operations unless 100% necessary.
	 */
	protected function define_api_hooks(): void {
		/** @phpstan-ignore-next-line return.void  */
		add_action( 'init', array( $this->api, 'create_directory' ) );
	}

	/**
	 * Maybe register the post type.
	 */
	protected function define_post_hooks(): void {

		if ( empty( $this->settings->get_post_type_name() ) ) {
			return;
		}

		$post = new Post_Type( $this->settings );

		add_action( 'init', array( $post, 'register_post_type' ) );
	}

	/**
	 * Register hooks for handling admin notices: display and dismissal.
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

		add_action( "private_uploads_unsnooze_dismissed_notice_{$this->settings->get_post_type_name()}", array( $cron, 'unsnooze_dismissed_notice' ) );
	}

	/**
	 * Register CLI commands: `download`.
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

		$media = new Media( $this->settings, new Media_Request() );

		add_action( 'wp_ajax_query-attachments', array( $media, 'on_query_attachments' ), 1 );
		add_action( 'admin_init', array( $media, 'on_upload_attachment' ), 1 );

		$admin_assets = new Admin_Assets( $this->settings );

		add_action( 'admin_init', array( $admin_assets, 'register_script' ), 1 );
	}

	/**
	 * When `Settings::get_meta_box_settings()` has an array (key:value post_type:meta-box-args), display the
	 * upload meta-box on the post type.
	 */
	protected function define_meta_box_hooks(): void {
		$admin_meta_boxes = new Admin_Meta_Boxes( $this->settings, $this->logger );

		add_action( 'add_meta_boxes', array( $admin_meta_boxes, 'add_meta_box' ), 10, 2 );
	}

	/**
	 * If `show_in_menu` is set, add a submenu to "Media" for the new post type.
	 */
	protected function define_admin_menu_hooks(): void {

		$admin_menu_hooks = new Admin_Menu( $this->settings );

		// This needs to run first for the other hooks to have any real effect.
		add_action( 'registered_post_type', array( $admin_menu_hooks, 'get_registered_post_type_object' ), 10, 2 );

		add_action( 'admin_menu', array( $admin_menu_hooks, 'add_private_media_library_menu' ) );
		add_filter( 'submenu_file', array( $admin_menu_hooks, 'highlight_menu' ), 10, 2 );
		add_action( 'admin_menu', array( $admin_menu_hooks, 'remove_top_level_menu' ), 1000 );
	}
}
