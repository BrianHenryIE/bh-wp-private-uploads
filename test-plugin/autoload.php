<?php
/**
 * Loads all required classes
 *
 * Uses classmap, PSR4 & wp-namespace-autoloader.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           BH_WP_Private_Uploads
 *
 * @see https://github.com/pablo-sg-pacheco/wp-namespace-autoloader/
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use Alley_Interactive\Autoloader\Autoloader;


require_once __DIR__ . '/../vendor/autoload.php';

Autoloader::generate(
	__NAMESPACE__,
	__DIR__,
)->register();
