<?php
/**
 * Filter UI uploads when there is a `post_type=` parameter.
 *
 * TODO: Should this be in the /admin/ folder?
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP;
use WP_Post;
use WP_Query;
use WP_Screen;

/**
 * Enables having a custom attachment post type by modifying cache entries.
 *
 * @see wp-admin/upload.php
 * @see wp-admin/media-new.php
 * @see wp-admin/async-upload.php
 */
class Upload {

	/**
	 * Constructor.
	 *
	 * @param Private_Uploads_Settings_Interface $settings
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings
	) {
		global $pagenow;
		$post_type = $settings->get_post_type_name();
		// &post_type=test_plugin_private
		// ?post_type=test_plugin_private

		if ( ! in_array( $pagenow, array( 'upload.php', 'media-new.php', 'async-upload.php' ) ) ) {
			return;
		}
		$request_post_type = isset( $_REQUEST['post_type'] ) && is_string( $_REQUEST['post_type'] )
			? sanitize_key( $_REQUEST['post_type'] )
			: '';
		$http_referer      = isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
		if ( ! ( $post_type === $request_post_type || false !== strpos( $http_referer, 'post_type=' . $post_type ) ) ) {
			return;
		}

		add_action( 'current_screen', array( $this, 'current_screen' ) );
		add_filter( 'query', array( $this, 'query' ) );
		add_action( 'wp', array( $this, 'wp' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'the_posts', array( $this, 'the_posts' ), 10, 2 );
		add_filter( 'clean_url', array( $this, 'clean_url' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'manage_upload_columns', array( $this, 'manage_upload_columns' ) );
	}

	/**
	 * Replace the current screen object with one for the private uploads post type.
	 *
	 * @hooked current_screen
	 *
	 * @param WP_Screen $current_screen_object
	 */
	public function current_screen( WP_Screen $current_screen_object ): void {

		$post_type        = $this->settings->get_post_type_name();
		$post_type_object = get_post_type_object( $post_type );
		if ( is_null( $post_type_object ) ) {
			return;
		}

		$current_screen_object->post_type = $post_type;

		global $current_screen;
		$current_screen = $current_screen_object;

		/** @var array<string,\WP_Post_Type> $wp_post_types */
		global $wp_post_types;
		$wp_post_types['attachment'] = $post_type_object;
	}

	/**
	 *
	 * TODO: be more specific: post_type = 'attachment'
	 *
	 * @hooked query
	 * @see wpdb::query()
	 *
	 * @param string $query
	 */
	public function query( string $query ): string {

		if ( false === strpos( $query, 'attachment' ) ) {
			return $query;
		}

		$post_type = $this->settings->get_post_type_name();

		return str_replace( 'attachment', $post_type, $query );
	}

	/**
	 * Replace 'attachment' with the private uploads post type.
	 *
	 * @hooked wp
	 *
	 * @param WP $wp
	 */
	public function wp( WP $wp ): void {

		$post_type = $this->settings->get_post_type_name();

		$wp->extra_query_vars['post_type'] = $post_type;
		$wp->query_vars['post_type']       = $post_type;
		$wp->query_string                  = str_replace( 'attachment', $post_type, $wp->query_string );

		/** @var WP_Query */
		global $wp_query;

		$wp_query->query['post_type']      = $post_type;
		$wp_query->query_vars['post_type'] = $post_type;

		$wp_query->request = str_replace( 'attachment', $post_type, $wp_query->request );
	}

	/**
	 *
	 * @hooked pre_get_posts
	 *
	 * @param WP_Query $wp_query
	 */
	public function pre_get_posts( WP_Query $wp_query ): void {

		if ( $wp_query->query['post_type'] !== 'attachment' ) {
			return;
		}

		$wp_query->query['post_type'] = $this->settings->get_post_type_name();
	}

	/**
	 * As the posts are retrieved, change the post type in the cached posts from attachment to the private uploads
	 * post type.
	 *
	 * @hooked the_posts
	 *
	 * @param WP_Post[] $posts
	 * @param WP_Query  $query
	 *
	 * @return WP_Post[]
	 */
	public function the_posts( array $posts, WP_Query $query ): array {

		if ( $query->query['post_type'] !== $this->settings->get_post_type_name() ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			$post->post_type = 'attachment';
			wp_cache_set( $post->ID, $post, 'posts' );
		}

		return $posts;
	}

	/**
	 * Update links inside the page to add the post type to the URLs.
	 *
	 * TODO: This could be neater by breaking down the URL and building it back up again.
	 *
	 * Although individual attachment links use a template defined on the post type object, other links in the page
	 * should link internally and not to the default library.
	 *
	 * @hooked clean_url
	 * @see esc_url
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function clean_url( string $url ): string {
		$post_type = $this->settings->get_post_type_name();

		// If it's already added, just return.
		if ( false !== strpos( $url, 'post_type=' ) ) {
			return $url;
		}

		if ( false === strpos( $url, '/upload.php' )
			&& false === strpos( $url, '/media-new.php' )
			&& false === strpos( $url, '/async-uploads.php' )
		) {
			return $url;
		}

		// TODO: DO NOT DO THIS for the real menu links.
		return add_query_arg( array( 'post_type' => $post_type ), $url );
	}

	/**
	 *
	 * When an attachment is uploaded, change the post type in the cache.
	 *
	 * @hooked admin_init
	 */
	public function admin_init(): void {
		// if we're on async-upload
		// 'fetch' = 3
		// 'attachment_id' = 67

		if ( ! isset( $_POST['fetch'] ) ) {
			return;
		}

		if ( ! isset( $_POST['attachment_id'] ) || ! is_numeric( $_POST['attachment_id'] ) ) {
			return;
		}
		$post_id = (int) $_POST['attachment_id'];

		$post = get_post( $post_id );
		if ( is_null( $post ) ) {
			return;
		}

		$post->post_type = 'attachment';
		wp_cache_set(
			$post_id,
			$post,
			'posts'
		);
	}

	/**
	 * Change table column header "Author" to "Owner".
	 *
	 * @param array{cb:string,title:string,author:string,parent:string,comments:string,date:string} $columns
	 *
	 * @return array{cb:string,title:string,author:string,parent:string,comments:string,date:string}
	 */
	public function manage_upload_columns( array $columns ): array {
		$columns['author'] = 'Owner';
		return $columns;
	}
}
