<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\REST_Private_Uploads_Controller
 */
class REST_Private_Uploads_Controller_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * Register the private uploads post type with a REST base and (re)initialise the REST server so its
	 * routes are registered.
	 *
	 * @param string $test_uploads_directory_name Unique (per-test) private uploads subdirectory name.
	 * @return array{settings:Private_Uploads_Settings_Interface, namespace:string, rest_base:string}
	 */
	protected function register_rest_post_type( string $test_uploads_directory_name ): array {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'               => 'bh-wp-private-uploads-test',
				'get_post_type_name'            => 'rest_test_private',
				'get_post_type_label'           => 'REST Test Uploads',
				'get_rest_base'                 => 'rest-test-uploads',
				'get_uploads_subdirectory_name' => $test_uploads_directory_name,
			)
		);

		( new Post_Type( $settings ) )->register_post_type();

		// Reinitialise the REST server so the just-registered post type's routes are added.
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		return array(
			'settings'  => $settings,
			'namespace' => 'bh-wp-private-uploads-test/v1',
			'rest_base' => 'rest-test-uploads',
		);
	}

	/**
	 * Removing the capability check from the API class (see API::move_file_to_private_uploads()) must not
	 * weaken the REST boundary: a subscriber POSTing to the attachments route is still rejected with 403.
	 *
	 * @covers ::create_item
	 */
	public function test_subscriber_post_returns_403(): void {

		$config = $this->register_rest_post_type( uniqid( 'restsubscriber' ) );

		// `wp_create_user()` creates a subscriber (the default role).
		$subscriber_id = wp_create_user( uniqid( 'subscriber' ), wp_generate_password(), uniqid( 'subscriber' ) . '@example.org' );
		assert( is_int( $subscriber_id ) );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'POST', "/{$config['namespace']}/{$config['rest_base']}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
