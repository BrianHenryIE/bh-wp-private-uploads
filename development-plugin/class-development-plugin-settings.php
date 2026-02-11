<?php
/**
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Development_Plugin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface as Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Trait;

class Development_Plugin_Settings implements Private_Uploads_Settings_Interface {

	use Private_Uploads_Settings_Trait;

	/**
	 * For friendly display.
	 */
	public function get_plugin_name(): string {
		return 'Private Uploads Development Plugin';
	}

	/**
	 * The plugin basename, for adding the Logs link on plugins.php.
	 */
	public function get_plugin_basename(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_BASENAME' ) && is_string( constant( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_BASENAME' ) )
			? constant( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_BASENAME' )
			: 'development-plugin/development-plugin.php';
	}

	/**
	 * The plugin version for asset caching.
	 *
	 * @see Settings_Interface::get_plugin_version()
	 */
	public function get_plugin_version(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_VERSION' )
			? constant( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_VERSION' )
			: '3.0.0';
	}

	/**
	 * The development plugin's slug as an identifier.
	 *
	 * @see Settings_Interface::get_plugin_slug()
	 * @see Private_Uploads_Settings_Interface::get_plugin_slug()
	 */
	public function get_plugin_slug(): string {
		return 'bh-wp-private-uploads-development-plugin';
	}

	/**
	 * Configure private uploads to upload to `wp-content/uploads/development-plugin`.
	 *
	 * @see Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
	 */
	public function get_uploads_subdirectory_name(): string {
		return 'private-media';
	}

	/**
	 * Configure the WP CLI command base for the plugin's private uploads.
	 *
	 * `wp my_plugin private_media upload ...`
	 *
	 * @see Private_Uploads_Settings_Interface::get_cli_base()
	 */
	public function get_cli_base(): ?string {
		return 'my_plugin private_media';
	}

	/**
	 * The custom post type name for the private uploads.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key ()."
	 *
	 * @see Private_Uploads_Settings_Interface::get_post_type_name()
	 */
	public function get_post_type_name(): string {
		return 'private_media';
	}

	/**
	 * Add the private uploads meta box to the WooCommerce shop order edit page.
	 *
	 * @see Private_Uploads_Settings_Interface::get_meta_box_settings()
	 *
	 * @return array<string,array<mixed>>
	 */
	public function get_meta_box_settings(): array {
		return array(
			'shop_order' => array(),
			'page'       => array(),
		);
	}
}
