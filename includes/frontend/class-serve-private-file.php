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
			 * WP_Filesystem is only loaded for admin requests, not applicable here.
			 *
			 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			 */
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
		 * @param string $file           The requested filename.
		 * @param string $plugin_slug    The plugin slug of this private uploads instance.
		 * @param string $post_type_name The post type name of this private uploads instance.
		 */
		$should_serve_file = apply_filters(
			'bh_wp_private_uploads_allow',
			$should_serve_file,
			$file,
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name()
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

		$path = $upload['basedir'] . DIRECTORY_SEPARATOR . $this->settings->get_uploads_subdirectory_name() . DIRECTORY_SEPARATOR . $file;
		if ( ! is_file( $path ) ) {
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

		$headers = array(
			'Content-Type'  => $mimetype,
			'Last-Modified' => $last_modified,
			'ETag'          => '"' . $etag . '"',
			'Expires'       => gmdate( $date_format, time() + HOUR_IN_SECONDS ), // an arbitrary hour from now.
			// Mark the response private so a shared proxy does not cache private content.
			'Cache-Control' => 'private, max-age=3600',
		);

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
				headers: $headers,
			);
		}

		$headers['Content-Length'] = (string) filesize( $path );

		return new Private_File_Response(
			status_code: 200,
			headers: $headers,
			file_to_stream: $path,
		);
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
