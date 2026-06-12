<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\API
 */
class API_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::get_last_checked_is_url_private
	 * @covers ::get_is_private_transient_name
	 */
	public function test_get_last_checked_is_url_private_happy_path(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		$happy_transient_value = new Is_Private_Result(
			url: 'success',
			is_private: true,
			http_response_code: 403,
			last_checked: new DateTimeImmutable(),
		);

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturn( $happy_transient_value );

		$result = $sut->get_last_checked_is_url_private();

		$this->assertEquals( 'success', $result?->url );
	}

	/**
	 * @covers ::get_last_checked_is_url_private
	 */
	public function test_get_last_checked_is_url_private_invalid_transient(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturn( 'bad value' );

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnTrue();

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_last_checked_is_url_private
	 */
	public function test_get_last_checked_is_url_private_error_deserializing_transient(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andThrow( new \TypeError( 'Something went wrong' ) );

		WP_Mock::userFunction( 'delete_transient' )
				->once()
				->with( $is_private_transient_name );

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnTrue();

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}


	/**
	 * @covers ::schedule_single_check_is_url_private
	 */
	public function test_get_last_checked_is_url_private_schedules_new_check(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_schedule_single_event' )
				->once()
				->with(
					\WP_Mock\Functions::type( 'int' ),
					'the_plugin_slug_private_uploads_check_url_the_post_type_name'
				);

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}

	/**
	 * When `wp_insert_attachment()` fails, the `WP_Error` should be wrapped in a `Private_Uploads_Exception`.
	 *
	 * @covers ::move_file_to_private_uploads_and_create_post
	 * @covers ::create_post_for_file
	 */
	public function test_create_post_for_file_throws_on_wp_error(): void {

		require_once codecept_root_dir( 'vendor/wordpress/wordpress/src/wp-includes/class-wp-error.php' );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-uploads-subdirectory',
				'get_post_type_name'            => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$tmp_file = tempnam( sys_get_temp_dir(), 'private-uploads-test-' );
		assert( false !== $tmp_file );
		file_put_contents( $tmp_file, 'file contents' );

		WP_Mock::userFunction( 'current_user_can' )
				->once()
				->with( 'upload_files' )
				->andReturnTrue();

		WP_Mock::userFunction( 'sanitize_file_name' )
				->once()
				->with( 'sample.pdf' )
				->andReturn( 'sample.pdf' );

		WP_Mock::userFunction( 'wp_check_filetype' )
				->once()
				->andReturn(
					array(
						'ext'  => 'pdf',
						'type' => 'application/pdf',
					)
				);

		WP_Mock::userFunction( 'wp_handle_upload' )
				->once()
				->andReturn(
					array(
						'file' => '/path/to/uploads/the-uploads-subdirectory/2026/06/sample.pdf',
						'url'  => 'https://example.org/wp-content/uploads/the-uploads-subdirectory/2026/06/sample.pdf',
						'type' => 'application/pdf',
					)
				);

		WP_Mock::userFunction( 'remove_filter' )
				->twice()
				->andReturnTrue();

		WP_Mock::userFunction( 'wp_basename' )
				->once()
				->andReturn( 'sample' );

		WP_Mock::userFunction( 'sanitize_text_field' )
				->once()
				->with( 'sample' )
				->andReturn( 'sample' );

		WP_Mock::userFunction( 'wp_insert_attachment' )
				->once()
				->andReturn( new \WP_Error() );

		WP_Mock::userFunction( 'is_wp_error' )
				->once()
				->andReturnTrue();

		$this->expectException( Private_Uploads_Exception::class );
		$this->expectExceptionMessageMatches( '/^Failed to create post for private upload/' );

		$sut->move_file_to_private_uploads_and_create_post(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);
	}

	/**
	 * The `upload_dir` filter should add the private subdirectory to `basedir`/`baseurl` and
	 * rebuild `path`/`url` from them, preserving the yyyy/mm `subdir`.
	 *
	 * @covers ::set_private_uploads_path
	 */
	public function test_set_private_uploads_path(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$upload_dir_data = array(
			'path'    => '/var/www/wp-content/uploads/2026/06',
			'url'     => 'https://example.org/wp-content/uploads/2026/06',
			'subdir'  => '/2026/06',
			'basedir' => '/var/www/wp-content/uploads',
			'baseurl' => 'https://example.org/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_private_uploads_path( $upload_dir_data );

		$this->assertSame( '/var/www/wp-content/uploads/the-private-directory', $result['basedir'] );
		$this->assertSame( 'https://example.org/wp-content/uploads/the-private-directory', $result['baseurl'] );
		$this->assertSame( '/var/www/wp-content/uploads/the-private-directory/2026/06', $result['path'] );
		$this->assertSame( 'https://example.org/wp-content/uploads/the-private-directory/2026/06', $result['url'] );

		// Unrelated keys should be unchanged.
		$this->assertSame( '/2026/06', $result['subdir'] );
		$this->assertFalse( $result['error'] );
	}

	/**
	 * When "uploads use year/month folders" is off, `subdir` is an empty string —
	 * `path`/`url` should then equal `basedir`/`baseurl`.
	 *
	 * @covers ::set_private_uploads_path
	 */
	public function test_set_private_uploads_path_empty_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$upload_dir_data = array(
			'path'    => '/var/www/wp-content/uploads',
			'url'     => 'https://example.org/wp-content/uploads',
			'subdir'  => '',
			'basedir' => '/var/www/wp-content/uploads',
			'baseurl' => 'https://example.org/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_private_uploads_path( $upload_dir_data );

		$this->assertSame( '/var/www/wp-content/uploads/the-private-directory', $result['basedir'] );
		$this->assertSame( '/var/www/wp-content/uploads/the-private-directory', $result['path'] );
		$this->assertSame( 'https://example.org/wp-content/uploads/the-private-directory', $result['url'] );
	}

	/**
	 * Point the `WP_CONTENT_DIR` constant at a test-controlled directory.
	 *
	 * `constant` is in patchwork.json's redefinable-internals (and is why the production code uses
	 * `constant( 'WP_CONTENT_DIR' )` rather than the bare constant). Restored by tearDown's
	 * `\Patchwork\restoreAll()`.
	 *
	 * @param string $wp_content_dir The directory to use as WP_CONTENT_DIR.
	 */
	protected function redefine_wp_content_dir( string $wp_content_dir ): void {
		\Patchwork\redefine(
			'constant',
			function ( string $name ) use ( $wp_content_dir ) {
				return 'WP_CONTENT_DIR' === $name ? $wp_content_dir : \Patchwork\relay();
			}
		);
	}

	/**
	 * When the directory already exists, it should not be created again (`wp_mkdir_p()` is not
	 * mocked, so calling it would be a fatal undefined-function error).
	 *
	 * @covers ::create_directory
	 */
	public function test_create_directory_already_exists(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$wp_content_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-content' );
		$expected_dir   = $wp_content_dir . '/uploads/the-private-directory';

		mkdir( $expected_dir, 0777, true );
		$this->redefine_wp_content_dir( $wp_content_dir );

		$result = $sut->create_directory();

		$this->assertSame( $expected_dir, $result->dir );
		$this->assertFalse( $result->created );
		$this->assertSame( 'Already exists', $result->message );

		rmdir( $expected_dir );
	}

	/**
	 * @covers ::create_directory
	 */
	public function test_create_directory_creates(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		// Never created on disk, so the `file_exists()` check is false.
		$wp_content_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-content' );
		$expected_dir   = $wp_content_dir . '/uploads/the-private-directory';

		$this->redefine_wp_content_dir( $wp_content_dir );

		WP_Mock::userFunction( 'wp_mkdir_p' )
				->once()
				->with( $expected_dir )
				->andReturnTrue();

		$result = $sut->create_directory();

		$this->assertSame( $expected_dir, $result->dir );
		$this->assertTrue( $result->created );
		$this->assertSame( 'Created', $result->message );
	}

	/**
	 * When `wp_mkdir_p()` fails, an exception should be thrown and an error logged.
	 *
	 * @covers ::create_directory
	 */
	public function test_create_directory_failure_logs_and_throws(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$wp_content_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-content' );

		$this->redefine_wp_content_dir( $wp_content_dir );

		WP_Mock::userFunction( 'wp_mkdir_p' )
				->once()
				->andReturnFalse();

		try {
			$sut->create_directory();
			$this->fail( 'Expected Private_Uploads_Exception' );
		} catch ( Private_Uploads_Exception $exception ) {
			$this->assertStringContainsString( 'Failed to create directory', $exception->getMessage() );
		}

		$this->assertTrue( $this->logger->hasErrorRecords() );
	}

	/**
	 * When the download step fails, the exception should propagate and no post should be created.
	 * (`wp_insert_attachment()` is not mocked, so reaching post-creation would be a fatal
	 * undefined-function error.)
	 *
	 * @covers ::download_remote_file_to_private_uploads_and_create_post
	 */
	public function test_download_remote_and_create_post_download_failure_creates_no_post(): void {

		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new API( $settings, $this->logger );

		WP_Mock::userFunction( 'current_user_can' )
				->once()
				->with( 'upload_files' )
				->andReturnFalse();

		$this->expectException( Private_Uploads_Exception::class );

		$sut->download_remote_file_to_private_uploads_and_create_post( file_url: 'https://example.org/file.pdf' );
	}
}
