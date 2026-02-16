<?php
/**
 * Class Plugin_Test. Tests the root plugin setup.
 *
 * @package BH_WP_Private_Uploads
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\API;

/**
 * Verifies the plugin has been instantiated and added to PHP's $GLOBALS variable.
 */
class Plugin_Integration_Test extends WPUnit_Testcase {

	/**
	 * Test the main plugin object is added to PHP's GLOBALS and that it is the correct class.
	 */
	public function test_plugin_instantiated(): void {

		$this->assertArrayHasKey( 'bh_wp_private_uploads_test_plugin', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['bh_wp_private_uploads_test_plugin'] );
	}
}
