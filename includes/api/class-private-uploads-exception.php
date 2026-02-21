<?php
/**
 * A project Exception so we can catch what we know.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use Exception;
use Throwable;
use WP_Error;

/**
 * A regular `\Exception` with an optional `WP_Error`.
 */
class Private_Uploads_Exception extends Exception {

	/**
	 * Constructor.
	 *
	 * @param string     $message The message (likely shown to users).
	 * @param ?WP_Error  $wp_error A WP_Error that may have been "caught" and wrapped.
	 * @param int        $code A machine-readable code.
	 * @param ?Throwable $previous A chain of previous exceptions ("Throwable").
	 */
	public function __construct(
		string $message = '',
		protected ?WP_Error $wp_error = null,
		int $code = 0,
		?Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}

	/**
	 * @return WP_Error|null
	 */
	public function get_wp_error(): ?WP_Error {
		return $this->wp_error;
	}
}
