<?php
/**
 * TODO: Add an admin notice if the folder is public.
 *
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\Admin;

use BrianHenryIE\WP_Private_Uploads_Test_Plugin\API\Settings_Interface;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    BH_WP_Private_Uploads
 * @subpackage BH_WP_Private_Uploads/admin
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */
class Admin {

	protected Settings_Interface $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings_Interface $settings The plugin settings.
	 */
	public function __construct( Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @hooked admin_enqueue_scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles(): void {

		wp_enqueue_style( $this->settings->get_plugin_slug(), plugin_dir_url( __FILE__ ) . 'css/bh-wp-private-uploads-admin.css', array(), $this->settings->get_plugin_version(), 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @hooked admin_enqueue_scripts
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts(): void {

		wp_enqueue_script( $this->settings->get_plugin_slug(), plugin_dir_url( __FILE__ ) . 'js/bh-wp-private-uploads-admin.js', array( 'jquery' ), $this->settings->get_plugin_version(), false );
	}
}
