<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

class Create_Directory_Result {

	public function __construct(
		protected string $dir,
		protected string $message
	) {
	}

	public function get_dir(): string {
		return $this->dir;
	}

	public function get_message(): string {
		return $this->message;
	}
}
