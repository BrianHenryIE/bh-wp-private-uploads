<?php
/**
 * Basic plugin settings for sharing configuration across the plugin
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

/**
 * Define the plugin slug and version for use enqueuing assets.
 */
interface Settings_Interface {

	/**
	 * The plugin slug for asset registration.
	 *
	 * @used-by Admin::enqueue_scripts()
	 * @used-by Admin::enqueue_styles()
	 */
	public function get_plugin_slug(): string;

	/**
	 * The plugin version for asset caching.
	 *
	 * @used-by Admin::enqueue_scripts()
	 * @used-by Admin::enqueue_styles()
	 */
	public function get_plugin_version(): string;
}
