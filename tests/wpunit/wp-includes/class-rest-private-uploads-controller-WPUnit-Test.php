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

	/**
	 * The path to the `sample.pdf` fixture.
	 */
	protected function get_fixture_path(): string {
		/** @var string $project_root_dir */
		global $project_root_dir;
		return "{$project_root_dir}/tests/_data/sample.pdf";
	}

	/**
	 * Build a POST request that uploads the fixture as the raw request body (which routes through
	 * `wp_handle_sideload()`, avoiding the `is_uploaded_file()` check that fails in tests).
	 *
	 * @param string $route The REST route to POST to.
	 */
	protected function make_upload_request( string $route ): WP_REST_Request {

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'Content-Type', 'application/pdf' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=sample.pdf' );
		$request->set_body( (string) file_get_contents( $this->get_fixture_path() ) );

		return $request;
	}

	/**
	 * An admin POSTing a file creates a post of the custom post type (not `attachment`), with the file
	 * stored under the private uploads subdirectory.
	 *
	 * @covers ::create_item
	 * @covers ::add_set_private_uploads_filters
	 */
	public function test_admin_post_creates_custom_post_type_post_with_file(): void {

		$subdir = uniqid( 'restcreate' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$request  = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$post_id = $data['id'];
		$this->assertIsInt( $post_id );

		$post = get_post( $post_id );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( 'rest_test_private', $post->post_type );

		// The file is stored under the private subdirectory (the full path, since the stored
		// `_wp_attached_file` is relative to the filtered private basedir).
		$attached_path = get_attached_file( $post_id );
		$this->assertStringContainsString( "/{$subdir}/", (string) $attached_path );
		$this->assertFileExists( (string) $attached_path );

		// Uploaded files are on disk (not rolled back with the DB); remove it.
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * The additional `upload_item` route stores the file without creating a post, and fires the
	 * `rest_private_uploads_upload` action.
	 *
	 * @covers ::upload_item
	 */
	public function test_upload_item_stores_file_without_post_and_fires_action(): void {

		$subdir = uniqid( 'restupload' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$fired = false;
		add_action(
			'rest_private_uploads_upload',
			function () use ( &$fired ): void {
				$fired = true;
			}
		);

		$posts_before = get_posts(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'inherit',
				'fields'      => 'ids',
			)
		);

		// The `upload_item` route is registered with a trailing slash, but `register_rest_route()` trims it,
		// so it merges into the create route and is not reachable via dispatch; exercise the method directly.
		$controller = new REST_Private_Uploads_Controller( 'rest_test_private' );
		$request    = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );

		$result = $controller->upload_item( $request );

		$this->assertNotInstanceOf( \WP_Error::class, $result );
		// The uploaded file is stored under the private subdirectory.
		$this->assertStringContainsString( "/{$subdir}/", $result['file']['file'] );

		$this->assertTrue( $fired );

		// No post was created.
		$posts_after = get_posts(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'inherit',
				'fields'      => 'ids',
			)
		);
		$this->assertCount( count( $posts_before ), $posts_after );

		// Uploaded file is on disk (not rolled back with the DB); remove it.
		if ( file_exists( $result['file']['file'] ) ) {
			unlink( $result['file']['file'] );
		}
	}

	/**
	 * The `post_author` and `post_parent` request params are respected on the created post.
	 *
	 * @covers ::create_item
	 * @covers ::add_set_private_uploads_filters
	 */
	public function test_post_author_and_post_parent_params_are_respected(): void {

		$subdir = uniqid( 'restparams' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$author_id = wp_create_user( uniqid( 'author' ), wp_generate_password(), uniqid( 'author' ) . '@example.org' );
		assert( is_int( $author_id ) );

		$parent_id = wp_insert_post( array( 'post_title' => 'Parent post' ) );

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_author', $author_id );
		$request->set_param( 'post_parent', $parent_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertArrayHasKey( 'id', $data );
		$post_id = $data['id'];
		$this->assertIsInt( $post_id );

		$post = get_post( $post_id );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertEquals( $author_id, $post->post_author );
		$this->assertSame( $parent_id, $post->post_parent );

		// Uploaded file is on disk (not rolled back with the DB); remove it.
		$attached_path = get_attached_file( $post_id );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}
}
