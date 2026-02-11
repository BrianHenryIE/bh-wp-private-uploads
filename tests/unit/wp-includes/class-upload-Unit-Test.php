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

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use Codeception\Test\Unit;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload
 */
class Upload_Unit_Test extends Unit {

	protected function setup(): void {
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::manage_upload_columns
	 */
	public function test_changes_author_column_to_owner(): void {

		global $pagenow;
		$pagenow = 'upload.php';

		\WP_Mock::passthruFunction( 'sanitize_key' );
		\WP_Mock::passthruFunction( 'wp_unslash' );

		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$_GET['post_type'] = 'private_media';

		$sut = new Upload( $settings );

		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Title',
			'author'   => 'Author',
			'parent'   => 'Uploaded to',
			'comments' => 'Comments',
			'date'     => 'Date',
		);

		$result = $sut->manage_upload_columns( $columns );

		$this->assertSame( 'Owner', $result['author'] );

		unset( $_GET['post_type'] );
	}

	/**
	 * @covers ::manage_upload_columns
	 */
	public function test_preserves_other_columns(): void {

		global $pagenow;
		$pagenow = 'upload.php';

		\WP_Mock::passthruFunction( 'sanitize_key' );
		\WP_Mock::passthruFunction( 'wp_unslash' );

		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$_GET['post_type'] = 'private_media';

		$sut = new Upload( $settings );

		$columns = array(
			'cb'       => '<input type="checkbox" />',
			'title'    => 'Title',
			'author'   => 'Author',
			'parent'   => 'Uploaded to',
			'comments' => 'Comments',
			'date'     => 'Date',
		);

		$result = $sut->manage_upload_columns( $columns );

		$this->assertSame( 'Title', $result['title'] );
		$this->assertSame( 'Date', $result['date'] );
		$this->assertSame( 'Uploaded to', $result['parent'] );

		unset( $_GET['post_type'] );
	}
}
