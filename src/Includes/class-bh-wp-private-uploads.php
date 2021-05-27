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
 * @package    BH_WP_Private_Uploads
 * @subpackage BH_WP_Private_Uploads/includes
 */

namespace BrianHenryIE\WP_Private_Uploads\Includes;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin;
use BrianHenryIE\WP_Private_Uploads\API\API_Interface;
use BrianHenryIE\WP_Private_Uploads\API\CLI;
use BrianHenryIE\WP_Private_Uploads\API\Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Frontend\Send_Private_File;
use Psr\Log\LoggerInterface;
use WP_CLI;

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * frontend-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    BH_WP_Private_Uploads
 * @subpackage BH_WP_Private_Uploads/includes
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */
class BH_WP_Private_Uploads {

	/** @var LoggerInterface  */
	protected $logger;

	/** @var Settings_Interface  */
	protected $settings;

	/** @var API_Interface  */
	protected $api;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the frontend-facing side of the site.
	 *
	 * @param API_Interface      $api
	 * @param Settings_Interface $settings
	 * @param LoggerInterface    $logger
	 *
	 * @since    1.0.0
	 */
	public function __construct( $api, $settings, $logger ) {

		$this->logger   = $logger;
		$this->settings = $settings;
		$this->api      = $api;

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_frontend_hooks();
		$this->define_api_hooks();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	protected function set_locale() {

		$plugin_i18n = new I18n();

		add_action( 'plugins_loaded', array( $plugin_i18n, 'load_plugin_textdomain' ) );

	}

	/**
	 * This also registers the REST API.
	 */
	protected function define_includes_hooks() {

		$post = new Post();

		add_action( 'init', array( $post, 'register_post_type' ) );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	protected function define_admin_hooks() {

		$plugin_admin = new Admin( $this->settings );

		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $plugin_admin, 'enqueue_scripts' ) );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	protected function define_frontend_hooks() {

		$this->send_private_file = new Send_Private_File();

		add_action( 'init', array( $this->send_private_file, 'init' ) );
	}

	/**
	 *
	 * @since    1.0.0
	 * @access   protected
	 */
	protected function define_api_hooks() {

		if ( class_exists( WP_CLI::class ) ) {
			CLI::$api = $this->api;
			// wp private-uploads
			WP_CLI::add_command( 'private-uploads', CLI::class );
		}

	}
}
