<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite
 */
class WP_Rewrite_WPUnit_Test extends WPUnit_Testcase {

	/**
	 * The rewrite regex must be derived from `wp_upload_dir()`'s `basedir` (made relative to `ABSPATH`),
	 * so it matches the relocated uploads path on multisite / relocated-uploads installs rather than a
	 * hard-coded `WP_CONTENT_DIR/uploads` path.
	 *
	 * @covers ::register_rewrite_rule
	 */
	public function test_register_rewrite_rule_uses_relocated_upload_dir(): void {

		$subdir = uniqid( 'rewritetest' );

		// Relocate the uploads baseurl under the site root; the rule is derived from the URL path.
		$relocated_baseurl = home_url( '/relocated-uploads-' . $subdir );

		$relocate = function ( array $uploads ) use ( $relocated_baseurl ): array {
			$uploads['baseurl'] = $relocated_baseurl;
			return $uploads;
		};
		add_filter( 'upload_dir', $relocate );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => 'rewrite_test',
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$sut = new WP_Rewrite( $settings, $this->logger );
		$sut->register_rewrite_rule();

		remove_filter( 'upload_dir', $relocate );

		$expected_regex = "relocated-uploads-{$subdir}/{$subdir}/(.*)$";
		$expected_query = 'index.php?rewrite-test-private-uploads-file=$1';

		$this->assertArrayHasKey( $expected_regex, $wp_rewrite->non_wp_rules );
		$this->assertSame( $expected_query, $wp_rewrite->non_wp_rules[ $expected_regex ] );
	}

	/**
	 * Count the callbacks registered on `shutdown` at the default priority.
	 */
	protected function count_shutdown_callbacks(): int {
		/** @var array<string, \WP_Hook> $wp_filter */
		global $wp_filter;
		if ( ! isset( $wp_filter['shutdown'] ) ) {
			return 0;
		}
		$callbacks = $wp_filter['shutdown']->callbacks[10] ?? array();
		return is_array( $callbacks ) ? count( $callbacks ) : 0;
	}

	/**
	 * The first time a rule is added it should schedule a one-time `flush_rewrite_rules()` on `shutdown`
	 * (so the rule is written to `.htaccess`), without writing the guard option until the flush actually runs.
	 *
	 * @covers ::register_rewrite_rule
	 * @covers ::maybe_flush_rewrite_rules
	 */
	public function test_register_rewrite_rule_schedules_flush_once(): void {

		$subdir = uniqid( 'rewriteflush' );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => 'rewrite_flush_test',
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		delete_option( 'bh_wp_private_uploads_rewrite_flush_test_rewrite_flushed' );

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		$wp_rewrite->non_wp_rules = array();

		$count_before = $this->count_shutdown_callbacks();

		$sut = new WP_Rewrite( $settings, $this->logger );
		$sut->register_rewrite_rule();

		$this->assertSame( $count_before + 1, $this->count_shutdown_callbacks() );

		// The option is only written once the flush runs on shutdown – not on every request.
		$this->assertFalse( get_option( 'bh_wp_private_uploads_rewrite_flush_test_rewrite_flushed' ) );
	}

	/**
	 * When the rule has already been flushed (guard option set), a subsequent registration must not
	 * schedule another flush.
	 *
	 * @covers ::register_rewrite_rule
	 * @covers ::maybe_flush_rewrite_rules
	 */
	public function test_register_rewrite_rule_does_not_reschedule_when_already_flushed(): void {

		$subdir = uniqid( 'rewritenoflush' );

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => 'rewrite_noflush_test',
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		$upload   = wp_upload_dir();
		$relative = str_replace( constant( 'ABSPATH' ), '', $upload['basedir'] . '/' . $subdir . '/' );
		$regex    = "{$relative}(.*)$";

		update_option( 'bh_wp_private_uploads_rewrite_noflush_test_rewrite_flushed', $regex, true );

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		$wp_rewrite->non_wp_rules = array();

		$count_before = $this->count_shutdown_callbacks();

		$sut = new WP_Rewrite( $settings, $this->logger );
		$sut->register_rewrite_rule();

		$this->assertSame( $count_before, $this->count_shutdown_callbacks() );
	}
}
