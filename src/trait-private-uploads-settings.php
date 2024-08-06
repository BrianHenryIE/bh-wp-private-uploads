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

		$plugin_snake = str_replace( '-', '_', $this->get_plugin_slug() );

		if ( strlen( $plugin_snake ) > 12 ) {
			return sanitize_key( substr( $plugin_snake, 0, 12 ) . '_private' );
		}

		return substr( sanitize_key( "{$this->get_plugin_slug()}_private_uploads" ), 0, 20 );
	}

	public function get_post_type_label(): string {
		return sprintf(
			'%s Uploads',
			$this->get_plugin_name_from_slug( $this->get_plugin_slug() )
		);
	}

	protected function get_plugin_name_from_slug( string $plugin_slug ) {

		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';

		$plugins         = get_plugins();
		$plugin_basename = $this->get_plugin_basename( $plugins, $plugin_slug );
		$plugin_name     = is_null( $plugin_basename )
			? $plugin_slug
			: $plugins[ $plugin_basename ]['Name'];

		return $plugin_name;
	}

	protected function get_plugin_basename( array $plugins, string $plugin_slug ): ?string {

		foreach ( $plugins as $plugin_basename => $plugin_data ) {
			if ( explode( '/', $plugin_basename )[0] === $plugin_slug ) {
				return $plugin_basename;
			}
		}

		return null;
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
