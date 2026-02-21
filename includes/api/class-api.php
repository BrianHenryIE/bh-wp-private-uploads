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
	 * @param Private_Uploads_Settings_Interface $settings The configuration.
	 * @param LoggerInterface                    $logger A PSR logger (optional).
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
	 * @return File_Upload_Result On success, returns file attributes.
	 *                            On failure, returns error message.
	 * @throws Private_Uploads_Exception On permissions failure|WordPress download_url() failure.
	 */
	public function download_remote_file_to_private_uploads( string $file_url, ?string $filename = null, ?DateTimeInterface $datetime = null ): File_Upload_Result {

		if ( ! current_user_can( 'upload_files' ) ) {
			throw new Private_Uploads_Exception( 'Current user does not have permissions to upload files.' );
		}

		$tmp_file = download_url( $file_url );

		if ( is_wp_error( $tmp_file ) ) {
			throw new Private_Uploads_Exception(
				message: "Failed `download_url( {$file_url} )` in " . __NAMESPACE__ . ' Private Uploads API: ' . $tmp_file->get_error_message(),
				wp_error: $tmp_file,
			);
		}

		$filename = sanitize_file_name( basename( $filename ?: $file_url ) );

		$result = $this->move_file_to_private_uploads( $tmp_file, $filename, $datetime );

		if ( is_readable( $tmp_file ) ) {
			wp_delete_file( $tmp_file );
		}

		return $result;
	}

	/**
	 * Use internal WordPress functions to move a file into private uploads.
	 * i.e. creates the folders and checks for unique filenames.
	 *
	 * @see wp_handle_upload()
	 *
	 * @param string             $tmp_file The full filepath of the existing file to move.
	 * @param string             $filename The preferred name of the destination file (will be appended with -1, -2 etc. as needed).
	 * @param ?DateTimeInterface $datetime A DateTime for which folder the file should be put in, i.e. 2022/22 etc.
	 * @param ?int               $filesize The size in bytes. Calculated automatically.
	 *
	 * @return File_Upload_Result On success, returns file attributes.
	 *
	 * @throws Private_Uploads_Exception On permissions failure|file exists failure.
	 */
	public function move_file_to_private_uploads( string $tmp_file, string $filename, ?DateTimeInterface $datetime = null, ?int $filesize = null ): File_Upload_Result {

		if ( ! current_user_can( 'upload_files' ) ) {
			throw new Private_Uploads_Exception( 'Current user does not have permissions to upload files.' );
		}

		if ( ! is_readable( $tmp_file ) ) {
			throw new Private_Uploads_Exception( "Failed to read file( {$tmp_file} )" );
		}

		$file = array(
			'name'     => sanitize_file_name( basename( $filename ) ),
			'tmp_name' => $tmp_file,
			'error'    => UPLOAD_ERR_OK,
		);

		// Look at the extension.
		$mime     = wp_check_filetype( $tmp_file );
		$mimetype = $mime['type'];
		if ( ! $mimetype && function_exists( 'mime_content_type' ) ) {
			// Use `ext-fileinfo` to look inside the file.
			$mimetype = mime_content_type( $tmp_file );
		}
		if ( is_string( $mimetype ) ) {
			$file['type'] = $mimetype;
		}

		$filesize = $filesize ?? filesize( $tmp_file );
		if ( is_int( $filesize ) ) {
			$file['size'] = $filesize;
		}

		add_filter( 'upload_dir', array( $this, 'set_private_uploads_path' ), 10, 1 );

		/**
		 * This function isn't designed to use the `action` POST parameter. We set it to our own action name and
		 * restore the existing one after. We don't use this value anywhere else.
		 *
		 * @see _wp_handle_upload
		 *
		 * phpcs:disable WordPress.Security.NonceVerification.Missing
		 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		 */
		if ( isset( $_POST['action'] ) ) {
			$post_action_before = $_POST['action'];
		}

		$action          = 'wp_handle_private_upload';
		$_POST['action'] = $action;

		/** @var array{error:string}|array{file:string,url:string,type:string} $file */
		$file = wp_handle_upload( $file, array( 'action' => $action ) );

		if ( isset( $post_action_before ) ) {
			$_POST['action'] = $post_action_before;
		}

		if ( isset( $file['error'] ) ) {
			throw new Private_Uploads_Exception( $file['error'] );
		}

		/** @var array{file:string,url:string,type:string} $file */
		remove_filter( 'upload_dir', array( $this, 'set_private_uploads_path' ) );

		return new File_Upload_Result(
			file: $file['file'],
			url: $file['url'],
			type: $file['type'],
		);
	}

	/**
	 * Filters the uploads directory data to add the uploads/{private-dir} subdirectory path.
	 *
	 * @see wp_upload_dir()
	 *
	 * @hooked upload_dir
	 *
	 * @param array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:string|false} $upload_dir_data The array from `wp_upload_dir()`.
	 * @return array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:string|false}
	 */
	public function set_private_uploads_path( array $upload_dir_data ): array {

		// Use private uploads dir.
		$private_directory_name = $this->settings->get_uploads_subdirectory_name();

		$upload_dir_data['basedir'] = "{$upload_dir_data['basedir']}/{$private_directory_name}";
		$upload_dir_data['baseurl'] = "{$upload_dir_data['baseurl']}/{$private_directory_name}";

		$upload_dir_data['path'] = $upload_dir_data['basedir'] . $upload_dir_data['subdir'];
		$upload_dir_data['url']  = $upload_dir_data['baseurl'] . $upload_dir_data['subdir'];

		return $upload_dir_data;
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
	 * @return Create_Directory_Result
	 * @throws Private_Uploads_Exception When PHP fails to create the missing directory.
	 */
	public function create_directory(): Create_Directory_Result {
		$dir = constant( 'WP_CONTENT_DIR' ) . '/uploads/' . $this->settings->get_uploads_subdirectory_name();

		if ( file_exists( $dir ) ) {
			return new Create_Directory_Result(
				dir: $dir,
				created: false,
				message: 'Already exists',
			);
		}

		$result = wp_mkdir_p( $dir );

		if ( false === $result ) {
			$this->logger->error( 'Failed to create directory: ' . $dir, array( 'dir' => $dir ) );
			throw new Private_Uploads_Exception( 'Failed to create directory: ' . $dir );
		}

		return new Create_Directory_Result(
			dir: $dir,
			created: true,
			message: 'Created',
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

		/**
		 * Had tried zero redirections but hadn't worked well.
		 * TODO: try using {@see wp_remote_head()} to make a lighter request.
		 */
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
	 * @param string $url The URL that should generally be private, but should be accessible always for logged in admins.
	 *
	 * @return array{is_private:bool|null} Null when it could not be determined.
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
