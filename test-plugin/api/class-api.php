<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

use BrianHenryIE\WP_Private_Uploads\WP_Includes\BH_WP_Private_Uploads;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API extends \BrianHenryIE\WP_Private_Uploads\API\API implements API_Interface {
	use LoggerAwareTrait;

	public function __construct( $settings, LoggerInterface $logger ) {

		parent::__construct( $settings, $logger );

		new BH_WP_Private_Uploads( $this, $settings, $logger );
	}

	/**
	 * @return array{is_private:bool}
	 */
	public function get_is_url_public_for_admin(): array {
		$url = WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		return $this->is_url_public_for_admin( $url );
	}

	/**
	 * @return array{url:string, is_private:bool|null, http_response_code?:int}
	 */
	public function get_is_url_private(): array {
		$url = WP_CONTENT_URL . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';
		return $this->is_url_private( $url );
	}
}
