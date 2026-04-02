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
use WP_Error;
use function BrianHenryIE\WP_Private_Uploads\str_hyphens_to_underscores;

/**
 * Register jobs with `wp_schedule_event()` and handle the actions they call via `add_action()`.
 */
class Cron {

	use LoggerAwareTrait;

	/**
	 * Cron constructor.
	 *
	 * @param API_Interface                      $api The logger's main functions.
	 * @param Private_Uploads_Settings_Interface $settings The logger settings.
	 * @param LoggerInterface                    $logger The logger itself for logging.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {

		$this->setLogger( $logger );
	}

	/**
	 * E.g. `my_plugin_private_uploads_check_url_file`.
	 *
	 * Every hook / option / transient / etc should start with the plugin name and then `private_uploads`, so
	 * later we can use that for a debug page or uninstall script.
	 */
	public function get_check_url_cron_hook_name(): string {
		return str_hyphens_to_underscores(
			sprintf(
				'%s_private_uploads_check_url_%s',
				$this->settings->get_plugin_slug(),
				$this->settings->get_post_type_name()
			)
		);
	}

	/**
	 * `{plugin_slug}_private_uploads_unsnooze_dismissed_notice`
	 */
	public function get_unsnooze_notice_cron_hook_name(): string {
		return str_hyphens_to_underscores(
			sprintf(
				'%s_private_uploads_unsnooze_dismissed_notice_%s',
				$this->settings->get_plugin_slug(),
				$this->settings->get_post_type_name()
			)
		);
	}

	/**
	 * Schedule an hourly check to ensure the directory is not publicly accessible
	 *
	 * @hooked init
	 */
	public function register_cron_job(): void {

		$cron_hook = $this->get_check_url_cron_hook_name();

		/** @var false|object{hook:string,timestamp:int,schedule:string|false,args:array<mixed>,interval:int} $schedule */
		$schedule = wp_get_scheduled_event( $cron_hook );

		if ( false !== $schedule ) {
			$this->logger->debug(
				'Cron job `{cron_hook}` is already registered.',
				array(
					'cron_hook'       => $cron_hook,
					'scheduled_event' => $schedule,
				)
			);
			return;
		}

		/** @var bool|WP_Error $schedule_event_result */
		$schedule_event_result = wp_schedule_event( time(), 'hourly', $cron_hook, array(), true ); /** @phpstan-ignore varTag.type */

		if ( true === $schedule_event_result ) {
			$this->logger->info( "Registered the `{$cron_hook}` cron job." );
		} else {
			$message = is_wp_error( $schedule_event_result )
				? $schedule_event_result->get_error_message()
				: (string) $schedule_event_result;
			$this->logger->error(
				"Failed to register the `{$cron_hook}` cron job: " . $message,
				array(
					'cron_hook' => $cron_hook,
					'error'     => $schedule_event_result,
				)
			);
		}
	}

	/**
	 * Handle the cron job.
	 *
	 * @hooked {plugin-slug}_private_uploads_check_url_{post_type_name}
	 */
	public function check_is_url_public(): void {
		$action = current_action();
		$this->logger->debug(
			'Executing {action} cron job.',
			array(
				'action' => $action,
			)
		);

		$this->api->check_and_update_is_url_private();
	}

	/**
	 * The wp-trt/admin-notices library dismisses permanently, but the "private uploads is accessible" warning
	 * should not be dismissed forever.
	 *
	 * @see Admin_Notices::on_dismiss()
	 * @see Cron::get_unsnooze_notice_cron_hook_name()
	 * @hooked private_uploads_unsnooze_dismissed_notice_{post_type_name}
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
