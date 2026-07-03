<?php
/**
 * Scan composer.lock and add each package into composer-wp-cli.json as a `provides` entry so they are not downloaded twice.
 */

$composer_lock_filepath = __DIR__ . '/../composer.lock';
$composer_wp_cli_filepath = __DIR__ . '/../composer-wp-cli.json';

$lock_array = json_decode(file_get_contents($composer_lock_filepath), true);
$composer_array = json_decode(file_get_contents($composer_wp_cli_filepath), true);

$provide = array();

foreach(array_merge($lock_array['packages'], $lock_array['packages-dev']) as $package) {
 $provide[$package['name']] = $package['version'];
}

$composer_array['provide'] = $provide;

file_put_contents($composer_wp_cli_filepath, json_encode($composer_array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
