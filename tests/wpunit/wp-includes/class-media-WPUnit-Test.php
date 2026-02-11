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

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_sets_post_type_to_cpt(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$data = array(
			'post_type'   => 'attachment',
			'post_title'  => 'sample.pdf',
			'post_status' => 'inherit',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), false );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_preserves_other_data_fields(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$data = array(
			'post_type'    => 'attachment',
			'post_title'   => 'sample.pdf',
			'post_status'  => 'inherit',
			'post_content' => 'file description',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), false );

		$this->assertSame( 'sample.pdf', $result['post_title'] );
		$this->assertSame( 'inherit', $result['post_status'] );
		$this->assertSame( 'file description', $result['post_content'] );
	}

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_sets_post_type_on_update(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$data = array(
			'post_type' => 'attachment',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), true );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_sets_query_post_type_to_cpt(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$query = array(
			'post_type' => 'attachment',
			's'         => 'search term',
			'order'     => 'DESC',
		);

		$result = $sut->set_query_post_type_to_cpt( $query );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_set_query_post_type_preserves_other_query_args(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$query = array(
			'post_type'      => 'attachment',
			's'              => 'search term',
			'order'          => 'DESC',
			'posts_per_page' => 40,
			'paged'          => 2,
		);

		$result = $sut->set_query_post_type_to_cpt( $query );

		$this->assertSame( 'search term', $result['s'] );
		$this->assertSame( 'DESC', $result['order'] );
		$this->assertSame( 40, $result['posts_per_page'] );
		$this->assertSame( 2, $result['paged'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_set_query_post_type_adds_post_type_when_absent(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings );

		$query = array(
			's' => 'search term',
		);

		$result = $sut->set_query_post_type_to_cpt( $query );

		$this->assertSame( 'private_media', $result['post_type'] );
	}
}
