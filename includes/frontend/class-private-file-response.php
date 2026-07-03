<?php
/**
 * The outcome of deciding how to respond to a private-file request: a plain value object so the
 * decision logic in {@see Serve_Private_File::get_response_for_request()} can be unit tested without
 * emitting headers or calling `die()`.
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Frontend;

/**
 * Immutable description of the HTTP response to send for a private-file request.
 */
class Private_File_Response {

	/**
	 * Constructor.
	 *
	 * @param int                   $status_code       The HTTP status code to send.
	 * @param array<string, string> $headers           Response headers, name => value.
	 * @param ?string               $file_to_stream    Absolute path of the file to `readfile()`, or null when there is no body.
	 * @param bool                  $redirect_to_login When true, the caller should `auth_redirect()` (send the visitor to wp-login) rather than sending `$status_code`.
	 * @param ?string               $status_description Optional status-header description, e.g. "File not found" for 404.
	 */
	public function __construct(
		public int $status_code,
		public array $headers = array(),
		public ?string $file_to_stream = null,
		public bool $redirect_to_login = false,
		public ?string $status_description = null,
	) {
	}
}
