<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron
 */
class Cron_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::get_cron_hook_name
	 */
	public function test_get_cron_hook_name(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new Cron( $api, $settings, $logger );

		$expected_cron_hook_name = 'the_plugin_slug_private_uploads_check_url_the_post_type_name';

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
			->once()
			->with( $expected_cron_hook_name )
			->andReturn( 'anything' );

		$sut->register_cron_job();
	}
}
