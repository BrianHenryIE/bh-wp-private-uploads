<?php
/**
 * Tests for the root plugin file.
 *
 * @package BH_WP_Private_Uploads
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\API;

/**
 * Class Plugin_WP_Mock_Test
 *
 * @coversNothing
 */
class Plugin_Unit_Test extends \Codeception\Test\Unit {

	protected function _before() {
		\WP_Mock::setUp();
	}

	/**
	 * Verifies the plugin initialization.
	 */
	public function test_plugin_include() {

		$plugin_root_dir = dirname( __DIR__, 2 ) . '/src';

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		\WP_Mock::userFunction(
			'register_activation_hook'
		);

		\WP_Mock::userFunction(
			'register_deactivation_hook'
		);

		\WP_Mock::userFunction(
			'get_current_user_id',
			array(
				'return' => 0
			)
		);

		\WP_Mock::userFunction(
			'wp_normalize_path',
			array(
				'return_arg' => 0
			)
		);

        \WP_Mock::userFunction(
            'is_admin',
            array(
                'return_arg' => false
            )
        );

        \WP_Mock::userFunction(
            'get_current_user_id'
        );

        \WP_Mock::userFunction(
            'wp_normalize_path',
            array(
                'return_arg' => true
            )
        );

		require_once $plugin_root_dir . '/bh-wp-private-uploads.php';

		$this->assertArrayHasKey( 'bh_wp_private_uploads', $GLOBALS );

		$this->assertInstanceOf( API::class, $GLOBALS['bh_wp_private_uploads'] );

	}


	/**
	 * Verifies the plugin does not output anything to screen.
	 */
	public function test_plugin_include_no_output() {

		$plugin_root_dir = dirname( __DIR__, 2 ) . '/src';

		\WP_Mock::userFunction(
			'plugin_dir_path',
			array(
				'args'   => array( \WP_Mock\Functions::type( 'string' ) ),
				'return' => $plugin_root_dir . '/',
			)
		);

		\WP_Mock::userFunction(
			'register_activation_hook'
		);

		\WP_Mock::userFunction(
			'register_deactivation_hook'
		);

		ob_start();

		require_once $plugin_root_dir . '/bh-wp-private-uploads.php';

		$printed_output = ob_get_contents();

		ob_end_clean();

		$this->assertEmpty( $printed_output );

	}

}
