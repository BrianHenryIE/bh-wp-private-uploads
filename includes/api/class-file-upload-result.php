<?php
/**
 * Filepath, URL, MIME, plain object.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

/**
 * @see wp_handle_upload()
 * @used-by API::move_file_to_private_uploads()
 */
class File_Upload_Result {

	/**
	 * Constructor
	 *
	 * @param string $file Filename of the newly-uploaded file.
	 * @param string $url URL of the newly-uploaded file.
	 * @param string $type Mime type of the newly-uploaded file.
	 */
	public function __construct(
		public string $file,
		public string $url,
		public string $type,
	) {
	}
}
