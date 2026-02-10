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
		 * PHPCS: This is a URL for a file, idempotent, not data being sent and saved.
		 *
		 * TODO: is there a global WordPress request object we can query rather than the PHP server globals directly?
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Recommended
		 */
		if ( ! isset( $_REQUEST[ $file_key ] ) ) {
			return;
		}

		// If the file key is set, we definitely want to handle the request.

		// This is empty when requesting the folder itself.
		$file = $this->get_sanitized_request_value( $file_key );

		$this->send_private_file( $file );
	}

	/**
	 * Get a sanitized request variable or return an empty string if not set.
	 *
	 * TODO: Can we change this to never return an empty string. Note above says it is empty when requesting the directory.
	 *
	 * @param string $key The GET/POST var name.
	 *
	 * PHPCS: This is a URL for a file, idempotent, not data being sent and saved.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification.Recommended
	 */
	protected function get_sanitized_request_value( string $key ): string {
		if ( ! isset( $_REQUEST[ $key ] ) || ! is_string( $_REQUEST[ $key ] ) ) {
			return '';
		}
		return trim( sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) );
	}

	/**
	 * The heavy lifting of the plugin. This sets the output headers and writes the file to the user's browser.
	 *
	 * Ends in `die()`.
	 *
	 * @param string $file The requested filename.
	 */
	protected function send_private_file( string $file ): void {
		/**
		 * Determine should the file be served.
		 *
		 * Default yes to administrator.
		 *
		 * TODO: Consider a custom capability added to the admin role on activation.
		 * TODO: Consider existing capabilities: `read_private_pages`, `edit_files`, `manage_options`.
		 *
		 * phpcs:disable WordPress.WP.Capabilities.RoleFound
		 */
		$should_serve_file = current_user_can( 'administrator' );

		/**
		 * Allow filtering for other users.
		 *
		 * @hooked "bh_wp_private_uploads_{post_type_name}_allow"
		 *
		 * @param bool $should_serve_file
		 * @param string $file
		 */
		$should_serve_file = apply_filters( "bh_wp_private_uploads_{$this->settings->get_post_type_name()}_allow", $should_serve_file, $file );

		// If the user is logged in and should not have access, return a 403.
		// If they are not logged in (401) redirect to the login screen.

		if ( ! $should_serve_file ) {
			// TODO: debug log the user.
			status_header( 403 );
			die();
		}

		// Check the input: $file is a path such as 'foo/bar/abc.jpg'
		// And strip any leading and trailing separators.
		$file = trim( $this->sanitize_filepath( $file ), '/' );

		$upload = wp_upload_dir();

		if ( $upload['error'] ) {
			status_header( 500, 'WP Upload directory error: ' . $upload['error'] );
			die();
		}

		$path = $upload['basedir'] . DIRECTORY_SEPARATOR . $this->settings->get_uploads_subdirectory_name() . DIRECTORY_SEPARATOR . $file;
		if ( ! is_file( $path ) ) {
			status_header( 404, 'File not found' );
			die();
		}

		// Add the mimetype header.
		$mime     = wp_check_filetype( $file );  // This function just looks at the extension.
		$mimetype = $mime['type'];
		if ( ! $mimetype && function_exists( 'mime_content_type' ) ) {

			$mimetype = mime_content_type( $path );  // Use ext-fileinfo to look inside the file.
		}
		if ( ! $mimetype ) {
			$mimetype = 'application/octet-stream';
		}

		header( 'Content-type: ' . $mimetype ); // always send this.

		// Add timing headers.
		$date_format        = 'D, d M Y H:i:s T';  // RFC2616 date format for HTTP.
		$last_modified_unix = filemtime( $path ) ?: time();
		$last_modified      = gmdate( $date_format, $last_modified_unix );
		$etag               = md5( $last_modified );
		header( "Last-Modified: $last_modified" );
		header( 'ETag: "' . $etag . '"' );
		header( 'Expires: ' . gmdate( $date_format, time() + HOUR_IN_SECONDS ) ); // an arbitrary hour from now.

		// Support for caching.
		$client_etag = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ?? '' ) );
		// Example: "If-Modified-Since: Wed, 21 Oct 2015 07:28:00 GMT".
		$client_if_mod_since      = sanitize_text_field( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '' ) );
		$client_if_mod_since_unix = strtotime( $client_if_mod_since );

		if ( $etag === $client_etag ||
			$last_modified_unix <= $client_if_mod_since_unix ) {
			// Return 'not modified' header.
			status_header( 304 );
			die();
		}

		// If we made it this far, just serve the file.
		status_header( 200 );
		/**
		 * WP_Filesystem is only loaded for admin requests, not applicable here.
		 *
		 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		 */
		readfile( $path );
		die();
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
