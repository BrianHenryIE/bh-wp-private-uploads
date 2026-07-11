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
	 * The rewrite rule regex for a given uploads subdirectory, as `register_rewrite_rule()` derives it.
	 *
	 * @param string $subdir The private uploads subdirectory name.
	 */
	protected function get_expected_regex( string $subdir ): string {

		$upload           = wp_upload_dir();
		$uploads_url_path = (string) wp_parse_url( trailingslashit( $upload['baseurl'] ) . $subdir, PHP_URL_PATH );
		$home_url_path    = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( '' !== $home_url_path && str_starts_with( $uploads_url_path, $home_url_path ) ) {
			$uploads_url_path = substr( $uploads_url_path, strlen( $home_url_path ) );
		}

		return trailingslashit( ltrim( $uploads_url_path, '/' ) ) . '(.*)$';
	}

	/**
	 * A `WP_Rewrite` reading a test-controlled `.htaccess`, whose flush is recorded rather than performed
	 * (a real flush would rewrite the test install's own `.htaccess`).
	 *
	 * @param string $htaccess_file Path to stand in for the site-root `.htaccess`.
	 * @param string $subdir        The private uploads subdirectory name.
	 * @param string $post_type     The private uploads post type name.
	 */
	protected function get_sut( string $htaccess_file, string $subdir, string $post_type = 'rewrite_test' ): Spy_WP_Rewrite {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_post_type_name'            => $post_type,
				'get_uploads_subdirectory_name' => $subdir,
			)
		);

		return new Spy_WP_Rewrite( $settings, $this->logger, $htaccess_file );
	}

	/**
	 * Set up an admin request against an Apache install with pretty permalinks – i.e. the only context in
	 * which WordPress will write the rules to `.htaccess`.
	 */
	protected function given_htaccess_is_writable(): void {

		set_current_screen( 'dashboard' );          // `is_admin()`.
		add_filter( 'got_rewrite', '__return_true' ); // `got_mod_rewrite()`, i.e. Apache with mod_rewrite.

		update_option( 'permalink_structure', '/%year%/%monthnum%/%postname%/' );

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		$wp_rewrite->init();
	}

	protected function tearDown(): void {
		set_current_screen( 'front' );
		remove_filter( 'got_rewrite', '__return_true' );
		parent::tearDown();
	}

	/**
	 * The rule is missing from `.htaccess`, so it must be flushed – `.htaccess` is the source of truth,
	 * not an option recording that a flush once happened.
	 *
	 * @covers ::register_rewrite_rule
	 * @covers ::maybe_flush_rewrite_rules
	 * @covers ::is_rule_in_htaccess
	 * @covers ::is_htaccess_writable
	 */
	public function test_flushes_when_rule_is_absent_from_htaccess(): void {

		$subdir = uniqid( 'rewriteflush' );

		$this->given_htaccess_is_writable();

		$htaccess_file = (string) tempnam( sys_get_temp_dir(), 'htaccess' );
		file_put_contents( $htaccess_file, "# BEGIN WordPress\nRewriteRule . /index.php [L]\n# END WordPress\n" );

		$sut = $this->get_sut( $htaccess_file, $subdir );
		$sut->register_rewrite_rule();

		$this->assertTrue( $sut->did_flush );

		unlink( $htaccess_file );
	}

	/**
	 * The rule is already in `.htaccess`, so there is nothing to do. This is the whole point of reading the
	 * file: no flush on every admin page load, and no option that could go stale.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 * @covers ::is_rule_in_htaccess
	 */
	public function test_does_not_flush_when_rule_is_already_in_htaccess(): void {

		$subdir = uniqid( 'rewritenoflush' );

		$this->given_htaccess_is_writable();

		// The line as WordPress writes it.
		// @see \WP_Rewrite::mod_rewrite_rules()
		$rule = 'RewriteRule ^' . $this->get_expected_regex( $subdir ) . ' /index.php?rewrite-test-private-uploads-file=$1 [QSA,L]';

		$htaccess_file = (string) tempnam( sys_get_temp_dir(), 'htaccess' );
		file_put_contents( $htaccess_file, "# BEGIN WordPress\n{$rule}\nRewriteRule . /index.php [L]\n# END WordPress\n" );

		$sut = $this->get_sut( $htaccess_file, $subdir );
		$sut->register_rewrite_rule();

		$this->assertFalse( $sut->did_flush );

		unlink( $htaccess_file );
	}

	/**
	 * A frontend page load must not flush – nor even read `.htaccess`. A flush there could not write the
	 * file anyway: `save_mod_rewrite_rules()` is only defined for admin requests.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 */
	public function test_does_not_flush_on_a_frontend_request(): void {

		$subdir = uniqid( 'rewritefrontend' );

		$this->given_htaccess_is_writable();
		set_current_screen( 'front' ); // `is_admin()` is now false.

		// A path that does not exist: reading it would be a failure, not a false negative.
		$sut = $this->get_sut( '/nonexistent/.htaccess', $subdir );
		$sut->register_rewrite_rule();

		$this->assertFalse( $sut->did_flush );

		// The rule is still registered in memory – only the flush is skipped.
		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		$this->assertArrayHasKey( $this->get_expected_regex( $subdir ), $wp_rewrite->non_wp_rules );
	}

	/**
	 * Without Apache and mod_rewrite (e.g. nginx), `save_mod_rewrite_rules()` writes nothing, so flushing
	 * could never add the rule. Flushing anyway would rewrite the `rewrite_rules` option on every single
	 * admin page load, forever.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 */
	public function test_does_not_flush_without_mod_rewrite(): void {

		$subdir = uniqid( 'rewritenginx' );

		$this->given_htaccess_is_writable();
		remove_filter( 'got_rewrite', '__return_true' );
		add_filter( 'got_rewrite', '__return_false' );

		$htaccess_file = (string) tempnam( sys_get_temp_dir(), 'htaccess' );

		$sut = $this->get_sut( $htaccess_file, $subdir );
		$sut->register_rewrite_rule();

		$this->assertFalse( $sut->did_flush );

		remove_filter( 'got_rewrite', '__return_false' );
		unlink( $htaccess_file );
	}

	/**
	 * With "Plain" permalinks, `WP_Rewrite::mod_rewrite_rules()` returns an empty string, so no rules –
	 * ours included – are ever written. Same reasoning as above: do not flush on every admin page load.
	 *
	 * @covers ::maybe_flush_rewrite_rules
	 */
	public function test_does_not_flush_with_plain_permalinks(): void {

		$subdir = uniqid( 'rewriteplain' );

		$this->given_htaccess_is_writable();

		update_option( 'permalink_structure', '' );

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;
		$wp_rewrite->init();

		$htaccess_file = (string) tempnam( sys_get_temp_dir(), 'htaccess' );

		$sut = $this->get_sut( $htaccess_file, $subdir );
		$sut->register_rewrite_rule();

		$this->assertFalse( $sut->did_flush );

		unlink( $htaccess_file );
	}
}
