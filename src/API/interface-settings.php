<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

interface Settings_Interface {

	public function get_plugin_slug(): string;
	public function get_plugin_version(): string;
}
