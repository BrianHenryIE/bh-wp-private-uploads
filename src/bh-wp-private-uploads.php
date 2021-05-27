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
 * @package           BH_WP_Private_Uploads
 *
 * @wordpress-plugin
 * Plugin Name:       Private Uploads
 * Plugin URI:        http://github.com/username/bh-wp-private-uploads/
 * Description:       PHP proxy for files stored in `wp-content/uploads/private`.
 * Version:           2.0.1
 * Author:            BrianHenryIE
 * Author URI:        http://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bh-wp-private-uploads
 * Domain Path:       /languages
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\API;
use BrianHenryIE\WP_Private_Uploads\API\Settings;
use BrianHenryIE\WP_Private_Uploads\BrianHenryIE\WP_Logger\Logger;
use BrianHenryIE\WP_Private_Uploads\Includes\Activator;
use BrianHenryIE\WP_Private_Uploads\Includes\Deactivator;
use BrianHenryIE\WP_Private_Uploads\Includes\BH_WP_Private_Uploads;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once plugin_dir_path( __FILE__ ) . 'autoload.php';

/**
 * Current plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BH_WP_PRIVATE_UPLOADS_VERSION', '2.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-activator.php
 */
function activate_bh_wp_private_uploads() {

	Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-deactivator.php
 */
function deactivate_bh_wp_private_uploads() {

	Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'BH_WP_Private_Uploads\activate_bh_wp_private_uploads' );
register_deactivation_hook( __FILE__, 'BH_WP_Private_Uploads\deactivate_bh_wp_private_uploads' );


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function instantiate_bh_wp_private_uploads() {

	$settings = new Settings();

	$logger = Logger::instance( $settings );

	$api = new API( $settings, $logger );

	$plugin = new BH_WP_Private_Uploads( $api, $settings, $logger );

	return $api;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and frontend-facing site hooks.
 */
$GLOBALS['bh_wp_private_uploads'] = instantiate_bh_wp_private_uploads();

