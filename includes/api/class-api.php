<?php
/**
 * Provides functions for moving files to the private uploads folder for the plugin.
 * Creates the directory and checks it is private via a http call.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * @uses \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
 * @uses \BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::get_post_type_name()
 */
class API implements API_Interface {
	use LoggerAwareTrait;

	/**
	 * API constructor.
	 *
	 * @param Private_Uploads_Settings_Interface $settings
	 * @param LoggerInterface                    $logger
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings,
		?LoggerInterface $logger = null
	) {
		$this->setLogger( $logger ?? new NullLogger() );
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
	public function download_remote_file_to_private_uploads( string $file_url, ?string $filename = null, ?DateTimeInterface $datetime = null ): array {
		$tmp_file = download_url( $file_url );

		if ( is_wp_error( $tmp_file ) ) {
			// TODO: Look into using `$overrides['upload_error_handler']( &$file, $message )`.
			return array( 'error' => "Failed `download_url( {$file_url} )` in " . __NAMESPACE__ . ' Private Uploads API.' );
		}

		$filename ??= basename( $file_url );

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
		 * @return array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:string|false} $uploads
		 */
		$private_path = function ( array $uploads ) use ( $yyyymm, $private_directory_name ): array {

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
		return sprintf(
			'bh_wp_private_uploads_%s_is_private',
			$this->settings->get_post_type_name()
		);
	}

	/**
	 * TODO: does this maybe happen automatically when the first file is moved?
	 *
	 * @hooked init
	 *
	 * TODO: Don't run this every time.
	 * TODO: Run it when necessary: when a file is moved
	 *
	 * @return array{dir:string|null,message:string}
	 * @throws Exception When PHP fails to create the directory.
	 */
	public function create_directory(): array {
		$dir = constant( 'WP_CONTENT_DIR' ) . '/uploads/' . $this->settings->get_uploads_subdirectory_name();

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
	 * @hooked admin_init
	 *
	 * @used-by Admin_Notices::admin_notices()
	 */
	public function get_last_checked_is_url_private(): ?Is_Private_Result {

		$transient_name = $this->get_is_private_transient_name();

		try {
			/**
			 * @var false|Is_Private_Result $transient_value
			 */
			$transient_value = get_transient( $transient_name );

			if ( $transient_value instanceof Is_Private_Result ) {
				return $transient_value;
			}
		} catch ( Throwable ) {
			// If the transient class is modified, deserializing the old value will fail.
			delete_transient( $transient_name );
		}

		// Run the check in the background because the desired 403 response can be misinterpreted by admins as an error message.
		wp_schedule_single_event(
			time(),
			'private_uploads_check_url_' . $this->settings->get_post_type_name()
		);

		return null;
	}

	/**
	 * Check is the URL public, store the result in a transient. Return null if undetermined.
	 *
	 * Runs a `wp_remote_get()` on the directory that should be private to verify the webserver is properly configured.
	 *
	 * It should be a pretty fast HTTP request since its target is itself.
	 *
	 * Pings the local private upload dir's url and returns an object indicating is it private and the HTTP response code.
	 *
	 * Changing this to always run on cron so users never misinterpret 403 as an error. It was appearing in Query
	 * Monitor and highlighted red, but 403 is the desired HTTP response code.
	 *
	 * @used-by Cron::check_is_url_public()
	 */
	public function check_and_update_is_url_private(): ?Is_Private_Result {

		$dir = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_DIR' ),
			$this->settings->get_uploads_subdirectory_name()
		);

		// If the folder does not exist, it does not exist to be private or public, so return null.
		if ( ! is_dir( $dir ) ) {
			delete_transient( $this->get_is_private_transient_name() );
			return null;
		}

		// NB: Browsing to the folder could request_response in 403 while browsing to a particular filename might not.
		$url = sprintf(
			'%s/uploads/%s/',
			constant( 'WP_CONTENT_URL' ),
			$this->settings->get_uploads_subdirectory_name()
		);

		// Get the directory listing, except for `.` and `..`.
		$files = array_diff( scandir( $dir ) ?: array(), array( '..', '.' ) );

		// When the directory is empty, we can't check if it is private or not.
		if ( empty( $files ) ) {
			delete_transient( $this->get_is_private_transient_name() );
			return null;
		}

		foreach ( $files as $file ) {
			if ( is_readable( "{$dir}{$file}" ) ) {
				$url = "{$url}{$file}";
				break;
			}
		}

		// Had tried zero redirections but hadn't worked well.
		// TODO: wp_remote_head()
		$request_response = wp_remote_get(
			$url,
			array(
				'timeout' => 2,
			)
		);

		if ( is_wp_error( $request_response ) ) {
			// This error seems to happen occasionally (intermittently).
			$this->logger->info(
				sprintf(
					'Checking private uploads folder %s failed with error %s',
					$this->settings->get_uploads_subdirectory_name(),
					$request_response->get_error_message()
				),
				array( 'error' => $request_response )
			);

			return null;
		}

		$is_private = in_array(
			wp_remote_retrieve_response_code( $request_response ),
			// I think 404 is valid when the directory does exist.
			array( 301, 302, 401, 403, 404 ),
			true
		);

		$is_url_private_result = new Is_Private_Result(
			$url,
			$is_private,
			(int) wp_remote_retrieve_response_code( $request_response ),
			new DateTimeImmutable()
		);

		// Expiration slightly longer than cron schedule – i.e. the transient should always be present.
		set_transient(
			$this->get_is_private_transient_name(),
			$is_url_private_result,
			constant( 'HOUR_IN_SECONDS' ) + 60
		);

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
			fn( $value, $key ) => str_contains( $key, 'WordPress' ),
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
