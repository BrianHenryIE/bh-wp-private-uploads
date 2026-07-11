<?php
/**
 * Register the rule with WordPress to keep the upload directory private.
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

/**
 * Use WordPress function to write `.htaccess`.
 */
class WP_Rewrite {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param Private_Uploads_Settings_Interface $settings Settings for this plugin's private uploads.
	 * @param LoggerInterface                    $logger A PSR logger.
	 */
	public function __construct(
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * @hooked init
	 */
	public function register_rewrite_rule(): void {

		// Derive the rule from the uploads URL (not the filesystem path) so it is correct on multisite and
		// relocated-uploads installs, and robust on Windows (filesystem paths use `\`, URLs use `/`).
		// `wp_upload_dir( null, false )` avoids the directory-creation side effect.
		$upload_dir = wp_upload_dir( null, false );
		$subdir     = trim( $this->settings->get_uploads_subdirectory_name(), '/' );

		// The uploads directory's URL path, made relative to the site's home path (the rewrite base).
		$uploads_url_path = (string) wp_parse_url( trailingslashit( $upload_dir['baseurl'] ) . $subdir, PHP_URL_PATH );
		$home_url_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home_url_path && str_starts_with( $uploads_url_path, $home_url_path ) ) {
			$uploads_url_path = substr( $uploads_url_path, strlen( $home_url_path ) );
		}
		$relative_path = trailingslashit( ltrim( $uploads_url_path, '/' ) );

		// TODO: Maybe this should be `.+` instead of `.*` – then it will only redirect for files and subfolders.
		// i.e. admins get a ~"no such file" message when browsing to the folder rather than a file.
		$regex = "{$relative_path}(.*)$";
		$query = sprintf(
			'index.php?%s-private-uploads-file=$1',
			str_underscores_to_hyphens( $this->settings->get_post_type_name() )
		);

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		// `add_external_rule()` only updates the in-memory rules; without a flush the rule is never written
		// to `.htaccess`, so the file-level protection would only take effect if the user re-saved permalinks.
		$wp_rewrite->add_external_rule( $regex, $query );

		$this->maybe_flush_rewrite_rules( $regex );
	}

	/**
	 * Flush the rewrite rules when our rule is missing from `.htaccess`.
	 *
	 * `.htaccess` is the source of truth, rather than an option recording that a flush has happened: a
	 * flush only writes the file from an admin request (see below), so an option would happily record a
	 * flush that wrote nothing – as happens when the plugin is activated over WP-CLI. Reading the file
	 * also means the rule is restored if it is ever removed, e.g. by another plugin's flush.
	 *
	 * @param string $regex The external rewrite rule regex just added.
	 */
	protected function maybe_flush_rewrite_rules( string $regex ): void {

		/**
		 * Only an admin page load can write `.htaccess`: `WP_Rewrite::flush_rules()` skips the file write
		 * unless `save_mod_rewrite_rules()` exists, and that lives in `wp-admin/includes/misc.php`, which
		 * only `wp-admin/includes/admin.php` loads. On frontend, cron and WP-CLI requests a "hard" flush
		 * silently writes nothing. This also keeps the file read off frontend page loads entirely.
		 *
		 * WP-CLI installs are covered by `wp rewrite flush --hard`, which shims the Apache detection
		 * `got_mod_rewrite()` needs.
		 *
		 * @see \WP_Rewrite::flush_rules()
		 */
		if ( ! is_admin() || wp_doing_ajax() ) {
			return;
		}

		// Already loaded on a real admin request; makes the functions used below explicit (and available
		// when `is_admin()` is true because a current screen is set, as in the tests).
		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/admin.php';

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		/**
		 * Each of these is a case where `save_mod_rewrite_rules()` writes nothing, so a flush could never
		 * add the rule. Without them we would flush – and rewrite the `rewrite_rules` option – on every
		 * single admin page load.
		 *
		 * @see save_mod_rewrite_rules() Returns early on multisite, and when `got_mod_rewrite()` is false.
		 * @see \WP_Rewrite::mod_rewrite_rules() Returns '' unless `using_permalinks()`.
		 * @see \WP_Rewrite::flush_rules() Skips the file write when `flush_rewrite_rules_hard` is false.
		 */
		if ( is_multisite()
			|| ! $wp_rewrite->using_permalinks()
			|| ! got_mod_rewrite()
			|| ! apply_filters( 'flush_rewrite_rules_hard', true )
		) {
			return;
		}

		if ( $this->is_rule_in_htaccess( $regex ) ) {
			return;
		}

		if ( ! $this->is_htaccess_writable() ) {
			$this->logger->warning(
				'Private uploads rewrite rule is missing from .htaccess, which is not writable. The private uploads directory may be publicly accessible.',
				array( 'htaccess' => $this->get_htaccess_file_path() )
			);
			return;
		}

		$this->flush();

		$this->logger->info( 'Flushed rewrite rules to write the private uploads .htaccess rule.' );
	}

	/**
	 * Flush the rewrite rules, writing them to `.htaccess`.
	 *
	 * Called on `init`, so `WP_Rewrite::flush_rules()` defers itself to `wp_loaded` – i.e. until after
	 * every plugin has registered its rules.
	 *
	 * A seam: the tests override this rather than rewrite the test install's `.htaccess`.
	 */
	protected function flush(): void {
		flush_rewrite_rules();
	}

	/**
	 * The site-root `.htaccess`, i.e. the one WordPress writes its rewrite rules to.
	 *
	 * `get_home_path()` – not `ABSPATH` – because the two differ when WordPress lives in a subdirectory.
	 *
	 * @see save_mod_rewrite_rules()
	 */
	protected function get_htaccess_file_path(): string {
		return get_home_path() . '.htaccess';
	}

	/**
	 * Whether `.htaccess` already contains the rewrite rule for this plugin's private uploads.
	 *
	 * @param string $regex The external rewrite rule regex added by {@see self::register_rewrite_rule()}.
	 */
	protected function is_rule_in_htaccess( string $regex ): bool {

		$htaccess_file = $this->get_htaccess_file_path();

		// No file: nothing to find. A flush will create it.
		if ( ! is_readable( $htaccess_file ) ) {
			return false;
		}

		/**
		 * `WP_Filesystem` is not loaded on every request, and this is a read of a file WordPress itself
		 * manages, so use the direct PHP function.
		 *
		 * phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		 */
		$contents = file_get_contents( $htaccess_file );

		if ( ! is_string( $contents ) ) {
			return false;
		}

		/**
		 * The same substitution core applies when it writes the rule, so the needle matches the file.
		 *
		 * @see \WP_Rewrite::mod_rewrite_rules()
		 */
		$match = str_replace( '.+?', '.+', $regex );

		return str_contains( $contents, 'RewriteRule ^' . $match );
	}

	/**
	 * Whether WordPress would be able to write the rules to `.htaccess`.
	 *
	 * Mirrors the check in `save_mod_rewrite_rules()`: the file itself when it exists, otherwise the
	 * directory it would be created in.
	 *
	 * `WP_Filesystem` is not loaded on every request, so use the direct PHP functions.
	 *
	 * phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
	 */
	protected function is_htaccess_writable(): bool {

		$htaccess_file = $this->get_htaccess_file_path();

		return file_exists( $htaccess_file )
			? is_writable( $htaccess_file )
			: is_writable( dirname( $htaccess_file ) );
	}
}
