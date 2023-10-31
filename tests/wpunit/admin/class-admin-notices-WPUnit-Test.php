<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Codeception\Stub\Expected;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices
 */
class Admin_Notices_WPUnit_Test extends \Codeception\TestCase\WPTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::admin_notices
	 */
	public function test_admin_notices_adds_notice_when_url_not_private(): void {
		$logger   = new ColorLogger();
		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'check_and_update_is_url_private' => Expected::once(
					array(
						'url'        => 'http://example.com/wp-content/uploads/private',
						'is_private' => false,
					)
				),
			)
		);
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => Expected::atLeastOnce( 'test-plugin' ),
			)
		);

		$sut = new Admin_Notices( $api, $settings, $logger );

		assert( 0 === count( $sut->get_all() ) );

		$sut->admin_notices();

		$result = $sut->get_all();

		self::assertNotEmpty( $result );
		self::assertArrayHasKey( 'test-plugin-private-uploads-url-is-public', $result );
	}
}
