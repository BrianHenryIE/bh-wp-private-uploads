<?php

namespace BrianHenryIE\WP_Private_Uploads\Frontend;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File
 */
class Serve_Private_File_Unit_Test extends Unit_Testcase {

	protected function make_sut(): Serve_Private_File {
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );
		return new Serve_Private_File( $settings, $this->logger );
	}

	/**
	 * Invoke a protected method on the SUT.
	 *
	 * @param Serve_Private_File $sut    The system under test.
	 * @param string             $method The protected method name to invoke.
	 * @param mixed              ...$args Arguments to pass to the method.
	 * @return mixed
	 */
	protected function invoke( Serve_Private_File $sut, string $method, ...$args ) {
		$reflection_method = new \ReflectionMethod( $sut, $method );
		$reflection_method->setAccessible( true );
		return $reflection_method->invoke( $sut, ...$args );
	}

	/**
	 * The ETag is sent quoted, so an `If-None-Match` value with surrounding quotes and/or a `W/` weak
	 * prefix must still match the raw hash. This is the previously-broken 304 branch.
	 *
	 * @covers ::etag_matches
	 * @dataProvider provide_matching_if_none_match
	 *
	 * @param string $if_none_match_header The `If-None-Match` request header value.
	 */
	public function test_etag_matches( string $if_none_match_header ): void {

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$_SERVER['HTTP_IF_NONE_MATCH'] = $if_none_match_header;

		$sut = $this->make_sut();

		$this->assertTrue( $this->invoke( $sut, 'etag_matches', 'abc123def456' ) );

		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function provide_matching_if_none_match(): array {
		return array(
			'plain'  => array( 'abc123def456' ),
			'quoted' => array( '"abc123def456"' ),
			'weak'   => array( 'W/"abc123def456"' ),
		);
	}

	/**
	 * @covers ::etag_matches
	 */
	public function test_etag_does_not_match_different_hash(): void {

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$_SERVER['HTTP_IF_NONE_MATCH'] = '"a-different-hash"';

		$sut = $this->make_sut();

		$this->assertFalse( $this->invoke( $sut, 'etag_matches', 'abc123def456' ) );

		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * @covers ::etag_matches
	 */
	public function test_etag_no_header_does_not_match(): void {

		unset( $_SERVER['HTTP_IF_NONE_MATCH'] );

		$sut = $this->make_sut();

		$this->assertFalse( $this->invoke( $sut, 'etag_matches', 'abc123def456' ) );
	}

	/**
	 * @covers ::is_not_modified_since
	 */
	public function test_is_not_modified_since_when_header_newer(): void {

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$file_mtime = 1_700_000_000;

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', $file_mtime + 100 );

		$sut = $this->make_sut();

		$this->assertTrue( $this->invoke( $sut, 'is_not_modified_since', $file_mtime ) );

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	}

	/**
	 * @covers ::is_not_modified_since
	 */
	public function test_is_modified_since_when_header_stale(): void {

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$file_mtime = 1_700_000_000;

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = gmdate( 'D, d M Y H:i:s T', $file_mtime - 100 );

		$sut = $this->make_sut();

		$this->assertFalse( $this->invoke( $sut, 'is_not_modified_since', $file_mtime ) );

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	}

	/**
	 * An unparseable `If-Modified-Since` header must be treated as "modified" (serve the file) and logged.
	 *
	 * @covers ::is_not_modified_since
	 */
	public function test_is_not_modified_since_unparseable_header_logs_and_returns_false(): void {

		WP_Mock::passthruFunction( 'wp_unslash' );
		WP_Mock::passthruFunction( 'sanitize_text_field' );

		$_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'not a date';

		$sut = $this->make_sut();

		$this->assertFalse( $this->invoke( $sut, 'is_not_modified_since', 1_700_000_000 ) );
		$this->assertTrue( $this->logger->hasWarningRecords() );

		unset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
	}
}
