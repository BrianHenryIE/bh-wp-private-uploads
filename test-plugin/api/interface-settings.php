<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

interface Settings_Interface {

	/**
	 * @used-by Admin::enqueue_scripts()
	 * @used-by Admin::enqueue_styles()
	 *
	 * @return string
	 */
	public function get_plugin_slug(): string;

	/**
	 * @used-by Admin::enqueue_scripts()
	 * @used-by Admin::enqueue_styles()
	 *
	 * @return string
	 */
	public function get_plugin_version(): string;

}
