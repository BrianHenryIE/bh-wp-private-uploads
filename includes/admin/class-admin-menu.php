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

	/**
	 * The WordPress registered post type object reference.
	 */
	protected ?WP_Post_Type $post_type = null;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings The settings, mainly to compare the post type name (key).
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings
	) {
	}

	/**
	 * Capture a reference to the post type object when it is registered.
	 *
	 * This class is constructed before the post type is registered.
	 *
	 * @see register_post_type()
	 * @hooked register_post_type_{post_type_key}
	 *
	 * @param string        $post_type_key The post type name (key, not title).
	 * @param ?WP_Post_Type $post_type_object The full post type object.
	 */
	public function get_registered_post_type_object( string $post_type_key, ?WP_Post_Type $post_type_object ): void {

		if ( $this->settings->get_post_type_name() !== $post_type_key ) {
			return;
		}

		if ( is_null( $post_type_object ) ) {
			return;
		}

		$this->post_type = $post_type_object;
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
		if ( ! $this->post_type?->show_in_menu ) {
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

		// This isn't POSTed data, it's just a URL.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['post_type'] )
			|| ! is_string( $_GET['post_type'] )
			|| $this->settings->get_post_type_name() !== sanitize_key( $_GET['post_type'] )
		) {
			return $submenu_file;
		}

		$url = add_query_arg(
			array(
				'post_type' => $this->settings->get_post_type_name(),
			),
			admin_url( 'upload.php' )
		);

		if ( isset( $_SERVER['REQUEST_URI'] )
			&& is_string( $_SERVER['REQUEST_URI'] )
			&& str_contains( $url, sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
		) {
			return $url;
		}

		return $submenu_file;
	}

	/**
	 * Because we're using `show_in_menu`, it's also adding a top level menu. Let's delete that.
	 *
	 * TODO: redirect wp-admin/edit.php?post_type=test_plugin_private to the library. @see edit.php:26.
	 *
	 * @hooked admin_menu
	 */
	public function remove_top_level_menu(): void {
		remove_menu_page( "edit.php?post_type={$this->settings->get_post_type_name()}" );
	}
}
