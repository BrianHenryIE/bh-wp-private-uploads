<?php
/**
 * Define constants that PhpStan cannot find.
 *
 * @see https://phpstan.org/user-guide/discovering-symbols#global-constants
 *
 * @package     brianhenryie/bh-wp-private-uploads
 */


define( 'WP_CONTENT_DIR', __DIR__ . '/wp-content' );
define( 'WP_PLUGIN_DIR', __DIR__ . '/wp-content/plugins' );

define( 'WP_CONTENT_URL', 'http://localhost:8080/bh-wp-private-uploads' );
