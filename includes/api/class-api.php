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
use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron;
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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_can_upload` filter returns false|WordPress download_url() failure.
	 */
	public function download_remote_file_to_private_uploads( string $file_url, ?string $filename = null, ?DateTimeInterface $datetime = null ): File_Upload_Result {

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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_can_upload` filter returns false|file exists failure.
	 */
	public function move_file_to_private_uploads( string $tmp_file, string $filename, ?DateTimeInterface $datetime = null, ?int $filesize = null ): File_Upload_Result {

		/**
		 * Filter whether this file may be moved into private uploads.
		 *
		 * Authorization is the responsibility of the calling code / request handler
		 * (REST permission_callback, admin-ajax capability checks, CLI). This filter
		 * exists for consumer plugins that want an additional guard.
		 *
		 * @hooked "bh_wp_private_uploads_can_upload"
		 *
		 * @param bool   $can_upload     Default true.
		 * @param string $tmp_file       Source filepath.
		 * @param string $filename       Destination filename.
		 * @param string $plugin_slug    The plugin slug of this private uploads instance.
		 * @param string $post_type_name The post type name of this private uploads instance.
		 */
		if ( ! apply_filters(
			'bh_wp_private_uploads_can_upload',
			true,
			$tmp_file,
			$filename,
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name()
		) ) {
			throw new Private_Uploads_Exception( 'Upload rejected by bh_wp_private_uploads_can_upload filter.' );
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
		$post_action_was_set = isset( $_POST['action'] );
		$post_action_before  = $post_action_was_set ? $_POST['action'] : null;

		$action          = 'wp_handle_private_upload';
		$_POST['action'] = $action;

		// The filter must always be removed and `$_POST['action']` restored, even when `wp_handle_upload()`
		// fails; otherwise the `upload_dir` filter stays attached for the rest of the request, silently
		// redirecting all subsequent uploads into the private directory.
		try {
			/**
			 * Because a custom error handler can be used, the array returned could potentially be any shape.
			 *
			 * @var array{error:string}|array{file?:string,url?:string,type?:string} $file
			 */
			$file = wp_handle_upload( $file, array( 'action' => $action ) );

			if ( isset( $file['error'] ) ) {
				throw new Private_Uploads_Exception( $file['error'] );
			}

			if ( ! isset( $file['file'], $file['url'], $file['type'] ) ) {
				$missing = array_diff( array( 'file', 'url', 'type' ), array_keys( $file ) );
				throw new Private_Uploads_Exception( 'Missing keys from wp_handle_upload() result: ' . implode( ', ', $missing ) );
			}
		} finally {
			remove_filter( 'upload_dir', array( $this, 'set_private_uploads_path' ) );

			if ( $post_action_was_set ) {
				$_POST['action'] = $post_action_before;
			} else {
				unset( $_POST['action'] );
			}
		}

		return new File_Upload_Result(
			file: $file['file'],
			url: $file['url'],
			type: $file['type'],
		);
	}

	/**
	 * Downloads a file, saves it in private uploads, and creates a post of the configured custom
	 * post type to record it, assigning an owner (post_author).
	 *
	 * @param string             $file_url Remote URL to download file from.
	 * @param ?string            $filename Destination filename.
	 * @param ?int               $post_author_id User id to assign as owner. Default: none (`post_author` = `0`).
	 * @param ?int               $post_parent_id Post id to attach the file's post to, e.g. a WooCommerce order id.
	 * @param ?DateTimeInterface $datetime Destination uploads subdir date. Does not affect the post's date.
	 *
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_can_upload` filter returns false|WordPress download_url() failure|post creation failure.
	 */
	public function download_remote_file_to_private_uploads_and_create_post(
		string $file_url,
		?string $filename = null,
		?int $post_author_id = null,
		?int $post_parent_id = null,
		?DateTimeInterface $datetime = null
	): File_Upload_With_Post_Result {

		$result = $this->download_remote_file_to_private_uploads( $file_url, $filename, $datetime );

		$post_id = $this->create_post_for_file( $result, $post_author_id, $post_parent_id );

		return new File_Upload_With_Post_Result(
			file: $result->file,
			url: $result->url,
			type: $result->type,
			post_id: $post_id,
		);
	}

	/**
	 * Moves a local file to private uploads and creates a post of the configured custom post type
	 * to record it, assigning an owner (post_author).
	 *
	 * @param string             $tmp_file The full filepath of the existing file to move.
	 * @param string             $filename The preferred name of the destination file (will be appended with -1, -2 etc. as needed).
	 * @param ?int               $post_author_id User id to assign as owner. Default: none (`post_author` = `0`).
	 * @param ?int               $post_parent_id Post id to attach the file's post to, e.g. a WooCommerce order id.
	 * @param ?DateTimeInterface $datetime A DateTime for which folder the file should be put in, i.e. 2022/22 etc. Does not affect the post's date.
	 * @param ?int               $filesize The size in bytes. Calculated automatically.
	 *
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_can_upload` filter returns false|file exists failure|post creation failure.
	 */
	public function move_file_to_private_uploads_and_create_post(
		string $tmp_file,
		string $filename,
		?int $post_author_id = null,
		?int $post_parent_id = null,
		?DateTimeInterface $datetime = null,
		?int $filesize = null
	): File_Upload_With_Post_Result {

		$result = $this->move_file_to_private_uploads( $tmp_file, $filename, $datetime, $filesize );

		$post_id = $this->create_post_for_file( $result, $post_author_id, $post_parent_id );

		return new File_Upload_With_Post_Result(
			file: $result->file,
			url: $result->url,
			type: $result->type,
			post_id: $post_id,
		);
	}

	/**
	 * Create a post of the configured custom post type recording a file already in private uploads.
	 *
	 * Follows the same steps as {@see media_handle_upload()} after {@see wp_handle_upload()}, with the
	 * post type swapped from `attachment` to the configured custom post type, as is done for web UI
	 * uploads in {@see \BrianHenryIE\WP_Private_Uploads\WP_Includes\Media::set_post_type_on_insert_attachment()}.
	 *
	 * @param File_Upload_Result $upload The already-moved file's path, URL and MIME type.
	 * @param ?int               $post_author_id User id to assign as owner. Default: none (`post_author` = `0`).
	 * @param ?int               $post_parent_id Post id to attach the file's post to.
	 *
	 * @return int The new post's id.
	 * @throws Private_Uploads_Exception When wp_insert_attachment() fails.
	 */
	protected function create_post_for_file( File_Upload_Result $upload, ?int $post_author_id = null, ?int $post_parent_id = null ): int {

		$args = array(
			'post_mime_type' => $upload->type,
			'guid'           => $upload->url,
			'post_title'     => sanitize_text_field( wp_basename( $upload->file, '.' . pathinfo( $upload->file, PATHINFO_EXTENSION ) ) ),
			'post_content'   => '',
			'post_excerpt'   => '',
			'post_author'    => $post_author_id ?? 0,
			'post_status'    => 'inherit', // What core forces for `attachment`, which our filtered post type would otherwise rely on.
		);

		if ( ! is_null( $post_parent_id ) ) {
			$args['post_parent'] = $post_parent_id;
		}

		// `wp_insert_attachment()` hardcodes the post type to `attachment`, so filter it to our custom post type.
		// The guid match scopes the swap to this exact insert, in case hooks inside `wp_insert_post()` insert further attachments.
		$post_type     = $this->settings->get_post_type_name();
		$set_post_type =
			/**
			 * @param array<string,mixed> $data An array of slashed, sanitized, and processed attachment post data.
			 * @param array<string,mixed> $postarr An array of slashed and sanitized attachment post data, but not processed.
			 * @return array<string,mixed>
			 */
			function ( array $data, array $postarr ) use ( $post_type, $upload ): array {
				if ( ( $postarr['guid'] ?? '' ) === $upload->url ) {
					$data['post_type'] = $post_type;
				}
				return $data;
			};
		add_filter( 'wp_insert_attachment_data', $set_post_type, 10, 2 );
		try {
			$post_id = wp_insert_attachment( $args, $upload->file, $post_parent_id ?? 0, true );
		} finally {
			remove_filter( 'wp_insert_attachment_data', $set_post_type );
		}

		if ( is_wp_error( $post_id ) ) {
			throw new Private_Uploads_Exception(
				message: 'Failed to create post for private upload: ' . $post_id->get_error_message(),
				wp_error: $post_id,
			);
		}

		// Core pattern, see WP_REST_Attachments_Controller::create_item().
		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/media.php';
		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/image.php';

		wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload->file ) );

		return $post_id;
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
	 * Ensure the private uploads directory exists, best-effort.
	 *
	 * Never throws: this is hooked directly on `init`, where a failure (e.g. a filesystem-permission
	 * error under WP-CLI) must not fatal the site. The directory is recreated lazily on the next upload.
	 * Failures are logged as errors and returned as a `created: false` result carrying the message.
	 *
	 * TODO: does this maybe happen automatically when the first file is moved?
	 *
	 * @hooked init
	 *
	 * TODO: Don't run this every time.
	 * TODO: Run it when necessary: when a file is moved
	 *
	 * @return Create_Directory_Result
	 */
	public function create_directory(): Create_Directory_Result {
		$dir = constant( 'WP_CONTENT_DIR' ) . '/uploads/' . $this->settings->get_uploads_subdirectory_name();

		// Frontend page loads don't need the directory; avoid the filesystem check on every request.
		if ( doing_action( 'init' ) && ! is_admin() && ! wp_doing_cron() ) {
			return new Create_Directory_Result(
				dir: $dir,
				created: false,
				message: 'Possibly a frontend request',
			);
		}

		try {

			if ( file_exists( $dir ) ) {
				return new Create_Directory_Result(
					dir: $dir,
					created: false,
					message: 'Already exists',
				);
			}

			$result = wp_mkdir_p( $dir );

			if ( false === $result ) {
				$message = 'Failed to create directory: ' . $dir;

				$this->logger->error( $message, array( 'dir' => $dir ) );

				return new Create_Directory_Result(
					dir: $dir,
					created: false,
					message: $message,
				);
			}

			return new Create_Directory_Result(
				dir: $dir,
				created: true,
				message: 'Created',
			);

		} catch ( \Throwable $throwable ) {
			$this->logger->debug(
				'Private uploads directory could not be created: ' . $throwable->getMessage(),
				array( 'exception' => $throwable )
			);

			return new Create_Directory_Result(
				dir: $dir,
				created: false,
				message: $throwable->getMessage(),
			);
		}
	}

	/**
	 * NB: "Transient key names are limited to 191 characters".
	 *
	 * @see https://developer.wordpress.org/reference/functions/set_transient/
	 */
	protected function get_is_private_transient_name(): string {
		return sprintf(
			'bh_wp_private_uploads_%s_is_private',
			$this->settings->get_post_type_name()
		);
	}

	/**
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
			// If the `Is_Private_Result` class is modified, deserializing the transient value will fail.
			delete_transient( $transient_name );
		}

		$this->schedule_single_check_is_url_private();

		return null;
	}

	/**
	 * Run the check in the background because the desired 403 response can be misinterpreted by admins as an error message.
	 *
	 * `{plugin_slug}_private_uploads_check_url_{post_type}`.
	 *
	 * @see BH_WP_Private_Uploads_Hooks::define_cron_job_hooks()
	 * @see Cron::check_is_url_public()
	 */
	protected function schedule_single_check_is_url_private(): void {
		$cron_hook = ( new Cron( $this, $this->settings, $this->logger ) )->get_check_url_cron_hook_name();
		if ( ! wp_get_scheduled_event( $cron_hook ) ) {
			wp_schedule_single_event(
				time(),
				$cron_hook
			);
		}
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
