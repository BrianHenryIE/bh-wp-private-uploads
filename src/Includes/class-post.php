<?php
/**
 * Seems to be te easiest way to register the REST route
 *
 * @see register_post_type();
 */

namespace BrianHenryIE\WP_Private_Uploads\Includes;

use BrianHenryIE\WP_Private_Uploads\API\REST_Private_Uploads_Controller;

class Post {


	/**
	 * @hooked init
	 */
	public function register_post_type() {

		register_post_type(
			'private-uploads',
			array(
				'public'                       => false,
				'publicly_queryable'           => false,
				'delete_with_user'             => true,
				// 'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments', 'revisions' ),
								'show_in_rest' => true,
				'rest_base'                    => 'pages',
				'rest_controller_class'        => REST_Private_Uploads_Controller::class,
			)
		);
	}


}
