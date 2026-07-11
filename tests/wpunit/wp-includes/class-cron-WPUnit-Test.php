<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks;
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

	/**
	 * The shared helper must return the option name the wp-trt/admin-notices library actually stores the
	 * dismissal under (`wptrt_notice_dismissed_` + the notice id), so hook registration and deletion match.
	 *
	 * @covers ::get_dismissed_notice_option_name
	 */
	public function test_get_dismissed_notice_option_name(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);

		$sut = new Cron( $api, $settings, $this->logger );

		$this->assertSame(
			'wptrt_notice_dismissed_the-post-type-name-private-uploads-url-is-public',
			$sut->get_dismissed_notice_option_name()
		);
	}

	/**
	 * End-to-end: dismissing the notice (creating the dismissal option) schedules a single un-snooze cron
	 * event ~a week out; firing that event deletes the dismissal option.
	 *
	 * @covers ::unsnooze_dismissed_notice
	 * @covers ::get_dismissed_notice_option_name
	 */
	public function test_dismissal_schedules_unsnooze_and_firing_it_deletes_the_option(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the_plugin_slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		// Registers the `add_option_`/`update_option_` dismissal hooks and the un-snooze cron action.
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );

		$cron          = new Cron( $api, $settings, $logger );
		$option_name   = 'wptrt_notice_dismissed_the-post-type-name-private-uploads-url-is-public';
		$unsnooze_hook = 'the_plugin_slug_private_uploads_unsnooze_dismissed_notice_the_post_type_name';

		$this->assertSame( $option_name, $cron->get_dismissed_notice_option_name() );
		$this->assertSame( $unsnooze_hook, $cron->get_unsnooze_notice_cron_hook_name() );

		// No un-snooze event scheduled yet.
		$before = wp_get_scheduled_event( $unsnooze_hook );
		$this->assertFalse( $before );

		// Simulate the wp-trt/admin-notices library dismissing the notice.
		update_option( $option_name, true );

		$scheduled = wp_get_scheduled_event( $unsnooze_hook );
		$this->assertNotFalse( $scheduled );
		$this->assertEqualsWithDelta( time() + constant( 'WEEK_IN_SECONDS' ), $scheduled->timestamp, 60 );

		// Fire the cron hook; the dismissal option should be deleted.
		do_action( $unsnooze_hook );

		$this->assertFalse( get_option( $option_name ) );
	}
}
