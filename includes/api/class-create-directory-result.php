<?php
/**
 * Plain object to communicate was the directory existing/new.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

/**
 * Info object to record the outcome.
 */
class Create_Directory_Result {

	/**
	 * Constructor.
	 *
	 * @param string $dir The target directory.
	 * @param bool   $created The outcome.
	 * @param string $message A friendly message.
	 */
	public function __construct(
		public string $dir,
		public bool $created,
		public string $message,
	) {
	}
}
