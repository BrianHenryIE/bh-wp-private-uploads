<?php
/**
 * Settings for the test plugin.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

use BrianHenryIE\WP_Logger\Logger_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Trait;
use Psr\Log\LogLevel;

/**
 * Plugin settings, and settings for the logger and private uploads libraries.
 */
class Settings implements Settings_Interface, Logger_Settings_Interface, Private_Uploads_Settings_Interface {
	use Private_Uploads_Settings_Trait;

	/**
	 * The plugin log level.
	 *
	 * @see Logger_Settings_Interface::get_log_level()
	 * @see LogLevel
	 */
	public function get_log_level(): string {
		return LogLevel::DEBUG;
	}

	/**
	 * For friendly display.
	 *
	 * @see Logger_Settings_Interface::get_plugin_name()
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'Private Uploads Test Plugin';
	}

	/**
	 * The plugin basename, for adding the Logs link on plugins.php.
	 *
	 * @see Logger_Settings_Interface::get_plugin_basename()
	 */
	public function get_plugin_basename(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME' )
			? constant( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME' )
			: 'bh-wp-private-uploads-test-plugin/bh-wp-private-uploads-test-plugin.php';
	}

	/**
	 * The plugin version for asset caching.
	 *
	 * @see Settings_Interface::get_plugin_version()
	 */
	public function get_plugin_version(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION' )
			? constant( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION' )
			: '3.0.0';
	}

	/**
	 * The test plugin's slug as an identifier.
	 *
	 * @see Settings_Interface::get_plugin_slug()
	 * @see Logger_Settings_Interface::get_plugin_slug()
	 * @see Private_Uploads_Settings_Interface::get_plugin_slug()
	 */
	public function get_plugin_slug(): string {
		return 'bh-wp-private-uploads-test-plugin';
	}

	/**
	 * Configure private uploads to upload to `wp-content/uploads/test-plugin`.
	 *
	 * @see Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
	 */
	public function get_uploads_subdirectory_name(): string {
		return 'test-plugin';
	}

	/**
	 * Configure the WP CLI command base for the plugin's private uploads.
	 *
	 * `wp test_plugin upload ...`
	 *
	 * @see Private_Uploads_Settings_Interface::get_cli_base()
	 */
	public function get_cli_base(): ?string {
		return 'test_plugin upload';
	}

	/**
	 * The custom post type name for the private uploads.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key ()."
	 *
	 * @see Private_Uploads_Settings_Interface::get_post_type_name()
	 */
	public function get_post_type_name(): string {
		return 'test_plugin_private';
	}

	/**
	 * Add the private uploads meta box to the WooCommerce shop order edit page.
	 *
	 * @see Private_Uploads_Settings_Interface::get_meta_box_settings()
	 *
	 * @return array<string,array>
	 */
	public function get_meta_box_settings(): array {
		return array(
			'shop_order' => array(),
		);
	}
}
