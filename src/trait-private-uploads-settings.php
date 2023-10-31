<?php
/**
 * Convenience defaults for Private_Uploads_Settings_Interface implementations.
 *
 * Implementing this allows new functions to be added to the interface without consumers needing to manually update.
 *
 * @package     brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

trait Private_Uploads_Settings_Trait {

	/**
	 * Default to `{$plugin_slug}_private_uploads`.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key()."
	 */
	public function get_post_type_name(): string {

		$plugin_slug = $this->get_plugin_slug();

		if ( strlen( $plugin_slug ) > 12 ) {
			return sanitize_key( substr( $plugin_slug, 0, 12 ) . '_private' );
		}

		return substr( sanitize_key( "{$this->get_plugin_slug()}_private_uploads" ), 0, 20 );
	}

	/**
	 * Default to the plugin's slug.
	 *
	 * E.g. wp-content/uploads/my-plugin-slug will be the private directory.
	 *
	 * @return ?string
	 */
	public function get_uploads_subdirectory_name(): string {
		return $this->get_plugin_slug();
	}

	/**
	 * Default to no CLI commands.
	 */
	public function get_cli_base(): ?string {
		return null;
	}

	/**
	 * Default does not add the upload meta box to any post types.
	 */
	public function get_meta_box_settings(): array {
		return array();
	}
}
