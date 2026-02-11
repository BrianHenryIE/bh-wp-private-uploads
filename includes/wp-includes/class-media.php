<?php
/**
 * Hook into WordPress's attachment functions to save the attachment in our specified directory.
 *
 * @see media_handle_upload()
 * @see \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Meta_Boxes
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP_Post;
use WP_Query;

/**
 * Hooked onto AJAX functions to handle Media Library interactions.
 */
class Media {

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings The private uploads settings, CPT name and directory are needed.
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings
	) {
	}

	/**
	 * When the media dialog is opened it queries for the attachments.
	 * We add the post_type to the request URI to indicate we are querying for our CPT.
	 * Then we update the query type and retrieved post types to work with WordPress's "attachment" functions.
	 *
	 * @hooked wp_ajax_query-attachments
	 * @see wp_ajax_query_attachments()
	 */
	public function on_query_attachments(): void {

		/**
		 * The AJAX `query-attachments` action does not send a nonce.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_GET['post_type'] ) || $this->settings->get_post_type_name() !== $_GET['post_type'] ) {
			return;
		}

		add_filter( 'ajax_query_attachments_args', array( $this, 'set_query_post_type_to_cpt' ) );

		add_filter( 'the_posts', array( $this, 'change_post_type_to_attachment' ), 10, 2 );

		// Then the URLs are wrong on the thumbnails.
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 10, 2 );
	}

	/**
	 * Hook into the upload process to handle private uploads.
	 *
	 * This is triggered via:
	 * 1. async-upload.php with post_type in referer (legacy uploader)
	 * 2. admin-ajax.php with post_type in POST data (plupload/modern uploader)
	 *
	 * The upload request loads `async-upload.php` which calls `wp_ajax_upload_attachment()` directly, not via an
	 * action, so we cannot hook into "its" action early, as I'd prefer.
	 *
	 * @see BH_WP_Private_Uploads_Hooks::define_media_library_hooks()
	 *
	 * @hooked admin_init
	 * @see wp_ajax_upload_attachment()
	 * @see async-upload.php
	 */
	public function on_upload_attachment(): void {

		// Only continue if this is one of our private uploads.
		if ( ! $this->is_private_upload_via_post() && ! $this->is_private_upload_via_referer() ) {
			return;
		}

		add_filter( 'upload_dir', array( $this, 'set_uploads_subdirectory' ), 10, 1 );

		add_filter( 'wp_insert_attachment_data', array( $this, 'set_post_type_on_insert_attachment' ), 10, 4 );

		add_action( 'add_attachment', array( $this, 'change_post_type_to_attachment_in_cache' ) );
	}

	/**
	 * Check if this is our private upload via POST parameter (modern uploader via admin-ajax.php).
	 * The JS sets:` window.wp.Uploader.defaults.multipart_params.post_type = private_attachment_post_type`.
	 */
	protected function is_private_upload_via_post(): bool {

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! is_string( $_REQUEST['_wpnonce'] ) ) {
			$is_valid_nonce = false;
		} else {
			$is_valid_nonce = wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'media-form' )
					|| wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'upload-attachment' )
					|| wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'query-attachments' );
		}

		if ( ! isset( $_POST['post_type'] ) || ! is_string( $_POST['post_type'] ) ) {
			return false;
		}

		return $this->settings->get_post_type_name() === sanitize_key( wp_unslash( $_POST['post_type'] ) );
	}

	/**
	 * Check if this is our private upload via `async-upload.php` with referer containing `post_type=`.
	 */
	protected function is_private_upload_via_referer(): bool {

		if ( ! isset( $_SERVER['HTTP_REFERER'] ) || ! is_string( $_SERVER['HTTP_REFERER'] ) ) {
			return false;
		}

		$http_referer = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

		/** @var string $pagenow */
		global $pagenow;

		return 'async-upload.php' === $pagenow
			&& str_contains( $http_referer, 'post_type=' . $this->settings->get_post_type_name() );
	}

	/**
	 * @hooked ajax_query_attachments_args
	 * @param array{s?:string,order?:string,orderby?:string,posts_per_page?:int,paged?:int,post_mime_type?:string,post_parent?:int,author?:int,post__in?:array<int>,post__not_in?:array<int>,year?:int,monthnum?:int,} $query From `$_REQUEST['query']`.
	 *
	 * @see wp_ajax_query_attachments()
	 *
	 * @return array{post_type:string}&array<string,mixed>
	 */
	public function set_query_post_type_to_cpt( array $query ): array {
		$query['post_type'] = $this->settings->get_post_type_name();
		return $query;
	}

	/**
	 *
	 * @hooked the_posts
	 *
	 * @see wp_prepare_attachment_for_js()
	 * @see WP_Query::get_posts()
	 *
	 * @param WP_Post[] $posts A list of posts to be sent to the frontend.
	 * @param WP_Query  $_query The original query that found those posts.
	 *
	 * @return WP_Post[]
	 */
	public function change_post_type_to_attachment( array $posts, WP_Query $_query ): array {

		foreach ( $posts as $post ) {
			$post->post_type = 'attachment';
			$this->change_post_type_to_attachment_in_cache( $post->ID );
		}

		return $posts;
	}

	/**
	 * After the attachment is saved/added, edit the cached posts so they appear to be `attachment`s, otherwise
	 * the media library UI will not display them.
	 *
	 * @see wp_insert_post()
	 * @hooked add_attachment
	 *
	 * @param int $post_id The new post's id.
	 */
	public function change_post_type_to_attachment_in_cache( int $post_id ): void {
		/** @var ?\stdClass $cached_post */
		$cached_post = wp_cache_get( $post_id, 'posts' );

		if ( ! $cached_post ) {
			return;
		}

		// This shouldn't be necessary due to the check before the `add_action()` above, but it is safe.
		if ( $this->settings->get_post_type_name() !== $cached_post->post_type ) {
			return;
		}

		$cached_post->post_type = 'attachment';

		wp_cache_set( $post_id, $cached_post, 'posts' );
	}

	/**
	 * Add the uploads' subdirectory to attachments' response.
	 *
	 * @hooked wp_prepare_attachment_for_js
	 * @see wp_prepare_attachment_for_js()
	 *
	 * @param array{sizes?:array{name:array{url:string}}} $response The array to be JSON encoded for the media library list.
	 * @param WP_Post                                     $attachment The WP_Post being sent to the frontend.
	 *
	 * @return array{sizes?:array{name:array{url:string}}}&array<string,mixed>
	 */
	public function prepare_attachment_for_js( array $response, WP_Post $attachment ) {
		$post_directory = $this->settings->get_uploads_subdirectory_name();
		if ( str_contains( $attachment->guid, $post_directory ) && isset( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as $name => $size ) {
				$response['sizes'][ $name ]['url'] = str_replace(
					content_url( '/uploads' ),
					content_url( '/uploads/' . $this->settings->get_uploads_subdirectory_name() ),
					$response['sizes'][ $name ]['url']
				);
			}
		}

		// This was already correct last time I looked
		// $response['url'] = str_replace( WP_CONTENT_URL . '/uploads', WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name(), $response['url'] );
		// TODO: check that $response['url'] is the correct URL and we only needed to update the sizes here.
		return $response;
	}

	/**
	 * We are manipulating `attachment` upload, so here we change the `attachment` post type to our own CPT.
	 *
	 * @hooked wp_insert_attachment_data
	 * @see wp_insert_post()
	 * @see wp_ajax_upload_attachment()
	 *
	 * @param array<string, string|int> $data                 An array of slashed, sanitized, and processed attachment post data.
	 * @param array<string, string|int> $_postarr             An array of slashed and sanitized attachment post data, but not processed.
	 * @param array<string, string|int> $_unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed attachment post data
	 *                                    as originally passed to wp_insert_post().
	 * @param bool                      $_update              Whether this is an existing attachment post being updated.
	 *
	 * @return array<string, string|int> A WP_Post array?
	 */
	public function set_post_type_on_insert_attachment( array $data, array $_postarr, array $_unsanitized_postarr, bool $_update ): array {
		$data['post_type'] = $this->settings->get_post_type_name();

		return $data;
	}

	/**
	 * Filter wp_upload_dir() to add private subdirectory name.
	 *
	 * @hooked upload_dir
	 * @see wp_upload_dir()
	 *
	 * @param array{path:string, url:string, subdir:string, basedir:string, baseurl:string, error:string|false} $uploads {
	 *     Array of information about the upload directory.
	 *
	 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
	 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
	 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
	 *     @type string       $basedir Path without subdir.
	 *     @type string       $baseurl URL path without subdir.
	 *     @type string|false $error   False or error message.
	 * }
	 * @return array{path:string, url:string, subdir:string, basedir:string, baseurl:string, error:string|false} $uploads
	 */
	public function set_uploads_subdirectory( array $uploads ): array {

		// We want this to return once when the file is being moved, but not to apply when the relative filepaths are being calculated.
		remove_filter( 'upload_dir', array( $this, 'set_uploads_subdirectory' ) );

		$uploads_subdirectory_name = $this->settings->get_uploads_subdirectory_name();

		// Use private uploads dir.
		$uploads['basedir'] = "{$uploads['basedir']}/{$uploads_subdirectory_name}";
		$uploads['baseurl'] = "{$uploads['baseurl']}/{$uploads_subdirectory_name}";

		$uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
		$uploads['url']  = $uploads['baseurl'] . $uploads['subdir'];

		return $uploads;
	}
}
