<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\WP_Logger\API\Logger_Settings_Interface;
use Psr\Log\LogLevel;

class Settings implements Settings_Interface, Logger_Settings_Interface {

	/**
	 * @return string
	 * @see LogLevel
	 *
	 */
	public function get_log_level(): string {
		return LogLevel::INFO;
	}

	/**
	 * For friendly display.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return "Private Uploads";
	}

	public function get_plugin_basename(): string {
		return 'bh-wp-private-uploads/bh-wp-private-uploads.php';
	}

	public function get_plugin_slug(): string {
		return 'bh-wp-private-uploads';
	}

	public function get_plugin_version(): string {
		return '2.0.2';
	}
}
