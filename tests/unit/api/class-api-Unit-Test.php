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
	 * Mock `wp_upload_dir( null, false )` to report a test-controlled uploads `basedir`, so the
	 * private-directory path is built from `wp_upload_dir()` (multisite / relocated-uploads safe)
	 * rather than a hard-coded constant.
	 *
	 * @param string $basedir The directory to use as the uploads `basedir`.
	 */
	protected function mock_wp_upload_dir( string $basedir ): void {
		WP_Mock::userFunction( 'wp_upload_dir' )
				->with( null, false )
				->andReturn(
					array(
						'basedir' => $basedir,
						'baseurl' => 'https://example.org/wp-content/uploads',
					)
				);
	}

	/**
	 * When the directory already exists, it should not be created again (`wp_mkdir_p()` is not
	 * mocked, so calling it would be a fatal undefined-function error).
	 *
	 * @covers ::create_directory
	 * @covers ::get_private_uploads_directory_path
	 */
	public function test_create_directory_already_exists(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$basedir      = sys_get_temp_dir() . '/' . uniqid( 'uploads' );
		$expected_dir = $basedir . '/the-private-directory';

		mkdir( $expected_dir, 0777, true );
		$this->mock_wp_upload_dir( $basedir );

		WP_Mock::userFunction( 'doing_action' )
				->with( 'init' )
				->andReturnFalse();

		$result = $sut->create_directory();

		$this->assertSame( $expected_dir, $result->dir );
		$this->assertFalse( $result->created );
		$this->assertSame( 'Already exists', $result->message );

		rmdir( $expected_dir );
	}

	/**
	 * @covers ::create_directory
	 * @covers ::get_private_uploads_directory_path
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
		$basedir      = sys_get_temp_dir() . '/' . uniqid( 'uploads' );
		$expected_dir = $basedir . '/the-private-directory';

		$this->mock_wp_upload_dir( $basedir );

		WP_Mock::userFunction( 'doing_action' )
				->with( 'init' )
				->andReturnFalse();

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
	 * `create_directory()` is hooked directly on `init`, so a failure must not fatal the site: it is
	 * logged and returned as an unsuccessful result rather than thrown.
	 *
	 * @covers ::create_directory
	 */
	public function test_create_directory_failure_logs_and_returns_result(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$wp_content_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-content' );
		$expected_dir   = $wp_content_dir . '/uploads/the-private-directory';
		$basedir = sys_get_temp_dir() . '/' . uniqid( 'uploads' );

		$this->mock_wp_upload_dir( $basedir );

		WP_Mock::userFunction( 'doing_action' )
				->with( 'init' )
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_mkdir_p' )
				->once()
				->andReturnFalse();

		$result = $sut->create_directory();

		$this->assertSame( $expected_dir, $result->dir );
		$this->assertFalse( $result->created );
		$this->assertStringContainsString( 'Failed to create directory', $result->message );

		$this->assertTrue( $this->logger->hasErrorRecords() );
	}

	/**
	 * On a frontend page load there is no need to touch the filesystem – the directory is created
	 * lazily when a file is actually uploaded.
	 *
	 * `wp_mkdir_p()` is not mocked, so reaching it would be a fatal undefined-function error.
	 *
	 * @covers ::create_directory
	 */
	public function test_create_directory_skipped_on_frontend_init(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'the-private-directory',
			)
		);

		$sut = new API( $settings, $this->logger );

		$wp_content_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-content' );
		$expected_dir   = $wp_content_dir . '/uploads/the-private-directory';

		$this->redefine_wp_content_dir( $wp_content_dir );

		WP_Mock::userFunction( 'doing_action' )
				->with( 'init' )
				->andReturnTrue();

		WP_Mock::userFunction( 'is_admin' )
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_doing_cron' )
				->andReturnFalse();

		$result = $sut->create_directory();

		$this->assertSame( $expected_dir, $result->dir );
		$this->assertFalse( $result->created );
		$this->assertSame( 'Possibly a frontend request', $result->message );
	}

	/**
	 * When the download step fails, the exception should propagate and no post should be created.
	 * (`wp_insert_attachment()` is not mocked, so reaching post-creation would be a fatal
	 * undefined-function error.)
	 *
	 * @covers ::download_remote_file_to_private_uploads_and_create_post
	 * @covers ::download_remote_file_to_private_uploads
	 */
	public function test_download_remote_and_create_post_download_failure_creates_no_post(): void {

		require_once codecept_root_dir( 'vendor/wordpress/wordpress/src/wp-includes/class-wp-error.php' );

		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new API( $settings, $this->logger );

		WP_Mock::userFunction( 'download_url' )
				->once()
				->with( 'https://example.org/file.pdf' )
				->andReturn( new \WP_Error( 'http_404', 'Not found' ) );

		WP_Mock::userFunction( 'is_wp_error' )
				->once()
				->andReturnTrue();

		$this->expectException( Private_Uploads_Exception::class );

		$sut->download_remote_file_to_private_uploads_and_create_post( file_url: 'https://example.org/file.pdf' );
	}
}
