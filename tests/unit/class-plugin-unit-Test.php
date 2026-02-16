<?php
/**
 * Tests for the root plugin file.
 *
 * @package BH_WP_Private_Uploads
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use BrianHenryIE\WP_Private_Uploads\API\API;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;

/**
 * Class Plugin_WP_Mock_Test
 *
 * @coversNothing
 */
class Plugin_Unit_Test extends Unit_Testcase {

	/**
	 * Verifies the plugin initialization.
	 */
	public function test_plugin_include(): void {

		// Prevents code-coverage counting, and removes the need to define the WordPress functions that are used in that class.
		\Patchwork\redefine(
			array( API::class, '__construct' ),
			function ( $setting, $logger ) {}
		);

		$plugin_root_dir = dirname( __DIR__, 2 );

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		\WP_Mock::userFunction(
			'plugin_basename',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => 'development-plugin/development-plugin.php',
			)
		);

		\WP_Mock::userFunction(
			'add_filter',
			array()
		);

		ob_start();

		include $plugin_root_dir . '/development-plugin/development-plugin.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );
	}
}
