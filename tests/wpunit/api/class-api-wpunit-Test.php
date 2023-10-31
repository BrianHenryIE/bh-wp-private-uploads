<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\API
 */
class API_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * If an error is returned when checking the URL, log the error.
	 *
	 * @covers ::check_url
	 */
	public function test_check_url_wp_error(): void {

		$this->markTestSkipped();

		$test_uploads_directory_name = 'test-dir';
		$url                         = 'http://localhost:8080/bh-wp-private-uploads/' . $test_uploads_directory_name;

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
				'get_plugin_slug'               => 'test_check_url_wp_error',
			)
		);

		$logger = new ColorLogger();
		$api    = new API( $settings, $logger );

		add_filter(
			'pre_http_request',
			function () {
				return new \WP_Error( '1', 'test_check_url_wp_error' );
			}
		);

		$reflection = new \ReflectionClass( API::class );
		$method     = $reflection->getMethod( 'check_url' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( $api, array( $url ) );

		$this->assertTrue( $logger->hasError( 'test_check_url_wp_error' ) );
	}

	/**
	 * @covers ::is_url_private
	 */
	public function test_is_url_protected_false(): void {

		add_filter(
			'site_url',
			function () {
				return 'http://localhost:8080/bh-wp-private-uploads';
			}
		);

		$test_uploads_directory_name = 'test-private-dir';

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
				'get_plugin_slug'               => 'test_is_url_protected_false',
			)
		);

		delete_transient( "bh_wp_private_uploads_{$settings->get_uploads_subdirectory_name()}_is_private" );

		@mkdir( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name );

		$logger = new ColorLogger();

		$api = new API( $settings, $logger );

		$htaccess_file = WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name . '/.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			unlink( $htaccess_file );
		}

		$url = WP_CONTENT_URL . '/uploads/' . $test_uploads_directory_name;

		$reflection = new \ReflectionClass( API::class );
		$method     = $reflection->getMethod( 'is_url_private' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( $api, array( $url ) );

		$is_private = $result['is_private'];

		$this->assertFalse( $is_private );
	}

	/**
	 * I'm not sure if .hataccess is respected during unit tests.
	 *
	 * @uses get_site_url()
	 * @covers ::add_protecting_htaccess
	 */
	public function test_is_url_protected_true(): void {

		$this->markTestSkipped( 'Change of approach makes this test redundant.' );

		add_filter(
			'site_url',
			function () {
				return 'http://localhost:8080/bh-wp-private-uploads/';
			}
		);

		$test_uploads_directory_name = 'test-private-dir';

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		delete_transient( "bh_wp_private_uploads_{$settings->get_uploads_subdirectory_name()}_is_private" );

		@mkdir( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name );

		$logger = new ColorLogger();

		$api = new API( $settings, $logger );

		$reflection = new \ReflectionClass( API::class );
		$method     = $reflection->getMethod( 'add_protecting_htaccess' );
		$method->setAccessible( true );

		$method->invokeArgs( $api, array() );

		$result = $api->check_and_update_is_url_private();

		$this->assertTrue( $result['is_private'] );
	}

	/**
	 * @covers ::check_and_update_is_url_private
	 */
	public function test_url_is_public_folder_missing(): void {

		add_filter(
			'site_url',
			function () {
				return 'http://localhost:8080/bh-wp-private-uploads/';
			}
		);

		$test_uploads_directory_name = 'test-private-dir-does-not-exist';

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		delete_transient( "bh_wp_private_uploads_{$settings->get_uploads_subdirectory_name()}_is_private" );

		assert( ! file_exists( WP_CONTENT_DIR . '/uploads/' . $test_uploads_directory_name ) );

		$logger = new ColorLogger();

		$api = new API( $settings, $logger );

		$result = $api->check_and_update_is_url_private();

		$this->assertNull( $result['is_private'] );
	}
}
