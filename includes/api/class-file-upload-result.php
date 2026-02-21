<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

class File_Upload_Result {

	public function __construct(
		public string $file,
		public string $url,
		public string $type,
	) {
	}
}
