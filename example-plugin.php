<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package    brianhenryie/bh-wp-private-uploads
 *
 * @wordpress-plugin
 * Plugin Name:       Private Uploads Example Plugin
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-private-uploads/
 * Description:       PHP proxy for files stored in `wp-content/uploads`.
 * Version:           3.0.0
 * Author:            BrianHenryIE
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use BrianHenryIE\WP_Logger\Logger_Settings_Interface;
use BrianHenryIE\WP_Logger\Logger_Settings_Trait;
use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Trait;
use BrianHenryIE\WP_Private_Uploads_Test_Plugin\API\Settings;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	return;
}

require_once __DIR__ . '/../vendor/autoload.php';

define( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION', '3.0.0' );
define( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );


$settings = new class() implements Logger_Settings_Interface, Private_Uploads_Settings_Interface {
	use Private_Uploads_Settings_Trait;
	use Logger_Settings_Trait;

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
};

class Example_Plugin extends \BrianHenryIE\WP_Private_Uploads\API\API {
	use LoggerAwareTrait;

	public function __construct( Settings $settings, LoggerInterface $logger ) {

		parent::__construct( $settings, $logger );

		new BH_WP_Private_Uploads_Hooks( $this, $settings, $logger );
	}

	/**
	 * @return array{is_private:bool}
	 */
	public function get_is_url_public_for_admin(): array {
		$url = WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		return $this->is_url_public_for_admin( $url );
	}

	/**
	 * @return array{url:string, is_private:bool|null, http_response_code?:int}
	 */
	public function get_is_url_private(): array {
		$url = WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		return $this->is_url_private( $url );
	}
}


// Fix the symlinks in symlinks in symlinks.
add_filter(
	'plugins_url',
	function ( $url ): string {
		$plugin_slug = 'bh-wp-private-uploads';
		return preg_replace( "/(.*$plugin_slug)(.*\/$plugin_slug)(\/.*)/", '$1$3', $url );
	}
);

// TODO: move to bh-wp-logger
// add_filter( 'register_post_type_args', '\BrianHenryIE\WP_Private_Uploads_Test_Plugin\configure_my_private_uploads_post_type_logs', 10, 2 );
function configure_my_private_uploads_post_type_logs( array $args, string $post_type ): array {
	// $args['show_in_menu'] = true;
	if ( 'bh-wp-privat_private' !== $post_type ) {
		return $args;
	}
	// $args['description'] = 'Private uploads for my-plugin';    // Description as shown ... ? TODO: where is it shown?
	$args['show_in_menu'] = false;          // Should the admin menu Media submenu be displayed?
	// $args['label'] = 'My-plugin Files';    // The name for the admin menu Media submenu item.
	$args['show_in_rest'] = false;          // Default is true.
	// $args['rest_namespace'] = 'my-plugin/v1'; // Default is `plugin-slug/v1`.
	// $args['rest_base'] = 'uploads';        // Default is `uploads`.
	// $args['taxonomies'] = array();         // E.g. `category`, `post_tag`.
	// $args['delete_with_user'] = true;      // Delete all posts of this type authored by a user when that user is deleted.
	// ...
	return $args;
}
