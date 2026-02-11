<?php
/**
 * Register the JavaScript which handles using the WordPress Media Library to upload attachments to the private
 * uploads directory.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;

/**
 * Registers (does not enqueue) the script used in admin UI AJAX uploads.
 */
class Admin_Assets {

	public function __construct(
		protected Private_Uploads_Settings_Interface $settings
	) {
	}

	/**
	 * Registers but does not enqueue the script for the private Media Library.
	 *
	 * `wp_enqueue_script( 'bh-wp-private-uploads-admin-js' );`
	 */
	public function register_script(): void {

		$handle = sprintf(
			'%s-private-uploads-media-library-js',
			$this->settings->get_plugin_slug()
		);

		$version = '1.0.0';

		$js_url = plugins_url( '/assets/bh-wp-private-uploads-admin.js', __FILE__ );

		wp_register_script(
			$handle,
			$js_url,
			array( 'jquery' ),
			$version,
			true
		);
	}
}
