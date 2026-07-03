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

		// Relocate the uploads basedir under ABSPATH so `str_replace( ABSPATH, ... )` yields a clean relative path.
		$relocated_basedir = constant( 'ABSPATH' ) . 'relocated-uploads-' . $subdir;

		$relocate = function ( array $uploads ) use ( $relocated_basedir ): array {
			$uploads['basedir'] = $relocated_basedir;
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
		$wp_rewrite->non_wp_rules = array();

		$sut = new WP_Rewrite( $settings, $this->logger );
		$sut->register_rewrite_rule();

		remove_filter( 'upload_dir', $relocate );

		$expected_regex = "relocated-uploads-{$subdir}/{$subdir}/(.*)$";
		$expected_query = 'index.php?rewrite-test-private-uploads-file=$1';

		$this->assertArrayHasKey( $expected_regex, $wp_rewrite->non_wp_rules );
		$this->assertSame( $expected_query, $wp_rewrite->non_wp_rules[ $expected_regex ] );
	}
}
