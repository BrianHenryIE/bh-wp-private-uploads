<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://bhwp.ie
 *
 * @wordpress-plugin
 * Plugin Name:       Private Uploads Development Plugin
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-private-uploads/
 * Description:       PHP proxy for files stored in `wp-content/uploads`.
 * Version:           3.0.0
 * Author:            BrianHenryIE
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wp-private-uploads
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Development_Plugin;

use Alley_Interactive\Autoloader\Autoloader;
use Psr\Log\NullLogger;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	return;
}

require_once __DIR__ . '/../vendor/autoload.php';

define( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_VERSION', '3.0.0' );
define( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

Autoloader::generate(
	'BrianHenryIE\\WP_Private_Uploads',
	__DIR__ . '/../includes',
)->register();


$settings                                     = new Development_Plugin_Settings();
$GLOBALS['bh_wp_private_uploads_test_plugin'] = new Example_Private_Uploads( $settings, new NullLogger() );

/**
 * Here we filter the parameters for registering the post type.
 *
 * There are fair arguments to expect these set via the Settings class, and also to allow them to be configured through
 * the conventional WordPress filters.
 *
 * @see register_post_type()
 */
add_filter(
	'register_post_type_args',
	function ( array $args, string $post_type ) use ( $settings ): array {

		if ( $settings->get_post_type_name() !== $post_type ) {
				return $args;
		}
		$args['show_in_menu'] = true;          // Should the admin menu Media submenu be displayed?
		$args['show_in_rest'] = true;          // Default is true.
		// ...
		return $args;
	},
	10,
	2
);

// * Options.
// *
// * `show_in_menu`: Should the admin menu Media submenu be displayed?
// * `label`: The name for the admin menu Media submenu item.
// * `show_in_rest`: Default is true.
// * `rest_namespace`: Default is `plugin-slug/v1`.
// * `rest_base`: Default is `uploads`.
// * `taxonomies`: E.g. `category`, `post_tag`.
// * `delete_with_user`: Delete all posts of this type authored by a user when that user is deleted.
// *
// * @var array{show_in_menu:bool, label:string, show_in_rest:bool, rest_namespace:string, rest_base:string, taxonomies:mixed, delete_with_user:bool}

/**
 * Because of the relative filepaths mapped inside Docker, we need to fix the plugin urls.
 *
 * `assets`, `include`, `vendor` are mapped to the wp-content/plugins directory, not the development-plugin subdir.
 *
 * @see .wp-env.json
 *
 * "http://localhost:8888/wp-content/plugins/includes/admin/assets/bh-wp-private-uploads-admin.js?ver=1.0.0"
 * should be
 * "http://localhost:8888/wp-content/plugins/assets/bh-wp-private-uploads-admin.js?ver=1.0.0"
 *
 * @hooked plugins_url
 * @see plugins_url()
 * This is only called from that one core action (so I will just copy the param docs verbatim).
 *
 * @param string $url The complete URL to the plugins directory including scheme and path.
 * @param string $_path Path relative to the URL to the plugins directory. Blank string if no path is specified.
 * @param string $_plugin The plugin file path to be relative to. Blank string if no plugin is specified.
 */
$filter_correct_local_path = function ( string $url, string $_path, string $_plugin ): string {

	/** @phpstan-ignore-next-line phpstanWP.wpConstant.fetch */
	$url = str_replace( WP_PLUGIN_URL . '/includes/admin', WP_PLUGIN_URL . '/', $url );

	return $url;
};
add_filter( '$filter_correct_local_path', $filter_correct_local_path, 10, 3 );
