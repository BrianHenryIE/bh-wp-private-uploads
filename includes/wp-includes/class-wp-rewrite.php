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

		$wp_rewrite->add_external_rule( $regex, $query );

		// TODO: Check is the rule saved or added each time? If it is saved, log this info message the first time it is saved.

		// TODO: Also delete the transient if this is the first time the rule is added.
	}
}
