<?php
/**
 *
 *
 * Required:
 *
 * @see Private_Uploads_Settings_Interface::get_plugin_slug()
 *
 * Provided:
 * @see Private_Uploads_Settings_Trait
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

interface Private_Uploads_Settings_Interface {

	public function get_plugin_slug(): string;

	/**
	 * Defaults to the plugin slug when using Private_Uploads_Settings_Trait.
	 *
	 * Should have no pre or trailing slash.
	 */
	public function get_uploads_subdirectory_name(): string;

	/**
	 *
	 * E.g. `brianhenryie/v1` will result in an endpoint of 'brianhenryie/v1/uploads`.
	 *
	 * Return null to NOT add a REST endpoint.
	 */
	public function get_rest_namespace(): ?string;

	/**
	 *
	 */
	public function get_cli_base(): ?string;

	/**
	 * Defaults to `{$plugin_slug}_private_uploads`.
	 *
	 * "Must not exceed 20 characters and may only contain lowercase alphanumeric characters, dashes, and underscores. See sanitize_key()."
	 */
	public function get_post_type_name(): string;
}
