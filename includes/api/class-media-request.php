<?php
/**
 * Functions to check if we are on a media library / upload page in order to conditionally add actions/filters.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload;
use WP_Hook;

/**
 * Checks the PHP file, and if `post_type=x` in URLs.
 */
class Media_Request {

	/**
	 * Check is the page loaded in the browser a media-upload related page.
	 */
	public function is_relevant_page(): bool {
		/** @var string $pagenow */
		global $pagenow;
		return in_array( $pagenow, array( 'upload.php', 'media-new.php', 'async-upload.php' ), true );
	}

	/**
	 * Check is `post_type={x}` set in the request url.
	 *
	 * We need to remove and re-add the filter we're running inside to prevent infinite recursion.
	 *
	 * @param string $post_type The WP Post type key (name) to look for.
	 */
	public function request_uri_has_post_type( string $post_type ): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		/** @var array<WP_Hook> $wp_filter */
		global $wp_filter;

		/** @var array{function?:array{0?:object}} $action */
		foreach ( $wp_filter['clean_url']?->callbacks[10] ?? array() as $action ) { /** @phpstan-ignore foreach.nonIterable */
			if ( isset( $action['function'][0] ) && $action['function'][0] instanceof Upload ) {
				/** @var Upload $upload */
				$upload = $action['function'][0];
				remove_filter( 'clean_url', array( $upload, 'clean_url' ) );
			}
		}

		$request_uri = sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) );

		if ( isset( $upload ) ) {
			add_filter( 'clean_url', array( $upload, 'clean_url' ) );
		}

		return $this->uri_has_post_type( $request_uri, $post_type );
	}

	/**
	 * Get the HTTP_REFERER and check does it contain `post_type=x`.
	 *
	 * @param string $post_type The expected post type key/name.
	 */
	public function referer_uri_has_post_type( string $post_type ): bool {
		if ( ! isset( $_SERVER['HTTP_REFERER'] ) || ! is_string( $_SERVER['HTTP_REFERER'] ) ) {
			return false;
		}

		$referrer_uri = sanitize_url( wp_unslash( $_SERVER['HTTP_REFERER'] ) );

		return $this->uri_has_post_type( $referrer_uri, $post_type );
	}

	/**
	 * Parse a URL and see does it have `post_type=x`.
	 *
	 * @param string $uri A URL to check.
	 * @param string $post_type The expected post_type.
	 */
	protected function uri_has_post_type( string $uri, string $post_type ): bool {

		$uri_querystring = wp_parse_url( $uri, PHP_URL_QUERY ) ?: '';

		$parts = wp_parse_args( $uri_querystring );

		return isset( $parts['post_type'] ) && $parts['post_type'] === $post_type;
	}
}
