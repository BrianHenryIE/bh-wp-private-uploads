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
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'autoload.php';

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

	$api = new API( $settings, $logger );

	$plugin = new BH_WP_Private_Uploads_Test_Plugin( $api, $settings, $logger );

	add_action(
		'admin_notices',
		function() use ( $api ) {

			$private = $api->get_is_url_private();

			$admin_public = $api->get_is_url_public_for_admin();

			echo '<div class="notice notice-warning">
             <p>[Test Plugin] This notice appears on the settings page.</p>
         </div>';

		}
	);

	return $api;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and frontend-facing site hooks.
 */
$GLOBALS['bh_wp_private_uploads_test_plugin'] = instantiate_bh_wp_private_uploads();
