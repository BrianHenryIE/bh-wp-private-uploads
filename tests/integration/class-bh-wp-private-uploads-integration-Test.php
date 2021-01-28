<?php
/**
 * Class Plugin_Test. Tests the root plugin setup.
 *
 * @package BH_WP_Private_Uploads
 * @author     BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BH_WP_Private_Uploads;

use BH_WP_Private_Uploads\includes\BH_WP_Private_Uploads;

/**
 * Verifies the plugin has been instantiated and added to PHP's $GLOBALS variable.
 */
class Plugin_Integration_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * Test the main plugin object is added to PHP's GLOBALS and that it is the correct class.
	 */
	public function test_plugin_instantiated() {

		$this->assertArrayHasKey( 'bh_wp_private_uploads', $GLOBALS );

		$this->assertInstanceOf( BH_WP_Private_Uploads::class, $GLOBALS['bh_wp_private_uploads'] );
	}

}
