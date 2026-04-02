<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron
 */
class Cron_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::get_check_url_cron_hook_name
	 * @covers ::__construct
	 */
	public function test_get_check_url_cron_hook_name(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new Cron( $api, $settings, $logger );

		$expected_cron_hook_name = 'the_plugin_slug_private_uploads_check_url_the_post_type_name';

		$this->assertEquals( $expected_cron_hook_name, $sut->get_check_url_cron_hook_name() );
	}

	/**
	 * @covers ::get_unsnooze_notice_cron_hook_name
	 */
	public function test_get_unsnooze_notice_cron_hook_name(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new Cron( $api, $settings, $logger );

		$expected_cron_hook_name = 'the_plugin_slug_private_uploads_unsnooze_dismissed_notice_the_post_type_name';

		$this->assertEquals( $expected_cron_hook_name, $sut->get_unsnooze_notice_cron_hook_name() );
	}

	/**
	 * @covers ::check_is_url_public
	 */
	public function test_check_is_url_public(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'check_and_update_is_url_private' => Expected::once(),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );
		$logger   = $this->logger;

		$sut = new Cron( $api, $settings, $logger );

		WP_Mock::userFunction( 'current_action' )
			->once()
			->andReturn( 'the_cron_hook_name' );

		$sut->check_is_url_public();

		$this->assertTrue( $logger->hasDebugThatContains( 'Executing {action} cron job.' ) );
	}
}
