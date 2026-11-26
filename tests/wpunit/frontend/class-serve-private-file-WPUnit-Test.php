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
	 * @param string $filename The name of the file to write.
	 * @return array{0:Serve_Private_File, 1:string, 2:string} [ SUT, requested relative path, absolute path ].
	 */
	protected function make_sut_with_file( string $contents = 'private file contents', string $filename = 'sample.txt' ): array {

		$subdir = uniqid( 'servetest' );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'               => 'serve-test-plugin',
				'get_post_type_name'            => 'serve_test',
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		$upload   = wp_upload_dir();
		$relative = '2026/07/' . $filename;
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

		$allow = function ( bool $should_serve_file, string $plugin_slug, string $post_type_name, string $filename ): bool {

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

	/**
	 * @return array<string, array{0:string, 1:string, 2:string, 3:bool}> [ filename, file contents, expected Content-Disposition, expect a Content-Security-Policy ].
	 */
	public function provide_files_and_expected_dispositions(): array {
		$html = '<html><body><script>alert(1)</script></body></html>';
		$svg  = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

		return array(
			'html is downloaded'                        => array( 'page.html', $html, 'attachment; filename="page.html"', false ),
			'svg is downloaded'                         => array( 'image.svg', $svg, 'attachment; filename="image.svg"', false ),
			'html with an unknown extension is sniffed' => array( 'page.unknownextension', $html, 'attachment; filename="page.unknownextension"', false ),
			'zip is downloaded'                         => array( 'archive.zip', 'zip', 'attachment; filename="archive.zip"', false ),
			'jpg is displayed'                          => array( 'photo.jpg', 'jpg', 'inline; filename="photo.jpg"', true ),
			'png is displayed'                          => array( 'photo.png', 'png', 'inline; filename="photo.png"', true ),
			'txt is displayed'                          => array( 'notes.txt', 'txt', 'inline; filename="notes.txt"', true ),
			'pdf is displayed'                          => array( 'doc.pdf', '%PDF-1.4', 'inline; filename="doc.pdf"', false ),
			'mp3 is displayed'                          => array( 'audio.mp3', 'mp3', 'inline; filename="audio.mp3"', false ),
			'mp4 is displayed'                          => array( 'video.mp4', 'mp4', 'inline; filename="video.mp4"', false ),
		);
	}

	/**
	 * Files with active content (HTML, SVG) must never be rendered on the site's origin: only an allowlist
	 * of types is displayed inline, and the browser is told not to sniff a different type.
	 *
	 * @covers ::get_response_for_request
	 * @covers ::get_content_disposition
	 * @covers ::is_inline_mime_type
	 * @dataProvider provide_files_and_expected_dispositions
	 *
	 * @param string $filename                        The name of the private file.
	 * @param string $contents                        The contents of the private file.
	 * @param string $expected_content_disposition    The expected `Content-Disposition` header.
	 * @param bool   $expects_content_security_policy Whether a `Content-Security-Policy` header is expected.
	 */
	public function test_anti_sniffing_and_disposition_headers( string $filename, string $contents, string $expected_content_disposition, bool $expects_content_security_policy ): void {

		list( $sut, $file ) = $this->make_sut_with_file( $contents, $filename );

		wp_set_current_user( 1 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 200, $response->status_code );
		$this->assertSame( 'nosniff', $response->headers['X-Content-Type-Options'] ?? null );
		$this->assertSame( $expected_content_disposition, $response->headers['Content-Disposition'] ?? null );

		if ( $expects_content_security_policy ) {
			$this->assertSame( "sandbox; default-src 'none'", $response->headers['Content-Security-Policy'] ?? null );
		} else {
			$this->assertArrayNotHasKey( 'Content-Security-Policy', $response->headers );
		}
	}

	/**
	 * A non-ASCII filename gets an ASCII-safe `filename` and an RFC 5987 encoded `filename*`.
	 *
	 * @covers ::get_content_disposition
	 */
	public function test_content_disposition_encodes_non_ascii_filename(): void {

		list( $sut ) = $this->make_sut_with_file();

		$reflection_method = new \ReflectionMethod( $sut, 'get_content_disposition' );
		$reflection_method->setAccessible( true );

		// 日本語.zip – `sanitize_file_name()` transliterates accented Latin characters, so they would not exercise this.
		$result = $reflection_method->invoke( $sut, 'application/zip', "2026/07/\u{65E5}\u{672C}\u{8A9E}.zip" );

		$this->assertSame( "attachment; filename=\"___.zip\"; filename*=UTF-8''%E6%97%A5%E6%9C%AC%E8%AA%9E.zip", $result );
	}

	/**
	 * @covers ::get_response_for_request
	 */
	public function test_304_response_has_nosniff_header(): void {

		list( $sut, $file, $absolute ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', (int) filemtime( $absolute ) + 10 );

		$response = $this->get_response( $sut, $file );

		$this->assertSame( 304, $response->status_code );
		$this->assertSame( 'nosniff', $response->headers['X-Content-Type-Options'] ?? null );
	}

	/**
	 * The `bh_wp_private_uploads_cache_control_max_age` filter sets the `Cache-Control` max-age, and the
	 * `Expires` header agrees with it.
	 *
	 * @covers ::get_response_for_request
	 * @covers ::get_cache_control_max_age
	 */
	public function test_cache_control_max_age_filter(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$set_max_age = function ( int $max_age_seconds, string $plugin_slug, string $post_type_name, string $filename ) use ( $file ): int {

			$this->assertSame( HOUR_IN_SECONDS, $max_age_seconds );
			$this->assertSame( 'serve-test-plugin', $plugin_slug );
			$this->assertSame( 'serve_test', $post_type_name );
			$this->assertSame( $file, $filename );

			return 60;
		};
		add_filter( 'bh_wp_private_uploads_cache_control_max_age', $set_max_age, 10, 4 );

		$time_before = time();
		$response    = $this->get_response( $sut, $file );
		$time_after  = time();

		remove_filter( 'bh_wp_private_uploads_cache_control_max_age', $set_max_age, 10 );

		$this->assertSame( 'private, max-age=60', $response->headers['Cache-Control'] );

		$expires = strtotime( $response->headers['Expires'] );
		$this->assertGreaterThanOrEqual( $time_before + 60, $expires );
		$this->assertLessThanOrEqual( $time_after + 60, $expires );
	}

	/**
	 * @return array<string, array{0:mixed, 1:string}>
	 */
	public function provide_max_age_filter_return_values(): array {
		return array(
			'zero disables caching'              => array( 0, 'private, max-age=0' ),
			'numeric string is accepted'         => array( '120', 'private, max-age=120' ),
			'negative falls back to the default' => array( -1, 'private, max-age=3600' ),
			'non-numeric falls back to default'  => array( 'forever', 'private, max-age=3600' ),
			'null falls back to the default'     => array( null, 'private, max-age=3600' ),
		);
	}

	/**
	 * @covers ::get_cache_control_max_age
	 * @dataProvider provide_max_age_filter_return_values
	 *
	 * @param mixed  $filter_return_value    What a consumer's filter returns.
	 * @param string $expected_cache_control The expected `Cache-Control` header.
	 */
	public function test_cache_control_max_age_filter_return_value_is_validated( $filter_return_value, string $expected_cache_control ): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$set_max_age = fn() => $filter_return_value;
		add_filter( 'bh_wp_private_uploads_cache_control_max_age', $set_max_age );

		$response = $this->get_response( $sut, $file );

		remove_filter( 'bh_wp_private_uploads_cache_control_max_age', $set_max_age );

		$this->assertSame( $expected_cache_control, $response->headers['Cache-Control'] );
	}

	/**
	 * The `bh_wp_private_uploads_response_headers` filter runs last and can add, change and remove headers.
	 *
	 * @covers ::get_response_for_request
	 * @covers ::filter_response_headers
	 */
	public function test_response_headers_filter(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		$filter_headers = function ( array $headers, string $plugin_slug, string $post_type_name, string $filename ) use ( $file ): array {

			$this->assertSame( 'nosniff', $headers['X-Content-Type-Options'] );
			$this->assertArrayHasKey( 'Content-Length', $headers );
			$this->assertSame( 'serve-test-plugin', $plugin_slug );
			$this->assertSame( 'serve_test', $post_type_name );
			$this->assertSame( $file, $filename );

			$headers['Content-Disposition'] = 'attachment; filename="renamed.txt"';
			$headers['X-Robots-Tag']        = 'noindex';
			$headers['X-Injected']          = "value\r\nSet-Cookie: injected=1";
			$headers['X-Not-A-String']      = array( 'dropped' );
			unset( $headers['Content-Security-Policy'] );

			return $headers;
		};
		add_filter( 'bh_wp_private_uploads_response_headers', $filter_headers, 10, 4 );

		$response = $this->get_response( $sut, $file );

		remove_filter( 'bh_wp_private_uploads_response_headers', $filter_headers, 10 );

		$this->assertSame( 200, $response->status_code );
		$this->assertSame( 'attachment; filename="renamed.txt"', $response->headers['Content-Disposition'] );
		$this->assertSame( 'noindex', $response->headers['X-Robots-Tag'] );
		$this->assertArrayNotHasKey( 'Content-Security-Policy', $response->headers );
		$this->assertArrayNotHasKey( 'X-Not-A-String', $response->headers );
		$this->assertSame( 'valueSet-Cookie: injected=1', $response->headers['X-Injected'] );
	}

	/**
	 * A filter that returns something other than an array must not remove the security headers.
	 *
	 * @covers ::filter_response_headers
	 */
	public function test_response_headers_filter_returning_non_array_is_ignored(): void {

		list( $sut, $file ) = $this->make_sut_with_file();

		wp_set_current_user( 1 );

		add_filter( 'bh_wp_private_uploads_response_headers', '__return_null' );

		$response = $this->get_response( $sut, $file );

		remove_filter( 'bh_wp_private_uploads_response_headers', '__return_null' );

		$this->assertSame( 200, $response->status_code );
		$this->assertSame( 'nosniff', $response->headers['X-Content-Type-Options'] ?? null );
	}
}
