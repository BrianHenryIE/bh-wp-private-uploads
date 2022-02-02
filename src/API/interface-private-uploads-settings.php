<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

interface Private_Uploads_Settings_Interface {

	public function get_plugin_slug(): string;

	/**
	 * @return string
	 */
	public function get_uploads_subdirectory_name(): string;

	/**
	 *
	 * e.g. `brianhenryie/v1`. will result in an endpoint of 'brianhenryie/v1/private-uploads`.
	 *
	 * Return null to NOT add a REST endpoint.
	 */
	public function get_rest_namespace(): ?string;

	public function get_cli_base(): ?string;
}
