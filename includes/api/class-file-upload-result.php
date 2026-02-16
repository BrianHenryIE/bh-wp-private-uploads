<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

class File_Upload_Result {

	public function __construct(
		protected ?string $file = null,
		protected ?string $url = null,
		protected ?string $type = null,
		protected ?string $error = null
	) {
	}

	public function get_file(): ?string {
		return $this->file;
	}

	public function get_url(): ?string {
		return $this->url;
	}

	public function get_type(): ?string {
		return $this->type;
	}

	public function get_error(): ?string {
		return $this->error;
	}

	public function has_error(): bool {
		return $this->error !== null;
	}

	public function is_success(): bool {
		return $this->error === null && $this->file !== null;
	}
}
