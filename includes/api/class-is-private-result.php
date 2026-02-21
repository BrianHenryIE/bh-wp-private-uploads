<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use DateTimeInterface;

class Is_Private_Result {

	public function __construct(
		public string $url,
		public bool $is_private,
		public int $http_response_code,
		public DateTimeInterface $last_checked
	) {
	}
}
