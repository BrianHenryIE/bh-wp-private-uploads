<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\API\Is_Private_Result;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;
use Codeception\Stub\Expected;
use DateTimeImmutable;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices
 */
class Admin_Notices_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::__construct
	 * @covers ::admin_notices
	 */
	public function test_admin_notices_adds_notice_when_url_not_private(): void {
		$logger = $this->logger;

		$is_private_result = new Is_Private_Result(
			'http://example.com/wp-content/uploads/private',
			false,
			200,
			new DateTimeImmutable()
		);

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'check_and_update_is_url_private' => Expected::once( $is_private_result ),
			)
		);
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name' => Expected::atLeastOnce( 'test-plugin' ),
			)
		);

		$sut = new Admin_Notices( $api, $settings, $logger );

		assert( 0 === count( $sut->get_all() ) );

		$sut->admin_notices();

		$result = $sut->get_all();

		$this->assertNotEmpty( $result );
		$this->assertArrayHasKey( 'test-plugin-private-uploads-url-is-public', $result );
	}
}
