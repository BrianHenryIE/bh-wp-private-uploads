<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\API;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class API implements API_Interface {
	use LoggerAwareTrait;

	public function __construct( $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
	}
}
