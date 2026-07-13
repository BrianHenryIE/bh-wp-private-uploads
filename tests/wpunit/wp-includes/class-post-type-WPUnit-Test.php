<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Post_Type
 */
class Post_Type_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * The post type is registered private (not public / not publicly queryable), exposed in REST, and wired
	 * to the private uploads REST controller when a REST base is configured.
	 *
	 * @covers ::register_post_type
	 */
	public function test_post_type_registered_with_rest_props(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'     => 'post-type-test-plugin',
				'get_post_type_name'  => 'posttype_test',
				'get_post_type_label' => 'Post Type Test',
				'get_rest_base'       => 'post-type-test-uploads',
			)
		);

		( new Post_Type( $settings ) )->register_post_type();

		$post_type_object = get_post_type_object( 'posttype_test' );

		$this->assertInstanceOf( \WP_Post_Type::class, $post_type_object );
		$this->assertFalse( $post_type_object->public );
		$this->assertFalse( $post_type_object->publicly_queryable );
		$this->assertTrue( $post_type_object->show_in_rest );
		$this->assertSame( 'post-type-test-plugin/v1', $post_type_object->rest_namespace );
		$this->assertSame( 'post-type-test-uploads', $post_type_object->rest_base );
		$this->assertSame( REST_Private_Uploads_Controller::class, $post_type_object->rest_controller_class );
	}

	/**
	 * When no REST base is configured, the custom REST controller is not wired up.
	 *
	 * @covers ::register_post_type
	 */
	public function test_post_type_without_rest_base_does_not_use_custom_controller(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'     => 'post-type-test-plugin',
				'get_post_type_name'  => 'posttype_norest',
				'get_post_type_label' => 'Post Type No REST',
				'get_rest_base'       => null,
			)
		);

		( new Post_Type( $settings ) )->register_post_type();

		$post_type_object = get_post_type_object( 'posttype_norest' );

		$this->assertInstanceOf( \WP_Post_Type::class, $post_type_object );
		$this->assertNotSame( REST_Private_Uploads_Controller::class, $post_type_object->rest_controller_class );
	}

	/**
	 * An empty post type name registers nothing.
	 *
	 * @covers ::register_post_type
	 */
	public function test_empty_post_type_name_registers_nothing(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => '',
			)
		);

		( new Post_Type( $settings ) )->register_post_type();

		$this->assertNull( get_post_type_object( '' ) );
	}
}
