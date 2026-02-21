<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
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
}
