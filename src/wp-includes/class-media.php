<?php
/**
 * Hook into WordPress's attachment functions to save the attachment in our specified directory.
 *
 * @see media_handle_upload()
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP_Post;

/**
 * Hooked onto AJAX functions to handle Media Library interactions.
 */
class Media {

	/**
	 * The CPT name and directory are needed.
	 *
	 * @uses Private_Uploads_Settings_Interface::get_post_type_name
	 * @uses Private_Uploads_Settings_Interface::get_uploads_subdirectory_name
	 *
	 * @var Private_Uploads_Settings_Interface
	 */
	protected Private_Uploads_Settings_Interface $settings;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings The private uploads settings.
	 */
	public function __construct( Private_Uploads_Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * When the media dialog is opened it queries for the attachments.
	 * We add the post_type to the request URI to indicate we are querying for our CPT.
	 * Then we update the query type and retrieved post types to work with WordPress's "attachment" functions.
	 *
	 * @hooked wp_ajax_query-attachments
	 * @see wp_ajax_query_attachments()
	 */
	public function on_query_attachments() {

		if ( ! isset( $_GET['post_type'] ) || $this->settings->get_post_type_name() !== $_GET['post_type'] ) {
			return;
		}

		add_filter( 'ajax_query_attachments_args', array( $this, 'set_query_post_type_to_cpt' ) );

		add_filter( 'the_posts', array( $this, 'change_post_type_to_attachment' ), 10, 2 );

		// Then the URLs are wrong on the thumbnails.
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'prepare_attachment_for_js' ), 10, 2 );
	}


	/**
	 *
	 *
	 * @hooked admin_init
	 * @see wp_ajax_upload_attachment()
	 * @see async-upload.php
	 */
	public function on_upload_attachment(): void {

		if ( ! isset( $_POST['action'] ) ) {
			return;
		}

		if ( ! check_ajax_referer( 'media-form', false, false ) ) {
			return;
		}

		if ( 'upload-attachment' !== $_POST['action'] ) {
			return;
		}

		if ( ! isset( $_POST['post_type'] ) || $this->settings->get_post_type_name() !== $_POST['post_type'] ) {
			return;
		}

		add_filter( 'upload_dir', array( $this, 'set_uploads_subdirectory' ), 10, 1 );

		add_filter( 'wp_insert_attachment_data', array( $this, 'set_post_type_on_insert_attachment' ), 10, 3 );

		add_action( 'add_attachment', array( $this, 'change_post_type_to_attachment_in_cache' ) );
	}

	/**
	 * @hooked ajax_query_attachments_args
	 * @see wp_ajax_query_attachments()
	 *
	 * @param array $query
	 *
	 * @return array
	 */
	public function set_query_post_type_to_cpt( array $query ): array {
		$post_type          = $this->settings->get_post_type_name();
		$query['post_type'] = $post_type;
		return $query;
	}


	// The wp_prepare_attachment_for_js() function expects the posts to be of type attachment
	// $this->posts = apply_filters_ref_array( 'the_posts', array( $this->posts, &$this ) );
	/**
	 * @see wp_prepare_attachment_for_js()
	 *
	 * @param WP_Post[] $posts
	 */
	public function change_post_type_to_attachment( array $posts, $query ): array {

		foreach ( $posts as $post ) {
			$post->post_type = 'attachment';
			$this->change_post_type_to_attachment_in_cache( $post->ID );
		}

		return $posts;
	}

	/**
	 *
	 * @hooked add_attachment
	 *
	 * @param int $post_id
	 */
	public function change_post_type_to_attachment_in_cache( int $post_id ) {

		$cached_post = wp_cache_get( $post_id, 'posts' );
		if ( ! $cached_post ) {
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
	 * @param array   $response
	 * @param WP_Post $attachment
	 *
	 * @return array
	 */
	public function prepare_attachment_for_js( array $response, WP_Post $attachment ) {
		$post_type = $this->settings->get_post_type_name();
		if ( false !== strpos( $attachment->guid, $post_type ) ) {

			foreach ( $response['sizes'] as $name => $size ) {
				$response['sizes'][ $name ]['url'] = str_replace( WP_CONTENT_URL . '/uploads', WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name(), $response['sizes'][ $name ]['url'] );
			}
		}

		$response['url'] = str_replace( WP_CONTENT_URL . '/uploads', WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name(), $response['url'] );

		return $response;
	}

	/**
	 * We are manipulating `attachment` upload, so here we change the `attachment` post type to our own CPT.
	 *
	 * @hooked wp_insert_attachment_data
	 * @see wp_insert_post()
	 * @see wp_ajax_upload_attachment()
	 *
	 * @param array $data                 An array of slashed, sanitized, and processed attachment post data.
	 * @param array $_postarr             An array of slashed and sanitized attachment post data, but not processed.
	 * @param array $_unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed attachment post data
	 *                                    as originally passed to wp_insert_post().
	 * @param bool  $_update              Whether this is an existing attachment post being updated.
	 *
	 * @return array
	 */
	public function set_post_type_on_insert_attachment( array $data, array $_postarr, array $_unsanitized_postarr, bool $_update ): array {
		$post_type         = $this->settings->get_post_type_name();
		$data['post_type'] = $post_type;

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
	public function set_uploads_subdirectory( array $uploads ):array {

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
