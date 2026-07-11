<?php
/**
 * Test double for WP_Rewrite.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerInterface;

/**
 * Reads a test-controlled `.htaccess`, and records the flush rather than performing it – a real
 * `flush_rewrite_rules()` would rewrite the test install's own `.htaccess`.
 */
class Spy_WP_Rewrite extends WP_Rewrite {

	/**
	 * Whether {@see WP_Rewrite::flush()} would have been called.
	 */
	public bool $did_flush = false;

	/**
	 * Constructor.
	 *
	 * @param Private_Uploads_Settings_Interface $settings Settings for this plugin's private uploads.
	 * @param LoggerInterface                    $logger A PSR logger.
	 * @param string                             $htaccess_file Stands in for the site-root `.htaccess`.
	 */
	public function __construct(
		Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger,
		protected string $htaccess_file
	) {
		parent::__construct( $settings, $logger );
	}

	/**
	 * The test-controlled `.htaccess`, rather than the test install's real one.
	 */
	protected function get_htaccess_file_path(): string {
		return $this->htaccess_file;
	}

	/**
	 * Record the flush instead of performing it.
	 */
	protected function flush(): void {
		$this->did_flush = true;
	}
}
