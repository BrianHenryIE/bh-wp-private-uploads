<?php

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\Is_Private_Result;
use DateTimeInterface;

interface API_Interface {

	/**
	 * Given a remote URL, save the file to the private uploads directory.
	 *
	 * @param string             $file_url
	 * @param string|null        $filename
	 * @param ?DateTimeInterface $datetime
	 *
	 * @return array{file?:string,url?:string,type?:string,error?:string}
	 */
	public function download_remote_file_to_private_uploads( string $file_url, ?string $filename = null, ?DateTimeInterface $datetime = null ): array;

	/**
	 * Given a local file, move the file to the private uploads directory.
	 *
	 * @param string             $tmp_file
	 * @param string             $filename
	 * @param ?DateTimeInterface $datetime
	 * @param ?int               $filesize
	 *
	 * @return array{file?:string,url?:string,type?:string,error?:string}
	 */
	public function move_file_to_private_uploads( string $tmp_file, string $filename, ?DateTimeInterface $datetime = null, $filesize = null ): array;

	/**
	 * Run a HTTP request against the private uploads folder to determine is it publicly accessible.
	 * Store the value in a transient for 15 minutes.
	 *
	 * Should be run on cron.
	 */
	public function check_and_update_is_url_private(): ?Is_Private_Result;

	/**
	 * @return array{dir:string|null,message:string}
	 */
	public function create_directory(): array;

	// TODO: Create a post with permissions to check before allowing downloads.
	// public function restrict_private_file( $user, $object );
}
