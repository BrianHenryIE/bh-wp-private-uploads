<?php
/**
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Development_Plugin;

use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface as Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class Example_Private_Uploads extends \BrianHenryIE\WP_Private_Uploads\API\API {
	use LoggerAwareTrait;

	public function __construct( Settings_Interface $settings, LoggerInterface $logger ) {

		parent::__construct( $settings, $logger );

		new BH_WP_Private_Uploads_Hooks( $this, $settings, $logger );
	}

	/**
	 * @return array{is_private:bool}
	 */
	public function get_is_url_public_for_admin(): array {
		$url = content_url( '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/' );
		return $this->is_url_public_for_admin( $url );
	}

	/**
	 * @return array{url:string, is_private:bool|null, http_response_code?:int}
	 */
	public function get_is_url_private(): array {
		$url = content_url( '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/' );
		return $this->check_is_url_private( $url );
	}
}
