<?php
/**
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use DateTimeInterface;

/**
 * @used-by API::check_and_update_is_url_private()
 */
class Is_Private_Result {

	/**
	 * Constructor.
	 *
	 * @param string            $url The tested URL.
	 * @param bool              $is_private The outcome.
	 * @param int               $http_response_code The specific HTTP code.
	 * @param DateTimeInterface $last_checked A record of when it was checked.
	 */
	public function __construct(
		public string $url,
		public bool $is_private,
		public int $http_response_code,
		public DateTimeInterface $last_checked
	) {
	}
}
