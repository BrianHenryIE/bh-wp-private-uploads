<?php
/**
 * The public entrypoint of the library.
 *
 * @link       https://github.com/BrianHenryIE/private-uploads
 *
 * phpcs:disable Squiz.Commenting.FileComment.DuplicateAuthorTag
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 * @author     Chris Dennis <cgdennis@btinternet.com>
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Frontend;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite;
use DateTimeImmutable;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

/**
 * @see WP_Rewrite::register_rewrite_rule()
 */
class Serve_Private_File {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param Private_Uploads_Settings_Interface $settings Mostly for the post type name.
	 * @param LoggerInterface                    $logger PSR logger.
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Hook into the init action to look for our HTTP arguments
	 *
	 * @hooked init
	 *
	 * @see WP_Rewrite::register_rewrite_rule()
	 *
	 * @return void Either returns quickly or outputs the file and `die()`s.
	 */
	public function init(): void {
		$file_key = sprintf(
			'%s-private-uploads-file',
			str_underscores_to_hyphens( $this->settings->get_post_type_name() )
		);

		/**
		 * If the file key is not set, the request is not relevant to us.
		 *
		 * PHPCS: This is a URL for a file, idempotent, not data being sent and saved.
		 *
		 * TODO: is there a global WordPress request object we can query rather than the PHP server globals directly?
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( empty( $_REQUEST[ $file_key ] ) || ! is_string( $_REQUEST[ $file_key ] ) ) {
			return;
		}

		// This is empty when requesting the folder itself.
		$file = trim( sanitize_text_field( wp_unslash( $_REQUEST[ $file_key ] ) ) );

		$this->send_private_file( $file );
	}

	/**
	 * The heavy lifting of the plugin. This sets the output headers and writes the file to the user's browser.
	 *
	 * The decision logic lives in the pure {@see self::get_response_for_request()}; this method only
	 * performs the side effects (redirect / status / headers / streaming) and ends in `die()`.
	 *
	 * @param string $file The requested filename.
	 */
	protected function send_private_file( string $file ): void {

		$response = $this->get_response_for_request( $file );

		// Not logged in and not allowed: send the visitor to wp-login with a return URL. `auth_redirect()`
		// sets the headers and exits itself.
		if ( $response->redirect_to_login ) {
			auth_redirect();
			return;
		}

		if ( null !== $response->status_description ) {
			status_header( $response->status_code, $response->status_description );
		} else {
			status_header( $response->status_code );
		}

		foreach ( $response->headers as $name => $value ) {
			header( "{$name}: {$value}" );
		}

		if ( null !== $response->file_to_stream ) {
			// Discard any active output buffers (prepended whitespace, notices, …) so they cannot corrupt
			// the streamed file.
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			/**
			 * `WP_Filesystem` has no streaming primitive – its only read methods are `get_contents()` and
			 * `get_contents_array()`, both of which read the entire file into PHP memory. That would be a
			 * `memory_limit` fatal on a large private file, so this cannot be migrated. WordPress serves
			 * multisite attachments the same way.
			 *
			 * `readfile()` writes straight to the output stream, and the buffers are torn down above, so
			 * the file is never materialised in memory.
			 *
			 * @see https://developer.wordpress.org/reference/classes/wp_filesystem_direct/get_contents/
			 * @see ms-files.php Core's own attachment handler: `readfile( $file );`
			 */
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- See above.
			readfile( $response->file_to_stream );
		}

		die();
	}

	/**
	 * Decide how to respond to a request for a private file, without performing any side effects.
	 *
	 * Extracted from {@see self::send_private_file()} so the access-control and caching logic can be
	 * unit tested (the `die()` calls previously made the class untestable).
	 *
	 * @param string $file The requested filename, relative to the private uploads directory.
	 */
	protected function get_response_for_request( string $file ): Private_File_Response {

		/**
		 * Determine should the file be served. Default yes to users who can `manage_options`.
		 *
		 * `manage_options` is a capability (unlike the `administrator` role name), so it also matches
		 * multisite super admins who have no explicit role on the site.
		 */
		$should_serve_file = current_user_can( 'manage_options' );

		/**
		 * Allow filtering for other users.
		 *
		 * @hooked "bh_wp_private_uploads_allow"
		 *
		 * @param bool   $should_serve_file
		 * @param string $plugin_slug    The plugin slug of this private uploads instance.
		 * @param string $post_type_name The post type name of this private uploads instance.
		 * @param string $file           The requested filename.
		 */
		$should_serve_file = apply_filters(
			'bh_wp_private_uploads_allow',
			$should_serve_file,
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name(),
			$file
		);

		/**
		 * Runs after the replacement filter so unmigrated callbacks keep the final say.
		 *
		 * @deprecated 0.4.0 Use "bh_wp_private_uploads_allow", which is passed the plugin slug and post type name.
		 */
		$should_serve_file = (bool) apply_filters_deprecated(
			"bh_wp_private_uploads_{$this->settings->get_post_type_name()}_allow",
			array( $should_serve_file, $file ),
			'0.4.0',
			'bh_wp_private_uploads_allow'
		);

		if ( ! $should_serve_file ) {
			// If they are not logged in, redirect to the login screen; otherwise a plain 403.
			if ( ! is_user_logged_in() ) {
				return new Private_File_Response( status_code: 302, redirect_to_login: true );
			}
			// TODO: debug log the user.
			return new Private_File_Response( status_code: 403 );
		}

		// Check the input: $file is a path such as 'foo/bar/abc.jpg'
		// And strip any leading and trailing separators.
		$file = trim( $this->sanitize_filepath( $file ), '/' );

		$upload = wp_upload_dir();

		if ( $upload['error'] ) {
			return new Private_File_Response(
				status_code: 500,
				status_description: 'WP Upload directory error: ' . $upload['error'],
			);
		}

		$private_uploads_directory = $upload['basedir'] . DIRECTORY_SEPARATOR . $this->settings->get_uploads_subdirectory_name();

		$path = $private_uploads_directory . DIRECTORY_SEPARATOR . $file;
		if ( ! is_file( $path ) ) {
			return new Private_File_Response(
				status_code: 404,
				status_description: 'File not found',
			);
		}

		/**
		 * Second layer of path traversal defence. `sanitize_filepath()` above removes `..`, but cannot know that
		 * a segment of the path is a symlink leading out of the private uploads directory.
		 *
		 * Answered as a 404, the same as a file that does not exist.
		 */
		if ( ! $this->is_path_inside_directory( $path, $private_uploads_directory ) ) {
			$this->logger->warning(
				'Refused to serve {file}: it resolves to a location outside the private uploads directory.',
				array(
					'file'    => $file,
					'user_id' => get_current_user_id(),
				)
			);
			return new Private_File_Response(
				status_code: 404,
				status_description: 'File not found',
			);
		}

		// Determine the mimetype header.
		$mime     = wp_check_filetype( $file );  // This function just looks at the extension.
		$mimetype = $mime['type'];
		if ( ! $mimetype && function_exists( 'mime_content_type' ) ) {

			$mimetype = mime_content_type( $path );  // Use ext-fileinfo to look inside the file.
		}
		if ( ! $mimetype ) {
			$mimetype = 'application/octet-stream';
		}

		$date_format        = 'D, d M Y H:i:s T';  // RFC2616 date format for HTTP.
		$last_modified_unix = filemtime( $path ) ?: time();
		$last_modified      = gmdate( $date_format, $last_modified_unix );
		$etag               = md5( $last_modified );

		$max_age_seconds = $this->get_cache_control_max_age( $file );

		$headers = array(
			'Content-Type'           => $mimetype,
			// The browser must use the `Content-Type` above, and never guess e.g. HTML from the file's contents.
			'X-Content-Type-Options' => 'nosniff',
			'Content-Disposition'    => $this->get_content_disposition( $mimetype, $file ),
			'Last-Modified'          => $last_modified,
			'ETag'                   => '"' . $etag . '"',
			'Expires'                => gmdate( $date_format, time() + $max_age_seconds ),
			// Mark the response private so a shared proxy does not cache private content.
			'Cache-Control'          => "private, max-age={$max_age_seconds}",
		);

		if ( $this->is_sandboxed_mime_type( $mimetype ) ) {
			// `sandbox` gives the document a unique origin with scripts disabled; `default-src 'none'` stops it loading anything.
			$headers['Content-Security-Policy'] = "sandbox; default-src 'none'";
		}

		/**
		 * Per RFC 7232 §3.3, a recipient must ignore `If-Modified-Since` when `If-None-Match` is present,
		 * so evaluate `If-Modified-Since` only when there is no `If-None-Match` header.
		 */
		$is_not_modified = $this->has_if_none_match()
			? $this->etag_matches( $etag )
			: $this->is_not_modified_since( $last_modified_unix );

		if ( $is_not_modified ) {
			/**
			 * Return 'not modified' header.
			 *
			 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Status/304
			 */
			return new Private_File_Response(
				status_code: 304,
				headers: $this->filter_response_headers( $headers, $file ),
			);
		}

		$headers['Content-Length'] = (string) filesize( $path );

		return new Private_File_Response(
			status_code: 200,
			headers: $this->filter_response_headers( $headers, $file ),
			file_to_stream: $path,
		);
	}

	/**
	 * After resolving symlinks, `.` and `..`, is the path inside the directory?
	 *
	 * Both are resolved, so the directory itself may be, or be below, a symlink. Whole path segments are
	 * compared: `/uploads/private-other/file` is not inside `/uploads/private`.
	 *
	 * @param string $path      The path of an existing file.
	 * @param string $directory The directory the file must be inside.
	 */
	protected function is_path_inside_directory( string $path, string $directory ): bool {

		$real_path      = realpath( $path );
		$real_directory = realpath( $directory );

		if ( false === $real_path || false === $real_directory ) {
			return false;
		}

		return str_starts_with(
			$real_path,
			rtrim( $real_directory, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR
		);
	}

	/**
	 * How long, in seconds, the browser may cache the file before revalidating it. Default one hour.
	 *
	 * The response is always `Cache-Control: private`, so only the user's own browser caches it. Until the
	 * max-age passes the browser may show its copy without asking the server, i.e. without the permission
	 * check running again – return `0` to have every view revalidated.
	 *
	 * @param string $file The sanitized requested filename, relative to the private uploads directory.
	 */
	protected function get_cache_control_max_age( string $file ): int {

		$default_max_age_seconds = HOUR_IN_SECONDS;

		/**
		 * Filter the `Cache-Control` max-age of a served private file. The `Expires` header is set to match.
		 *
		 * A return value that is not a non-negative integer is ignored and the default is used. (The value is
		 * documented as `mixed` because that is what a callback may return; it is passed, and should be, an `int`.)
		 *
		 * @hooked "bh_wp_private_uploads_cache_control_max_age"
		 *
		 * @param mixed  $max_age_seconds The max-age in seconds, an integer. Default `HOUR_IN_SECONDS`.
		 * @param string $plugin_slug     The plugin slug of this private uploads instance.
		 * @param string $post_type_name  The post type name of this private uploads instance.
		 * @param string $file            The requested filename, relative to the private uploads directory.
		 */
		$max_age_seconds = apply_filters(
			'bh_wp_private_uploads_cache_control_max_age',
			$default_max_age_seconds,
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name(),
			$file
		);

		if ( ! is_numeric( $max_age_seconds ) || (int) $max_age_seconds < 0 ) {
			return $default_max_age_seconds;
		}

		return (int) $max_age_seconds;
	}

	/**
	 * Mime types that cannot run scripts, so are safe to display in the browser on the site's origin.
	 *
	 * Everything else – notably HTML, SVG and XML – is sent as a download. The types are those the file is
	 * *served* as: with `nosniff` the browser does not second-guess them.
	 *
	 * @param string $mimetype The `Content-Type` the file is served with.
	 */
	protected function is_inline_mime_type( string $mimetype ): bool {

		$inline_mime_types = array(
			'image/jpeg',
			'image/png',
			'image/gif',
			'image/webp',
			'image/avif',
			'application/pdf',
			'text/plain',
		);

		return in_array( $mimetype, $inline_mime_types, true )
			|| str_starts_with( $mimetype, 'audio/' )
			|| str_starts_with( $mimetype, 'video/' );
	}

	/**
	 * Should the inline response also be sandboxed with a `Content-Security-Policy`?
	 *
	 * Images and plain text are. PDF, audio and video are displayed by the browser's built-in viewers, which
	 * fail to load under `sandbox` / `default-src 'none'`, and those types cannot run scripts on the site's
	 * origin anyway. Downloads (`Content-Disposition: attachment`) are not rendered so need no policy.
	 *
	 * @param string $mimetype The `Content-Type` the file is served with.
	 */
	protected function is_sandboxed_mime_type( string $mimetype ): bool {
		return $this->is_inline_mime_type( $mimetype )
			&& ( str_starts_with( $mimetype, 'image/' ) || 'text/plain' === $mimetype );
	}

	/**
	 * `inline` for the types that are safe to display in the browser, `attachment` (download) for the rest.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Disposition
	 *
	 * @param string $mimetype The `Content-Type` the file is served with.
	 * @param string $file     The sanitized requested filename, relative to the private uploads directory.
	 */
	protected function get_content_disposition( string $mimetype, string $file ): string {

		$disposition = $this->is_inline_mime_type( $mimetype ) ? 'inline' : 'attachment';

		$filename = sanitize_file_name( wp_basename( $file ) );

		// The quoted `filename` must be ASCII, without quotes, backslashes or control characters.
		$ascii_filename = (string) preg_replace( '/[^\x20-\x7E]|["\\\\]/u', '_', $filename );

		$content_disposition = "{$disposition}; filename=\"{$ascii_filename}\"";

		if ( $ascii_filename !== $filename ) {
			// RFC 5987 encoding, which browsers prefer when present.
			$content_disposition .= "; filename*=UTF-8''" . rawurlencode( $filename );
		}

		return $content_disposition;
	}

	/**
	 * Allow consumers to add, change or remove the headers sent with a private file.
	 *
	 * Only applied when the file is being served (200) or is unchanged (304), i.e. after the access check.
	 *
	 * @param array<string,string> $headers The response headers, name => value.
	 * @param string               $file    The sanitized requested filename, relative to the private uploads directory.
	 * @return array<string,string>
	 */
	protected function filter_response_headers( array $headers, string $file ): array {

		/**
		 * Filter the headers sent with a private file.
		 *
		 * Take care removing `X-Content-Type-Options`, or changing `Content-Disposition` to `inline` for a
		 * type that can contain scripts (HTML, SVG): the file would run on the site's origin as the viewer.
		 *
		 * A return value that is not an array is ignored, as are entries that are not `string => scalar`. (The
		 * value is documented as `mixed` because that is what a callback may return; it is passed, and should
		 * be, an `array<string,string>` of name => value.)
		 *
		 * @hooked "bh_wp_private_uploads_response_headers"
		 *
		 * @param mixed  $headers        The response headers, name => value.
		 * @param string $plugin_slug    The plugin slug of this private uploads instance.
		 * @param string $post_type_name The post type name of this private uploads instance.
		 * @param string $file           The requested filename, relative to the private uploads directory.
		 */
		$filtered_headers = apply_filters(
			'bh_wp_private_uploads_response_headers',
			$headers,
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name(),
			$file
		);

		if ( ! is_array( $filtered_headers ) ) {
			return $headers;
		}

		$valid_headers = array();
		foreach ( $filtered_headers as $name => $value ) {
			if ( ! is_string( $name ) || ! is_scalar( $value ) ) {
				continue;
			}
			// A header can never span lines.
			$valid_headers[ str_replace( array( "\r", "\n" ), '', $name ) ] = str_replace( array( "\r", "\n" ), '', (string) $value );
		}

		return $valid_headers;
	}

	/**
	 * Whether the request carries an `If-None-Match` header. When it does, `If-Modified-Since` must be
	 * ignored (RFC 7232 §3.3).
	 */
	protected function has_if_none_match(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) && is_string( $_SERVER['HTTP_IF_NONE_MATCH'] );
	}

	/**
	 * Whether the request's `If-None-Match` header matches the file's ETag.
	 *
	 * The ETag is sent quoted (and may be returned by the client with a `W/` weak-validator prefix), so
	 * strip both before comparing with the raw hash.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-None-Match
	 *
	 * @param string $etag The raw (unquoted) ETag hash.
	 */
	protected function etag_matches( string $etag ): bool {

		/**
		 * PHPCS: This is a cache validator for a file download, idempotent, not data being saved.
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) || ! is_string( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			return false;
		}

		$candidate = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) );

		// Strip an optional weak-validator prefix, then the surrounding double quotes.
		if ( str_starts_with( $candidate, 'W/' ) ) {
			$candidate = substr( $candidate, 2 );
		}
		$candidate = trim( $candidate, '"' );

		return hash_equals( $etag, $candidate );
	}

	/**
	 * Whether the request's `If-Modified-Since` header is at or after the file's modified time.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/If-Modified-Since
	 *
	 * Example: "If-Modified-Since: Wed, 21 Oct 2015 07:28:00 GMT".
	 *
	 * @param int $last_modified_unix The file's modified time as a Unix timestamp.
	 */
	protected function is_not_modified_since( int $last_modified_unix ): bool {

		/**
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) || ! is_string( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			return false;
		}

		$modified_since_string = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) );

		try {
			$modified_since = new DateTimeImmutable( $modified_since_string );
		} catch ( Exception $exception ) {
			$this->logger->warning(
				'Failed to parse If-Modified-Since header ' . $modified_since_string . ' as DateTime. The file will be returned regardless.',
				array(
					'exception' => $exception,
				)
			);
			return false;
		}

		return $last_modified_unix <= (int) $modified_since->format( 'U' );
	}

	/**
	 * Sanitize each part of a file path.
	 *
	 * @see sanitize_file_name()
	 *
	 * @param string $relative_filepath The path is always relative to private uploads' directory.
	 * @return string Without leading slash.
	 */
	protected function sanitize_filepath( string $relative_filepath ): string {
		return implode( '/', array_map( 'sanitize_file_name', explode( '/', $relative_filepath ) ) );
	}
}
