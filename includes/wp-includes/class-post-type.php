<?php
/**
 * Seems to be the easiest way to register the REST route.
 *
 * @see register_post_type();
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;

class Post_Type {

	protected Private_Uploads_Settings_Interface $settings;

	public function __construct( Private_Uploads_Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register the post type specified in Settings.
	 *
	 * Use filter `register_post_type_args` to further customise.
	 *
	 * @see Private_Uploads_Settings_Interface::get_post_type_name()
	 * @see \WP_Post_Type::set_props()
	 *
	 * @hooked init
	 */
	public function register_post_type(): void {

		$post_type_name = $this->settings->get_post_type_name();

		$post_type_config = array(
			'public'             => false,
			'publicly_queryable' => false,
			'delete_with_user'   => true,
			'supports'           => array( // Matches the default for attachments.
				'title',
				'author',
				'comments',
			),
			'label'              => $this->settings->get_post_type_label(),
			'show_ui'            => true,
			'show_in_menu'       => false, // Should the admin menu Media submenu be displayed?
			'show_in_rest'       => true,
			'_edit_link'         => "post.php?post=%d&post_type={$post_type_name}",
			'dependencies'       => array( 'settings' => $this->settings ), // Arbitrary data accessible via post type object.
		);

		if ( ! is_null( $this->settings->get_rest_base() ) ) {
			$post_type_config['rest_namespace']        = $this->settings->get_plugin_slug() . '/v1';
			$post_type_config['rest_base']             = $this->settings->get_rest_base();
			$post_type_config['rest_controller_class'] = REST_Private_Uploads_Controller::class;
		}

		register_post_type(
			$post_type_name,
			$post_type_config
		);
	}
}
