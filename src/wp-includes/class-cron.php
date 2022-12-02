<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

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

		$cron_hook = "private_uploads_check_url_{$this->settings->get_plugin_slug()}";

		if ( false !== wp_get_scheduled_event( $cron_hook ) ) {
			return;
		}

		wp_schedule_event( time(), 'hourly', $cron_hook );

		$this->logger->info( "Registered the `{$cron_hook}` cron job." );
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
}
