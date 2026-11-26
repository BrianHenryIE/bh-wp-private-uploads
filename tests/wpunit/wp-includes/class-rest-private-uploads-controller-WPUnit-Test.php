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
	 * The `upload_item` route is permission-checked too.
	 *
	 * It shares `create_item_permissions_check()` with the collection route, but until this route was moved
	 * to `/upload` it was shadowed by `create_item` and could never be dispatched – so nothing had ever
	 * exercised its permission callback. Making it reachable must not open an unauthenticated upload path.
	 *
	 * @covers ::register_routes
	 * @covers ::create_item_permissions_check
	 */
	public function test_subscriber_post_to_upload_route_returns_403(): void {

		$subdir = uniqid( 'restuploadsubscriber' );
		$config = $this->register_rest_post_type( $subdir );

		// `wp_create_user()` creates a subscriber (the default role).
		$subscriber_id = wp_create_user( uniqid( 'subscriber' ), wp_generate_password(), uniqid( 'subscriber' ) . '@example.org' );
		assert( is_int( $subscriber_id ) );
		wp_set_current_user( $subscriber_id );

		// A real file, so a permission check that ran *after* the write would be caught below.
		$request  = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}/upload" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );

		// The status code alone is not enough: nothing may have been written to disk.
		$upload = wp_upload_dir();
		$dir    = $upload['basedir'] . '/' . $subdir;

		$this->assertEmpty(
			glob( "{$dir}/*/*/*" ) ?: array(),
			'A rejected upload must not write a file into the private uploads directory.'
		);
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
	 * @covers ::__construct
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
	 * The `upload_item` route stores the file without creating a post, and fires the
	 * `bh_wp_private_uploads_rest_upload` action.
	 *
	 * Dispatched through the REST server rather than by calling the method, so this also pins the route
	 * itself: registered at `"/{$rest_base}/"` it collided with the collection route (`register_rest_route()`
	 * trims the trailing slash) and `create_item` won, making `upload_item` unreachable.
	 *
	 * @covers ::register_routes
	 * @covers ::upload_item
	 */
	public function test_upload_item_stores_file_without_post_and_fires_action(): void {

		$subdir = uniqid( 'restupload' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$fired = false;
		add_action(
			'bh_wp_private_uploads_rest_upload',
			function ( string $plugin_slug, string $post_type_name, array $file, $request ) use ( &$fired ): void {

				$this->assertSame( 'bh-wp-private-uploads-test', $plugin_slug );
				$this->assertSame( 'rest_test_private', $post_type_name );

				$fired = true;
			},
			10,
			4
		);

		$posts_before = get_posts(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'inherit',
				'fields'      => 'ids',
			)
		);

		$request  = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}/upload" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertIsArray( $data );
		$this->assertIsArray( $data['file'] );
		$this->assertIsString( $data['file']['file'] );

		$uploaded_file = WP_CONTENT_DIR . '/' . $data['file']['file'];

		// The uploaded file is stored under the private subdirectory.
		$this->assertStringContainsString( "/{$subdir}/", $uploaded_file );
		$this->assertFileExists( $uploaded_file );

		$this->assertTrue( $fired, 'The bh_wp_private_uploads_rest_upload action should have fired.' );

		// No post was created – this is the difference from `create_item`.
		$posts_after = get_posts(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'inherit',
				'fields'      => 'ids',
			)
		);
		$this->assertCount( count( $posts_before ), $posts_after );

		// Uploaded file is on disk (not rolled back with the DB); remove it.
		if ( file_exists( $uploaded_file ) ) {
			unlink( $uploaded_file );
		}
	}

	/**
	 * The `upload_item` response must not disclose the server's filesystem layout: the `file` path is
	 * relative to `WP_CONTENT_DIR`, while the `bh_wp_private_uploads_rest_upload` action still receives
	 * the absolute path for server-side consumers.
	 *
	 * @covers ::upload_item
	 * @covers ::get_path_relative_to_wp_content_dir
	 */
	public function test_upload_item_response_file_is_relative_to_wp_content_dir(): void {

		$subdir = uniqid( 'restuploadrelative' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$action_file = null;
		add_action(
			'bh_wp_private_uploads_rest_upload',
			function ( string $plugin_slug, string $post_type_name, array $file ) use ( &$action_file ): void {
				$action_file = $file['file'];
			},
			10,
			3
		);

		$request  = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}/upload" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsArray( $data['file'] );
		$this->assertIsString( $data['file']['file'] );

		$relative_file = $data['file']['file'];

		$this->assertStringNotContainsString( ABSPATH, $relative_file );
		$this->assertStringNotContainsString( WP_CONTENT_DIR, $relative_file );
		$this->assertStringStartsWith( 'uploads/', $relative_file );
		$this->assertStringContainsString( "/{$subdir}/", $relative_file );

		$this->assertIsString( $action_file );
		$this->assertSame( WP_CONTENT_DIR . '/' . $relative_file, $action_file );

		// Uploaded file is on disk (not rolled back with the DB); remove it.
		if ( file_exists( $action_file ) ) {
			unlink( $action_file );
		}
	}

	/**
	 * When uploads are relocated outside `WP_CONTENT_DIR`, the response contains only the filename rather
	 * than falling back to the absolute path.
	 *
	 * @covers ::upload_item
	 * @covers ::get_path_relative_to_wp_content_dir
	 */
	public function test_upload_item_response_file_is_filename_when_uploads_outside_wp_content_dir(): void {

		$subdir = uniqid( 'restuploadrelocated' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$relocated_basedir = sys_get_temp_dir() . '/' . uniqid( 'relocated-uploads' );
		add_filter(
			'upload_dir',
			function ( array $upload_dir_data ) use ( $relocated_basedir ): array {
				/** @var string $subdir */
				$subdir                     = $upload_dir_data['subdir'];
				$upload_dir_data['basedir'] = $relocated_basedir;
				$upload_dir_data['path']    = $relocated_basedir . $subdir;
				return $upload_dir_data;
			},
			1
		);

		$action_file = null;
		add_action(
			'bh_wp_private_uploads_rest_upload',
			function ( string $plugin_slug, string $post_type_name, array $file ) use ( &$action_file ): void {
				$action_file = $file['file'];
			},
			10,
			3
		);

		$request  = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}/upload" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsArray( $data['file'] );

		$this->assertIsString( $action_file );
		$this->assertStringStartsWith( $relocated_basedir, $action_file );

		$this->assertSame( wp_basename( $action_file ), $data['file']['file'] );

		// Uploaded file is on disk (not rolled back with the DB); remove it.
		if ( file_exists( $action_file ) ) {
			unlink( $action_file );
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

	/**
	 * Create a user with the Author role (has `upload_files` and `edit_posts`, but not `edit_others_posts`)
	 * and set them as the current user.
	 */
	protected function set_current_user_to_new_author(): int {
		$author_id = wp_insert_user(
			array(
				'user_login' => uniqid( 'author' ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( 'author' ) . '@example.org',
				'role'       => 'author',
			)
		);
		assert( is_int( $author_id ) );
		wp_set_current_user( $author_id );

		return $author_id;
	}

	/**
	 * @return int[] The ids of all posts of the test's private uploads post type.
	 */
	protected function get_private_uploads_post_ids(): array {
		/** @var int[] $post_ids */
		$post_ids = get_posts(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);
		return $post_ids;
	}

	/**
	 * Core only checks `edit_post` for its own `post` param, so a user with `upload_files` could attach a
	 * private file to any post (e.g. another customer's order) through this library's `post_parent` param.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::get_id_param
	 */
	public function test_author_with_post_parent_they_cannot_edit_returns_403(): void {

		$subdir = uniqid( 'restforeignparent' );
		$config = $this->register_rest_post_type( $subdir );

		$admins_post_id = wp_insert_post(
			array(
				'post_title'  => 'Admin post',
				'post_author' => 1,
				'post_status' => 'publish',
			)
		);

		$this->set_current_user_to_new_author();

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_parent', $admins_post_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertEmpty( $this->get_private_uploads_post_ids() );
		$this->assertEmpty( glob( wp_upload_dir()['basedir'] . "/{$subdir}/*/*/*" ) ?: array() );
	}

	/**
	 * Core only checks `edit_others_posts` for its own `author` param, so a user with `upload_files` could
	 * record any user as the owner of a file through this library's `post_author` param.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::get_id_param
	 * @covers ::current_user_can_edit_others_posts
	 */
	public function test_author_with_post_author_of_another_user_returns_403(): void {

		$subdir = uniqid( 'restforeignauthor' );
		$config = $this->register_rest_post_type( $subdir );

		$this->set_current_user_to_new_author();

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_author', 1 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertEmpty( $this->get_private_uploads_post_ids() );
		$this->assertEmpty( glob( wp_upload_dir()['basedir'] . "/{$subdir}/*/*/*" ) ?: array() );
	}

	/**
	 * The `/upload` route shares the permission check.
	 *
	 * @covers ::create_item_permissions_check
	 */
	public function test_author_upload_route_with_post_parent_they_cannot_edit_returns_403(): void {

		$subdir = uniqid( 'restuploadforeignparent' );
		$config = $this->register_rest_post_type( $subdir );

		$admins_post_id = wp_insert_post(
			array(
				'post_title'  => 'Admin post',
				'post_author' => 1,
				'post_status' => 'publish',
			)
		);

		$this->set_current_user_to_new_author();

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}/upload" );
		$request->set_param( 'post_parent', $admins_post_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertEmpty( glob( wp_upload_dir()['basedir'] . "/{$subdir}/*/*/*" ) ?: array() );
	}

	/**
	 * The authorization checks must not block a user acting on their own post, as themselves.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::create_item
	 * @covers ::get_id_param
	 * @covers ::validate_post_parent_param
	 * @covers ::validate_post_author_param
	 * @covers ::add_insert_post_filters
	 */
	public function test_author_with_own_post_parent_and_post_author_returns_201(): void {

		$subdir = uniqid( 'restownids' );
		$config = $this->register_rest_post_type( $subdir );

		$author_id = $this->set_current_user_to_new_author();

		$own_post_id = wp_insert_post(
			array(
				'post_title'  => 'Own post',
				'post_author' => $author_id,
				'post_status' => 'publish',
			)
		);

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		// Strings, as they arrive in a real request: they should be sanitized to integers.
		$request->set_param( 'post_parent', (string) $own_post_id );
		$request->set_param( 'post_author', (string) $author_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsInt( $data['id'] );
		$post = get_post( $data['id'] );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertEquals( $author_id, $post->post_author );
		$this->assertSame( $own_post_id, $post->post_parent );

		$attached_path = get_attached_file( $post->ID );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * @return array<string, array{0:string, 1:mixed}>
	 */
	public function invalid_params_provider(): array {
		return array(
			'post_parent is not an integer' => array( 'post_parent', 'abc' ),
			'post_parent does not exist'    => array( 'post_parent', 99999999 ),
			'post_author is not an integer' => array( 'post_author', 'abc' ),
			'post_author does not exist'    => array( 'post_author', 99999999 ),
		);
	}

	/**
	 * The params are declared in the route's `args`, so they are validated before anything is written.
	 *
	 * @dataProvider invalid_params_provider
	 *
	 * @covers ::get_endpoint_args_for_item_schema
	 * @covers ::get_post_parent_and_post_author_args
	 * @covers ::register_routes
	 * @covers ::validate_post_parent_param
	 * @covers ::validate_post_author_param
	 *
	 * @param string $param_name  The request param.
	 * @param mixed  $param_value An invalid value for it.
	 */
	public function test_invalid_param_returns_400( string $param_name, $param_value ): void {

		$subdir = uniqid( 'restinvalidparam' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		foreach ( array( '', '/upload' ) as $route_suffix ) {
			$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}{$route_suffix}" );
			$request->set_param( $param_name, $param_value );

			$response = rest_get_server()->dispatch( $request );

			$this->assertSame( 400, $response->get_status(), "Route suffix: '{$route_suffix}'" );
		}

		$this->assertEmpty( $this->get_private_uploads_post_ids() );
		$this->assertEmpty( glob( wp_upload_dir()['basedir'] . "/{$subdir}/*/*/*" ) ?: array() );
	}

	/**
	 * The filters that set the post type, author and parent apply only to the one insert: an attachment
	 * inserted by another plugin's hook during the request must not be converted or re-parented.
	 *
	 * @covers ::create_item
	 * @covers ::add_insert_post_filters
	 */
	public function test_other_attachments_inserted_during_the_request_are_not_modified(): void {

		$subdir = uniqid( 'restscoped' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$parent_id = wp_insert_post( array( 'post_title' => 'Parent post' ) );

		$other_attachment_id = null;
		// `wp_insert_post()` returns early for attachments, so `add_attachment` is the action that fires.
		add_action(
			'add_attachment',
			function () use ( &$other_attachment_id ): void {
				if ( null !== $other_attachment_id ) {
					return;
				}
				$other_attachment_id = 0; // Prevent recursion.
				$other_attachment_id = wp_insert_attachment(
					array(
						'post_title'  => 'Another plugin\'s attachment',
						'guid'        => 'https://example.org/another-plugins-attachment.pdf',
						'post_author' => 1,
					)
				);
			}
		);

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_parent', $parent_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$this->assertIsInt( $other_attachment_id );
		$other_attachment = get_post( $other_attachment_id );
		$this->assertInstanceOf( \WP_Post::class, $other_attachment );
		$this->assertSame( 'attachment', $other_attachment->post_type );
		$this->assertSame( 0, $other_attachment->post_parent );
		$this->assertEquals( 1, $other_attachment->post_author );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsInt( $data['id'] );
		$attached_path = get_attached_file( $data['id'] );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * @covers ::create_item
	 * @covers ::add_insert_post_filters
	 */
	public function test_insert_filters_are_removed_after_the_request(): void {

		$subdir = uniqid( 'restfiltersremoved' );
		$config = $this->register_rest_post_type( $subdir );

		wp_set_current_user( 1 );

		$parent_id = wp_insert_post( array( 'post_title' => 'Parent post' ) );

		$had_insert_attachment_data_filter = has_filter( 'wp_insert_attachment_data' );
		$had_insert_post_parent_filter     = has_filter( 'wp_insert_post_parent' );
		$had_handle_upload_filter          = has_filter( 'wp_handle_upload' );

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_parent', $parent_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$this->assertSame( $had_insert_attachment_data_filter, has_filter( 'wp_insert_attachment_data' ) );
		$this->assertSame( $had_insert_post_parent_filter, has_filter( 'wp_insert_post_parent' ) );
		$this->assertSame( $had_handle_upload_filter, has_filter( 'wp_handle_upload' ) );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsInt( $data['id'] );
		$attached_path = get_attached_file( $data['id'] );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * `0` means "no parent" and "no owner": it is valid, needs no permission, and is what is saved.
	 *
	 * @covers ::validate_post_parent_param
	 * @covers ::validate_post_author_param
	 * @covers ::create_item_permissions_check
	 * @covers ::get_id_param
	 * @covers ::add_insert_post_filters
	 * @covers ::create_item
	 */
	public function test_zero_post_parent_and_post_author_are_accepted(): void {

		$subdir = uniqid( 'restzeroids' );
		$config = $this->register_rest_post_type( $subdir );

		$this->set_current_user_to_new_author();

		$had_insert_post_parent_filter = has_filter( 'wp_insert_post_parent' );

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_parent', 0 );
		$request->set_param( 'post_author', '0' );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsInt( $data['id'] );
		$post = get_post( $data['id'] );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertSame( 'rest_test_private', $post->post_type );
		$this->assertEquals( 0, $post->post_author );
		$this->assertSame( 0, $post->post_parent );
		$this->assertSame( $had_insert_post_parent_filter, has_filter( 'wp_insert_post_parent' ) );

		$attached_path = get_attached_file( $post->ID );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * The positive side of the `post_author` check for a non-admin: an Editor has `edit_others_posts`, so
	 * may record another user as the owner of the file.
	 *
	 * @covers ::create_item_permissions_check
	 * @covers ::current_user_can_edit_others_posts
	 * @covers ::get_id_param
	 * @covers ::add_insert_post_filters
	 */
	public function test_editor_can_set_another_user_as_post_author(): void {

		$subdir = uniqid( 'resteditor' );
		$config = $this->register_rest_post_type( $subdir );

		$other_user_id = wp_create_user( uniqid( 'other' ), wp_generate_password(), uniqid( 'other' ) . '@example.org' );
		assert( is_int( $other_user_id ) );

		$editor_id = wp_insert_user(
			array(
				'user_login' => uniqid( 'editor' ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( 'editor' ) . '@example.org',
				'role'       => 'editor',
			)
		);
		assert( is_int( $editor_id ) );
		wp_set_current_user( $editor_id );

		$request = $this->make_upload_request( "/{$config['namespace']}/{$config['rest_base']}" );
		$request->set_param( 'post_author', $other_user_id );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertIsInt( $data['id'] );
		$post = get_post( $data['id'] );

		$this->assertInstanceOf( \WP_Post::class, $post );
		$this->assertEquals( $other_user_id, $post->post_author );

		$attached_path = get_attached_file( $post->ID );
		if ( is_string( $attached_path ) && file_exists( $attached_path ) ) {
			unlink( $attached_path );
		}
	}

	/**
	 * Fail closed: if the post type is no longer registered there is no capability to check, so nobody
	 * may create posts on behalf of other users – not even an administrator.
	 *
	 * @covers ::current_user_can_edit_others_posts
	 */
	public function test_current_user_can_edit_others_posts_is_false_when_post_type_is_not_registered(): void {

		$this->register_rest_post_type( uniqid( 'restunregistered' ) );

		$sut = new REST_Private_Uploads_Controller( 'rest_test_private' );

		$reflection_method = new \ReflectionMethod( $sut, 'current_user_can_edit_others_posts' );
		$reflection_method->setAccessible( true );

		wp_set_current_user( 1 );

		$this->assertTrue( $reflection_method->invoke( $sut ) );

		unregister_post_type( 'rest_test_private' );

		$this->assertFalse( $reflection_method->invoke( $sut ) );
	}

	/**
	 * Insert a private uploads post directly, as the API class does for an uploaded file.
	 *
	 * @param int    $post_author_id The owner of the file; `0` for none.
	 * @param string $post_title     The post title.
	 */
	protected function insert_private_upload_post( int $post_author_id, string $post_title ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'rest_test_private',
				'post_status' => 'inherit',
				'post_title'  => $post_title,
				'post_author' => $post_author_id,
				'guid'        => 'http://example.org/wp-content/uploads/private/' . sanitize_title( $post_title ) . '.pdf',
			)
		);
		assert( $post_id > 0 );

		return $post_id;
	}

	/**
	 * Create a user with the given role and set them as the current user.
	 *
	 * @param string $role A WordPress role name.
	 */
	protected function set_current_user_to_new_user_with_role( string $role ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => uniqid( $role ),
				'user_pass'  => wp_generate_password(),
				'user_email' => uniqid( $role ) . '@example.org',
				'role'       => $role,
			)
		);
		assert( is_int( $user_id ) );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Core treats a post with the `inherit` status and no parent as public, which is right for attachments
	 * but would list every private upload – title, owner, date – to logged-out visitors.
	 *
	 * @covers ::get_items_permissions_check
	 */
	public function test_anonymous_visitor_cannot_list_private_uploads(): void {

		$config = $this->register_rest_post_type( uniqid( 'restreadanon' ) );

		$this->insert_private_upload_post( 1, 'Secret document title' );

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret document title', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * @covers ::check_read_permission
	 */
	public function test_anonymous_visitor_cannot_read_a_private_upload(): void {

		$config = $this->register_rest_post_type( uniqid( 'restreadanonitem' ) );

		$post_id = $this->insert_private_upload_post( 1, 'Secret document title' );

		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}/{$post_id}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret document title', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * A logged-in user without `edit_others_posts` cannot read another user's private upload.
	 *
	 * @covers ::check_read_permission
	 * @dataProvider provide_roles_that_cannot_read_others_private_uploads
	 *
	 * @param string $role A WordPress role name.
	 */
	public function test_user_cannot_read_another_users_private_upload( string $role ): void {

		$config = $this->register_rest_post_type( uniqid( 'restreadother' ) );

		$post_id = $this->insert_private_upload_post( 1, 'Secret document title' );

		$this->set_current_user_to_new_user_with_role( $role );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}/{$post_id}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertStringNotContainsString( 'Secret document title', (string) wp_json_encode( $response->get_data() ) );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public function provide_roles_that_cannot_read_others_private_uploads(): array {
		return array(
			'subscriber'  => array( 'subscriber' ),
			'contributor' => array( 'contributor' ),
			'author'      => array( 'author' ),
		);
	}

	/**
	 * The owner of a file (its `post_author`) can read its record, whatever their role.
	 *
	 * @covers ::check_read_permission
	 */
	public function test_owner_can_read_their_private_upload(): void {

		$config = $this->register_rest_post_type( uniqid( 'restreadowner' ) );

		$subscriber_id = $this->set_current_user_to_new_user_with_role( 'subscriber' );

		$post_id = $this->insert_private_upload_post( $subscriber_id, 'My own document' );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}/{$post_id}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertSame( $post_id, $data['id'] );
	}

	/**
	 * A user with the post type's `edit_others_posts` capability can read every private upload.
	 *
	 * @covers ::check_read_permission
	 * @covers ::get_items_permissions_check
	 * @covers ::prepare_items_query
	 * @dataProvider provide_roles_that_can_read_all_private_uploads
	 *
	 * @param string $role A WordPress role name.
	 */
	public function test_user_with_edit_others_posts_can_read_and_list_all_private_uploads( string $role ): void {

		$config = $this->register_rest_post_type( uniqid( 'restreadall' ) );

		$other_user_id = wp_create_user( uniqid( 'other' ), wp_generate_password(), uniqid( 'other' ) . '@example.org' );
		assert( is_int( $other_user_id ) );

		$post_id       = $this->insert_private_upload_post( $other_user_id, 'Another user\'s document' );
		$unowned_id    = $this->insert_private_upload_post( 0, 'Document with no owner' );
		$ids_before    = $this->get_private_uploads_post_ids();
		$current_count = count( $ids_before );

		$this->set_current_user_to_new_user_with_role( $role );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}/{$post_id}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$request  = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}" );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$listed_ids = array_column( $data, 'id' );

		$this->assertContains( $post_id, $listed_ids );
		$this->assertContains( $unowned_id, $listed_ids );
		$this->assertSame( $current_count, $response->get_headers()['X-WP-Total'] );
	}

	/**
	 * @return array<string, array{0:string}>
	 */
	public function provide_roles_that_can_read_all_private_uploads(): array {
		return array(
			'editor'        => array( 'editor' ),
			'administrator' => array( 'administrator' ),
		);
	}

	/**
	 * A logged-in user without `edit_others_posts` may list private uploads, but the list – and its total,
	 * which would otherwise disclose how many files exist – contains only the files they own.
	 *
	 * @covers ::get_items_permissions_check
	 * @covers ::prepare_items_query
	 */
	public function test_list_contains_only_the_users_own_private_uploads(): void {

		$config = $this->register_rest_post_type( uniqid( 'restlistown' ) );

		$others_post_id = $this->insert_private_upload_post( 1, 'Secret document title' );
		$unowned_id     = $this->insert_private_upload_post( 0, 'Document with no owner' );

		$subscriber_id = $this->set_current_user_to_new_user_with_role( 'subscriber' );

		$own_post_id = $this->insert_private_upload_post( $subscriber_id, 'My own document' );

		$request = new WP_REST_Request( 'GET', "/{$config['namespace']}/{$config['rest_base']}" );
		// The user must not be able to ask for someone else's files.
		$request->set_param( 'author', array( 1 ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$listed_ids = array_column( $data, 'id' );

		$this->assertSame( array( $own_post_id ), $listed_ids );
		$this->assertNotContains( $others_post_id, $listed_ids );
		$this->assertNotContains( $unowned_id, $listed_ids );
		$this->assertSame( 1, $response->get_headers()['X-WP-Total'] );
	}
}
