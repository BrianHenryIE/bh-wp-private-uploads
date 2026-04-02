<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;
use WP_Error;

/**
 * @coversDefaultClass  \BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron
 */
class Cron_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * @covers ::register_cron_job
	 */
	public function test_register_cron_job_happy_path(): void {

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

		$sut->register_cron_job();

		$cron_hook = 'the_plugin_slug_private_uploads_check_url_the_post_type_name';

		$this->assertNotFalse( wp_get_scheduled_event( $cron_hook ) );
		$this->assertTrue( $logger->hasInfoThatContains( 'Registered the' ) );
	}

	/**
	 * @covers ::register_cron_job
	 */
	public function test_register_cron_job_already_registered(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$cron_hook = 'the_plugin_slug_private_uploads_check_url_the_post_type_name';
		wp_schedule_event( time(), 'hourly', $cron_hook );

		$sut = new Cron( $api, $settings, $logger );

		$sut->register_cron_job();

		$this->assertTrue( $logger->hasDebugThatContains( 'already registered' ) );
	}

	/**
	 * @covers ::register_cron_job
	 */
	public function test_register_cron_job_wp_error(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$return_wp_error = function () {
			return new WP_Error( 'test_error', 'Test error message' );
		};
		add_filter( 'pre_schedule_event', $return_wp_error );

		$sut = new Cron( $api, $settings, $logger );

		$sut->register_cron_job();

		remove_filter( 'pre_schedule_event', $return_wp_error );

		$this->assertTrue( $logger->hasErrorThatContains( 'Test error message' ) );
	}

	/**
	 * @covers ::register_cron_job
	 */
	public function test_register_cron_job_other_error(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		// Return a non-true, non-WP_Error, non-false value to exercise the (string) cast branch.
		$return_pseudo_error = function () {
			return 0;
		};
		add_filter( 'pre_schedule_event', $return_pseudo_error );

		$sut = new Cron( $api, $settings, $logger );

		$sut->register_cron_job();

		remove_filter( 'pre_schedule_event', $return_pseudo_error );

		$this->assertTrue( $logger->hasErrorThatContains( 'Failed to register' ) );
	}
}
