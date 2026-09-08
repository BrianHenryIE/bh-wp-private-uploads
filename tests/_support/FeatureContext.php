<?php
/**
 * Behat feature context for WP-CLI tests.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use RuntimeException;

/**
 * Extends WP-CLI's FeatureContext to add custom step definitions for testing
 * the private uploads CLI commands.
 */
class FeatureContext extends \WP_CLI\Tests\Context\FeatureContext {

	/**
	 * @BeforeSuite
	 *
	 * @see \WP_CLI\Tests\Context\FeatureContext::prepare()
	 * @see \WP_CLI\Tests\Context\FeatureContext::bootstrap_feature_context()
	 */
	public static function prepare( BeforeSuiteScope $scope ): void {

		// No-op to stop the superclass from running.
		// If it does it fails to determine the whack vendor directory we're using here.
	}

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
				throw new RuntimeException( "Path not found: {$path}"  );
		}

		if(false === $source_dir){
			throw new RuntimeException( "Error determining realpath for: {$path}"  );
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

	/**
	 * Short-circuit the is-private HTTP probe so the scenario does not depend on a webserver or the network.
	 *
	 * The WP-CLI test install has no webserver, and its site URL is `https://example.com`, so the
	 * `wp_remote_get()` in `API::check_and_update_is_url_private()` would otherwise go out to the internet.
	 * An mu-plugin answers any request for the site's own uploads URL with a 403, as a correctly
	 * configured webserver would.
	 *
	 * @Given /^the webserver serves the uploads directory as private$/
	 *
	 * @see \BrianHenryIE\WP_Private_Uploads\API\API::check_and_update_is_url_private()
	 */
	public function given_the_webserver_serves_the_uploads_directory_as_private(): void {

		$mu_plugins_dir = $this->variables['RUN_DIR'] . '/wp-content/mu-plugins';

		if ( ! is_dir( $mu_plugins_dir ) ) {
			mkdir( $mu_plugins_dir, 0777, true );
		}

		$mu_plugin = <<<'PHP'
<?php
/**
 * Plugin Name: Behat: uploads directory is private
 */

add_filter(
	'pre_http_request',
	function ( $response, array $args, string $url ) {
		if ( ! str_starts_with( $url, wp_upload_dir()['baseurl'] ) ) {
			return $response;
		}
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => 403,
				'message' => 'Forbidden',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
PHP;

		file_put_contents( $mu_plugins_dir . '/uploads-directory-is-private.php', $mu_plugin );
	}
}
