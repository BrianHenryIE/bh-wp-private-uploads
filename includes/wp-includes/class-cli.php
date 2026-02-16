<?php
/**
 * Adds WP CLI commands for downloading files to the private uploads directory.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Exception;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_CLI\ExitException;

/**
 * Implements some of the API via CLI.
 */
class CLI {
	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface                      $api To perform the actions.
	 * @param Private_Uploads_Settings_Interface $settings Uses `::get_cli_base()` to configure (or not) the commands.
	 * @param LoggerInterface                    $logger PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * Register the commands with WP CLI.
	 *
	 * @hooked cli_init
	 * @see WP_CLI\Runner::setup_bootstrap_hooks()
	 */
	public function register_commands(): void {

		$cli_base = $this->settings->get_cli_base();

		if ( is_null( $cli_base ) ) {
			return;
		}

		WP_CLI::add_command( "{$cli_base} download", array( $this, 'download_url' ) );
	}

	/**
	 * Download a file from a remote URL to the private uploads directory.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The location of the file to download.
	 *
	 * [--field=<field>]
	 * : Display the value of a single field
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *   # Download Brian Henry's resume.
	 *   $ wp cli-base download https://BrianHenry.ie/cv
	 *
	 * @param string[]             $args Positional arguments.
	 * @param array<string,string> $assoc_args Named arguments.
	 * @throws ExitException On `WP_CLI::error()`.
	 */
	public function download_url( array $args, array $assoc_args ): void {

		$url = $args[0];

		$filtered_url = sanitize_url( $url );

		if ( $url !== $filtered_url ) {
			WP_CLI::error( 'Input URL did not filter cleanly: ' . $filtered_url );
		}

		// TODO: Move this into the API class.
		$head_response = wp_safe_remote_head( $filtered_url, array( 'redirection' => 10 ) );
		if ( ! is_wp_error( $head_response ) ) {
			$filtered_url = $head_response['http_response']->get_response_object()->url;
		}

		// Don't print progress if the user wants a machine-readable format.
		if ( 'table' === $assoc_args['format'] ) {
			WP_CLI::log( 'Beginning download of  ' . $filtered_url );
		}

		// TODO: Return an immutable pojo.
		// TODO: Add post_id to output.
		try {
			$result = $this->api->download_remote_file_to_private_uploads( $filtered_url );
		} catch ( Private_Uploads_Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}

		// Convert result object to array for WP_CLI formatting.
		$result_array = array(
			'file' => $result->get_file(),
			'url'  => $result->get_url(),
			'type' => $result->get_type(),
		);

		switch ( true ) {
			case isset( $assoc_args['field'] ):
				$fields = array( $assoc_args['field'] );
				break;
			case isset( $assoc_args['fields'] ):
				$fields = $assoc_args['fields'];
				break;
			default:
				$fields = array_keys( $result_array );
		}

		WP_CLI\Utils\format_items(
			$assoc_args['format'] ?: 'table',
			array( $result_array ),
			$fields
		);
	}
}
