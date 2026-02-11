<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Upload
 */
class Upload_WPUnit_Test extends WPTestCase {

	/**
	 * @dataProvider provider_clean_url
	 * @covers ::clean_url
	 * @covers ::request_uri_has_post_type
	 *
	 * @param string $current_page_url The page loaded in the user's browser.
	 * @param string $input_url The URL being printed in the HTML.
	 * @param string $expected_url What the updated URL should be after being filtered by the function.
	 */
	public function test_clean_url( string $current_page_url, string $input_url, string $expected_url ): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => 'test_private',
			)
		);

		$_SERVER['REQUEST_URI'] = $current_page_url;

		$sut = new Upload( $settings );

		$result = $sut->clean_url( $input_url );

		$this->assertSame( $expected_url, $result );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public function provider_clean_url(): array {
		return array(
			'upload.php gets post_type appended.'         => array(
				'http://example.com/index.php?post_type=test_private',
				'http://example.com/wp-admin/upload.php',
				'http://example.com/wp-admin/upload.php?post_type=test_private',
			),
			'upload.php does not get post_type appended.' => array(
				'http://example.com/index.php',
				'http://example.com/wp-admin/upload.php',
				'http://example.com/wp-admin/upload.php',
			),
			'upload.php does not get post_type appended 2.' => array(
				'http://example.com/index.php?post_type=test_2',
				'http://example.com/wp-admin/upload.php',
				'http://example.com/wp-admin/upload.php',
			),
			'upload.php does not get post_type appended 3.' => array(
				'http://example.com/index.php?post_type=test_private_2',
				'http://example.com/wp-admin/upload.php',
				'http://example.com/wp-admin/upload.php',
			),
			'media-new.php gets post_type appended'       => array(
				'http://example.com/wp-admin/media-new.php?post_type=test_private',
				'http://example.com/wp-admin/media-new.php',
				'http://example.com/wp-admin/media-new.php?post_type=test_private',
			),
			'async-uploads.php gets post_type appended'   => array(
				'http://example.com/wp-admin/upload.php?post_type=test_private',
				'http://example.com/wp-admin/async-uploads.php',
				'http://example.com/wp-admin/async-uploads.php?post_type=test_private',
			),
			'already has post_type returns unchanged'     => array(
				'http://example.com/wp-admin/upload.php?post_type=existing',
				'http://example.com/wp-admin/upload.php?post_type=existing',
				'http://example.com/wp-admin/upload.php?post_type=existing',
			),
			'unrelated URL returns unchanged'             => array(
				'http://example.com/wp-admin/upload.php?post_type=test_private',
				'http://example.com/wp-admin/edit.php',
				'http://example.com/wp-admin/edit.php',
			),
			'upload.php with existing query params'       => array(
				'http://example.com/wp-admin/upload.php?post_type=test_private',
				'http://example.com/wp-admin/upload.php?mode=list',
				'http://example.com/wp-admin/upload.php?mode=list&post_type=test_private',
			),
		);
	}
}
