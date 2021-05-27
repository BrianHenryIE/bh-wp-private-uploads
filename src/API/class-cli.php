<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use Psr\Log\LoggerInterface;
use WP_CLI;

class CLI {

	/** @var LoggerInterface */
	public static $logger;

	/** @var API_Interface */
	public static $api;

	/**
	 * wp private-uploads download-url http://example.org/my.pdf
	 */
	public function download_url( $args ) {

		$url = $args[0];

		// wp_parse_url()

		$filtered_url = filter_var( $url, FILTER_SANITIZE_URL );

		if ( $url !== $filtered_url ) {
			WP_CLI::log( 'Input URL did not filter cleanly.' );
			return;
		}

		WP_CLI::log( 'Beginning download of  ' . $url );

		self::$api->download_remote_file_to_private_uploads( $url );
	}
}
