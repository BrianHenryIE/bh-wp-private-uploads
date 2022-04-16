<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

use BrianHenryIE\WP_Logger\Logger_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LogLevel;

class Settings implements Settings_Interface, Logger_Settings_Interface, Private_Uploads_Settings_Interface {

	/**
	 * @return string
	 * @see LogLevel
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
		return 'Private Uploads Test Plugin';
	}

	public function get_plugin_slug(): string {
		return 'bh-wp-private-uploads-test-plugin';
	}

	public function get_plugin_basename(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME' ) ? BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_BASENAME : 'bh-wp-private-uploads-test-plugin/bh-wp-private-uploads-test-plugin.php';
	}

	public function get_plugin_version(): string {
		return defined( 'BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION' ) ? BH_WP_PRIVATE_UPLOADS_TEST_PLUGIN_VERSION : '3.0.0';
	}

	/**
	 * @return string
	 */
	public function get_uploads_subdirectory_name(): string {
		return 'test-plugin';
	}

	/**
	 *
	 * e.g. `brianhenryie/v1`. will result in an endpoint of 'brianhenryie/v1/uploads`.
	 *
	 * Return null to NOT add a REST endpoint.
	 */
	public function get_rest_namespace(): ?string {
		return 'test-plugin/v1';
	}

	public function get_cli_base(): ?string {
		return 'test_plugin';
	}
}
