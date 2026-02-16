<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

class File_Upload_Result {

	public function __construct(
		protected string $file,
		protected string $url,
		protected string $type,
	) {
	}

	public function get_file(): string {
		return $this->file;
	}

	public function get_url(): string {
		return $this->url;
	}

	public function get_type(): string {
		return $this->type;
	}
}
