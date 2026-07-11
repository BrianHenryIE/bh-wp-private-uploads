<?php

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\API\Is_Private_Result;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use WPTRT\AdminNotices\Notice;

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
				'get_last_checked_is_url_private' => Expected::once( $is_private_result ),
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

	/**
	 * The notice text filter is passed the plugin slug and post type name so one callback can
	 * distinguish between private uploads instances.
	 *
	 * @covers ::admin_notices
	 */
	public function test_url_is_public_warning_filter_is_passed_slug_and_post_type(): void {

		$sut = $this->get_admin_notices_for_public_url();

		add_filter(
			'bh_wp_private_uploads_url_is_public_warning',
			function ( string $content, string $url, string $plugin_slug, string $post_type_name ): string {

				$this->assertSame( 'http://example.com/wp-content/uploads/private', $url );
				$this->assertSame( 'test-plugin-slug', $plugin_slug );
				$this->assertSame( 'test-plugin', $post_type_name );

				return 'filtered notice';
			},
			10,
			4
		);

		$sut->admin_notices();

		$notice = $sut->get_all()['test-plugin-private-uploads-url-is-public'];

		$this->assertInstanceOf( Notice::class, $notice );

		// `get_message()` runs the text through `wpautop()`.
		$this->assertStringContainsString( 'filtered notice', $notice->get_message() );
	}

	/**
	 * The old per-post-type hook name still filters the notice, and warns that it is deprecated.
	 *
	 * @covers ::admin_notices
	 */
	public function test_deprecated_url_is_public_warning_filter_still_applies(): void {

		$this->setExpectedDeprecated( 'bh_wp_private_uploads_url_is_public_warning_test-plugin' );

		$sut = $this->get_admin_notices_for_public_url();

		add_filter(
			'bh_wp_private_uploads_url_is_public_warning_test-plugin',
			fn(): string => 'filtered by deprecated hook',
		);

		$sut->admin_notices();

		$notice = $sut->get_all()['test-plugin-private-uploads-url-is-public'];

		$this->assertInstanceOf( Notice::class, $notice );

		$this->assertStringContainsString( 'filtered by deprecated hook', $notice->get_message() );
	}

	/**
	 * An Admin_Notices whose API reports the private uploads URL is publicly accessible,
	 * i.e. one which will add the notice when `admin_notices()` is called.
	 */
	protected function get_admin_notices_for_public_url(): Admin_Notices {

		$is_private_result = new Is_Private_Result(
			'http://example.com/wp-content/uploads/private',
			false,
			200,
			new DateTimeImmutable()
		);

		$api = $this->makeEmpty(
			API_Interface::class,
			array(
				'get_last_checked_is_url_private' => Expected::once( $is_private_result ),
			)
		);

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'test-plugin-slug',
				'get_post_type_name' => 'test-plugin',
			)
		);

		return new Admin_Notices( $api, $settings, $this->logger );
	}
}
