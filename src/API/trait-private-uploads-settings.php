<?php
/**
 * Convenience defaults for Settings_Interface implementations.
 */

namespace BrianHenryIE\WP_Private_Uploads\API;

trait Private_Uploads_Settings_Trait {

	/**
	 * Default to the plugins slug.
	 *
	 * e.g. wp-content/uploads/my-plugin-slug will be the private directory.
	 *
	 * @return string
	 */
	public function get_uploads_subdirectory_name(): string {
		return $this->get_plugin_slug();
	}

	/**
	 * Default to no REST endpoint.
	 */
	public function get_rest_namespace(): ?string {
		return null;
	}

	/**
	 * Default to no CLI commands.
	 */
	public function get_cli_base(): ?string {
		return null;
	}

}
