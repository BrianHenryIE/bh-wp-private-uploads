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

		if ( empty( $this->namespace ) ) {
			return;
		}

		/**
		 * Register an "upload_item" route that uploads to private uploads but does not create an
		 * attachment/post. Defaults to `plugin-slug/v1/plugin-slug-uploads/upload`.
		 *
		 * The `/upload` suffix is required: `register_rest_route()` trims the route's trailing slash, so
		 * registering this at `"/{$this->rest_base}/"` gave it the same path as the collection route
		 * `parent::register_routes()` registers above. Both are `CREATABLE`, and the parent's `create_item`
		 * wins on dispatch – so this callback was unreachable.
		 *
		 * @see register_rest_route() `$full_route = '/' . $clean_namespace . '/' . trim( $route, '/' );`
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/upload',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'                => $this->get_post_parent_and_post_author_args(),
			)
		);
	}

	/**
	 * Add the `post_parent` and `post_author` params to the inherited "create" route, so they are validated
	 * and sanitized before the permission check and the callback run.
	 *
	 * @see WP_REST_Posts_Controller::register_routes()
	 *
	 * @param string $method The HTTP method(s) of the route the args are for.
	 * @return array<string,array<string,mixed>> The endpoint args.
	 */
	public function get_endpoint_args_for_item_schema( $method = \WP_REST_Server::CREATABLE ) {

		$endpoint_args = parent::get_endpoint_args_for_item_schema( $method );

		if ( \WP_REST_Server::CREATABLE !== $method ) {
			return $endpoint_args;
		}

		return array_merge( $endpoint_args, $this->get_post_parent_and_post_author_args() );
	}

	/**
	 * The definitions of the two params this controller adds to core's attachment params.
	 *
	 * Authorization for the values is in {@see self::create_item_permissions_check()}.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_post_parent_and_post_author_args(): array {
		return array(
			'post_parent' => array(
				'description'       => __( 'The id of the post the file belongs to. Requires permission to edit that post.', 'bh-wp-private-uploads' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'validate_callback' => array( $this, 'validate_post_parent_param' ),
				'sanitize_callback' => 'absint',
			),
			'post_author' => array(
				'description'       => __( 'The id of the user who owns the file. Requires permission to edit others\' posts when it is not the current user.', 'bh-wp-private-uploads' ),
				'type'              => 'integer',
				'minimum'           => 0,
				'validate_callback' => array( $this, 'validate_post_author_param' ),
				'sanitize_callback' => 'absint',
			),
		);
	}

	/**
	 * Check the `post_parent` param is an integer, and the id of an existing post.
	 *
	 * @see self::get_post_parent_and_post_author_args()
	 *
	 * @param mixed           $value   The unsanitized param value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The param name.
	 * @return true|WP_Error
	 */
	public function validate_post_parent_param( $value, WP_REST_Request $request, string $param ) {

		$is_valid_integer = rest_validate_request_arg( $value, $request, $param );

		if ( is_wp_error( $is_valid_integer ) ) {
			return $is_valid_integer;
		}

		/** @var int|numeric-string $value */
		$post_parent_id = (int) $value;

		if ( 0 !== $post_parent_id && ! ( get_post( $post_parent_id ) instanceof \WP_Post ) ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post parent ID.', 'bh-wp-private-uploads' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Check the `post_author` param is an integer, and the id of an existing user.
	 *
	 * @see self::get_post_parent_and_post_author_args()
	 *
	 * @param mixed           $value   The unsanitized param value.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The param name.
	 * @return true|WP_Error
	 */
	public function validate_post_author_param( $value, WP_REST_Request $request, string $param ) {

		$is_valid_integer = rest_validate_request_arg( $value, $request, $param );

		if ( is_wp_error( $is_valid_integer ) ) {
			return $is_valid_integer;
		}

		/** @var int|numeric-string $value */
		$post_author_id = (int) $value;

		if ( 0 !== $post_author_id && false === get_userdata( $post_author_id ) ) {
			return new WP_Error(
				'rest_user_invalid_id',
				__( 'Invalid post author ID.', 'bh-wp-private-uploads' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Core's checks, plus per-object authorization for this controller's `post_parent` and `post_author` params.
	 *
	 * Core only checks `edit_post` for its own `post` param and `edit_others_posts` for its own `author` param.
	 * Without these checks, any user with `upload_files` could attach a private file to any post (e.g. another
	 * customer's order), or record any user as its owner.
	 *
	 * Used by both the "create" route and the "upload" route.
	 *
	 * @see WP_REST_Attachments_Controller::create_item_permissions_check()
	 * @see WP_REST_Posts_Controller::create_item_permissions_check()
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {

		$core_permissions_check = parent::create_item_permissions_check( $request );

		if ( true !== $core_permissions_check ) {
			return $core_permissions_check;
		}

		$post_parent_id = $this->get_id_param( $request, 'post_parent' );

		if ( 0 !== $post_parent_id && ! current_user_can( 'edit_post', $post_parent_id ) ) {
			return new WP_Error(
				'rest_cannot_edit',
				__( 'Sorry, you are not allowed to upload private files to this post.', 'bh-wp-private-uploads' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post_author_id = $this->get_id_param( $request, 'post_author' );

		if (
			0 !== $post_author_id
			&& get_current_user_id() !== $post_author_id
			&& ! $this->current_user_can_edit_others_posts()
		) {
			return new WP_Error(
				'rest_cannot_edit_others',
				__( 'Sorry, you are not allowed to upload private files as this user.', 'bh-wp-private-uploads' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Read a post id or user id request param as a non-negative integer; `0` when it is absent.
	 *
	 * @param WP_REST_Request $request    The request object.
	 * @param string          $param_name The name of the param: `post_parent` or `post_author`.
	 */
	protected function get_id_param( WP_REST_Request $request, string $param_name ): int {

		$param_value = $request->get_param( $param_name );

		return is_numeric( $param_value ) ? absint( $param_value ) : 0;
	}

	/**
	 * Can the current user create posts of this controller's post type on behalf of other users?
	 *
	 * Uses the post type's own mapping of the `edit_others_posts` capability.
	 */
	protected function current_user_can_edit_others_posts(): bool {

		$post_type_object = get_post_type_object( $this->post_type );

		if ( ! ( $post_type_object instanceof WP_Post_Type ) ) {
			return false;
		}

		$edit_others_posts_capability = $post_type_object->cap->edit_others_posts ?? null;

		return is_string( $edit_others_posts_capability ) && current_user_can( $edit_others_posts_capability );
	}

	/**
	 * Filter `wp_upload_dir()` so the file is saved in the private uploads subdirectory.
	 *
	 * The post type, author and parent post are set by {@see self::add_insert_post_filters()}.
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
	}

	/**
	 * Filters to, on the post inserted for this request's upload:
	 * * set the post type
	 * * set the post author
	 * * set the parent post
	 *
	 * The filters only change the insert whose `guid` is the URL of the file uploaded in this request, in case
	 * hooks during the request insert other attachments. The URL is not known until the upload is handled, so
	 * it is captured on `wp_handle_upload`.
	 *
	 * @see WP_REST_Attachments_Controller::insert_attachment()
	 * @see \BrianHenryIE\WP_Private_Uploads\API\API::create_post_for_file()
	 *
	 * @param WP_REST_Request $request The request object, whose params have been validated, sanitized and authorized.
	 * @return callable(): void Removes the filters.
	 */
	protected function add_insert_post_filters( WP_REST_Request $request ): callable {

		$post_type      = $this->post_type;
		$post_author_id = $this->get_id_param( $request, 'post_author' );
		$post_parent_id = $this->get_id_param( $request, 'post_parent' );

		$uploaded_file_url = null;

		/**
		 * Record the URL of the uploaded file, which core uses as the new post's `guid`.
		 *
		 * @see _wp_handle_upload()
		 *
		 * @param array{file:string, url:string, type:string} $upload The saved file.
		 * @return array{file:string, url:string, type:string}
		 */
		$record_uploaded_file_url = function ( array $upload ) use ( &$uploaded_file_url ): array {
			$uploaded_file_url = $upload['url'];
			return $upload;
		};
		add_filter( 'wp_handle_upload', $record_uploaded_file_url, PHP_INT_MAX );

		/**
		 * `$postarr` is slashed.
		 *
		 * @param array<string,mixed> $postarr The post data passed to `wp_insert_post()`.
		 */
		$is_post_for_uploaded_file = function ( array $postarr ) use ( &$uploaded_file_url ): bool {
			return is_string( $uploaded_file_url )
				&& isset( $postarr['guid'] )
				&& is_string( $postarr['guid'] )
				&& wp_unslash( $postarr['guid'] ) === $uploaded_file_url;
		};

		/**
		 * The REST attachment controller calls insert_attachment which forces the post type to attachment rather than
		 * our preferred own post type.
		 *
		 * Filters attachment post data before it is updated in or added to the database.
		 *
		 * @see wp_insert_post()
		 *
		 * @param array<string,mixed> $data    An array of slashed, sanitized, and processed attachment post data.
		 * @param array<string,mixed> $postarr An array of slashed and sanitized attachment post data, but not processed.
		 * @return array<string,mixed>
		 */
		$set_post_type_and_author = function ( array $data, array $postarr ) use ( $post_type, $post_author_id, $is_post_for_uploaded_file ): array {
			if ( $is_post_for_uploaded_file( $postarr ) ) {
				$data['post_type']   = $post_type;
				$data['post_author'] = $post_author_id;
			}
			return $data;
		};
		add_filter( 'wp_insert_attachment_data', $set_post_type_and_author, 10, 2 );

		/**
		 * Filters the post parent -- used to check for and prevent hierarchy loops.
		 *
		 * @see wp_insert_post()
		 *
		 * @param int                 $post_parent Post parent ID.
		 * @param int                 $post_id     Post ID.
		 * @param array<string,mixed> $new_postarr Array of parsed post data.
		 * @param array<string,mixed> $postarr     Array of sanitized, but otherwise unmodified post data.
		 */
		$set_post_parent = function ( $post_parent, $post_id, array $new_postarr, array $postarr ) use ( $post_parent_id, $is_post_for_uploaded_file ) {
			return $is_post_for_uploaded_file( $postarr ) ? $post_parent_id : $post_parent;
		};
		if ( 0 !== $post_parent_id ) {
			add_filter( 'wp_insert_post_parent', $set_post_parent, 10, 4 );
		}

		return function () use ( $record_uploaded_file_url, $set_post_type_and_author, $set_post_parent ): void {
			remove_filter( 'wp_handle_upload', $record_uploaded_file_url, PHP_INT_MAX );
			remove_filter( 'wp_insert_attachment_data', $set_post_type_and_author, 10 );
			remove_filter( 'wp_insert_post_parent', $set_post_parent, 10 );
		};
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

		$remove_insert_post_filters = $this->add_insert_post_filters( $request );

		try {
			return parent::create_item( $request );
		} finally {
			$remove_insert_post_filters();
		}
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
	 * The `file` path in the response is relative to `WP_CONTENT_DIR`, so the server's filesystem layout is
	 * not disclosed. The `bh_wp_private_uploads_rest_upload` action still receives the absolute path.
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

		/**
		 * Fires after a file has been uploaded via the REST API, before the response is returned.
		 *
		 * @hooked "bh_wp_private_uploads_rest_upload"
		 *
		 * @param string                                      $plugin_slug The plugin slug of this private uploads instance.
		 * @param string                                      $post_type_name The post type name of this private uploads instance.
		 * @param array{file:string, url:string, type:string} $file The saved file.
		 * @param WP_REST_Request                             $request The request that uploaded the file.
		 */
		do_action(
			'bh_wp_private_uploads_rest_upload',
			$this->settings->get_plugin_slug(),
			$this->settings->get_post_type_name(),
			$file,
			$request
		);

		/**
		 * @deprecated 0.4.0 Use "bh_wp_private_uploads_rest_upload", which is passed the plugin slug and post type name in place of the settings object.
		 */
		do_action_deprecated(
			'rest_private_uploads_upload',
			array( $file, $request, $this->settings ),
			'0.4.0',
			'bh_wp_private_uploads_rest_upload'
		);

		$file['file'] = $this->get_path_relative_to_wp_content_dir( $file['file'] );

		return array(
			'file' => $file,
		);
	}

	/**
	 * Remove the `WP_CONTENT_DIR` prefix from an absolute path.
	 *
	 * When the uploads directory has been relocated outside `WP_CONTENT_DIR`, only the filename is returned,
	 * so an absolute path is never disclosed.
	 *
	 * @param string $absolute_path The absolute path of the uploaded file.
	 */
	protected function get_path_relative_to_wp_content_dir( string $absolute_path ): string {
		$path        = wp_normalize_path( $absolute_path );
		$content_dir = trailingslashit( wp_normalize_path( WP_CONTENT_DIR ) );

		if ( str_starts_with( $path, $content_dir ) ) {
			return substr( $path, strlen( $content_dir ) );
		}

		return wp_basename( $path );
	}
}
