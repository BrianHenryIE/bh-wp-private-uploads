<?php
/**
 * Extend WP_REST_Attachments_Controller.
 *
 * Uploads are uploaded to the correct private subdirectory.
 * Post type is correctly set on attachments.
 * Post parent is correctly set on attachments.
 * Additional endpoint allows uploading without creating an attachment-wp_post.
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use WP_Error;
use WP_Post_Type;
use WP_REST_Attachments_Controller;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * @phpstan-type DependenciesArray array{settings: Private_Uploads_Settings_Interface}
 */
class REST_Private_Uploads_Controller extends WP_REST_Attachments_Controller {

	/**
	 * @uses Private_Uploads_Settings_Interface::get_uploads_subdirectory_name()
	 */
	protected Private_Uploads_Settings_Interface $settings;

	/**
	 * Constructor
	 *
	 * Earlier we added the dependencies array to the post type object which is used here.
	 *
	 * @see Post_Type
	 *
	 * @param string $post_type_name The post type name/key is essential for children of `WP_REST_Posts_Controller`.
	 */
	public function __construct( $post_type_name ) {

		$post_type_object = get_post_type_object( $post_type_name );

		if ( null !== $post_type_object && property_exists( $post_type_object, 'dependencies' ) && is_array( $post_type_object->dependencies ) ) {
			/** @var DependenciesArray $dependencies */
			$dependencies   = $post_type_object->dependencies;
			$this->settings = $dependencies['settings'];
		}

		parent::__construct( $post_type_name );
	}

	/**
	 * Register the standard attachment routes, plus an additional route with does not create an actual post.
	 *
	 * POST /wp-json/my-plugin/v1/uploads
	 *
	 * @see WP_REST_Controller::register_routes()
	 * @see WP_REST_Attachments_Controller::register_routes()
	 *
	 * @see register_rest_route()
	 *
	 * @return void
	 */
	public function register_routes() {

		// Register the standard create attachment route (and more).
		parent::register_routes();

		// Register an "upload_item" route that uploads to private uploads but does not create an attachment/post.
		// Defaults to `plugin-slug/v1/plugin-slug-uploads` or `plugin-slug/v1/post-type-name`.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/', // TODO: trailing slash?
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			)
		);
	}

	/**
	 * Filters to:
	 * * set the correct uploads subdir
	 * * set the post type
	 * * set the parent post
	 *
	 * @param WP_REST_Request $request The request object.
	 */
	protected function add_set_private_uploads_filters( WP_REST_Request $request ): void {

		$uploads_subdirectory_name = $this->settings->get_uploads_subdirectory_name();

		/**
		 * Filter wp_upload_dir() to add private
		 *
		 * @see wp_upload_dir()
		 *
		 * Filters the uploads directory data.
		 */
		$private_path = function ( array $upload_dir_data ) use ( $uploads_subdirectory_name ): array {
			/** @var array{path:string, url:string, subdir:string, basedir:string, baseurl:string, error:string|false} $upload_dir_data The array from `wp_upload_dir()`. */

			// Use private uploads dir.
			$upload_dir_data['basedir'] = "{$upload_dir_data['basedir']}/{$uploads_subdirectory_name}";
			$upload_dir_data['baseurl'] = "{$upload_dir_data['baseurl']}/{$uploads_subdirectory_name}";

			$upload_dir_data['path'] = $upload_dir_data['basedir'] . $upload_dir_data['subdir'];
			$upload_dir_data['url']  = $upload_dir_data['baseurl'] . $upload_dir_data['subdir'];

			return $upload_dir_data;
		};
		add_filter( 'upload_dir', $private_path, 10, 1 );

		/**
		 * The REST attachment controller calls insert_attachment which forces the post type to attachment rather than
		 * our preferred own post type.
		 *
		 * Filters attachment post data before it is updated in or added to the database.
		 *
		 * @param array $data                An array of slashed, sanitized, and processed attachment post data.
		 * @param array $postarr             An array of slashed and sanitized attachment post data, but not processed.
		 * @param array $unsanitized_postarr An array of slashed yet *unsanitized* and unprocessed attachment post data
		 *                                   as originally passed to wp_insert_post().
		 */
		$post_type = $this->post_type;
		add_filter(
			'wp_insert_attachment_data',
			/**
			 * @param array<string,mixed> $data
			 * @param array<string,mixed> $postarr
			 * @param array<string,mixed> $unsanitized_postarr
			 * @return array<string,mixed>
			 */
			function ( array $data, array $postarr, array $unsanitized_postarr ) use ( $post_type, $request ): array {
				$data['post_type'] = $post_type;

				if ( ! empty( $request->get_param( 'post_author' ) ) ) {
					$data['post_author'] = $request->get_param( 'post_author' );
				} else {
					$data['post_author'] = 0;
				}

				return $data;
			},
			10,
			3
		);

		/**
		 * During `wp_insert_post()` this filter is called and we immediately return the value from the request. It
		 * doesn't look like it should work, but I think it does.
		 *
		 * @see wp_insert_post()
		 */
		if ( ! empty( $request->get_param( 'post_parent' ) ) ) {
			add_filter(
				'wp_insert_post_parent',
				fn() => $request->get_param( 'post_parent' )
			);
		}
	}

	/**
	 * Upload a file and create a new post
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$this->add_set_private_uploads_filters( $request );

		return parent::create_item( $request );
	}

	/**
	 *
	 * Based on insert_attachment()
	 * We don't use insert_attachment because that also creates an entry in the Media Library, which we do not always want.
	 *
	 * @see self::create_item() when we want to upload AND add a new attachment-post.
	 *
	 * @see WP_REST_Attachments_Controller::insert_attachment()
	 *
	 * @param WP_REST_Request $request The parsed request.
	 * @return array{file:array{file:string, url:string, type:string}}|WP_Error
	 */
	public function upload_item( WP_REST_Request $request ) {

		$this->add_set_private_uploads_filters( $request );

		// Get the file via $_FILES or raw data.
		$files   = $request->get_file_params();
		$headers = $request->get_headers();

		if ( ! empty( $files ) ) {
			$file = $this->upload_from_file( $files, $headers );
		} else {
			// This `$data` type does not match the function signature, but it's lifted from WordPress core, so presumed ok.
			$file = $this->upload_from_data( $request->get_body(), $headers );
		}

		if ( is_wp_error( $file ) ) {
			return $file;
		}

		/** @var array{file:string, url:string, type:string} $file */
		// Slug / Post type name?
		do_action( 'rest_private_uploads_upload', $file, $request, $this->settings );

		return array(
			'file' => $file,
		);
	}
}
