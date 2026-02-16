<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;
use WP_Scripts;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets
 */
class Admin_Assets_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::__construct
	 * @covers ::register_script
	 */
	public function test_register_script(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => 'test',
			)
		);

		$sut = new Admin_Assets( $settings );

		$sut->register_script();

		/** @var WP_Scripts $wp_scripts */
		global $wp_scripts;

		$registered = $wp_scripts->query( 'test-private-uploads-media-library-js' );

		$this->assertNotFalse( $registered );
	}
}
