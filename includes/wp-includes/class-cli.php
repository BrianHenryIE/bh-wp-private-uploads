<?php
/**
 * Adds WP CLI commands for downloading files to the private uploads directory.
 *
 * TODO: check is URL public
 * TODO: unsnooze admin notice
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API\File_Upload_With_Post_Result;
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
	 * [--create-post]
	 * : Create a post of the private uploads custom post type recording the file.
	 *
	 * [--post_author=<user_id>]
	 * : User id to assign as owner of the created post. Implies --create-post. Default: no owner.
	 *
	 * [--post_parent=<post_id>]
	 * : Post id to attach the created post to, e.g. an order id. Implies --create-post.
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
	 *   # Download a file and record it with a post owned by user 2, attached to post 123.
	 *   $ wp cli-base download https://BrianHenry.ie/cv --post_author=2 --post_parent=123
	 *
	 * @param string[]                  $args Positional arguments.
	 * @param array<string,string|bool> $assoc_args Named arguments; flags are passed as booleans.
	 * @throws ExitException On `WP_CLI::error()`.
	 */
	public function download_url( array $args, array $assoc_args ): void {

		$url = $args[0];

		$filtered_url = sanitize_url( $url );

		if ( $url !== $filtered_url ) {
			WP_CLI::error( 'Input URL did not filter cleanly: ' . $filtered_url );
		}

		$post_author_id = null;
		if ( isset( $assoc_args['post_author'] ) ) {
			if ( ! is_string( $assoc_args['post_author'] ) || ! ctype_digit( $assoc_args['post_author'] ) ) {
				WP_CLI::error( 'Invalid --post_author; expected a numeric user id.' );
			}
			$post_author_id = (int) $assoc_args['post_author'];
		}

		$post_parent_id = null;
		if ( isset( $assoc_args['post_parent'] ) ) {
			if ( ! is_string( $assoc_args['post_parent'] ) || ! ctype_digit( $assoc_args['post_parent'] ) ) {
				WP_CLI::error( 'Invalid --post_parent; expected a numeric post id.' );
			}
			$post_parent_id = (int) $assoc_args['post_parent'];
		}

		$create_post = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'create-post', false )
			|| ! is_null( $post_author_id )
			|| ! is_null( $post_parent_id );

		// TODO: Move this into the API class.
		$head_response = wp_safe_remote_head( $filtered_url, array( 'redirection' => 10 ) );
		if ( ! is_wp_error( $head_response ) ) {
			$filtered_url = $head_response['http_response']->get_response_object()->url;
		}

		$format = $assoc_args['format'] ?? 'table';
		$format = is_string( $format ) ? $format : 'table';

		// Don't print progress if the user wants a machine-readable format.
		if ( 'table' === $format ) {
			WP_CLI::log( 'Beginning download of ' . $filtered_url );
		}

		try {
			$result = $create_post
				? $this->api->download_remote_file_to_private_uploads_and_create_post(
					file_url: $filtered_url,
					post_author_id: $post_author_id,
					post_parent_id: $post_parent_id,
				)
				: $this->api->download_remote_file_to_private_uploads( $filtered_url );
		} catch ( Private_Uploads_Exception $exception ) {
			WP_CLI::error( $exception->getMessage() );
		}

		// Convert result object to array for WP_CLI formatting.
		$result_array = array(
			'file' => $result->file,
			'url'  => $result->url,
			'type' => $result->type,
		);

		if ( $result instanceof File_Upload_With_Post_Result ) {
			$result_array['post_id'] = $result->post_id;
		}

		switch ( true ) {
			case isset( $assoc_args['field'] ) && is_string( $assoc_args['field'] ):
				$fields = array( $assoc_args['field'] );
				break;
			case isset( $assoc_args['fields'] ) && is_string( $assoc_args['fields'] ):
				$fields = $assoc_args['fields'];
				break;
			default:
				$fields = array_keys( $result_array );
		}

		WP_CLI\Utils\format_items(
			$format,
			array( $result_array ),
			$fields
		);
	}
}
