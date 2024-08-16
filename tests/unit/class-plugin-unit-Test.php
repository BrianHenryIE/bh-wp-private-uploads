<?php
/**
 * Tests for the root plugin file.
 *
 * @package BH_WP_Private_Uploads
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use BrianHenryIE\WP_Logger\Logger;
use BrianHenryIE\WP_Private_Uploads\API\API;

/**
 * Class Plugin_WP_Mock_Test
 *
 * @coversNothing
 */
class Plugin_Unit_Test extends \Codeception\Test\Unit {

	protected function setup(): void {
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		\WP_Mock::tearDown();
		\Patchwork\restoreAll();
	}

	/**
	 * Verifies the plugin initialization.
	 */
	public function test_plugin_include(): void {

		// Prevents code-coverage counting, and removes the need to define the WordPress functions that are used in that class.
		\Patchwork\redefine(
			array( BH_WP_Private_Uploads_Test_Plugin::class, '__construct' ),
			function ( $api, $settings, $logger ) {}
		);

		\Patchwork\redefine(
			array( Logger::class, '__construct' ),
			function ( $setting ) {}
		);

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
				'times'  => 1,
			)
		);

		\WP_Mock::userFunction(
			'plugin_basename',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => 'bh-wp-private-uploads-test-plugin/bh-wp-private-uploads-test-plugin.php',
				'times'  => 1,
			)
		);

		ob_start();

		include $plugin_root_dir . '/example-plugin.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );

		$this->assertArrayHasKey( 'bh_wp_private_uploads_test_plugin', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['bh_wp_private_uploads_test_plugin'] );
	}
}
