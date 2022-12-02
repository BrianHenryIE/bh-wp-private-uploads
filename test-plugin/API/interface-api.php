<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

interface API_Interface {

	public function get_is_url_public_for_admin(): array;

	public function get_is_url_private(): array;
}
