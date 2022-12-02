<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WP_CLI;

class CLI {

	use LoggerAwareTrait;

	public API_Interface $api;

	public function __construct( API_Interface $api, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->api = $api;
	}

	/**
	 * wp plugin-slug download http://example.org/my.pdf
	 *
	 * @param string[] $args
	 * @param array<string,string> $assoc_args
	 *
	 * @return void
	 */
	public function download_url( array $args, array $assoc_args ):void {

		$url = $args[0];

		// wp_parse_url()

		$filtered_url = filter_var( $url, FILTER_SANITIZE_URL );

		if ( $url !== $filtered_url ) {
			WP_CLI::log( 'Input URL did not filter cleanly.' );
			return;
		}

		WP_CLI::log( 'Beginning download of  ' . $filtered_url ); // TODO: ... print to where??

		$this->api->download_remote_file_to_private_uploads( $filtered_url );
	}
}
