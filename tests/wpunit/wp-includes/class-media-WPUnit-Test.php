<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Codeception\TestCase\WPTestCase;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Private_Uploads\WP_Includes\Media
 */
class Media_WPUnit_Test extends WPTestCase {

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_prepends_subdirectory_to_basedir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media', $result['basedir'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_prepends_subdirectory_to_baseurl(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( 'http://example.com/wp-content/uploads/private-media', $result['baseurl'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_reconstructs_path_from_basedir_and_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media/2026/02', $result['path'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_reconstructs_url_from_baseurl_and_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( 'http://example.com/wp-content/uploads/private-media/2026/02', $result['url'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_removes_itself_from_upload_dir_filter(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		add_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) );

		$this->assertNotFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$sut->set_uploads_subdirectory( $uploads );

		$this->assertFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_handles_empty_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads',
			'url'     => 'http://example.com/wp-content/uploads',
			'subdir'  => '',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media', $result['path'] );
		$this->assertSame( 'http://example.com/wp-content/uploads/private-media', $result['url'] );
	}
}
