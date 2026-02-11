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

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Trait;
use Psr\Log\NullLogger;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	return;
}

require_once __DIR__ . '/../vendor/autoload.php';

define( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_VERSION', '3.0.0' );
define( 'BH_WP_PRIVATE_UPLOADS_DEVELOPMENT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$settings = new Development_Plugin_Settings();
new Example_Private_Uploads( $settings, new NullLogger() );

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
		// $args['description'] = 'Private uploads for my-plugin';    // Description as shown ... ? TODO: where is it shown?
		$args['show_in_menu'] = true;          // Should the admin menu Media submenu be displayed?
		// $args['label'] = 'My-plugin Files';    // The name for the admin menu Media submenu item.
		$args['show_in_rest'] = false;          // Default is true.
		// $args['rest_namespace'] = 'my-plugin/v1'; // Default is `plugin-slug/v1`.
		// $args['rest_base'] = 'uploads';        // Default is `uploads`.
		// $args['taxonomies'] = array();         // E.g. `category`, `post_tag`.
		// $args['delete_with_user'] = true;      // Delete all posts of this type authored by a user when that user is deleted.
		// ...
		return $args;
	},
	10,
	2
);


/**
 * Because the relative filepaths mapped inside Docker, we need to fix the plugin urls.
 *
 * `assets`, `include`, `vendor` are mapped to the wp-content/plugins directory, not the development-plugin subdir.
 *
 * @see .wp-env.json
 *
 * "http://localhost:8888/wp-content/plugins/includes/admin/assets/bh-wp-private-uploads-admin.js?ver=1.0.0"
 * should be
 * "http://localhost:8888/wp-content/plugins/assets/bh-wp-private-uploads-admin.js?ver=1.0.0"
 *
 * @see plugins_url()
 */
add_filter(
	'plugins_url',
	function ( string $url, string $_path, string $_plugin ): string {

		/** @phpstan-ignore-next-line phpstanWP.wpConstant.fetch */
		$url = str_replace( WP_PLUGIN_URL . '/includes/admin', WP_PLUGIN_URL . '/', $url );

		return $url;
	},
	10,
	3
);
