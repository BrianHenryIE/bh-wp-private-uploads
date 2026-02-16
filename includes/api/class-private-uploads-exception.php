<?php
/**
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use Exception;
use Throwable;
use WP_Error;

class Private_Uploads_Exception extends Exception {
	public function __construct(
		string $message = '',
		protected ?WP_Error $wp_error = null,
		int $code = 0,
		?Throwable $previous = null
	) {
		parent::__construct( $message, $code, $previous );
	}

	public function get_wp_error(): ?WP_Error {
		return $this->wp_error;
	}
}
