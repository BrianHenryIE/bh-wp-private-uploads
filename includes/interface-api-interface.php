<?php
/**
 * The primary methods that might be used by plugins.
 *
 * The library major version will change whenever a public API changes (after 1.x).
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\Create_Directory_Result;
use BrianHenryIE\WP_Private_Uploads\API\File_Upload_Result;
use BrianHenryIE\WP_Private_Uploads\API\File_Upload_With_Post_Result;
use BrianHenryIE\WP_Private_Uploads\API\Is_Private_Result;
use BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Exception;
use DateTimeInterface;

interface API_Interface {

	/**
	 * Given a remote URL, save the file to the private uploads directory.
	 *
	 * @param string             $file_url Remote URL to download file from.
	 * @param string|null        $filename Preferred filename; may be suffixed with `-1`,`-2` etc.
	 * @param ?DateTimeInterface $datetime A DateTime that should be used for the yyyy/mm directory.
	 *
	 * @throws Private_Uploads_Exception On permissions failure|WordPress download_url() failure.
	 */
	public function download_remote_file_to_private_uploads( string $file_url, ?string $filename = null, ?DateTimeInterface $datetime = null ): File_Upload_Result;

	/**
	 * Given a local file, move the file to the private uploads directory.
	 *
	 * @param string             $tmp_file The source file.
	 * @param string             $filename Preferred filename; may be suffixed with `-1`,`-2` etc.
	 * @param ?DateTimeInterface $datetime A DateTime that the yyyy/mm directory should use.
	 * @param ?int               $filesize "The size, in bytes, of the uploaded file.", ideally.
	 *
	 * @throws Private_Uploads_Exception On permissions failure|file exists failure.
	 */
	public function move_file_to_private_uploads( string $tmp_file, string $filename, ?DateTimeInterface $datetime = null, ?int $filesize = null ): File_Upload_Result;

	/**
	 * Given a remote URL, save the file to the private uploads directory and create a post of the
	 * configured custom post type to record it, assigning an owner (post_author).
	 *
	 * @param string             $file_url Remote URL to download file from.
	 * @param string|null        $filename Preferred filename; may be suffixed with `-1`,`-2` etc.
	 * @param ?int               $post_author_id User id to assign as owner. Default: none (`post_author` = `0`).
	 * @param ?int               $post_parent_id Post id to attach the file's post to, e.g. a WooCommerce order id.
	 * @param ?DateTimeInterface $datetime A DateTime that should be used for the yyyy/mm directory. Does not affect the post's date.
	 *
	 * @throws Private_Uploads_Exception On permissions failure|WordPress download_url() failure|post creation failure.
	 */
	public function download_remote_file_to_private_uploads_and_create_post( string $file_url, ?string $filename = null, ?int $post_author_id = null, ?int $post_parent_id = null, ?DateTimeInterface $datetime = null ): File_Upload_With_Post_Result;

	/**
	 * Given a local file, move the file to the private uploads directory and create a post of the
	 * configured custom post type to record it, assigning an owner (post_author).
	 *
	 * @param string             $tmp_file The source file.
	 * @param string             $filename Preferred filename; may be suffixed with `-1`,`-2` etc.
	 * @param ?int               $post_author_id User id to assign as owner. Default: none (`post_author` = `0`).
	 * @param ?int               $post_parent_id Post id to attach the file's post to, e.g. a WooCommerce order id.
	 * @param ?DateTimeInterface $datetime A DateTime that the yyyy/mm directory should use. Does not affect the post's date.
	 * @param ?int               $filesize "The size, in bytes, of the uploaded file.", ideally.
	 *
	 * @throws Private_Uploads_Exception On permissions failure|file exists failure|post creation failure.
	 */
	public function move_file_to_private_uploads_and_create_post( string $tmp_file, string $filename, ?int $post_author_id = null, ?int $post_parent_id = null, ?DateTimeInterface $datetime = null, ?int $filesize = null ): File_Upload_With_Post_Result;

	/**
	 * Run a HTTP request against the private uploads folder to determine is it publicly accessible.
	 * Store the value in a transient for 15 minutes.
	 *
	 * Should be run on cron.
	 */
	public function check_and_update_is_url_private(): ?Is_Private_Result;

	/**
	 * Get the most recent checked result. I.e. avoid synchronous HTTP calls.
	 */
	public function get_last_checked_is_url_private(): ?Is_Private_Result;

	/**
	 * Create the directory. TODO: this should be deferred until a file is saved there.
	 */
	public function create_directory(): Create_Directory_Result;
}
