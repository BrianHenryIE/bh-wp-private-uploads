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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_*_can_upload` filter returns false|WordPress download_url() failure.
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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_*_can_upload` filter returns false|file exists failure.
	 */
	public function move_file_to_private_uploads( string $tmp_file, string $filename, ?DateTimeInterface $datetime = null, ?int $filesize = null ): File_Upload_Result {

		/**
		 * Filter whether this file may be moved into private uploads.
		 *
		 * Authorization is the responsibility of the calling code / request handler
		 * (REST permission_callback, admin-ajax capability checks, CLI). This filter
		 * exists for consumer plugins that want an additional guard.
		 *
		 * @param bool   $can_upload Default true.
		 * @param string $tmp_file   Source filepath.
		 * @param string $filename   Destination filename.
		 */
		if ( ! apply_filters( "bh_wp_private_uploads_{$this->settings->get_post_type_name()}_can_upload", true, $tmp_file, $filename ) ) {
			throw new Private_Uploads_Exception( 'Upload rejected by bh_wp_private_uploads_*_can_upload filter.' );
		}

		if ( ! is_readable( $tmp_file ) ) {
			throw new Private_Uploads_Exception( "Failed to read file( {$tmp_file} )" );
		}

		// Ensure the private directory and its guard files exist even when `init`-time creation was skipped
		// (e.g. cron / WP-CLI, or throttled). Cheap when already created (an option lookup).
		$this->create_directory();

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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_*_can_upload` filter returns false|WordPress download_url() failure|post creation failure.
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
	 * @throws Private_Uploads_Exception When the `bh_wp_private_uploads_*_can_upload` filter returns false|file exists failure|post creation failure.
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
	 * The absolute filesystem path to the private uploads directory.
	 *
	 * Derived from `wp_upload_dir()` rather than `WP_CONTENT_DIR` so it is correct on multisite
	 * (per-site `sites/{blog_id}` directories) and relocated-uploads (`UPLOADS` constant) installs, and
	 * matches where {@see \BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File::send_private_file()}
	 * reads files from.
	 *
	 * `wp_upload_dir( null, false )` avoids the directory-creation side effect where only the path is needed.
	 */
	protected function get_private_uploads_directory_path(): string {
		$upload_dir = wp_upload_dir( null, false );
		$subdir     = trim( $this->settings->get_uploads_subdirectory_name(), '/' );
		return rtrim( $upload_dir['basedir'], '/' ) . '/' . $subdir;
	}

	/**
	 * The URL of the private uploads directory. Companion to {@see self::get_private_uploads_directory_path()}.
	 */
	protected function get_private_uploads_directory_url(): string {
		$upload_dir = wp_upload_dir( null, false );
		$subdir     = trim( $this->settings->get_uploads_subdirectory_name(), '/' );
		return rtrim( $upload_dir['baseurl'], '/' ) . '/' . $subdir;
	}

	/**
	 * Bumped whenever the guard-file contents change, so an existing install rewrites them.
	 *
	 * @see self::get_directory_signature()
	 */
	protected const GUARD_FILES_VERSION = 'g1';

	/**
	 * The `wp_options` name recording that the directory (and its guard files) has been created, used to
	 * skip the filesystem work on every `init`.
	 */
	protected function get_directory_created_option_name(): string {
		return sprintf(
			'bh_wp_private_uploads_%s_directory_created',
			$this->settings->get_post_type_name()
		);
	}

	/**
	 * A value identifying the current directory + guard-file state. When the stored option matches this,
	 * the directory and its guard files are known to be in place and the work can be skipped.
	 *
	 * @param string $dir The private uploads directory path.
	 */
	protected function get_directory_signature( string $dir ): string {
		return $dir . '|' . self::GUARD_FILES_VERSION;
	}

	/**
	 * Ensure the private uploads directory exists and is protected.
	 *
	 * Creates the directory if missing and writes two guard files if absent: a deny-all `.htaccess`
	 * (defence-in-depth even before/without a rewrite flush) and an empty `index.php` (to prevent
	 * directory listing where `.htaccess` is not honoured). The `.htaccess` denies *all* direct access,
	 * which is correct because files are served by PHP via the `?{post-type}-private-uploads-file=` route.
	 *
	 * Throttled via an option so the filesystem is not touched on every request.
	 *
	 * @hooked init
	 *
	 * @return Create_Directory_Result
	 * @throws Private_Uploads_Exception When PHP fails to create the missing directory.
	 */
	public function create_directory(): Create_Directory_Result {
		$dir = $this->get_private_uploads_directory_path();

		$signature   = $this->get_directory_signature( $dir );
		$option_name = $this->get_directory_created_option_name();

		// Skip the filesystem work when this exact directory + guard files were already created.
		if ( get_option( $option_name ) === $signature ) {
			return new Create_Directory_Result(
				dir: $dir,
				created: false,
				message: 'Already exists',
			);
		}

		$created = false;
		if ( ! file_exists( $dir ) ) {
			$result = wp_mkdir_p( $dir );

			if ( false === $result ) {
				$this->logger->error( 'Failed to create directory: ' . $dir, array( 'dir' => $dir ) );
				throw new Private_Uploads_Exception( 'Failed to create directory: ' . $dir );
			}

			$created = true;
		}

		// Only record success (skipping future filesystem work) once the guard files are actually in place;
		// otherwise a transient write failure would be cached and the directory left unprotected.
		// Store as autoloaded so the `init`-time `get_option()` check does not hit the database each request.
		if ( $this->write_guard_files( $dir ) ) {
			update_option( $option_name, $signature, true );
		}

		return new Create_Directory_Result(
			dir: $dir,
			created: $created,
			message: $created ? 'Created' : 'Already exists',
		);
	}

	/**
	 * Write the `index.php` guard file into the directory if it is not already present, so a server with
	 * autoindex enabled cannot list the directory's contents.
	 *
	 * Deliberately no deny-all `.htaccess`. Files here are served by PHP, but the request only reaches PHP
	 * because the rewrite rule in the *site-root* `.htaccess` intercepts the direct uploads URL. Apache
	 * evaluates `Require all denied` in its auth phase, which runs before the fixup phase where
	 * per-directory `mod_rewrite` rules execute – so a deny-all here would 403 the request before the
	 * rewrite could hand it to `Serve_Private_File`, breaking access for authorised users. It would add
	 * nothing on nginx, which ignores `.htaccess` entirely, and it would make
	 * {@see self::check_and_update_is_url_private()} probe a file that servers 403 unconditionally.
	 *
	 * @see \BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite::register_rewrite_rule()
	 *
	 * @param string $dir The private uploads directory path.
	 * @return bool True when the guard file is present (already existed or was written successfully).
	 */
	protected function write_guard_files( string $dir ): bool {

		if ( ! is_dir( $dir ) ) {
			return false;
		}

		/**
		 * This is a one-off guard file in a directory this plugin owns; `WP_Filesystem` is not loaded on
		 * front-end/cron requests, so use the direct PHP function.
		 *
		 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		 */
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) && false === file_put_contents( $index, "<?php\n// Silence is golden.\n" ) ) {
			return false;
		}

		return true;
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
	 * Find a real uploaded file for {@see self::check_and_update_is_url_private()} to request. The check is
	 * only meaningful against a file the webserver would actually serve to a visitor.
	 *
	 * Descends into the `yyyy/mm` subdirectories rather than probing a directory URL, which returns 403 on
	 * any server with autoindex disabled regardless of whether the files inside are readable.
	 *
	 * Skips:
	 * - dotfiles – Apache ships `<FilesMatch "^\.ht">` at server level and most nginx configs deny
	 *   dotfiles, so a `.htaccess` returns 403 however exposed the directory is;
	 * - the `index.php` guard file – hosts commonly deny PHP execution inside `uploads`, which would
	 *   likewise report 403 while every uploaded document remains downloadable.
	 *
	 * Probing either would be a permanent false negative, silently disabling the admin notice.
	 *
	 * @param string $dir Absolute path of the directory to search.
	 * @return ?string Path relative to `$dir`, or null when it contains no uploaded files.
	 */
	protected function find_file_to_probe( string $dir ): ?string {

		$entries = array_filter(
			scandir( $dir ) ?: array(),
			fn( string $entry ): bool => ! str_starts_with( $entry, '.' ) && 'index.php' !== $entry
		);

		sort( $entries );

		foreach ( $entries as $entry ) {
			$path = "{$dir}/{$entry}";

			if ( is_file( $path ) && is_readable( $path ) ) {
				return $entry;
			}

			if ( is_dir( $path ) ) {
				$found = $this->find_file_to_probe( $path );
				if ( null !== $found ) {
					return "{$entry}/{$found}";
				}
			}
		}

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

		$dir = $this->get_private_uploads_directory_path();

		// If the folder does not exist, it does not exist to be private or public, so return null.
		if ( ! is_dir( $dir ) ) {
			delete_transient( $this->get_is_private_transient_name() );
			return null;
		}

		$file_to_probe = $this->find_file_to_probe( $dir );

		// With no uploaded files there is nothing to protect, so nothing to check.
		if ( null === $file_to_probe ) {
			delete_transient( $this->get_is_private_transient_name() );
			return null;
		}

		$url = $this->get_private_uploads_directory_url() . '/' . $file_to_probe;

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
