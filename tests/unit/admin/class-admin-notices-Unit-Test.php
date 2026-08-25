<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices
 */
class Admin_Notices_Unit_Test extends Unit_Testcase {

	/**
	 * While the notice's dismissal is snoozed, the notice must not be registered at all: the WPTRT
	 * library prints each registered notice's inline dismiss script even when the notice itself is
	 * suppressed as dismissed, throwing "dismissBtn is null" TypeErrors on every admin page.
	 *
	 * @covers ::admin_notices
	 */
	public function test_admin_notices_returns_early_while_dismissal_is_snoozed(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_last_checked_is_url_private' => Expected::never(),
			)
		);
		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type',
			)
		);

		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'wptrt_notice_dismissed_the-post-type-private-uploads-url-is-public' )
			->andReturn( true );

		$sut = new Admin_Notices( $api, $settings, $this->logger );

		$sut->admin_notices();

		$this->assertEmpty( $sut->get_all() );
	}

	/**
	 * When the dismissal is not snoozed, the check proceeds to querying the last is-private result.
	 *
	 * @covers ::admin_notices
	 */
	public function test_admin_notices_proceeds_when_dismissal_is_not_snoozed(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_last_checked_is_url_private' => Expected::once( null ),
			)
		);
		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type',
			)
		);

		WP_Mock::userFunction( 'get_option' )
			->once()
			->with( 'wptrt_notice_dismissed_the-post-type-private-uploads-url-is-public' )
			->andReturn( false );

		$sut = new Admin_Notices( $api, $settings, $this->logger );

		$sut->admin_notices();

		$this->assertEmpty( $sut->get_all() );
	}

	/**
	 * @covers ::on_dismiss
	 */
	public function test_on_dismiss(): void {

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			\BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type',
			)
		);
		$logger   = $this->logger;

		$sut = new Admin_Notices( $api, $settings, $logger );

		\Patchwork\redefine(
			'constant',
			function ( string $constant_name ) {
				return 'WEEK_IN_SECONDS' === $constant_name
					? 60 * 60 * 24 * 7
					: \Patchwork\relay( func_get_args() );
			}
		);

		\Patchwork\redefine(
			'time',
			function () {
				return 946684800;
			}
		);

		WP_Mock::userFunction( 'wp_schedule_single_event' )
			->once()
			->withArgs(
				function ( $time, $hook ) {
					return 947289600 === $time
						&& 'the_plugin_slug_private_uploads_unsnooze_dismissed_notice_the_post_type' === $hook;
				}
			);

		$sut->on_dismiss( '', '', '' );
	}
}
