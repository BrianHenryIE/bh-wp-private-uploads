<?php
/**
 * Tests the CLI class using the real WP-CLI framework classes (which are composer-autoloadable).
 *
 * `WP_CLI::error()` throws a catchable `ExitException` instead of calling `exit()` when the private
 * `WP_CLI::$capture_exit` flag is set — the same mechanism `WP_CLI::runcommand()` uses internally.
 * Output is captured with the `WP_CLI\Loggers\Execution` logger.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API\File_Upload_Result;
use BrianHenryIE\WP_Private_Uploads\API\File_Upload_With_Post_Result;
use BrianHenryIE\WP_Private_Uploads\API\Private_Uploads_Exception;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI\Loggers\Execution;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\WP_Includes\CLI
 */
class CLI_Unit_Test extends Unit_Testcase {

	protected Execution $cli_logger;

	/**
	 * The logger that was set before the test, to be restored after. Initially null.
	 */
	protected ?object $previous_logger = null;

	protected bool $ob_ended = false;

	protected function setUp(): void {
		parent::setUp();

		// `WP_CLI\Utils` and `WP_CLI\Dispatcher` functions are not composer-autoloaded (only WP_CLI classes are).
		if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
			require_once codecept_root_dir( 'vendor-wp-cli/wp-cli/wp-cli/php/utils.php' );
		}
		if ( ! function_exists( 'WP_CLI\Dispatcher\get_path' ) ) {
			require_once codecept_root_dir( 'vendor-wp-cli/wp-cli/wp-cli/php/dispatcher.php' );
		}

		// Recent WP-CLI registers its built-in output formats (table/json/csv/yaml/…) during runner
		// bootstrap, which does not run here. Register them so `format_items()` recognises `--format`.
		\WP_CLI\Formatter::register_builtin_formats();

		if ( ! class_exists( \WP_Error::class ) ) {
			require_once codecept_root_dir( 'vendor/wordpress/wordpress/src/wp-includes/class-wp-error.php' );
		}

		// NB: returns null before any logger has been set, despite the docblock.
		$this->previous_logger = WP_CLI::get_logger();
		$this->cli_logger      = new Execution();
		$this->cli_logger->ob_start();
		$this->ob_ended = false;
		WP_CLI::set_logger( $this->cli_logger );

		$this->set_capture_exit( true );

		// `Loggers\Base::debug()` reads `WP_CLI::get_runner()->config['debug']`, which a bare
		// (non-bootstrapped) Runner does not have — seed it to avoid an undefined-array-key warning.
		$runner = WP_CLI::get_runner();
		if ( ! $runner instanceof \WP_CLI\Runner ) {
			self::fail( 'Expected WP_CLI::get_runner() to return a Runner instance.' );
		}
		$config_property = new \ReflectionProperty( $runner, 'config' );
		$config_property->setAccessible( true );
		$existing_config = $config_property->getValue( $runner );
		$config_property->setValue(
			$runner,
			array_merge( is_array( $existing_config ) ? $existing_config : array(), array( 'debug' => false ) )
		);
	}

	protected function tearDown(): void {
		if ( ! $this->ob_ended ) {
			$this->cli_logger->ob_end();
		}
		WP_CLI::set_logger( $this->previous_logger ?? new \WP_CLI\Loggers\Quiet() );
		$this->set_capture_exit( false );

		parent::tearDown();
	}

	/**
	 * `WP_CLI::$capture_exit` is private with no setter; `WP_CLI::runcommand()` sets it the same way
	 * so `WP_CLI::error()` throws `ExitException` rather than `exit()`.
	 *
	 * @param bool $value Whether to throw instead of exit.
	 */
	protected function set_capture_exit( bool $value ): void {
		$property = new \ReflectionProperty( WP_CLI::class, 'capture_exit' );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/**
	 * End output buffering and return everything written to STDOUT (including `echo` from `format_items()`).
	 */
	protected function get_stdout(): string {
		$this->cli_logger->ob_end();
		$this->ob_ended = true;
		$stdout         = $this->cli_logger->stdout;
		return is_string( $stdout ) ? $stdout : '';
	}

	/**
	 * Everything written to STDERR, e.g. by `WP_CLI::error()`. (The `Execution` logger's properties are untyped.)
	 */
	protected function get_stderr(): string {
		$stderr = $this->cli_logger->stderr;
		return is_string( $stderr ) ? $stderr : '';
	}

	/**
	 * `WP_CLI::add_command()` bails unless the WP_CLI constant is defined.
	 * Nothing in `includes/` branches on these constants, so defining them does not affect other tests.
	 */
	protected function define_wp_cli_constants(): void {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			define( 'WP_CLI_ROOT', codecept_root_dir( 'vendor-wp-cli/wp-cli/wp-cli' ) );
		}
	}

	/**
	 * @covers ::register_commands
	 * @covers ::__construct
	 */
	public function test_register_commands_registers_download(): void {

		$this->define_wp_cli_constants();

		// Unique per test: `WP_CLI::get_root_command()` is a process-wide static singleton.
		$cli_base = uniqid( 'base' );

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_cli_base' => $cli_base,
			)
		);

		$sut = new CLI( $api, $settings, $this->logger );

		// "{base} download" would be deferred if the parent command does not exist yet,
		// so register an empty container for the base first (WP-CLI's Runner does the
		// equivalent for deferred additions at bootstrap).
		WP_CLI::add_command( $cli_base, new class() {} );

		$sut->register_commands();

		// `find_subcommand()` consumes one element of `$path` per call, so traverse the chain.
		$path       = array( $cli_base, 'download' );
		$subcommand = WP_CLI::get_root_command();
		while ( ! empty( $path ) && false !== $subcommand ) {
			$subcommand = $subcommand->find_subcommand( $path );
		}

		$this->assertNotFalse( $subcommand );
		$this->assertSame( 'download', $subcommand->get_name() );
	}

	/**
	 * @covers ::register_commands
	 */
	public function test_register_commands_does_nothing_when_cli_base_null(): void {

		$this->define_wp_cli_constants();

		$api      = $this->makeEmpty( API_Interface::class );
		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_cli_base' => Expected::once( null ),
			)
		);

		$sut = new CLI( $api, $settings, $this->logger );

		$deferred_before = count( WP_CLI::get_deferred_additions() );

		$sut->register_commands();

		$this->assertCount( $deferred_before, WP_CLI::get_deferred_additions() );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_invalid_url_errors(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads' => Expected::never(),
				'download_remote_file_to_private_uploads_and_create_post' => Expected::never(),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->with( 'not-a-valid-url' )
				->andReturn( 'http://not-a-valid-url' );

		try {
			$sut->download_url( array( 'not-a-valid-url' ), array() );
			$this->fail( 'Expected ExitException' );
		} catch ( ExitException $exception ) {
			$this->assertSame( 1, $exception->getCode() );
		}

		$this->assertStringContainsString( 'did not filter cleanly', $this->get_stderr() );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_invalid_post_author_errors(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads_and_create_post' => Expected::never(),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->andReturnUsing( fn( string $url ): string => $url );

		try {
			$sut->download_url(
				array( 'https://example.org/file.pdf' ),
				array( 'post_author' => 'not-a-number' )
			);
			$this->fail( 'Expected ExitException' );
		} catch ( ExitException $exception ) {
			$this->assertSame( 1, $exception->getCode() );
		}

		$this->assertStringContainsString( 'Invalid --post_author', $this->get_stderr() );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_invalid_post_parent_errors(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads_and_create_post' => Expected::never(),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->andReturnUsing( fn( string $url ): string => $url );

		try {
			$sut->download_url(
				array( 'https://example.org/file.pdf' ),
				array( 'post_parent' => 'not-a-number' )
			);
			$this->fail( 'Expected ExitException' );
		} catch ( ExitException $exception ) {
			$this->assertSame( 1, $exception->getCode() );
		}

		$this->assertStringContainsString( 'Invalid --post_parent', $this->get_stderr() );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_without_create_post_delegates_to_plain_download(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads' => Expected::once(
					fn() => new File_Upload_Result(
						file: '/path/to/uploads/private/2026/06/file.pdf',
						url: 'https://example.org/wp-content/uploads/private/2026/06/file.pdf',
						type: 'application/pdf',
					)
				),
				'download_remote_file_to_private_uploads_and_create_post' => Expected::never(),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->andReturnUsing( fn( string $url ): string => $url );
		WP_Mock::userFunction( 'wp_safe_remote_head' )
				->once()
				->andReturn( new \WP_Error() );
		WP_Mock::userFunction( 'is_wp_error' )
				->once()
				->andReturnTrue();

		$sut->download_url(
			array( 'https://example.org/file.pdf' ),
			array( 'format' => 'json' )
		);

		$stdout = $this->get_stdout();

		$this->assertStringContainsString( '"file":', $stdout );
		$this->assertStringContainsString( 'application\/pdf', $stdout );
		$this->assertStringNotContainsString( 'post_id', $stdout );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_create_post_delegates_and_outputs_post_id(): void {

		$api = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads' => Expected::never(),
				'download_remote_file_to_private_uploads_and_create_post' => Expected::once(
					function ( string $file_url, ?string $filename = null, ?int $post_author_id = null, ?int $post_parent_id = null ): File_Upload_With_Post_Result {
						$this->assertSame( 'https://example.org/file.pdf', $file_url );
						$this->assertSame( 2, $post_author_id );
						$this->assertSame( 123, $post_parent_id );

						return new File_Upload_With_Post_Result(
							file: '/path/to/uploads/private/2026/06/file.pdf',
							url: 'https://example.org/wp-content/uploads/private/2026/06/file.pdf',
							type: 'application/pdf',
							post_id: 456,
						);
					}
				),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->andReturnUsing( fn( string $url ): string => $url );
		WP_Mock::userFunction( 'wp_safe_remote_head' )
				->once()
				->andReturn( new \WP_Error() );
		WP_Mock::userFunction( 'is_wp_error' )
				->once()
				->andReturnTrue();

		$sut->download_url(
			array( 'https://example.org/file.pdf' ),
			array(
				'create-post' => true,
				'post_author' => '2',
				'post_parent' => '123',
				'format'      => 'json',
			)
		);

		$this->assertStringContainsString( '"post_id":456', $this->get_stdout() );
	}

	/**
	 * @covers ::download_url
	 */
	public function test_download_url_api_exception_becomes_cli_error(): void {

		$api      = $this->makeEmpty(
			API_Interface::class,
			array(
				'download_remote_file_to_private_uploads' => Expected::once(
					function (): File_Upload_Result {
						throw new Private_Uploads_Exception( 'the reason the download failed' );
					}
				),
			)
		);
		$settings = $this->makeEmpty( Private_Uploads_Settings_Interface::class );

		$sut = new CLI( $api, $settings, $this->logger );

		WP_Mock::userFunction( 'sanitize_url' )
				->once()
				->andReturnUsing( fn( string $url ): string => $url );
		WP_Mock::userFunction( 'wp_safe_remote_head' )
				->once()
				->andReturn( new \WP_Error() );
		WP_Mock::userFunction( 'is_wp_error' )
				->once()
				->andReturnTrue();

		try {
			$sut->download_url(
				array( 'https://example.org/file.pdf' ),
				array( 'format' => 'json' )
			);
			$this->fail( 'Expected ExitException' );
		} catch ( ExitException $exception ) {
			$this->assertSame( 1, $exception->getCode() );
		}

		$this->assertStringContainsString( 'the reason the download failed', $this->get_stderr() );
	}
}
