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
 * Plugin Name:       Private Uploads Test Plugin
 * Plugin URI:        http://github.com/BrianHenryIE/bh-wp-private-uploads/
 * Description:       PHP proxy for files stored in `wp-content/uploads`.
 * Version:           3.0.0
 * Author:            BrianHenryIE
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wp-private-uploads
 * Domain Path:       /languages
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use BrianHenryIE\WP_Private_Uploads_Test_Plugin\API\API;
use BrianHenryIE\WP_Private_Uploads_Test_Plugin\API\Settings;
use BrianHenryIE\WP_Logger\Logger;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	return;
}

require_once __DIR__ . '/../vendor/autoload.php';

define( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION', '3.0.0' );
define( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function instantiate_bh_wp_private_uploads(): API {

	$settings = new Settings();

	$logger = Logger::instance( $settings );
	$api    = new API( $settings, $logger );


	return $api;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and frontend-facing site hooks.
 */
$GLOBALS['bh_wp_private_uploads_test_plugin'] = instantiate_bh_wp_private_uploads();

// Fix the symlinks in symlinks in symlinks.
add_filter(
	'plugins_url',
	function ( $url ): string {
		$plugin_slug = 'bh-wp-private-uploads';
		return preg_replace( "/(.*$plugin_slug)(.*\/$plugin_slug)(\/.*)/", '$1$3', $url );
	}
);

// TODO: move to bh-wp-logger
//add_filter( 'register_post_type_args', '\BrianHenryIE\WP_Private_Uploads_Test_Plugin\configure_my_private_uploads_post_type_logs', 10, 2 );
function configure_my_private_uploads_post_type_logs( array $args, string $post_type ): array {
//	$args['show_in_menu'] = true;
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
