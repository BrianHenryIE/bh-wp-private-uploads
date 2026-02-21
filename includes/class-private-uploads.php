<?php
/**
 * Private Uploads – library for access control for your WordPress plugin's wp-content/uploads files.
 *
 * This is a singleton class for convenience. Instantiate the API class directly for more control.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\API\API;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Makes a singleton available at `Private_Uploads::instance()`.
 */
class Private_Uploads extends API implements API_Interface {
	use LoggerAwareTrait;

	/** @var ?Private_Uploads The singleton.  */
	protected static ?Private_Uploads $instance = null;

	/**
	 * Get the singleton instance for this plugin.
	 *
	 * Use of this is optional – the API class can be instantiated directly.
	 *
	 * @param ?Private_Uploads_Settings_Interface $settings The settings, which must be provided on first instantiation, but not on subsequent uses of the singleton.
	 * @param ?LoggerInterface                    $logger Optional PSR logger. NullLogger will be used if omitted.
	 *
	 * @return Private_Uploads Which is really just API_Interface.
	 * @throws Exception When settings are not provided.
	 */
	public static function instance( ?Private_Uploads_Settings_Interface $settings = null, ?LoggerInterface $logger = null ): Private_Uploads {

		if ( ! is_null( self::$instance ) ) {

			return self::$instance;
		}

		if ( ! is_null( $settings ) ) {

			$logger ??= new NullLogger();

			self::$instance = new self( $settings, $logger );

			new BH_WP_Private_Uploads_Hooks( self::$instance, $settings, $logger );

			return self::$instance;
		}

		throw new Exception( 'Settings must be provided on first use.' );
	}
}
