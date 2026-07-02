<?php
/**
 * Fix `plugin_basename()` and URLs that use symlinked files.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Development_Plugin;

/**
 * Some string replace and a little global poisoning.
 */
class Mappings {

	/**
	 * Add the action and filter.
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'wp_plugin_paths' ) );
		add_filter( 'plugins_url', array( $this, 'plugins_url_fix' ), 10, 3 );
	}

	/**
	 * Insert an entry so subdirectories of `.../wp-content/uploads/plugin-symlinks/` resolve to this plugin.
	 */
	public function wp_plugin_paths(): void {

		/**
		 * Fix for mapped directories. I.e. vendor is not under `wp-content/plugins/development-plugins`.
		 *
		 * @see plugin_basename()
		 *
		 * @var array<string, string> $wp_plugin_paths
		 *
		 * phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
		 */
		global $wp_plugin_paths;
		$plugin_path = '/var/www/html/wp-content/uploads/development-plugin/';
		$wp_plugin_paths[ WP_PLUGIN_DIR . '/development-plugin/' ] = $plugin_path;
	}

	/**
	 * Partial fix for symlinks.
	 *
	 * In wp-env: vendor is mapped to wp-content/plugins/vendor.
	 * TODO: address the same issue in integration tests.
	 *
	 * /var/www/html/wp-content/uploads/bh-wp-mailboxes/vendor/brianhenryie/bh-wp-private-uploads/includes/admin/class-admin-assets.php
	 * http://localhost:8888/wp-content/plugins/development-plugin/vendor/brianhenryie/bh-wp-private-uploads/includes/admin/assets/bh-wp-private-uploads-admin.js
	 * http://localhost:8888/wp-content/uploads/bh-wp-mailboxes/vendor/brianhenryie/bh-wp-private-uploads/includes/admin/assets/bh-wp-private-uploads-admin.js
	 *
	 * @hooked plugins_url
	 *
	 * @param mixed|string $url The URL we may need to fix.
	 * @param string       $_path The file path is also passed in but is unused.
	 * @param string       $_plugin The plugin slug/basename? is passed in but unused.
	 *
	 * @return string|mixed A string, but mixed in the unlikely event something else was passed.
	 */
	public function plugins_url_fix( mixed $url, $_path, $_plugin ) {
		if ( ! is_string( $url ) ) {
			return $url;
		}
		// /wp-content/uploads/development-plugin/includes/admin/assets/bh-wp-private-uploads-admin.js
		$url = str_replace( 'wp-content/plugins/var/www/html/', '', $url );
		$url = str_replace( 'plugins/development-plugin/assets', 'uploads/development-plugin/assets', $url );
		$url = str_replace( 'plugins/development-plugin/includes', 'uploads/development-plugin/includes', $url );
		$url = str_replace( 'plugins/development-plugin/vendor', 'uploads/development-plugin/vendor', $url );
		return $url;
	}
}
