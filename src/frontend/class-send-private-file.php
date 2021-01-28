<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/BrianHenryIE/private-uploads
 * @since      0.2.0
 *
 * @package    Private_Uploads
 * @subpackage Private_Uploads/frontend
 */

namespace BH_WP_Private_Uploads\frontend;

/**
 * The public-facing functionality of the plugin.
 *
 * @package    Private_Uploads
 * @subpackage Private_Uploads/frontend
 * @author     Chris Dennis <cgdennis@btinternet.com>
 * @author     Brian Henry <BrianHenryIE@gmail.com>
 */
class Send_Private_File {

	/**
	 * Hook into the init action to look for our HTTP arguments
	 *
	 * @hooked init
	 */
	public function init() {
		$folder = $this->request_value( 'pucd-folder' );
		$file   = $this->request_value( 'pucd-file' );

		if ( $file && $folder ) {
			$this->send_private_file( $folder, $file );
		}
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	protected function request_value( $key ) {
		return isset( $_REQUEST[ $key ] ) ? trim( $_REQUEST[ $key ] ) : '';
	}

	/**
	 * The heavy lifting of the plugin. This sets the output headers and writes the file to the user's browser.
	 *
	 * @param string $folder The folder path to return the file from.
	 * @param string $file The requested filename.
	 */
	protected function send_private_file( $folder, $file ) {

		// Only return files to logged-in users.
		if ( ! is_user_logged_in() ) {
			status_header( '403' );
			die();
		}

		// Check the inputs: both $folder and $file are either simple
		// filenames such as 'abc.jpg' or paths such as 'foo/bar/abc.jpg'
		// And strip any leading and trailing separators.
		$folder = trim( $this->sanitize_dir_name( $folder ), '/' );
		$file   = trim( $this->sanitize_dir_name( $file ), '/' );

		$upload = wp_upload_dir();

		if ( $upload['error'] ) {
			status_header( 500, 'WP Upload directory error: ' . $upload['error'] );
			die();
		}

		$path = $upload['basedir'] . '/' . $folder . '/' . $file;
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
		$last_modified_unix = filemtime( $path );
		$last_modified      = gmdate( $date_format, filemtime( $path ) );
		$etag               = md5( $last_modified );
		header( "Last-Modified: $last_modified" );
		header( 'ETag: "' . $etag . '"' );
		header( 'Expires: ' . gmdate( $date_format, time() + 3600 ) ); // an arbitrary hour from now.

		// Support for caching.
		$client_etag              = $this->request_value( 'HTTP_IF_NONE_MATCH' );
		$client_if_mod_since      = $this->request_value( 'HTTP_IF_MODIFIED_SINCE' );
		$client_if_mod_since_unix = strtotime( $client_if_mod_since );

		if ( $etag === $client_etag ||
			$last_modified_unix <= $client_if_mod_since_unix ) {
			// Return 'not modified' header.
			status_header( 304 );
			die();
		}

		// If we made it this far, just serve the file.
		status_header( 200 );
		readfile( $path );
		die();

	}

	/**
	 * Sanitize each part of a path name.
	 *
	 * @see sanitize_file_name()
	 *
	 * @param string $dir
	 *
	 * @return string
	 */
	protected function sanitize_dir_name( $dir ) {

		$filenames     = explode( '/', $dir );
		$new_filenames = array();
		foreach ( $filenames as $fn ) {
			$new_filenames[] = sanitize_file_name( $fn );
		}

		return implode( '/', $new_filenames );
	}
}
