<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets
 */
class Admin_Assets_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::register_script
	 */
	public function test_register_script(): void {

		$sut = new Admin_Assets();

		$sut->register_script();

		global $wp_scripts;

		$registered = $wp_scripts->query( 'bh-wp-private-uploads-admin-js' );

		$this->assertNotFalse( $registered );
	}
}
