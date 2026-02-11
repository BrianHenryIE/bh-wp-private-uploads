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

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Codeception\Test\Unit;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Menu
 */
class Admin_Menu_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
		WP_Mock::tearDown();
	}

	/**
	 * @covers ::remove_top_level_menu
	 */
	public function test_remove_top_level_menu(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'my_post_type',
			)
		);

		$sut = new Admin_Menu($settings);

		WP_Mock::userFunction( 'remove_menu_page' )
			->once()
			->with( 'edit.php?post_type=my_post_type' );

		$sut->remove_top_level_menu();
	}
}
