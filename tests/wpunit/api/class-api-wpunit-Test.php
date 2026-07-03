<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Post_Type;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\API
 */
class API_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * When there is no private uploads directory we don't really care if it's "public", since there's nothing to protect.
	 *
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_url_is_public_folder_missing(): void {

		$test_uploads_directory_name = uniqid( __FUNCTION__ );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name
		);

		assert( ! is_dir( $dir ) );

		$logger = $this->logger;

		$api = new API( $settings, $logger );

		$result = $api->check_and_update_is_url_private();

		$this->assertNull( $result );
	}


	/**
	 * When the directory is empty we don't really care if it's "public", since there's nothing to protect.
	 *
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_url_is_public_file_missing(): void {

		$test_uploads_directory_name = uniqid( __FUNCTION__ );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name
		);

		@mkdir( $dir, 0777, true );

		assert( is_dir( $dir ) );
		assert( 2 === count( scandir( $dir ) ) );

		$logger = $this->logger;

		$api = new API( $settings, $logger );

		$result = $api->check_and_update_is_url_private();

		$this->assertNull( $result );
	}

	/**
	 * If an error is returned when checking the URL, log the error.
	 *
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_check_url_wp_error(): void {

		$test_uploads_directory_name = uniqid( __FUNCTION__ );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
				'get_plugin_slug'               => 'test_check_url_wp_error',
			)
		);

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name
		);

		@mkdir( $dir, 0777, true );

		file_put_contents( "{$dir}index.php", '<?php', 0777 );

		assert( is_dir( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name ) );
		assert( file_exists( "{$dir}index.php" ) );

		$logger = $this->logger;
		$api    = new API( $settings, $logger );

		add_filter(
			'pre_http_request',
			fn() => new \WP_Error( '1', 'test_check_url_wp_error' )
		);

		$api->check_and_update_is_url_private();

		$this->assertTrue( $logger->hasInfoRecords() );

		unlink( $dir . 'index.php' );
		rmdir( $dir );
	}

	/**
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_is_url_protected_false(): void {

		$test_uploads_directory_name = uniqid( __FUNCTION__ );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
				'get_plugin_slug'               => 'test_check_url_wp_error',
			)
		);

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name
		);

		@mkdir( $dir, 0777, true );

		file_put_contents( "{$dir}index.php", '<?php', 0777 );

		assert( is_dir( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name ) );
		assert( file_exists( "{$dir}index.php" ) );

		$logger = $this->logger;
		$api    = new API( $settings, $logger );

		add_filter(
			'pre_http_request',
			fn() => array(
				'body'     => '',
				'response' => array(
					'code' => 200,
				),
			)
		);

		$result = $api->check_and_update_is_url_private();

		$this->assertNotNull( $result );
		$this->assertFalse( $result->is_private );
	}

	/**
	 *
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_is_url_protected_true(): void {

		$test_uploads_directory_name = uniqid( __FUNCTION__ );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
				'get_plugin_slug'               => 'test_check_url_wp_error',
			)
		);

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name
		);

		@mkdir( $dir, 0777, true );

		file_put_contents( "{$dir}index.php", '<?php', 0777 );

		assert( is_dir( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name ) );
		assert( file_exists( "{$dir}index.php" ) );

		$logger = $this->logger;
		$api    = new API( $settings, $logger );

		add_filter(
			'pre_http_request',
			fn() => array(
				'body'     => '',
				'response' => array(
					'code' => 403,
				),
			)
		);

		$result = $api->check_and_update_is_url_private();

		$this->assertTrue( $result?->is_private );
	}

	/**
	 * Create an API instance whose settings use a unique uploads subdirectory, and register its post type.
	 *
	 * @param string $test_uploads_directory_name Unique (per-test) private uploads subdirectory name.
	 */
	protected function get_api_with_post_type( string $test_uploads_directory_name ): API {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'               => 'bh-wp-private-uploads-test',
				'get_post_type_name'            => 'api_test_private',
				'get_post_type_label'           => 'API Test Uploads',
				'get_rest_base'                 => null,
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		( new Post_Type( $settings ) )->register_post_type();

		return new API( $settings, $this->logger );
	}

	/**
	 * The source file is consumed (`rename()`) by `wp_handle_upload()`, so copy the fixture to a temp path.
	 *
	 * @param string $fixture_filename Filename in `tests/_data/` to copy.
	 */
	protected function copy_fixture_to_tmp_file( string $fixture_filename ): string {

		/** @var string $project_root_dir */
		global $project_root_dir;

		$tmp_file = tempnam( sys_get_temp_dir(), 'private-uploads-test-' );
		assert( false !== $tmp_file );

		copy( "{$project_root_dir}/tests/_data/{$fixture_filename}", $tmp_file );

		return $tmp_file;
	}

	/**
	 * @covers ::move_file_to_private_uploads_and_create_post
	 * @covers ::create_post_for_file
	 */
	public function test_move_file_and_create_post_happy_path(): void {

		$test_uploads_directory_name = uniqid( 'movecreatehappy' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		$author_user_id = wp_create_user( uniqid( 'customer' ), wp_generate_password(), uniqid( 'customer' ) . '@example.org' );
		assert( is_int( $author_user_id ) );

		$parent_post_id = wp_insert_post( array( 'post_content' => 'attach the pdf to this post' ) );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$result = $api->move_file_to_private_uploads_and_create_post(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
			post_author_id: $author_user_id,
			post_parent_id: $parent_post_id,
		);

		$yyyymm = ( new \DateTimeImmutable() )->format( 'Y/m' );

		$expected_file_path = sprintf(
			'%s/uploads/%s/%s/sample.pdf',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name,
			$yyyymm
		);

		$this->assertSame( $expected_file_path, $result->file );
		$this->assertFileExists( $result->file );

		$post = get_post( $result->post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$this->assertSame( 'api_test_private', $post->post_type );
		$this->assertEquals( $author_user_id, $post->post_author );
		$this->assertSame( $parent_post_id, $post->post_parent );
		$this->assertSame( $result->url, $post->guid );
		$this->assertStringContainsString( "/uploads/{$test_uploads_directory_name}/", $post->guid );
		$this->assertSame( 'application/pdf', $post->post_mime_type );
		$this->assertSame( 'sample', $post->post_title );

		$this->assertSame(
			"{$test_uploads_directory_name}/{$yyyymm}/sample.pdf",
			get_post_meta( $result->post_id, '_wp_attached_file', true )
		);
	}

	/**
	 * When no author is specified, the post should have no owner, i.e. `post_author` = `0`,
	 * even when there is a logged-in user.
	 *
	 * @covers ::move_file_to_private_uploads_and_create_post
	 * @covers ::create_post_for_file
	 */
	public function test_create_post_no_author_defaults_to_zero(): void {

		$test_uploads_directory_name = uniqid( 'movecreatenoauthor' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$result = $api->move_file_to_private_uploads_and_create_post(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);

		$post = get_post( $result->post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$this->assertEquals( 0, $post->post_author );
		$this->assertSame( 0, $post->post_parent );
	}

	/**
	 * The created post should be returned by the query the media library grid uses,
	 * i.e. appear alongside posts created via the web UI upload flow.
	 *
	 * @see \BrianHenryIE\WP_Private_Uploads\WP_Includes\Media::set_query_post_type_to_cpt()
	 *
	 * @covers ::move_file_to_private_uploads_and_create_post
	 */
	public function test_created_post_appears_in_media_library_query(): void {

		$test_uploads_directory_name = uniqid( 'movecreatequery' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$result = $api->move_file_to_private_uploads_and_create_post(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);

		$query = new \WP_Query(
			array(
				'post_type'   => 'api_test_private',
				'post_status' => 'inherit',
			)
		);

		$queried_post_ids = wp_list_pluck( $query->posts, 'ID' );

		$this->assertContains( $result->post_id, $queried_post_ids );
	}

	/**
	 * @covers ::download_remote_file_to_private_uploads_and_create_post
	 * @covers ::create_post_for_file
	 */
	public function test_download_remote_and_create_post(): void {

		$test_uploads_directory_name = uniqid( 'downloadcreate' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		$author_user_id = wp_create_user( uniqid( 'customer' ), wp_generate_password(), uniqid( 'customer' ) . '@example.org' );
		assert( is_int( $author_user_id ) );

		/** @var string $project_root_dir */
		global $project_root_dir;
		$fixture_path = "{$project_root_dir}/tests/_data/sample.pdf";

		// `download_url()` streams the response body to a temp file, so the mocked response must write it.
		add_filter(
			'pre_http_request',
			function ( $preempt, array $parsed_args ) use ( $fixture_path ) {
				if ( isset( $parsed_args['filename'] ) && is_string( $parsed_args['filename'] ) ) {
					copy( $fixture_path, $parsed_args['filename'] );
				}
				return array(
					'body'     => '',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			10,
			2
		);

		$result = $api->download_remote_file_to_private_uploads_and_create_post(
			file_url: 'https://example.org/sample.pdf',
			post_author_id: $author_user_id,
		);

		$this->assertFileExists( $result->file );

		$post = get_post( $result->post_id );
		$this->assertInstanceOf( \WP_Post::class, $post );

		$this->assertSame( 'api_test_private', $post->post_type );
		$this->assertEquals( $author_user_id, $post->post_author );
		$this->assertSame( 'application/pdf', $post->post_mime_type );
	}

	/**
	 * Uploads happen on cron and WP-CLI where there is no logged-in user (user id 0). The API class must not
	 * require the `upload_files` capability – authorization is the caller's responsibility. This is the
	 * regression that motivated removing the capability check.
	 *
	 * @covers ::move_file_to_private_uploads
	 */
	public function test_move_file_to_private_uploads_succeeds_with_no_logged_in_user_during_cron(): void {

		$test_uploads_directory_name = uniqid( 'movenologinuser' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		// Simulate a cron request: no logged-in user.
		wp_set_current_user( 0 );
		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		assert( ! current_user_can( 'upload_files' ) );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$result = $api->move_file_to_private_uploads(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);

		$yyyymm = ( new \DateTimeImmutable() )->format( 'Y/m' );

		$expected_file_path = sprintf(
			'%s/uploads/%s/%s/sample.pdf',
			constant( 'WP_CONTENT_DIR' ),
			$test_uploads_directory_name,
			$yyyymm
		);

		$this->assertSame( $expected_file_path, $result->file );
		$this->assertFileExists( $result->file );
	}

	/**
	 * Consumer plugins can veto an upload via the `bh_wp_private_uploads_{post_type}_can_upload` filter.
	 *
	 * @covers ::move_file_to_private_uploads
	 */
	public function test_move_file_to_private_uploads_throws_when_can_upload_filter_returns_false(): void {

		$test_uploads_directory_name = uniqid( 'movecannotupload' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		add_filter( 'bh_wp_private_uploads_api_test_private_can_upload', '__return_false' );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$this->expectException( Private_Uploads_Exception::class );

		$api->move_file_to_private_uploads(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);
	}

	/**
	 * When `wp_handle_upload()` fails, the `upload_dir` filter must still be removed, otherwise every
	 * subsequent upload in the request is silently redirected into the private directory.
	 *
	 * @covers ::move_file_to_private_uploads
	 */
	public function test_upload_dir_filter_is_removed_when_wp_handle_upload_fails(): void {

		$test_uploads_directory_name = uniqid( 'uploaddirfilterremoved' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 1 );

		// Force `wp_handle_upload()` to fail by making `wp_upload_dir()` report an error (priority 20 runs
		// after the API's own priority-10 filter).
		$force_error = function ( array $uploads ): array {
			$uploads['error'] = 'Forced upload_dir error for test.';
			return $uploads;
		};
		add_filter( 'upload_dir', $force_error, 20 );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		try {
			$api->move_file_to_private_uploads( $tmp_file, 'sample.pdf' );
			$this->fail( 'Expected Private_Uploads_Exception' );
		} catch ( Private_Uploads_Exception $exception ) {
			$this->assertStringContainsString( 'Forced upload_dir error for test.', $exception->getMessage() );
		} finally {
			remove_filter( 'upload_dir', $force_error, 20 );
			// `wp_handle_upload()` was forced to fail, so the temp file was never moved – clean it up.
			if ( file_exists( $tmp_file ) ) {
				unlink( $tmp_file );
			}
		}

		$this->assertFalse( has_filter( 'upload_dir', array( $api, 'set_private_uploads_path' ) ) );

		// A subsequent plain `wp_upload_dir()` must return the non-private path.
		$upload = wp_upload_dir();
		$this->assertStringNotContainsString( $test_uploads_directory_name, $upload['basedir'] );
	}

	/**
	 * When `$_POST['action']` was not set before the call, it must be unset again afterwards, not left
	 * set to the library's internal `wp_handle_private_upload` value.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @covers ::move_file_to_private_uploads
	 */
	public function test_post_action_global_is_unset_after_upload_when_not_previously_set(): void {

		$api = $this->get_api_with_post_type( uniqid( 'postactionunset' ) );

		wp_set_current_user( 1 );

		unset( $_POST['action'] );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$api->move_file_to_private_uploads( $tmp_file, 'sample.pdf' );

		$this->assertArrayNotHasKey( 'action', $_POST );
	}

	/**
	 * When `$_POST['action']` was set before the call, its original value must be restored afterwards.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	 *
	 * @covers ::move_file_to_private_uploads
	 */
	public function test_post_action_global_is_restored_after_upload_when_previously_set(): void {

		$api = $this->get_api_with_post_type( uniqid( 'postactionrestore' ) );

		wp_set_current_user( 1 );

		$_POST['action'] = 'some_other_action';

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$api->move_file_to_private_uploads( $tmp_file, 'sample.pdf' );

		$this->assertSame( 'some_other_action', $_POST['action'] );

		unset( $_POST['action'] );
	}

	/**
	 * `create_directory()` must build the path from `wp_upload_dir()`, so it is created inside the
	 * relocated uploads directory on multisite / relocated-uploads installs (not `WP_CONTENT_DIR/uploads`).
	 *
	 * @covers ::create_directory
	 * @covers ::get_private_uploads_directory_path
	 */
	public function test_create_directory_uses_relocated_upload_dir(): void {

		$subdir            = uniqid( 'relocatedcreate' );
		$relocated_basedir = sys_get_temp_dir() . '/' . uniqid( 'relocated-uploads' );
		mkdir( $relocated_basedir, 0777, true );

		$relocate = function ( array $uploads ) use ( $relocated_basedir ): array {
			$uploads['basedir'] = $relocated_basedir;
			$uploads['baseurl'] = 'https://example.org/relocated-uploads';
			return $uploads;
		};
		add_filter( 'upload_dir', $relocate );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		$api = new API( $settings, $this->logger );

		$result = $api->create_directory();

		remove_filter( 'upload_dir', $relocate );

		$this->assertTrue( $result->created );
		$this->assertSame( "{$relocated_basedir}/{$subdir}", $result->dir );
		$this->assertDirectoryExists( "{$relocated_basedir}/{$subdir}" );

		rmdir( "{$relocated_basedir}/{$subdir}" );
		rmdir( $relocated_basedir );
	}

	/**
	 * `check_and_update_is_url_private()` must probe the URL built from `wp_upload_dir()`, so it targets
	 * the relocated uploads URL rather than `WP_CONTENT_URL/uploads`.
	 *
	 * @covers ::check_and_update_is_url_private
	 * @covers ::get_private_uploads_directory_path
	 * @covers ::get_private_uploads_directory_url
	 */
	public function test_check_url_uses_relocated_upload_dir_url(): void {

		$subdir            = uniqid( 'relocatedcheck' );
		$relocated_basedir = sys_get_temp_dir() . '/' . uniqid( 'relocated-uploads' );
		$relocated_baseurl = 'https://relocated.example.org/files';
		mkdir( "{$relocated_basedir}/{$subdir}", 0777, true );
		file_put_contents( "{$relocated_basedir}/{$subdir}/index.php", '<?php' );

		$relocate = function ( array $uploads ) use ( $relocated_basedir, $relocated_baseurl ): array {
			$uploads['basedir'] = $relocated_basedir;
			$uploads['baseurl'] = $relocated_baseurl;
			return $uploads;
		};
		add_filter( 'upload_dir', $relocate );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $subdir,
				'get_plugin_slug'               => 'relocated-test',
			)
		);

		$api = new API( $settings, $this->logger );

		$requested_url = null;
		$capture_url   = function ( $preempt, array $args, string $url ) use ( &$requested_url ) {
			$requested_url = $url;
			return array(
				'body'     => '',
				'response' => array( 'code' => 403 ),
			);
		};
		add_filter( 'pre_http_request', $capture_url, 10, 3 );

		$result = $api->check_and_update_is_url_private();

		remove_filter( 'pre_http_request', $capture_url, 10 );
		remove_filter( 'upload_dir', $relocate );

		$this->assertNotNull( $result );
		$this->assertStringStartsWith( "{$relocated_baseurl}/{$subdir}/", (string) $requested_url );

		unlink( "{$relocated_basedir}/{$subdir}/index.php" );
		rmdir( "{$relocated_basedir}/{$subdir}" );
		rmdir( $relocated_basedir );
	}
}
