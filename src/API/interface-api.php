<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use DateTime;

interface API_Interface {

	public function download_remote_file_to_private_uploads( string $file_url, string $filename = null, ?DateTime $datetime = null ): array;

	// TODO: Create a post with permissions to check before allowing downloads.
//	public function restrict_private_file( $user, $object );

    public function move_file_to_private_uploads( $tmp_file, $filename, $datetime = null, $filesize = null ): array;
}
