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
	 * @covers ::move_file_to_private_uploads_and_create_post
	 */
	public function test_move_file_and_create_post_throws_without_upload_files_capability(): void {

		$test_uploads_directory_name = uniqid( 'movecreatenocap' );

		$api = $this->get_api_with_post_type( $test_uploads_directory_name );

		wp_set_current_user( 0 );

		$tmp_file = $this->copy_fixture_to_tmp_file( 'sample.pdf' );

		$this->expectException( Private_Uploads_Exception::class );

		$api->move_file_to_private_uploads_and_create_post(
			tmp_file: $tmp_file,
			filename: 'sample.pdf',
		);
	}
}
