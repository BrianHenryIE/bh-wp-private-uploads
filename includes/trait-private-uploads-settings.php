<?php
/**
 * Convenience defaults for Private_Uploads_Settings_Interface implementations.
 *
 * Implementing this allows new functions to be added to the interface without consumers needing to manually update.
 *
 * @package     brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

/**
 * Provide defaults using the plugin-slug and plugin Name. If `get_post_type_name()` is overridden, that change will cascade.
 */
trait Private_Uploads_Settings_Trait {

	/**
	 * Default to `{$plugin_slug}_private_uploads`.
	 *
	 * Recommended to override this function.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key()."
	 *
	 * Conventionally, post type names use underscores as separators.
	 *
	 * @see Private_Uploads_Settings_Interface::get_post_type_name()
	 */
	public function get_post_type_name(): string {

		$plugin_snake = str_hyphens_to_underscores( $this->get_plugin_slug() );

		// Longest allowed plugin slug is 20 characters; "_private" is 8 characters long.
		return sanitize_key( substr( $plugin_snake, 0, 12 ) . '_private' );
	}

	/**
	 * The title when displayed in the UI.
	 *
	 * E.g. The Media submenu.
	 *
	 * Default: "{Plugin Name} Uploads", or "{Post Type Name} Uploads".
	 *
	 * @see Private_Uploads_Settings_Interface::get_post_type_label()
	 */
	public function get_post_type_label(): string {

		if ( str_underscores_to_hyphens( $this->get_post_type_name() ) === $this->get_plugin_slug() ) {
			$label = get_plugin_name_from_slug( $this->get_plugin_slug() );
		} else {
			$label = str_underscores_to_title_case( $this->get_post_type_name() );
		}

		return sprintf(
			'%s Uploads',
			$label
		);
	}

	/**
	 * Default: the post type name with hyphens.
	 *
	 * E.g. wp-content/uploads/my-posttype-name will be the private directory.
	 *
	 * @see Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
	 */
	public function get_uploads_subdirectory_name(): string {
		return str_underscores_to_hyphens( $this->get_post_type_name() );
	}

	/**
	 * Default to no CLI commands.
	 *
	 * Suggested: use the post type name but with hyphens ({@see self::get_uploads_subdirectory_name()}).
	 *
	 * @see Private_Uploads_Settings_Interface::get_cli_base()
	 */
	public function get_cli_base(): ?string {
		return null;
	}

	/**
	 * Default to no REST API.
	 *
	 * Suggested: use the post type name but with hyphens ({@see self::get_uploads_subdirectory_name()}).
	 *
	 * @see Private_Uploads_Settings_Interface::get_rest_base()
	 */
	public function get_rest_base(): ?string {
		return null;
	}

	/**
	 * Default does not add the upload meta box to any post types.
	 *
	 * @see Private_Uploads_Settings_Interface::get_meta_box_settings()
	 *
	 * @return array<string, array<mixed>>|array{}
	 */
	public function get_meta_box_settings(): array {
		return array();
	}
}
