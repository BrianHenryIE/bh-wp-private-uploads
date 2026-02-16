<?php

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin\WP_Includes;

use WP_REST_Request;

class Upload_File_Integration_Test extends \BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase {


	/**
	 * @requires function imagejpeg
	 */
	public function test_create_item() {

		global $project_root_dir;

		$test_file_path = $project_root_dir . '/tests/_data/sample.pdf';

		$yyyymm                      = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		$expected_uploaded_file_path = $project_root_dir . "/wp-content/uploads/private-media/$yyyymm/sample.pdf";

		if ( file_exists( $expected_uploaded_file_path ) ) {
			unlink( $expected_uploaded_file_path );
		}

		$post_id = wp_insert_post( array( 'post_content' => 'attach the pdf to this post' ) );

		$user_id = wp_create_user( 'customer', 'customer@example.org' );

		// User 1 is admin.
		wp_set_current_user( 1 );

		$request = new WP_REST_Request( 'POST', '/bh-wp-private-uploads-development-plugin/v1/test-plugin-private' );
		$request->set_header( 'Content-Type', 'image/jpeg' );
		$request->set_header( 'Content-Disposition', 'attachment; filename=' . basename( $test_file_path ) );
		$request->set_param( 'title', 'My title is very cool' );
		$request->set_param( 'caption', 'This is a better caption.' );
		$request->set_param( 'description', 'Without a description, my attachment is descriptionless.' );
		$request->set_param( 'alt_text', 'Alt text is stored outside post schema.' );
		$request->set_param( 'post_parent', $post_id ); // e.g. the WooCommerce order id.
		$request->set_param( 'post_author', $user_id ); // e.g. the WooCommerce customer user id.

		$request->set_body( file_get_contents( $test_file_path ) );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'file', $data['media_type'] );

		$attachment = get_post( $data['id'] );
		$this->assertSame( 'My title is very cool', $data['title']['raw'] );
		$this->assertSame( 'My title is very cool', $attachment->post_title );
		$this->assertSame( 'This is a better caption.', $data['caption']['raw'] );
		$this->assertSame( 'This is a better caption.', $attachment->post_excerpt );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $data['description']['raw'] );
		$this->assertSame( 'Without a description, my attachment is descriptionless.', $attachment->post_content );
		$this->assertSame( 'Alt text is stored outside post schema.', $data['alt_text'] );
		$this->assertSame( 'Alt text is stored outside post schema.', get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );

		$this->assertEquals( 'private_media', $attachment->post_type );

		$this->assertEquals( $post_id, $attachment->post_parent );

		$this->assertEquals( $user_id, $attachment->post_author );

		$this->assertFileExists( $expected_uploaded_file_path );
	}
}
