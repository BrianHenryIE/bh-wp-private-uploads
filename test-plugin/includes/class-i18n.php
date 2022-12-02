<?php
/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\WP_Includes;

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    BH_WP_Private_Uploads
 * @subpackage BH_WP_Private_Uploads/includes
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */
class I18n {

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @hooked plugins_loaded
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain(): void {

		load_plugin_textdomain(
			'bh-wp-private-uploads',
			false,
			plugin_basename( dirname( __FILE__, 2 ) ) . '/languages/'
		);

	}

}
