<?php
/**
 * Behat feature context for WP-CLI tests.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use WP_CLI;

/**
 * Extends WP-CLI's FeatureContext to add custom step definitions for testing
 * the private uploads CLI commands.
 */
class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	/**
	 * Install a plugin by creating symlinks to the project directories.
	 *
	 * This mimics the .wp-env.json mappings by symlinking:
	 * - development-plugin/ -> wp-content/plugins/development-plugin/
	 * - includes/ -> wp-content/plugins/includes/
	 * - vendor/ -> wp-content/plugins/vendor/
	 * - assets/ -> wp-content/plugins/assets/
	 *
	 * @Given /^a plugin located at ([^\s]+)$/
	 *
	 * @see \AmpProject\AmpWP\Tests\Behat\FeatureContext::given_a_wp_installation_with_the_amp_plugin()
	 * @see https://github.com/ampproject/amp-wp/blob/d4200c4b26446541282aef3c3cc2acd3b93674d7/tests/php/src/Behat/FeatureContext.php#L79-L93
	 *
	 * @param string $path Path to the plugin directory or file, relative to project root.
	 */
	public function given_a_plugin_located_at( string $path ): void {

		$project_dir = realpath( self::get_vendor_dir() . '/../' );

		// path could be relative, the directory, or the plugin file.
		switch ( true ) {
			case is_dir( $path ):
				$source_dir = realpath( $path );
				break;
			case is_file( $path ):
				$source_dir = realpath( dirname( $path ) );
				break;
			case is_dir( $project_dir . '/' . $path ):
				$source_dir = $project_dir . '/' . $path;
				break;
			case is_file( $project_dir . '/' . $path ):
				$source_dir = $project_dir . '/' . dirname( $path );
				break;
			default:
				WP_CLI::error( "Path not found: {$path}" );
				return;
		}

		$plugin_slug = basename( $source_dir );

		// Symlink the source folder into the WP folder as a plugin.
		$wp_plugins_dir = $this->variables['RUN_DIR'] . '/wp-content/plugins';
		$this->proc( "ln -sf {$source_dir} {$wp_plugins_dir}/{$plugin_slug}" )->run_check();
	}

	/**
	 * Install the development plugin with all its dependencies.
	 *
	 * This creates symlinks mimicking the .wp-env.json mappings so the development
	 * plugin can find its dependencies (includes/, vendor/, assets/).
	 *
	 * @Given /^the development plugin is installed$/
	 */
	public function given_the_development_plugin_is_installed(): void {

		$project_dir    = realpath( self::get_vendor_dir() . '/../' );
		$wp_plugins_dir = $this->variables['RUN_DIR'] . '/wp-content/plugins';

		// Create symlinks matching .wp-env.json mappings.
		// The development plugin expects these at wp-content/plugins/ level.
		$mappings = array(
			'development-plugin' => 'development-plugin',
			'includes'           => 'includes',
			'vendor'             => 'vendor',
			'assets'             => 'assets',
		);

		foreach ( $mappings as $link_name => $source_path ) {
			$source = $project_dir . '/' . $source_path;
			$link   = $wp_plugins_dir . '/' . $link_name;

			if ( is_dir( $source ) || is_file( $source ) ) {
				$this->proc( "ln -sf {$source} {$link}" )->run_check();
			}
		}

		// Activate the development plugin.
		$this->proc( 'wp plugin activate development-plugin' )->run_check();
	}
}
