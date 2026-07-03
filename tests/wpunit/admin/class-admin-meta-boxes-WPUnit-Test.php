<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Meta_Boxes
 */
class Admin_Meta_Boxes_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * The meta box is registered on a post type that is configured in `get_meta_box_settings()`.
	 *
	 * @covers ::add_meta_box
	 */
	public function test_meta_box_registered_for_configured_post_type(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'    => 'meta_test_uploads',
				'get_meta_box_settings' => array( 'shop_order' => array( 'some' => 'config' ) ),
			)
		);

		$sut = new Admin_Meta_Boxes( $settings, $this->logger );

		$post_id = wp_insert_post(
			array(
				'post_title' => 'Meta box test order',
				'post_type'  => 'shop_order',
			)
		);
		$post    = get_post( $post_id );

		$sut->add_meta_box( 'shop_order', $post );

		/** @var array<string, array<string, array<string, array<string, mixed>>>> $wp_meta_boxes */
		global $wp_meta_boxes;
		$this->assertArrayHasKey( 'meta_test_uploads', $wp_meta_boxes['shop_order']['side']['default'] );
	}

	/**
	 * The meta box is not registered on a post type absent from `get_meta_box_settings()`.
	 *
	 * @covers ::add_meta_box
	 */
	public function test_meta_box_not_registered_for_unconfigured_post_type(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'    => 'meta_test_uploads',
				'get_meta_box_settings' => array( 'shop_order' => array( 'some' => 'config' ) ),
			)
		);

		$sut = new Admin_Meta_Boxes( $settings, $this->logger );

		$post_id = wp_insert_post(
			array(
				'post_title' => 'Unconfigured post',
				'post_type'  => 'post',
			)
		);
		$post    = get_post( $post_id );

		$sut->add_meta_box( 'unconfigured_type', $post );

		/** @var array<string, mixed> $wp_meta_boxes */
		global $wp_meta_boxes;
		$this->assertTrue( empty( $wp_meta_boxes['unconfigured_type'] ) );
	}
}
