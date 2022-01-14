<?php

namespace  BrianHenryIE\WP_Private_Uploads\API;

use WP_Error;
use WP_REST_Attachments_Controller;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;


/**
 * Extend WP_REST_Posts_Controller and just use a subset of its functions.
 *
 * Class WP_REST_Attachments_Controller
 *
 * @package PED_Warehouse\private_uploads
 */
class REST_Private_Uploads_Controller extends WP_REST_Attachments_Controller {

	/** @var API_Interface */
	protected $api;

	public function __construct() {
		parent::__construct( 'private-uploads' );

		$this->namespace = 'brianhenryie/v1';
		$this->rest_base = 'private-uploads';

		// During normal plugin lifecycle, this will already be populated.
		global $bh_wp_private_uploads;
		$this->api = $bh_wp_private_uploads;
	}

	/**
	 * POST /wp-json/brianhenryie/v1/private-uploads
	 *
	 * @see WP_REST_Controller::register_routes()
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/', // TODO: trailing slash?
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);

	}


	/**
	 *
	 * Based on insert_attachment()
     * We don't use insert_attachment because that also creates an entry in the Media Library, which we do not want.
	 *
	 * @see WP_REST_Attachments_Controller::insert_attachment()
	 *
	 * @param WP_REST_Request $request
	 * @return array|WP_Error
	 */
	public function upload_item( $request ) {

		$yyyymm = '/' . gmdate( 'Y' ) . '/' . gmdate( 'm' );

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

		// Get the file via $_FILES or raw data.
		$files   = $request->get_file_params();
		$headers = $request->get_headers();

		if ( ! empty( $files ) ) {
			$file = $this->upload_from_file( $files, $headers );
		} else {
			$file = $this->upload_from_data( $request->get_body(), $headers );
		}

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		do_action( 'rest_private_uploads_upload', $file, $request );

		remove_filter( 'upload_dir', $private_path, 10 );

		return array(
			'file' => $file,
		);

	}
}


