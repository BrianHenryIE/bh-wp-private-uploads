<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * frontend-facing side of the site and the admin area.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\ColorLogger\ColorLogger;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Assets;
use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use BrianHenryIE\WP_Private_Uploads\Frontend\Serve_Private_File;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Media;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Post_Type;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\WP_Rewrite;
use Codeception\Stub\Expected;
use Codeception\Test\Unit;
use WP_Mock\Matcher\AnyInstance;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\BH_WP_Private_Uploads_Hooks
 */
class BH_WP_Private_Uploads_Hooks_Unit_Test extends Unit {

	protected function setup(): void {
		\WP_Mock::setUp();
	}

	protected function tearDown(): void {
		\WP_Mock::tearDown();
	}

	/**
	 * @covers ::__construct
	 * @covers ::define_api_hooks
	 */
	public function test_define_api_hooks(): void {

		$api = self::makeEmpty( API_Interface::class );

		\WP_Mock::expectActionAdded(
			'init',
			array( $api, 'create_directory' )
		);

		$logger   = new ColorLogger();
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_post_hooks
	 */
	public function test_define_post_hooks(): void {

		\WP_Mock::expectActionAdded(
			'init',
			array( new AnyInstance( Post_Type::class ), 'register_post_type' )
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_admin_notices_hooks
	 */
	public function test_define_admin_notices_hooks(): void {
		\WP_Mock::expectActionAdded(
			'admin_init',
			array( new AnyInstance( Admin_Notices::class ), 'admin_notices' ),
			9
		);
		\WP_Mock::expectActionAdded(
			'admin_notices',
			array( new AnyInstance( Admin_Notices::class ), 'the_notices' )
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_frontend_hooks
	 */
	public function test_define_frontend_hooks(): void {

		\WP_Mock::expectActionAdded(
			'init',
			array( new AnyInstance( Serve_Private_File::class ), 'init' )
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_cron_job_hooks
	 */
	public function test_define_cron_job_hooks(): void {

		\WP_Mock::expectActionAdded(
			'private_uploads_check_url_test-plugin',
			array( new AnyInstance( Cron::class ), 'check_is_url_public' )
		);

		\WP_Mock::expectActionAdded(
			'test-plugin_unsnooze_dismissed_private_uploads_notice',
			array( new AnyInstance( Cron::class ), 'unsnooze_dismissed_notice' )
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug' => Expected::atLeastOnce( 'test-plugin' ),
			)
		);
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_cli_hooks
	 */
	public function test_define_cli_hooks(): void {

		$this->markTestIncomplete( 'Might need WPUnit to test this.' );
	}

	/**
	 * @covers ::define_rewrite_hooks
	 */
	public function test_define_rewrite_hooks(): void {
		\WP_Mock::expectActionAdded(
			'init',
			array( new AnyInstance( WP_Rewrite::class ), 'register_rewrite_rule' )
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}

	/**
	 * @covers ::define_media_library_hooks
	 */
	public function test_define_media_library_hooks(): void {
		\WP_Mock::expectActionAdded(
			'wp_ajax_query-attachments',
			array( new AnyInstance( Media::class ), 'on_query_attachments' ),
			1
		);
		\WP_Mock::expectActionAdded(
			'admin_init',
			array( new AnyInstance( Media::class ), 'on_upload_attachment' ),
			1
		);

		\WP_Mock::expectActionAdded(
			'admin_init',
			array( new AnyInstance( Admin_Assets::class ), 'register_script' ),
			1
		);

		$logger   = new ColorLogger();
		$api      = self::makeEmpty( API_Interface::class );
		$settings = self::makeEmpty( Private_Uploads_Settings_Interface::class );
		new BH_WP_Private_Uploads_Hooks( $api, $settings, $logger );
	}
}
