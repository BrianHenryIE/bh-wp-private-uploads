<?php
/**
 * Add a submennu of Media linking to the Private Media Library.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP_Post_Type;

/**
 * When a menu title is configured, add a submenu of Media linking to the Private Media Library.
 */
class Admin_Menu {

	protected Private_Uploads_Settings_Interface $settings;

	protected WP_Post_Type $post_type;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings To know whether to add a submenu, and the post type.
	 */
	public function __construct( Private_Uploads_Settings_Interface $settings ) {
		$this->settings = $settings;

		add_action(
			"registered_post_type_{$settings->get_post_type_name()}",
			function () {
				/**
				 * This will always work, or we have bigger problems.
				 *
				 * @var WP_Post_Type
				 */
				$this->post_type = get_post_type_object( $this->settings->get_post_type_name() );
			}
		);
	}

	/**
	 * Add a submenu of Media linking to `upload.php?post_type=private_uploads_post_type`.
	 *
	 * `upload.php` is the standard Media Library page.
	 *
	 * @hooked admin_menu
	 * @see includes/menu.php
	 */
	public function add_private_media_library_menu(): void {

		// If the plugin is not configured to add a submenu, don't add one.
		if ( ! $this->post_type->show_in_menu ) {
			return;
		}

		add_submenu_page(
			'upload.php',
			$this->post_type->label, // Page title.
			$this->post_type->label, // Menu title.
			'manage_options',
			add_query_arg(
				array(
					'post_type' => $this->settings->get_post_type_name(),
				),
				admin_url( 'upload.php' )
			)
		);
	}

	/**
	 * When the submenu is selected, highlight it in the menu.
	 *
	 * Without this, the default "Library" menu item is highlighted.
	 *
	 * @hooked submenu_file
	 * @see wp-admin/menu-header.php
	 *
	 * @param ?string $submenu_file The submenu file.
	 * @param string  $_parent_file The submenu item's parent file.
	 */
	public function highlight_menu( ?string $submenu_file, string $_parent_file ): ?string {

		$url = add_query_arg(
			array(
				'post_type' => $this->settings->get_post_type_name(),
			),
			admin_url( 'upload.php' )
		);

		// This isn't POSTed data, it's just a URL.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['post_type'] )
			|| $this->settings->get_post_type_name() !== sanitize_key( $_GET['post_type'] )
		) {
			return $submenu_file;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $url, sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) ) {
			return $url;
		}

		return $submenu_file;
	}

	/**
	 * Because we're using `show_in_menu`, it's also adding a top level menu. Let's delete that.
	 *
	 * TODO: redirect wp-admin/edit.php?post_type=test_plugin_private to the library.
	 *
	 * @hooked admin_menu
	 */
	public function remove_top_level_menu(): void {
		remove_menu_page( "edit.php?post_type={$this->settings->get_post_type_name()}" );
	}
}
