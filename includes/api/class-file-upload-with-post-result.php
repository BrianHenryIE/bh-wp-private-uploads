<?php
/**
 * Filepath, URL, MIME, post id, plain object.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

/**
 * @see wp_insert_attachment()
 * @used-by API::move_file_to_private_uploads_and_create_post()
 * @used-by API::download_remote_file_to_private_uploads_and_create_post()
 */
class File_Upload_With_Post_Result extends File_Upload_Result {

	/**
	 * Constructor
	 *
	 * @param string $file Filename of the newly-uploaded file.
	 * @param string $url URL of the newly-uploaded file.
	 * @param string $type Mime type of the newly-uploaded file.
	 * @param int    $post_id Id of the newly-created post recording the file.
	 */
	public function __construct(
		string $file,
		string $url,
		string $type,
		public int $post_id,
	) {
		parent::__construct( $file, $url, $type );
	}
}
