<?php
/**
 * WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat -c behat.php
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DVDoug\Behat\CodeCoverage\Extension;

/**
 * @see \WP_CLI\Tests\Context\FeatureContext::get_vendor_dir()
 * @see \BrianHenryIE\WP_Private_Uploads\FeatureContext::prepare()
 */
require_once __DIR__ . '/vendor-wp-cli/autoload.php';

putenv('WP_CLI_BIN_DIR=' . __DIR__ . 'vendor-wp-cli/wp-cli/wp-cli/bin');

define( 'WP_CLI_ROOT', __DIR__ . '/vendor-wp-cli/wp-cli/wp-cli' );

require_once WP_CLI_ROOT . '/php/utils.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/Process.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/ProcessRun.php';

return ( new Config() )
	->withProfile(
		( new Profile( 'default' ) )
		->withSuite(
			( new Suite( 'default' ) )
			->withContexts( \BrianHenryIE\WP_Private_Uploads\FeatureContext::class )
			->withPaths( 'tests/features' )
		)
	);
// ->withProfile((new Profile('coverage'))
// ->withExtension(new Extension(Extension::class, [
// 'filter' => [
// 'include' => [
// 'directories' => [
// 'includes' => null,
// ],
// ],
// 'exclude' => [
// 'directories' => [
// 'vendor' => null,
// 'tests' => null,
// ],
// ],
// ],
// 'reports' => [
// 'clover' => [
// 'target' => 'tests/_output/behat-clover.xml',
// ],
// 'html' => [
// 'target' => 'tests/_output/behat-html',
// 'lowUpperBound' => 50,
// 'highLowerBound' => 90,
// ],
// 'text' => [
// 'showColors' => true,
// 'showOnlySummary' => false,
// ],
// 'php' => [
// 'target' => 'tests/_output/behat-coverage.php',
// ],
// ],
// ]))
// ->withSuite((new Suite('default'))
// ->withPaths('tests/features')))
