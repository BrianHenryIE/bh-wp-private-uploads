<?php

namespace BrianHenryIE\WP_Private_Uploads\Frontend;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File
 */
class Serve_Private_File_WPUnit_Test extends WPUnit_Testcase {

	protected function tearDown(): void {
		unset( $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
		parent::tearDown();
	}

	/**
	 * Create a `Serve_Private_File` whose settings use a unique private uploads subdirectory, and write a
	 * real file into it.
	 *
	 * @param string $contents The file contents to write.
	 * @return array{0:Serve_Private_File, 1:string, 2:string} [ SUT, requested relative path, absolute path ].
	 */
	protected function make_sut_with_file( string $contents = 'private file contents' ): array {

		$subdir = uniqid( 'servetest' );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => 'serve_test',
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		$upload   = wp_upload_dir();
		$relative = '2026/07/sample.txt';
		$absolute = $upload['basedir'] . '/' . $subdir . '/' . $relative;

		mkdir( dirname( $absolute ), 0777, true );
		file_put_contents( $absolute, $contents );

		$sut = new Serve_Private_File( $settings, $this->logger );

		return array( $sut, $relative, $absolute );
	}

	/**
	 * Invoke the protected decision method.
	 *
	 * @param Serve_Private_File $sut  The system under test.
	 * @param string             $file The requested file path relative to the private uploads directory.
	 */
	protected function get_response( Serve_Private_File $sut, string $file ): Private_File_Response {
		$reflection_method = new \ReflectionMethod( $sut, 'get_response_for_request' );
		$reflection_method->setAccessible( true );
		/** @var Private_File_Response $response */
		$response = $reflection_method->invoke( $sut, $file );
		return $response;
	}

	/**
	 * @covers ::get_response_for_request
	 */
	public function test_logged_out_user_is_redirected_to_login(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		wp_set_current_user( 0 );

		$response = $this->get_response( $sut, $file );

		$this->assertTrue( $response->redirect_to_login );
	}

	/**
	 * @covers ::get_response_for_request
	 */
	public function test_logged_in_non_admin_is_forbidden(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		$subscriber_id = wp_create_user( uniqid( 'subscriber' ), wp_generate_password(), uniqid( 'subscriber' ) . '@example.org' );
		assert( is_int( $subscriber_id ) );
		wp_set_current_user( $subscriber_id );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 403, $response->status_code );
		$this->assertFalse( $response->redirect_to_login );
	}

	/**
	 * An admin (`manage_options`) receives the file with the caching and length headers.
	 *
	 * @covers ::get_response_for_request
	 */
	public function test_admin_is_served_the_file_with_headers(): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 200, $response->status_code );
		$this->assertSame( $absolute, $response->file_to_stream );
		$this->assertSame( 'private file contents', file_get_contents( (string) $response->file_to_stream ) );

		$this->assertSame( 'text/plain', $response->headers['Content-Type'] );
		$this->assertSame( 'private, max-age=3600', $response->headers['Cache-Control'] );
		$this->assertSame( (string) filesize( $absolute ), $response->headers['Content-Length'] );
	}

	/**
	 * The `bh_wp_private_uploads_allow` filter can grant a non-admin access. It is passed the plugin slug
	 * and post type name so one callback can tell private uploads instances apart.
	 *
	 * @covers ::get_response_for_request
	 */
	public function test_allow_filter_grants_non_admin_access(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		$subscriber_id = wp_create_user( uniqid( 'subscriber' ), wp_generate_password(), uniqid( 'subscriber' ) . '@example.org' );
		assert( is_int( $subscriber_id ) );
		wp_set_current_user( $subscriber_id );

		$allow = function ( bool $should_serve_file, string $filename, string $plugin_slug, string $post_type_name ): bool {

			$this->assertSame( 'serve_test', $post_type_name );

			return true;
		};

		add_filter( 'bh_wp_private_uploads_allow', $allow, 10, 4 );

		$response = $this->get_response( $sut, $file );

		remove_filter( 'bh_wp_private_uploads_allow', $allow, 10 );

		$this->assertSame( 200, $response->status_code );
	}

	/**
	 * @covers ::get_response_for_request
	 */
	public function test_missing_file_returns_404(): void {

		list( $sut ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$response = $this->get_response( $sut, 'does/not/exist.txt' );

		$this->assertSame( 404, $response->status_code );
	}

	/**
	 * Traversal inputs must be sanitized so the resolved path cannot escape the private directory.
	 *
	 * @covers ::get_response_for_request
	 * @covers ::sanitize_filepath
	 */
	public function test_path_traversal_is_prevented(): void {

		list( $sut ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		// Would resolve to wp-config.php if `..` segments were honoured.
		$response = $this->get_response( $sut, '../../wp-config.php' );

		$this->assertSame( 404, $response->status_code );

		// The sanitized path must contain no `..` segment.
		$reflection_method = new \ReflectionMethod( $sut, 'sanitize_filepath' );
		$reflection_method->setAccessible( true );
		$sanitized = $reflection_method->invoke( $sut, 'foo/../../wp-config.php' );

		$this->assertIsString( $sanitized );
		$this->assertNotContains( '..', explode( '/', $sanitized ) );
	}

	/**
	 * @covers ::get_response_for_request
	 * @covers ::etag_matches
	 * @dataProvider provide_matching_if_none_match_wrapping
	 *
	 * @param string $wrap_left  Prefix wrapping the ETag in the `If-None-Match` header.
	 * @param string $wrap_right Suffix wrapping the ETag in the `If-None-Match` header.
	 */
	public function test_matching_if_none_match_returns_304( string $wrap_left, string $wrap_right ): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$etag = md5( gmdate( 'D, d M Y H:i:s T', (int) filemtime( $absolute ) ) );

		$_SERVER['HTTP_IF_NONE_MATCH'] = $wrap_left . $etag . $wrap_right;

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 304, $response->status_code );
		$this->assertNull( $response->file_to_stream );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function provide_matching_if_none_match_wrapping(): array {
		return array(
			'quoted' => array( '"', '"' ),
			'weak'   => array( 'W/"', '"' ),
		);
	}

	/**
	 * @covers ::get_response_for_request
	 * @covers ::is_not_modified_since
	 */
	public function test_if_modified_since_newer_returns_304(): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', (int) filemtime( $absolute ) + 10 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 304, $response->status_code );
	}

	/**
	 * @covers ::get_response_for_request
	 * @covers ::is_not_modified_since
	 */
	public function test_if_modified_since_stale_returns_200(): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', (int) filemtime( $absolute ) - 100 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 200, $response->status_code );
	}

	/**
	 * Per RFC 7232, when `If-None-Match` is present it takes precedence: a non-matching ETag serves the
	 * file (200) even if `If-Modified-Since` alone would produce a 304.
	 *
	 * @covers ::get_response_for_request
	 * @covers ::has_if_none_match
	 */
	public function test_if_none_match_takes_precedence_over_if_modified_since(): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		// Non-matching ETag, but an If-Modified-Since that is newer than the file.
		$_SERVER['HTTP_IF_NONE_MATCH']     = '"a-mismatched-etag"';
		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', (int) filemtime( $absolute ) + 10 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 200, $response->status_code );
	}
}
