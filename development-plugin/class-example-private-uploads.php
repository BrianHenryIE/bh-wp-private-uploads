<?php
/**
 * Extend the main plugin API to add experimental functions.
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Development_Plugin;

use BrianHenryIE\WP_Private_Uploads\API\API;
use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface as Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * A test-bed for new functions.
 */
class Example_Private_Uploads extends API {
	use LoggerAwareTrait;

	/**
	 * Constructor
	 *
	 * @param Settings_Interface $settings The configured settings.
	 * @param LoggerInterface    $logger A PSR logger.
	 */
	public function __construct( Settings_Interface $settings, LoggerInterface $logger ) {

		parent::__construct( $settings, $logger );

		new BH_WP_Private_Uploads_Hooks( $this, $settings, $logger );
	}

	/**
	 * @return array{is_private:bool|null}
	 */
	public function get_is_url_public_for_admin(): array {
		$url = content_url( '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/' );
		return $this->is_url_public_for_admin( $url );
	}
}
