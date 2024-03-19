<?php
/**
 * Provides functions for moving files to the private uploads folder for the plugin.
 * Creates the directory and checks it is private via a http call.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API implements API_Interface {
	use LoggerAwareTrait;

	/**
	 *
	 * @uses \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
	 * @uses \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::get_plugin_slug()
	 *
	 * @var Private_Uploads_Settings_Interface
	 */
	protected Private_Uploads_Settings_Interface $settings;

	/**
	 * API constructor.
	 *
	 * @param Private_Uploads_Settings_Interface $settings
	 * @param LoggerInterface                    $logger
	 */
	public function __construct( Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->settings = $settings;
	}

	/**
	 * Downloads a file and saves it in uploads/private/...
	 *
	 * @param string             $file_url Remote URL to download file from.
	 * @param ?string            $filename Destination filename.
	 * @param ?DateTimeInterface $datetime Destination uploads subdir date.
	 *
	 * @return array On success, returns an associative array of file attributes.
	 *               On failure, returns `$overrides['upload_error_handler']( &$file, $message )`
	 *               or `array( 'error' => $message )`.
	 */
	public function download_remote_file_to_private_uploads( string $file_url, string $filename = null, ?DateTimeInterface $datetime = null ): array {
		$tmp_file = download_url( $file_url );

		if ( is_wp_error( $tmp_file ) ) {
			// TODO: Look into using `$overrides['upload_error_handler']( &$file, $message )`.
			return array( 'error' => "Failed `download_url( {$file_url} )` in " . __NAMESPACE__ . ' Private Uploads API.' );
		}

		$filename = $filename ?? basename( $file_url );

		$result = $this->move_file_to_private_uploads( $tmp_file, $filename, $datetime );

		if ( is_readable( $tmp_file ) ) {
			unlink( $tmp_file );
		}

		return $result;
	}

	/**
	 * Use internal WordPress functions to move a file into private uploads.
	 * i.e. creates the folders and checks for unique filenames.
	 *
	 * Emulates a file upload.
	 *
	 * The original file is automatically deleted by wp_handle_upload().
	 *
	 * @see wp_handle_upload()
	 *
	 * @param string             $tmp_file The full filepath of the existing file to move.
	 * @param string             $filename The preferred name of the destination file (will be appended with -1, -2 etc as needed).
	 * @param ?DateTimeInterface $datetime A DateTime for which folder the file should be put in, i.e. 2022/22 etc.
	 * @param ?int               $filesize The size in bytes. Calculated automatically.
	 *
	 * @return array On success, returns an associative array of file attributes.
	 *               On failure, returns `$overrides['upload_error_handler']( &$file, $message )`
	 *               or `array( 'error' => $message )`.
	 */
	public function move_file_to_private_uploads( $tmp_file, $filename, ?DateTimeInterface $datetime = null, $filesize = null ): array {
		// Use the file's created date, which is either 'now' or hopefully was read from the webserver.
		// TODO: check does WordPress attempt to read the original file creation date during `download_url()`.
		$datetime = $datetime
					?? DateTimeImmutable::createFromFormat( 'U', (string) ( filectime( $tmp_file ) ?: time() ) )
					?: new DateTimeImmutable();

		// Look at the extension.
		$mime     = wp_check_filetype( $tmp_file );
		$mimetype = $mime['type'];
		if ( ! $mimetype && function_exists( 'mime_content_type' ) ) {
			// Use ext-fileinfo to look inside the file.
			$mimetype = mime_content_type( $tmp_file );
		}

		$file = array(
			'name'     => $filename,
			'type'     => $mimetype,
			'tmp_name' => $tmp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => $filesize ?? filesize( $tmp_file ),
		);

		// TODO, maybe use `$datetime->format('/Y/m');` instead. Consider timezones.
		$yyyymm = '/' . gmdate( 'Y', $datetime->getTimestamp() ) . '/' . gmdate( 'm', $datetime->getTimestamp() );

		$private_directory_name = $this->settings->get_uploads_subdirectory_name();

		/**
		 * Filter wp_upload_dir() to add private
		 *
		 * @see wp_upload_dir()
		 *
		 * Filters the uploads directory data.
		 *
		 * @since 2.0.0
		 *
		 * @param array $uploads {
		 *     Array of information about the upload directory.
		 *
		 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
		 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
		 *     @type string       $private_directory_name  Subdirectory if uploads use year/month folders option is on.
		 *     @type string       $basedir Path without subdir.
		 *     @type string       $baseurl URL path without subdir.
		 *     @type string|false $error   False or error message.
		 * }
		 * @return array $uploads
		 */
		$private_path = function ( $uploads ) use ( $yyyymm, $private_directory_name ) {

			// Use private uploads dir.

			$uploads['basedir'] = "{$uploads['basedir']}/{$private_directory_name}";
			$uploads['baseurl'] = "{$uploads['baseurl']}/{$private_directory_name}";

			$uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
			$uploads['url']  = $uploads['baseurl'] . $uploads['subdir'];
			// Use the correct month.
			$uploads['path'] = str_replace( $uploads['subdir'], $yyyymm, $uploads['path'] );

			return $uploads;
		};
		add_filter( 'upload_dir', $private_path, 10, 1 );

		if ( isset( $_POST['action'] ) ) {
			$post_action_before = $_POST['action'];
		}

		$action          = 'wp_handle_private_upload';
		$_POST['action'] = $action;

		$file = wp_handle_upload( $file, array( 'action' => $action ) );

		if ( isset( $post_action_before ) ) {
			$_POST['action'] = $post_action_before;
		}

		remove_filter( 'upload_dir', $private_path );

		return $file;
	}

	/**
	 * NB: "Transient key names are limited to 191 characters".
	 *
	 * @see https://developer.wordpress.org/reference/functions/set_transient/
	 *
	 * @return string
	 */
	protected function get_is_private_transient_name(): string {
		// Don't share transients between plugins in case schema changes.
		$plugin_slug = sanitize_key( $this->settings->get_plugin_slug() );
		// Sanitize this with a view to allowing private subdirs.
		$subdirectory = sanitize_key( $this->settings->get_uploads_subdirectory_name() );
		return "{$plugin_slug}_private_uploads_{$subdirectory}_is_private";
	}

	/**
	 * @hooked init
	 *
	 * TODO: Don't run this every time.
	 * TODO: Run it when necessary: when a file is moved
	 *
	 * @return array{dir:string|null,message:string}
	 * @throws Exception When PHP fails to create the directory.
	 */
	public function create_directory(): array {
		$dir = WP_CONTENT_DIR . '/uploads/' . $this->settings->get_uploads_subdirectory_name();

		if ( file_exists( $dir ) ) {
			return array(
				'dir'     => $dir,
				'message' => 'Already exists',
			);
		}

		$result = mkdir( $dir );

		if ( false === $result ) {
			$this->logger->error( 'Failed to create directory: ' . $dir, array( 'dir' => $dir ) );
			throw new Exception( 'Failed to create directory: ' . $dir );
		}

		return array(
			'dir'     => $dir,
			'message' => 'Created',
		);
	}

	/**
	 * Check is the URL public, if not add a .htaccess to try make it private.
	 *
	 * Run a `wp_remote_get()` on the directory that should be private to verify the webserver is properly configured.
	 * Save the result in a transient with the value 'public' or 'protected'.
	 *
	 * Run on admin_init, store the transient for 15 minutes, delete on .htaccess write.
	 *
	 * It should be a pretty fast HTTP request anyway, since its target is itself.
	 *
	 * @return array{url:string, is_private:bool|null, http_response_code?:int}
	 */
	public function check_and_update_is_url_private(): array {

		$transient_name = $this->get_is_private_transient_name();

		/**
		 * Null suggests the last check failed.
		 *
		 * @var false|array{is_private:bool|null, url:string} $transient_value
		 */
		$transient_value = get_transient( $transient_name );

		if ( ! empty( $transient_value )
			&& is_array( $transient_value )
			&& isset( $transient_value['is_private'] ) ) {
			return $transient_value;
		}

		$is_url_private_result = array();

		// NB: Browsing to the folder could result in 403 while browsing to a particular filename might not.
		$url                          = WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		$is_url_private_result['url'] = $url;

		$file = WP_CONTENT_DIR . '/uploads/' . $this->settings->get_uploads_subdirectory_name();

		// If the folder does not exist, it does not exist to be private or public, so return null.
		if ( ! file_exists( $file ) ) {
			$is_url_private_result['is_private'] = null;
			return $is_url_private_result;
		}

		$dir   = WP_CONTENT_DIR . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		$files = scandir( $dir ) ?: array();

		// What to do when the directory is empty?

		foreach ( $files as $file ) {
			// This could be a folder or "." or "..".
			if ( is_file( $file ) ) {
				$url = $url . $file;
				break;
			}
		}

		$is_url_private_result = $this->is_url_private( $url );

		// Expiration should match cron job schedule (minus length of time to execute?).
		set_transient( $transient_name, $is_url_private_result, HOUR_IN_SECONDS - 60 );

		return $is_url_private_result;
	}

	/**
	 * Ping the local private upload dir's url and return the HTTP response code and server string.
	 *
	 * @param string $url
	 *
	 * @return array{url:string, is_private:bool|null, http_response_code?:int}
	 */
	protected function is_url_private( string $url ): array {

		$is_url_private_result               = array();
		$is_url_private_result['url']        = $url;
		$is_url_private_result['is_private'] = null;

		// Had tried zero redirections but hadn't worked well.
		$args = array(
			'timeout' => 2,
		);
		// TODO: wp_remote_head()
		$result = wp_remote_get( $url, $args );

		if ( is_wp_error( $result ) ) {
			// This error seems to happen occasionally (intermittently).
			// $this->logger->error( $result->get_error_message(), array( 'error' => $result ) );

		} else {

			$response_code = $result['response']['code'];

			// I think 404 is valid when the directory does exist.
			$private_response_codes = array( 301, 302, 401, 403, 404 );

			$is_url_private_result['is_private'] = in_array( $response_code, $private_response_codes, true );

		}

		return $is_url_private_result;
	}

	/**
	 * Test if the .htaccess redirect is working to return the file when appropriate.
	 * i.e. the webserver might be 403ing for another reason, and never 200ing.
	 *
	 * @param string $url
	 *
	 * @return array{is_private:bool|null}
	 */
	protected function is_url_public_for_admin( string $url ): array {

		$args = array(
			'timeout' => 2,
		);

		$args['cookies'] = array_filter(
			$_COOKIE,
			function ( $value, $key ) {
				return false !== strpos( $key, 'WordPress' );
			},
			ARRAY_FILTER_USE_BOTH
		);

		$result = wp_remote_get( $url, $args );

		$is_url_admin_public_result = array();

		// Should be able to use the cookies that are currently in the request?

		if ( is_wp_error( $result ) ) {
			// This error seems to happen occasionally (intermittently).
			// Return null to indicate we could not determine is it private or not.
			return array( 'is_private' => null );
		} else {
			$response_code = $result['response']['code'];

			// I think 404 is valid when the directory does exist.
			$private_response_codes = array( 301, 401, 403, 404 );

			$is_url_admin_public_result['is_private'] = in_array( $response_code, $private_response_codes, true );
		}

		return $is_url_admin_public_result;
	}
}
