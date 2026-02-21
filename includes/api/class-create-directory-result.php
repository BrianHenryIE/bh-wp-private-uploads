<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

class Create_Directory_Result {

	public function __construct(
		public string $dir,
		public string $message
	) {
	}
}
