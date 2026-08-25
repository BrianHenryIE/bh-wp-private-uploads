<?php
/**
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

/**
 * @used-by API::get_status()
 */
class Status_Result {

	/**
	 * Constructor.
	 *
	 * @param string             $path The absolute filesystem path to the private uploads directory.
	 * @param string             $url The URL of the private uploads directory.
	 * @param int                $post_count The number of posts of the instance's private uploads post type (excluding trashed and auto-draft posts).
	 * @param ?Is_Private_Result $last_checked_is_private The most recent is-the-URL-private check result, null when the URL has not been checked recently.
	 */
	public function __construct(
		public string $path,
		public string $url,
		public int $post_count,
		public ?Is_Private_Result $last_checked_is_private
	) {
	}
}
