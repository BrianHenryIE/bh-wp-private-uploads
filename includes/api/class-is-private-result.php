<?php


namespace BrianHenryIE\WP_Private_Uploads\API;

use DateTimeInterface;

class Is_Private_Result {

	public function __construct(
		protected string $url,
		protected bool $is_private,
		protected int $http_response_code,
		protected DateTimeInterface $last_checked
	) {
	}

	public function get_last_checked(): DateTimeInterface {
		return $this->last_checked;
	}

	public function get_url(): string {
		return $this->url;
	}

	public function is_private(): bool {
		return $this->is_private;
	}

	public function get_http_response_code(): int {
		return $this->http_response_code;
	}
}
