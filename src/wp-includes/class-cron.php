<?php
/**
 * Registers and handles a cron job for checking is the private uploads directory correctly inaccessible.
 *
 * It is preferred to run this as a background task because the valid "403" response is sometimes interpreted as an error.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Register jobs with `wp_schedule_event()` and handle the actions they call via `add_action()`.
 */
class Cron {

	use LoggerAwareTrait;

	/**
	 * The logger settings are used to determine which plugin we're working with.
	 *
	 * @see Logger_Settings_Interface::get_plugin_slug()
	 */
	protected Private_Uploads_Settings_Interface $settings;

	/**
	 * The API instance will delete the old logs.
	 *
	 * @see API_Interface::delete_old_logs()
	 */
	protected API_Interface $api;

	/**
	 * Cron constructor.
	 *
	 * @param API_Interface                      $api The logger's main functions.
	 * @param Private_Uploads_Settings_Interface $settings The logger settings.
	 * @param LoggerInterface                    $logger The logger itself for logging.
	 */
	public function __construct( API_Interface $api, Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {

		$this->setLogger( $logger );
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * Schedule a daily cron job to delete old logs, just after midnight.
	 *
	 * Does not schedule the cleanup if it is a WooCommerce logger (since WooCommerce handles that itself).
	 *
	 * @hooked init
	 */
	public function register_cron_job(): void {

		$cron_hook = "private_uploads_check_url_{$this->settings->get_post_type_name()}";

		if ( false === wp_get_scheduled_event( $cron_hook ) ) {
			wp_schedule_event( time(), 'hourly', $cron_hook );
			$this->logger->info( "Registered the `{$cron_hook}` cron job." );
		}
	}

	/**
	 * Handle the cron job.
	 *
	 * @hooked private_uploads_check_url_{plugin-slug}
	 */
	public function check_is_url_public(): void {
		$action = current_action();
		$this->logger->debug( "Executing {$action} cron job." );

		$this->api->check_and_update_is_url_private();
	}

	/**
	 * The wp-trt/admin-notices library dismisses permanently, but the "private uploads is accessible" warning
	 * should not be dismissed forever.
	 *
	 * @see Admin_Notices::on_dismiss()
	 * @hooked <plugin_slug>_unsnooze_dismissed_private_uploads_notice
	 */
	public function unsnooze_dismissed_notice(): void {

		$delete_dismissed_notice_option_name = sprintf(
			'wptrt_notice_dismissed_%s_private_uploads_public_url',
			$this->settings->get_post_type_name()
		);

		// TODO: Move into API and add CLI command.
		delete_option( $delete_dismissed_notice_option_name );
	}
}
