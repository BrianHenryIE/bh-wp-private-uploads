<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\Media_Request
 */
class Media_Request_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::is_relevant_page
	 * @dataProvider provider_is_relevant_page
	 *
	 * @param string $pagenow Arrange the test as though this page was loaded.
	 * @param bool   $expected Should it be deemed a relevant page.
	 */
	public function test_is_relevant_page( string $pagenow, bool $expected ): void {
		$GLOBALS['pagenow'] = $pagenow;

		$sut = new Media_Request();

		$this->assertSame( $expected, $sut->is_relevant_page() );
	}

	/**
	 * @return array<string, array{string, bool}>
	 */
	public function provider_is_relevant_page(): array {
		return array(
			'upload.php is relevant'       => array( 'upload.php', true ),
			'media-new.php is relevant'    => array( 'media-new.php', true ),
			'async-upload.php is relevant' => array( 'async-upload.php', true ),
			'edit.php is not relevant'     => array( 'edit.php', false ),
			'index.php is not relevant'    => array( 'index.php', false ),
		);
	}

	/**
	 * @covers ::request_uri_has_post_type
	 * @covers ::uri_has_post_type
	 */
	public function test_request_uri_has_post_type_true(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/upload.php?post_type=test_private';

		$sut = new Media_Request();

		$this->assertTrue( $sut->request_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::request_uri_has_post_type
	 * @covers ::uri_has_post_type
	 */
	public function test_request_uri_has_post_type_wrong_type(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/upload.php?post_type=other';

		$sut = new Media_Request();

		$this->assertFalse( $sut->request_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::request_uri_has_post_type
	 * @covers ::uri_has_post_type
	 */
	public function test_request_uri_has_post_type_missing(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/upload.php';

		$sut = new Media_Request();

		$this->assertFalse( $sut->request_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::request_uri_has_post_type
	 */
	public function test_request_uri_has_post_type_no_request_uri(): void {
		unset( $_SERVER['REQUEST_URI'] );

		$sut = new Media_Request();

		$this->assertFalse( $sut->request_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::referer_uri_has_post_type
	 */
	public function test_referer_uri_has_post_type_true(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/upload.php?post_type=test_private';

		$sut = new Media_Request();

		$this->assertTrue( $sut->referer_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::referer_uri_has_post_type
	 */
	public function test_referer_uri_has_post_type_wrong_type(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/upload.php?post_type=other';

		$sut = new Media_Request();

		$this->assertFalse( $sut->referer_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::referer_uri_has_post_type
	 */
	public function test_referer_uri_has_post_type_missing(): void {
		$_SERVER['HTTP_REFERER'] = 'http://example.com/wp-admin/upload.php';

		$sut = new Media_Request();

		$this->assertFalse( $sut->referer_uri_has_post_type( 'test_private' ) );
	}

	/**
	 * @covers ::referer_uri_has_post_type
	 */
	public function test_referer_uri_has_post_type_no_referer(): void {
		unset( $_SERVER['HTTP_REFERER'] );

		$sut = new Media_Request();

		$this->assertFalse( $sut->referer_uri_has_post_type( 'test_private' ) );
	}
}
