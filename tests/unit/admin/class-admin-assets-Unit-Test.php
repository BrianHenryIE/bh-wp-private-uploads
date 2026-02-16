<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets
 */
class Admin_Assets_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::register_script
	 */
	public function test_register_script_uses_plugin_slug_in_handle(): void {

		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => 'my-plugin',
			)
		);

		$sut = new Admin_Assets( $settings );

		WP_Mock::userFunction( 'plugins_url' )
			->once()
			->andReturn( 'http://example.com/wp-content/plugins/my-plugin/assets/bh-wp-private-uploads-admin.js' );

		WP_Mock::userFunction( 'wp_register_script' )
			->once()
			->with(
				'my-plugin-private-uploads-media-library-js',
				\WP_Mock\Functions::type( 'string' ),
				array( 'jquery' ),
				'1.0.0',
				true
			);

		$sut->register_script();
	}

	/**
	 * @covers ::register_script
	 */
	public function test_register_script_depends_on_jquery(): void {

		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => 'test-plugin',
			)
		);

		$sut = new Admin_Assets( $settings );

		WP_Mock::userFunction( 'plugins_url' )
			->once()
			->andReturn( 'http://example.com/assets/bh-wp-private-uploads-admin.js' );

		WP_Mock::userFunction( 'wp_register_script' )
			->once()
			->withArgs(
				fn( $handle, $src, $deps ) => array( 'jquery' ) === $deps
			);

		$sut->register_script();
	}

	/**
	 * @covers ::register_script
	 */
	public function test_register_script_loads_in_footer(): void {

		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => 'test-plugin',
			)
		);

		$sut = new Admin_Assets( $settings );

		WP_Mock::userFunction( 'plugins_url' )
			->once()
			->andReturn( 'http://example.com/assets/bh-wp-private-uploads-admin.js' );

		WP_Mock::userFunction( 'wp_register_script' )
			->once()
			->withArgs(
				fn( $handle, $src, $deps, $ver, $in_footer ) => true === $in_footer
			);

		$sut->register_script();
	}
}
