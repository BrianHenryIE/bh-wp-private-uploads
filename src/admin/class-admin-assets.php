<?php
/**
 * Register the JavaScript which handles using the WordPress Media Library to upload attachments to the private
 * uploads directory.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

class Admin_Assets {

	/**
	 * `wp_enqueue_script( 'bh-wp-private-uploads-admin-js' );`
	 */
	public function register_script(): void {

		$handle = 'bh-wp-private-uploads-admin-js';

		$version = '1.0.0';

		$js_path = realpath( __DIR__ . '/../../' ) . '/assets/bh-wp-private-uploads-admin.js';
		$js_url  = plugin_dir_url( $js_path ) . 'bh-wp-private-uploads-admin.js';

		wp_register_script( $handle, $js_url, 'jquery', $version, true );
	}
}
