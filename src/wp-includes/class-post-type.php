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
	 * @hooked init
	 */
	public function register_post_type(): void {

		$post_type_name = $this->settings->get_post_type_name();

		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';

		$plugin_slug     = $this->settings->get_plugin_slug();
		$plugins         = get_plugins();
		$plugin_basename = function ( string $plugin_slug ) use ( $plugins ): ?string {
			foreach ( $plugins as $plugin_basename => $plugin_data ) {
				if ( explode( '/', $plugin_basename )[0] === $plugin_slug ) {
					return $plugin_basename;
				}
			}
			return null;
		};
		$plugin_name     = is_null( $plugin_basename )
				? $plugin_slug
				: $plugins[ $plugin_basename( $plugin_slug ) ]['Name'];

		$post_type_config = array(
			'public'                => false,
			'publicly_queryable'    => false,
			'delete_with_user'      => true,
			'supports'              => array(),
			'label'                 => "{$plugin_name} Uploads",
			'show_ui'               => true,
			'show_in_menu'          => true,
			'show_in_rest'          => true,
			'rest_namespace'        => $this->settings->get_plugin_slug() . '/v1',
			'rest_base'             => 'uploads',
			'rest_controller_class' => REST_Private_Uploads_Controller::class,
			'_edit_link'            => "post.php?post=%d&post_type={$post_type_name}",
			'settings'              => $this->settings, // Can we set arbitrary data on a post type?!
		);

		register_post_type(
			$post_type_name,
			$post_type_config
		);
	}
}
