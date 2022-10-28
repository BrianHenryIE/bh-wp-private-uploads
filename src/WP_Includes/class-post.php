<?php
/**
 * Seems to be te easiest way to register the REST route
 *
 * @see register_post_type();
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;

class Post {

	protected Private_Uploads_Settings_Interface $settings;

	public function __construct( Private_Uploads_Settings_Interface $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @hooked init
	 */
	public function register_post_type(): void {

		$post_type_name = $this->settings->get_post_type_name();

		$post_type_config = array(
			'public'                         => false,
			'publicly_queryable'             => false,
			'delete_with_user'               => true,
			'supports'                       => array(),
			'show_in_rest'                   => ! is_null( $this->settings->get_rest_namespace() ),
			// 'rest_base'             => 'uploads',
							'rest_namespace' => $this->settings->get_rest_namespace(),
			'rest_controller_class'          => REST_Private_Uploads_Controller::class,
			'settings'                       => $this->settings, // Can we set arbitrary data on a post type?!
		);

		register_post_type(
			$post_type_name,
			$post_type_config
		);
	}

}
