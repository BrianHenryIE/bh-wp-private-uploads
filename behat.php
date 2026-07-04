<?php
/**
 * WP_CLI_TEST_DBTYPE=sqlite vendor/bin/behat -c behat.php
 */

use Behat\Config\Config;
use Behat\Config\Profile;
use Behat\Config\Suite;
use DVDoug\Behat\CodeCoverage\Extension;

require_once __DIR__ . '/vendor-wp-cli/autoload.php';

define( 'WP_CLI_ROOT', __DIR__ . '/vendor-wp-cli/wp-cli/wp-cli' );

include WP_CLI_ROOT . '/php/utils.php';
$utils_path = WP_CLI_ROOT . '/php/utils.php';

return (new Config())
    ->withProfile((new Profile('default'))
        ->withSuite((new Suite('default'))
			->withContexts(\BrianHenryIE\WP_Private_Uploads\FeatureContext::class)
            ->withPaths('tests/features')))
//    ->withProfile((new Profile('coverage'))
//        ->withExtension(new Extension(Extension::class, [
//            'filter' => [
//                'include' => [
//                    'directories' => [
//                        'includes' => null,
//                    ],
//                ],
//                'exclude' => [
//                    'directories' => [
//                        'vendor' => null,
//                        'tests' => null,
//                    ],
//                ],
//            ],
//            'reports' => [
//                'clover' => [
//                    'target' => 'tests/_output/behat-clover.xml',
//                ],
//                'html' => [
//                    'target' => 'tests/_output/behat-html',
//                    'lowUpperBound' => 50,
//                    'highLowerBound' => 90,
//                ],
//                'text' => [
//                    'showColors' => true,
//                    'showOnlySummary' => false,
//                ],
//                'php' => [
//                    'target' => 'tests/_output/behat-coverage.php',
//                ],
//            ],
//        ]))
//        ->withSuite((new Suite('default'))
//            ->withPaths('tests/features')))
;
