<?php


namespace BrianHenryIE\WP_Private_Uploads\API;

use DateTimeInterface;

class Is_Private_Result {

	protected DateTimeInterface $last_checked;

	protected string $url;

	protected bool $is_private;

	protected int $http_response_code;

	public function __construct(
		string $url,
		bool $is_private,
		int $http_response_code,
		DateTimeInterface $last_checked
	) {
		$this->url                = $url;
		$this->is_private         = $is_private;
		$this->http_response_code = $http_response_code;
		$this->last_checked       = $last_checked;
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
