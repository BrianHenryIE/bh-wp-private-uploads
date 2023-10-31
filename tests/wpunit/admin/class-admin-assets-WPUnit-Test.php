<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets
 */
class Admin_Assets_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::register_script
	 */
	public function test_register_script(): void {

		$settings = self::makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => 'test',
			)
		);

		$sut = new Admin_Assets( $settings );

		$sut->register_script();

		global $wp_scripts;

		$registered = $wp_scripts->query( 'test-private-uploads-media-library-js' );

		$this->assertNotFalse( $registered );
	}
}
