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

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Codeception\Test\Unit;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload
 */
class Upload_Unit_Test extends Unit {

	protected function setUp(): void {
		parent::setUp();
		WP_Mock::setUp();

		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::passthruFunction( 'wp_unslash' );
	}

	protected function tearDown(): void {
		WP_Mock::tearDown();
		parent::tearDown();
	}

	/**
	 * @covers ::manage_upload_columns
	 */
	public function test_changes_author_column_to_owner(): void {

		global $pagenow;
		$pagenow = 'upload.php';

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

		WP_Mock::passthruFunction( 'sanitize_key' );
		WP_Mock::passthruFunction( 'wp_unslash' );

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

	/**
	 * @dataProvider provider_replace_post_type_in_query
	 * @covers ::replace_post_type_in_query
	 */
	public function test_replace_post_type_in_query( string $input_query, string $expected ): void {
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'test_private',
			)
		);

		$sut = new Upload( $settings );

		$result = $sut->replace_post_type_in_query( $input_query );

		$this->assertSame( $expected, $result );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function provider_replace_post_type_in_query(): array {
		return array(
			array(
				<<<'EOD'
				SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
							FROM wp_posts
							WHERE post_type = 'attachment'
							ORDER BY post_date DESC
				EOD,

				<<<'EOD'
				SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
							FROM wp_posts
							WHERE post_type = 'test_private'
							ORDER BY post_date DESC
				EOD,
			),
			array(
				<<<'EOD'
				SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
							FROM wp_posts
							WHERE post_type
							=
							'attachment'
							ORDER BY post_date DESC
				EOD,
				<<<'EOD'
				SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
							FROM wp_posts
							WHERE post_type
							=
							'test_private'
							ORDER BY post_date DESC
				EOD,
			),
			array(
				'SELECT my_attachment FROM whatever',
				'SELECT my_attachment FROM whatever',
			),
			array(
				'SELECT something FROM whatever',
				'SELECT something FROM whatever',
			),
		);
	}
}
