<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Private_Uploads\WP_Includes\Media
 */
class Media_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * The `$pagenow` global before the test, restored in {@see self::tearDown()}.
	 *
	 * @var mixed
	 */
	protected $pagenow_before;

	/**
	 * The `REQUEST_URI` before the test, restored in {@see self::tearDown()}.
	 *
	 * @var mixed
	 */
	protected $request_uri_before;

	protected function setUp(): void {
		parent::setUp();

		global $pagenow;
		$this->pagenow_before     = $pagenow;
		$this->request_uri_before = $_SERVER['REQUEST_URI'] ?? null;
	}

	/**
	 * The request globals the tests set must not leak into other tests, including when an assertion fails.
	 */
	protected function tearDown(): void {
		global $pagenow;
		$pagenow = $this->pagenow_before;

		unset( $_SERVER['HTTP_REFERER'], $_POST['post_type'], $_REQUEST['_wpnonce'] );

		if ( null === $this->request_uri_before ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri_before;
		}

		parent::tearDown();
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_prepends_subdirectory_to_basedir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media', $result['basedir'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_prepends_subdirectory_to_baseurl(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( 'http://example.com/wp-content/uploads/private-media', $result['baseurl'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_reconstructs_path_from_basedir_and_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media/2026/02', $result['path'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_reconstructs_url_from_baseurl_and_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( 'http://example.com/wp-content/uploads/private-media/2026/02', $result['url'] );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_removes_itself_from_upload_dir_filter(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		add_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) );

		$this->assertNotFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads/2026/02',
			'url'     => 'http://example.com/wp-content/uploads/2026/02',
			'subdir'  => '/2026/02',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$sut->set_uploads_subdirectory( $uploads );

		$this->assertFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );
	}

	/**
	 * @covers ::set_uploads_subdirectory
	 */
	public function test_handles_empty_subdir(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$uploads = array(
			'path'    => '/var/www/html/wp-content/uploads',
			'url'     => 'http://example.com/wp-content/uploads',
			'subdir'  => '',
			'basedir' => '/var/www/html/wp-content/uploads',
			'baseurl' => 'http://example.com/wp-content/uploads',
			'error'   => false,
		);

		$result = $sut->set_uploads_subdirectory( $uploads );

		$this->assertSame( '/var/www/html/wp-content/uploads/private-media', $result['path'] );
		$this->assertSame( 'http://example.com/wp-content/uploads/private-media', $result['url'] );
	}

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_sets_post_type_to_cpt(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$data = array(
			'post_type'   => 'attachment',
			'post_title'  => 'sample.pdf',
			'post_status' => 'inherit',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), false );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_preserves_other_data_fields(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$data = array(
			'post_type'    => 'attachment',
			'post_title'   => 'sample.pdf',
			'post_status'  => 'inherit',
			'post_content' => 'file description',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), false );

		$this->assertSame( 'sample.pdf', $result['post_title'] );
		$this->assertSame( 'inherit', $result['post_status'] );
		$this->assertSame( 'file description', $result['post_content'] );
	}

	/**
	 * @covers ::set_post_type_on_insert_attachment
	 */
	public function test_sets_post_type_on_update(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$data = array(
			'post_type' => 'attachment',
		);

		$result = $sut->set_post_type_on_insert_attachment( $data, array(), array(), true );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_sets_query_post_type_to_cpt(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$query = array(
			'post_type' => 'attachment',
			's'         => 'search term',
			'order'     => 'DESC',
		);

		$result = $sut->set_query_post_type_to_cpt( $query );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_set_query_post_type_preserves_other_query_args(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$query = array(
			'post_type'      => 'attachment',
			's'              => 'search term',
			'order'          => 'DESC',
			'posts_per_page' => 40,
			'paged'          => 2,
		);

		/** @var array<string, mixed> $result */
		$result = $sut->set_query_post_type_to_cpt( $query ); /** @phpstan-ignore varTag.type */

		$this->assertArrayHasKey( 's', $result );
		$this->assertSame( 'search term', $result['s'] );
		$this->assertSame( 'DESC', $result['order'] );
		$this->assertSame( 40, $result['posts_per_page'] );
		$this->assertSame( 2, $result['paged'] );
	}

	/**
	 * @covers ::set_query_post_type_to_cpt
	 */
	public function test_set_query_post_type_adds_post_type_when_absent(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'private_media',
			)
		);

		$sut = new Media( $settings, new Media_Request() );

		$query = array(
			's' => 'search term',
		);

		$result = $sut->set_query_post_type_to_cpt( $query );

		$this->assertSame( 'private_media', $result['post_type'] );
	}

	/**
	 * Build a `Media` whose settings use the given post type.
	 *
	 * @param string $post_type_name The private uploads post type name.
	 */
	protected function get_media_for_post_type( string $post_type_name ): Media {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => $post_type_name,
				'get_uploads_subdirectory_name' => 'private-media',
			)
		);

		return new Media( $settings, new Media_Request() );
	}

	/**
	 * With a valid `media-form` nonce and a matching `post_type` POST value, `on_upload_attachment()`
	 * adds its three redirect-to-private hooks.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_post
	 */
	public function test_on_upload_attachment_adds_hooks_with_valid_nonce_and_post_type(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'media-form' );
		$_POST['post_type']   = 'media_upload_test';

		$sut->on_upload_attachment();

		$this->assertNotFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );
		$this->assertNotFalse( has_filter( 'wp_insert_attachment_data', array( $sut, 'set_post_type_on_insert_attachment' ) ) );
		$this->assertNotFalse( has_action( 'add_attachment', array( $sut, 'change_post_type_to_attachment_in_cache' ) ) );

		unset( $_REQUEST['_wpnonce'], $_POST['post_type'] );
	}

	/**
	 * Without a nonce, `on_upload_attachment()` does nothing (a plain attachment upload is unaffected).
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_post
	 */
	public function test_on_upload_attachment_does_nothing_without_nonce(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		unset( $_REQUEST['_wpnonce'] );
		$_POST['post_type'] = 'media_upload_test';

		$sut->on_upload_attachment();

		$this->assertFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );

		unset( $_POST['post_type'] );
	}

	/**
	 * The `async-upload.php` referer branch also triggers the redirect-to-private hooks.
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_referer
	 */
	public function test_on_upload_attachment_referer_branch_adds_hooks(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		global $pagenow;
		$pagenow_before = $pagenow;
		$pagenow        = 'async-upload.php';

		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/media-new.php?post_type=media_upload_test';

		$sut->on_upload_attachment();

		$this->assertNotFalse( has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) ) );

		unset( $_SERVER['HTTP_REFERER'] );
		$pagenow = $pagenow_before;
	}

	/**
	 * Was the upload routed to the private uploads directory and post type?
	 *
	 * @param Media $sut The instance `on_upload_attachment()` was called on.
	 */
	protected function has_private_upload_hooks( Media $sut ): bool {
		return false !== has_filter( 'upload_dir', array( $sut, 'set_uploads_subdirectory' ) )
			|| false !== has_filter( 'wp_insert_attachment_data', array( $sut, 'set_post_type_on_insert_attachment' ) )
			|| false !== has_action( 'add_attachment', array( $sut, 'change_post_type_to_attachment_in_cache' ) );
	}

	/**
	 * SECURITY-PLAN.md F4: the legitimate flow must not depend on the `Referer` header. The admin script sends
	 * `post_type` in the upload's POST body, so an `async-upload.php` request with a valid nonce and no
	 * `Referer` at all is still routed to the private directory.
	 *
	 * This must keep passing when the `Referer` branch is removed.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_post
	 */
	public function test_on_upload_attachment_adds_hooks_without_referer(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		global $pagenow;
		$pagenow = 'async-upload.php';

		unset( $_SERVER['HTTP_REFERER'] );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'media-form' );
		$_POST['post_type']   = 'media_upload_test';

		$sut->on_upload_attachment();

		$this->assertTrue( $this->has_private_upload_hooks( $sut ) );
	}

	/**
	 * A valid nonce is not enough: the `post_type` POST field must be this instance's post type. An ordinary
	 * media library upload (no `post_type`, or another instance's) is left alone.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_post
	 * @dataProvider provide_post_types_of_other_uploads
	 *
	 * @param ?string $posted_post_type The `post_type` POST field; null when it is absent.
	 */
	public function test_on_upload_attachment_does_nothing_for_another_post_type( ?string $posted_post_type ): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		global $pagenow;
		$pagenow = 'async-upload.php';

		unset( $_SERVER['HTTP_REFERER'] );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'media-form' );
		if ( null !== $posted_post_type ) {
			$_POST['post_type'] = $posted_post_type;
		}

		$sut->on_upload_attachment();

		$this->assertFalse( $this->has_private_upload_hooks( $sut ) );
	}

	/**
	 * @return array<string, array{0:?string}>
	 */
	public function provide_post_types_of_other_uploads(): array {
		return array(
			'no post_type field'                    => array( null ),
			'core attachment'                       => array( 'attachment' ),
			'another instance'                      => array( 'other_private_media' ),
			'post type with this one as its prefix' => array( 'media_upload_test_other' ),
		);
	}

	/**
	 * SECURITY-PLAN.md F4: negative cases for the `Referer` branch. No nonce and no `post_type` POST field are
	 * sent, so only the `Referer` could route these uploads – and it must not.
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_referer
	 * @dataProvider provide_referers_that_must_not_route_the_upload
	 *
	 * @param string  $page_now The `$pagenow` global.
	 * @param ?string $referer  The `Referer` header; null when it is absent.
	 */
	public function test_on_upload_attachment_referer_branch_does_nothing( string $page_now, ?string $referer ): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		global $pagenow;
		$pagenow = $page_now;

		unset( $_REQUEST['_wpnonce'], $_POST['post_type'], $_SERVER['HTTP_REFERER'] );
		if ( null !== $referer ) {
			$_SERVER['HTTP_REFERER'] = $referer;
		}

		$sut->on_upload_attachment();

		$this->assertFalse( $this->has_private_upload_hooks( $sut ) );
	}

	/**
	 * @return array<string, array{0:string, 1:?string}>
	 */
	public function provide_referers_that_must_not_route_the_upload(): array {
		$admin_url = 'http://example.org/wp-admin';

		return array(
			'no referer'                           => array( 'async-upload.php', null ),
			'referer without a post type'          => array( 'async-upload.php', "{$admin_url}/media-new.php" ),
			'referer for core attachments'         => array( 'async-upload.php', "{$admin_url}/upload.php?post_type=attachment" ),
			'referer for another instance'         => array( 'async-upload.php', "{$admin_url}/media-new.php?post_type=other_private_media" ),
			'matching referer, not an upload page' => array( 'admin-ajax.php', "{$admin_url}/media-new.php?post_type=media_upload_test" ),
			'matching referer on upload.php'       => array( 'upload.php', "{$admin_url}/upload.php?post_type=media_upload_test" ),
		);
	}

	/**
	 * SECURITY-PLAN.md F4: this documents a KNOWN BUG, it is not desired behaviour.
	 *
	 * `is_private_upload_via_referer()` matches with `str_contains()`, so the `Referer` of an unrelated page
	 * whose post type merely *starts with* this instance's post type name (`media_upload_test_other` vs
	 * `media_upload_test`) routes that upload into this instance's private directory.
	 *
	 * When F4 is fixed, change `assertTrue` to `assertFalse`.
	 *
	 * @covers ::on_upload_attachment
	 * @covers ::is_private_upload_via_referer
	 */
	public function test_on_upload_attachment_referer_branch_matches_post_type_prefix_known_bug(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		global $pagenow;
		$pagenow = 'async-upload.php';

		unset( $_REQUEST['_wpnonce'], $_POST['post_type'] );
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/media-new.php?post_type=media_upload_test_other';

		$sut->on_upload_attachment();

		$this->assertTrue( $this->has_private_upload_hooks( $sut ), 'The prefix collision is fixed: invert this assertion.' );
	}

	/**
	 * Were the media library query's filters added?
	 *
	 * @param Media $sut The instance `on_query_attachments()` was called on.
	 */
	protected function has_query_attachments_hooks( Media $sut ): bool {
		return false !== has_filter( 'ajax_query_attachments_args', array( $sut, 'set_query_post_type_to_cpt' ) )
			|| false !== has_filter( 'the_posts', array( $sut, 'change_post_type_to_attachment' ) )
			|| false !== has_filter( 'wp_prepare_attachment_for_js', array( $sut, 'prepare_attachment_for_js' ) );
	}

	/**
	 * The admin script adds `?post_type=` to the AJAX URL, so the media library query is switched to the
	 * private uploads post type with no `Referer`.
	 *
	 * @covers ::on_query_attachments
	 */
	public function test_on_query_attachments_adds_hooks_for_request_uri_without_referer(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		unset( $_SERVER['HTTP_REFERER'] );
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php?post_type=media_upload_test';

		$sut->on_query_attachments();

		$this->assertTrue( $this->has_query_attachments_hooks( $sut ) );
	}

	/**
	 * The current fallback for media library *reads*: the `Referer` of the private media library page.
	 *
	 * SECURITY-PLAN.md PR 6 may keep this (reads only) or remove it; update this test with that decision.
	 *
	 * @covers ::on_query_attachments
	 */
	public function test_on_query_attachments_adds_hooks_for_referer(): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		$_SERVER['REQUEST_URI']  = '/wp-admin/admin-ajax.php';
		$_SERVER['HTTP_REFERER'] = 'http://example.org/wp-admin/upload.php?post_type=media_upload_test&mode=grid';

		$sut->on_query_attachments();

		$this->assertTrue( $this->has_query_attachments_hooks( $sut ) );
	}

	/**
	 * An ordinary media library query, or another instance's, is left alone. Unlike the upload's `Referer`
	 * check, the query string is parsed, so a post type with this one as its prefix does not match.
	 *
	 * @covers ::on_query_attachments
	 * @dataProvider provide_requests_that_must_not_switch_the_query
	 *
	 * @param string  $request_uri The `REQUEST_URI`.
	 * @param ?string $referer     The `Referer` header; null when it is absent.
	 */
	public function test_on_query_attachments_does_nothing_for_other_requests( string $request_uri, ?string $referer ): void {

		$sut = $this->get_media_for_post_type( 'media_upload_test' );

		unset( $_SERVER['HTTP_REFERER'] );
		$_SERVER['REQUEST_URI'] = $request_uri;
		if ( null !== $referer ) {
			$_SERVER['HTTP_REFERER'] = $referer;
		}

		$sut->on_query_attachments();

		$this->assertFalse( $this->has_query_attachments_hooks( $sut ) );
	}

	/**
	 * @return array<string, array{0:string, 1:?string}>
	 */
	public function provide_requests_that_must_not_switch_the_query(): array {
		$admin_url = 'http://example.org/wp-admin';
		$ajax_uri  = '/wp-admin/admin-ajax.php';

		return array(
			'neither has a post type'             => array( $ajax_uri, null ),
			'referer of the core media library'   => array( $ajax_uri, "{$admin_url}/upload.php" ),
			'referer for another instance'        => array( $ajax_uri, "{$admin_url}/upload.php?post_type=other_private_media" ),
			'referer with this type as a prefix'  => array( $ajax_uri, "{$admin_url}/upload.php?post_type=media_upload_test_other" ),
			'request uri for another instance'    => array( "{$ajax_uri}?post_type=other_private_media", null ),
			'request uri with this type a prefix' => array( "{$ajax_uri}?post_type=media_upload_test_other", null ),
		);
	}
}
