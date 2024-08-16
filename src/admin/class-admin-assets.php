<?php
/**
 * Register the JavaScript which handles using the WordPress Media Library to upload attachments to the private
 * uploads directory.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

class Admin_Assets {

	protected Private_Uploads_Settings_Interface $settings;

	public function __construct( Private_Uploads_Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Registers but does not enqueue the script for the private Media Library.
	 *
	 * `wp_enqueue_script( 'bh-wp-private-uploads-admin-js' );`
	 */
	public function register_script(): void {

		$handle = sprintf(
			'%s-private-uploads-media-library-js',
			str_underscores_to_hyphens( $this->settings->get_post_type_name() )
		);

		$version = '1.0.0';

		$js_absolute_path = realpath( dirname( __DIR__, 2 ) ) . '/assets/bh-wp-private-uploads-admin.js';

		$plugin_dir = trailingslashit( constant( 'WP_PLUGIN_DIR' ) ) . plugin_basename( __FILE__ );

		$js_relative_path = str_replace( $plugin_dir, '', $js_absolute_path );

		$js_url = plugins_url( $js_relative_path );

		wp_register_script(
			$handle,
			$js_url,
			array( 'jquery' ),
			$version,
			true
		);
	}
}
