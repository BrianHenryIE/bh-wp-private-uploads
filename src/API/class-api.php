<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use Psr\Log\LoggerInterface;
use DateTime;

class API implements API_Interface {

	/** @var LoggerInterface  */
	protected $logger;

	/** @var Settings_Interface  */
	protected $settings;

	/**
	 * API constructor.
	 *
	 * @param Settings_Interface $settings
	 * @param LoggerInterface $logger
	 */
	public function __construct( $settings, $logger ) {
		$this->logger = $logger;
		$this->settings = $settings;
	}

	/**
	 * Downloads a file and saves it in uploads/private/...
	 *
	 * @param string    $url
	 * @param ?string   $filename
	 * @param DateTime $datetime
	 *
	 * @return array On success, returns an associative array of file attributes.
	 *               On failure, returns `$overrides['upload_error_handler']( &$file, $message )`
	 *               or `array( 'error' => $message )`.
	 */
	public function download_remote_file_to_private_uploads( string $file_url, string $filename = null, ?DateTime $datetime = null ): array {

		$tmp_file = download_url( $file_url );

		return $this->move_file_to_private_uploads( $tmp_file, $filename, $datetime );

	}

	/**
	 * Use internal WordPress functions to move a file into private uploads.
	 * i.e. creates the folders and checks for unique filenames.
	 *
	 * Emulates a file upload.
	 *
	 * The original file is automatically deleted by wp_handle_upload().
	 *
	 * @see wp_handle_upload()
	 *
	 * @param string    $tmp_file
	 * @param string    $filename
	 * @param ?DateTime $datetime
	 * @param ?int      $filesize
	 *
	 * @return array On success, returns an associative array of file attributes.
	 *               On failure, returns `$overrides['upload_error_handler']( &$file, $message )`
	 *               or `array( 'error' => $message )`.
	 */
	public function move_file_to_private_uploads( $tmp_file, $filename, $datetime = null, $filesize = null ): array {

		$datetime = $datetime ?? new DateTime();

		$file = array(
			'name'     => $filename,
			'type'     => 'application/pdf', // TODO: assert mime type to verify download.
			'tmp_name' => $tmp_file,
			'error'    => UPLOAD_ERR_OK,
			'size'     => $filesize ?? filesize( $tmp_file ),
		);

		$yyyymm = '/' . date( 'Y', $datetime->getTimestamp() ) . '/' . date( 'm', $datetime->getTimestamp() );

		/**
		 * Filter wp_upload_dir() to add private
		 *
		 * @see wp_upload_dir()
		 *
		 * Filters the uploads directory data.
		 *
		 * @since 2.0.0
		 *
		 * @param array $uploads {
		 *     Array of information about the upload directory.
		 *
		 *     @type string       $path    Base directory and subdirectory or full path to upload directory.
		 *     @type string       $url     Base URL and subdirectory or absolute URL to upload directory.
		 *     @type string       $subdir  Subdirectory if uploads use year/month folders option is on.
		 *     @type string       $basedir Path without subdir.
		 *     @type string       $baseurl URL path without subdir.
		 *     @type string|false $error   False or error message.
		 * }
		 * @return array $uploads
		 */
		$private_path = function( $uploads ) use ( $yyyymm ) {

			// Use private uploads dir.

			$uploads['basedir'] = "{$uploads['basedir']}/private";
			$uploads['baseurl'] = "{$uploads['baseurl']}/private";

			$uploads['path'] = $uploads['basedir'] . $uploads['subdir'];
			$uploads['url']  = $uploads['baseurl'] . $uploads['subdir'];
			// Use the correct month.
			$uploads['path'] = str_replace( $uploads['subdir'], $yyyymm, $uploads['path'] );

			return $uploads;
		};
		add_filter( 'upload_dir', $private_path, 10, 1 );


		$action = array( 'action' => 'wp_handle_private_upload' );
		$_POST  = $_POST + $action;

		$file = wp_handle_upload( $file, $action );

		remove_filter( 'upload_dir', $private_path );

		return $file;
	}

}
