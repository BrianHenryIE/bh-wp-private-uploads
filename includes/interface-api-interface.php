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
	 * Run a HTTP request against the private uploads folder to determine is it publicly accessible.
	 * Store the value in a transient for 15 minutes.
	 *
	 * Should be run on cron.
	 */
	public function check_and_update_is_url_private(): ?Is_Private_Result;

	/**
	 * @return Create_Directory_Result
	 */
	public function create_directory(): Create_Directory_Result;
}
