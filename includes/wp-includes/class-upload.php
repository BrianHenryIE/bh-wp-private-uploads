<?php
/**
 * Filter UI uploads when there is a `post_type=` parameter.
 *
 * TODO: Some of this should be in the /admin/ folder. E.g. changing the column title is very much an admin ui function.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP;
use WP_Post;
use WP_Post_Type;
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
	 * @param Private_Uploads_Settings_Interface $settings To get the post type name.
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings
	) {
		/** @var string $pagenow */
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'upload.php', 'media-new.php', 'async-upload.php' ), true ) ) {
			return;
		}

		$request_post_type = ( function (): string {
			/**
			 * There is no nonce being passed; we are only checking is the `?post_type` equal to a value, not
			 * inserting or updating anything.
			 *
			 * phpcs:disable WordPress.Security.NonceVerification.Recommended
			 */
			return isset( $_REQUEST['post_type'] ) && is_string( $_REQUEST['post_type'] )
				? sanitize_key( wp_unslash( $_REQUEST['post_type'] ) )
				: '';
		} )();

		$http_referer = ( function (): string {
			return isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
		} )();

		$post_type = $settings->get_post_type_name();

		if ( ! ( $post_type === $request_post_type ) && ! str_contains( $http_referer, 'post_type=' . $post_type ) ) {
			return;
		}

		add_action( 'current_screen', array( $this, 'current_screen' ) );
		add_filter( 'query', array( $this, 'replace_post_type_in_query' ) );
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
	 * @param WP_Screen $current_screen_object The global `$current_screen` which we will modify and overwrite.
	 *
	 * We'd prefer not to, but the only way to add a custom "attachment" involves messing with globals.
	 *
	 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
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

		/** @var array<string,WP_Post_Type> $wp_post_types */
		global $wp_post_types;
		$wp_post_types['attachment'] = $post_type_object;
	}

	/**
	 *
	 * The query being modified is raw SQL in `wp-includes/media.php:4894`.
	 *
	 * @see wp_enqueue_media()
	 *
	 * @hooked query
	 * @see wpdb::query()
	 *
	 * @param string $query SQL query from wpdb.
	 */
	public function replace_post_type_in_query( string $query ): string {

		if ( ! str_contains( $query, 'attachment' ) ) {
			return $query;
		}

		return preg_replace(
			'/(post_type\s*=\s*)([\'"])(attachment)([\'"])/',
			'$1$2' . sanitize_key( $this->settings->get_post_type_name() ) . '$4',
			$query
		) ?? $query; // TODO: log if thus happens.
	}

	/**
	 * Replace 'attachment' with the private uploads post type.
	 *
	 * @hooked wp
	 * @see WP::main()
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
	 * This is only hooked when `post_type={our-post-type}` via a media library UI fetch for "atachments". So we
	 * change the post type from attachment to the post type the private uploads use.
	 *
	 * @hooked pre_get_posts
	 *
	 * @param WP_Query $wp_query The query that is about to be performed.
	 */
	public function pre_get_posts( WP_Query $wp_query ): void {

		if ( 'attachment' !== $wp_query->query['post_type'] ) {
			return;
		}

		$wp_query->query['post_type'] = $this->settings->get_post_type_name();
	}

	/**
	 * As the posts are retrieved, change the post type in the cached posts from attachment to the private uploads
	 * post type.
	 *
	 * TODO: This is the same logic as {@see Media::change_post_type_to_attachment()}.
	 *
	 * @hooked the_posts
	 *
	 * @param WP_Post[] $posts A list of posts to be sent to the frontend.
	 * @param WP_Query  $query The original query that found those posts.
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
	 * Although individual attachment links use a template defined on the post type object, other links in the page
	 * should link internally and not to the default media library.
	 *
	 * @hooked clean_url
	 * @see esc_url
	 *
	 * @param string $url A link that has been passed through `esc_url()`.
	 */
	public function clean_url( string $url ): string {

		$post_type = $this->settings->get_post_type_name();

		// If we're not on a page that has it in its querystring, return.
		if ( ! $this->request_uri_has_post_type( $post_type ) ) {
			return $url;
		}

		// If it's already added to the url, just return.
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query && str_contains( $query, 'post_type=' ) ) {
			return $url;
		}

		// If it's not a media library URL, do not change it.
		if ( ! in_array(
			basename( wp_parse_url( $url, PHP_URL_PATH ) ?: '' ),
			array( 'upload.php', 'media-new.php', 'async-uploads.php' ),
			true
		) ) {
			return $url;
		}

		return add_query_arg( array( 'post_type' => $post_type ), $url );
	}

	/**
	 * Check is `post_type={x}` set in the request url.
	 *
	 * We need to remove and re-add the filter we're running inside to prevent infinite recursion.
	 *
	 * @param string $post_type The WP Post type key (name) to look for.
	 */
	protected function request_uri_has_post_type( string $post_type ): bool {
		remove_filter( 'clean_url', array( $this, 'clean_url' ) );
		$request_uri_query = wp_parse_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), PHP_URL_QUERY ) ?: '';
		add_filter( 'clean_url', array( $this, 'clean_url' ) );

		$parts = wp_parse_args( $request_uri_query );

		return isset( $parts['post_type'] ) && $parts['post_type'] === $post_type;
	}

	/**
	 * ???? I guess immediately after it's uploaded there's another request to fetch the details??
	 *
	 * When we're on async-upload the request looks like: `{fetch:3, attachment_id:int}`.
	 *
	 * When an attachment is uploaded, change the post type in the cache.
	 *
	 * The "Add Media File" menu, `media-new.php` POSTs new uploads from the "Upload New Media" drag and drop form
	 * to `async-upload.php` without a nonce.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Missing
	 *
	 * @hooked admin_init
	 */
	public function admin_init(): void {

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
	 * The `cb` key in the arrays means "checkbox".
	 *
	 * @param array{cb:string,title:string,author:string,parent:string,comments:string,date:string} $columns The columns displayed on the Media > Library page.
	 *
	 * @return array{cb:string,title:string,author:string,parent:string,comments:string,date:string}
	 */
	public function manage_upload_columns( array $columns ): array {
		$columns['author'] = 'Owner';
		return $columns;
	}
}
