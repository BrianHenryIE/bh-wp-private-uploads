<?php
/**
 * Minimum required settings for instantiating the private uploads functionality.
 *
 * Additional configuration can be done via WordPress's register post type filter:
 * ```
 * add_filter( 'register_post_type_args', 'configure_my_private_uploads_post_type', 10, 2 );
 *
 * function configure_my_private_uploads_post_type( array $args, string $post_type ): array {
 *   if( 'my_post_type' !== $post_type ) {
 *     return $args;
 *   }
 *   $args['description'] = 'Private uploads for my-plugin';    // Description as shown ... ? TODO: where is it shown?
 *   $args['show_in_menu'] = true;          // Should the admin menu Media submenu be displayed?
 *   $args['label'] = 'My Plugin Uploads';    // The name for the admin menu Media submenu item.
 *   $args['show_in_rest'] = true;          // Default is true. ?
 *   $args['rest_namespace'] = 'my-plugin/v1'; // Default is `plugin-slug/v1`.
 *   $args['rest_base'] = 'uploads';        // Default is `uploads`.
 *   $args['taxonomies'] = array();         // E.g. `category`, `post_tag`.
 *   $args['delete_with_user'] = true;      // Delete all posts of this type authored by a user when that user is deleted.
 *   // ...
 *   return $args;
 * }
 * ```
 *
 * Required:
 *
 * @see Private_Uploads_Settings_Interface::get_plugin_slug()
 *
 * Provided:
 * @see Private_Uploads_Settings_Trait
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\WP_Includes\CLI;

interface Private_Uploads_Settings_Interface {

	/**
	 * The plugin slug (directory name from WP_PLUGINS_DIR) is essential to distinguish from other instances.
	 *
	 * JS scripts are registered once per plugin.
	 * The default rest-base is the plugin slug.
	 */
	public function get_plugin_slug(): string;

	/**
	 * The post type name for posts generated for each saved file.
	 *
	 * A custom post type is needed for tracking the owners of files, and for the REST API.
	 *
	 * Recommended to change this to something more specific to your feature, otherwise the concatenation and truncating
	 * results in a post type name that is not very readable.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key()."
	 *
	 * @see register_post_type()
	 *
	 * Trait default: `{$plugin_slug}_private_uploads`.
	 * @see Private_Uploads_Settings_Trait::get_post_type_name()
	 */
	public function get_post_type_name(): string;

	/**
	 * Friendly display name for the post type.
	 * Used in the Media submenu.
	 */
	public function get_post_type_label(): string;

	/**
	 * The name for the private directory, on the filesystem as a wp-content/uploads subdirectory.
	 *
	 * I.e. `wp-content/uploads/subdirectory-name/`.
	 *
	 * The directory will otherwise act the same as `wp-content/uploads`, i.e. with year/month subdirectories.
	 *
	 * Defaults to the plugin slug when using Private_Uploads_Settings_Trait.
	 *
	 * Should have no pre or trailing slash.
	 *
	 * Trait default: `{$plugin_slug}`.
	 *
	 * @see Private_Uploads_Settings_Trait::get_uploads_subdirectory_name()
	 */
	public function get_uploads_subdirectory_name(): string;

	/**
	 * The base for CLI commands, e.g. `my-plugin uploads` for `wp my-plugin uploads ...`.
	 *
	 * Return null to not add any CLI commands.
	 *
	 * TODO: Is convention here underscores or hyphens?
	 * TODO: This should handle multiple post types somehow.
	 *
	 * @see CLI
	 *
	 * Trait default: `null`.
	 * @see Private_Uploads_Settings_Trait::get_cli_base()
	 */
	public function get_cli_base(): ?string;

	/**
	 * When specified, the REST API will be enabled for this post type. It behaves like an attachment.
	 */
	public function get_rest_base(): ?string;

	/**
	 * Array of meta box settings, keyed by the post type where the meta box should be added. Default empty.
	 *
	 * For each key found, a meta box with upload dialog will be added to that post's edit screen.
	 *
	 * E.g. `array( 'shop_order' => array() )` will add the meta box to the WooCommerce order edit screen.
	 *
	 * Trait default: `array()`.
	 *
	 * @see add_meta_box()
	 *
	 * @see Private_Uploads_Settings_Trait::get_meta_box_settings()
	 *
	 * @return array<string, array<mixed>>|array{} Array of <post type name/key : array to pass to add_meta_box()>.
	 */
	public function get_meta_box_settings(): array;

	// TODO: autodelete.
}
