<?php
/**
 * Show an admin notice when the "private" folder can be accessed publicly.
 * TODO: Also when it cannot be accessed by authorized users.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\WP_Includes\Cron;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WPTRT\AdminNotices\Notices;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

/**
 * Uses the WordPress Themes Team library.
 */
class Admin_Notices extends Notices {

	use LoggerAwareTrait;

	/**
	 * Constructor.
	 *
	 * @param API_Interface                      $api Ask the cache if we should display a notice.
	 * @param Private_Uploads_Settings_Interface $settings Interpolate the correct post_type in output.
	 * @param LoggerInterface                    $logger A PSR logger.
	 */
	public function __construct(
		protected API_Interface $api,
		protected Private_Uploads_Settings_Interface $settings,
		LoggerInterface $logger
	) {
		$this->setLogger( $logger );
	}

	/**
	 * The WPTRT notice id, i.e. the CSS id and – prefixed with `wptrt_notice_dismissed_` – the option
	 * name the dismissal is stored under.
	 *
	 * @see Cron::get_dismissed_notice_option_name()
	 */
	public function get_notice_id(): string {
		return sprintf(
			'%s-private-uploads-url-is-public',
			str_underscores_to_hyphens( $this->settings->get_post_type_name() )
		);
	}

	/**
	 * @hooked admin_notices
	 */
	public function admin_notices(): void {

		// This _should_ be returning the transient value.
		$is_private_result = $this->api->get_last_checked_is_url_private();

		if ( null === $is_private_result ) {
			return;
		}

		$url        = $is_private_result->url;
		$is_private = $is_private_result->is_private;

		if ( false !== $is_private ) {
			// URL is private, no need to display admin notice (and no need to log this fact!).
			return;
		}

		$notice_id = $this->get_notice_id();

		$href = '<a href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>';
		// translators: %s is a HTML link to the directory's URL.
		$content = sprintf( __( 'Private uploads directory at %s is publicly accessible.', 'bh-wp-private-uploads' ), $href );
		$content = apply_filters( 'bh_wp_private_uploads_url_is_public_warning_' . $this->settings->get_post_type_name(), $content, $url );

		if ( ! is_string( $content ) ) {
			$this->logger->warning( 'Filtered message value was not a string' );
			return;
		}

		// ID must be globally unique because it is the css id that will be used.
		$this->add(
			$notice_id,
			'',   // The title for this notice. If this were set it would be a h2 title; we don't want any.
			$content, // The content for this notice.
			array(
				'scope' => 'global',
				'type'  => 'warning',
			)
		);
	}

	/**
	 * On dismiss, add a cron job to delete the dismissal in a week.
	 * I.e. you can sleep the warning that your private files are accessible, but not hide from it forever.
	 *
	 * I.e. when the dismissed option is created, schedule a cron job to delete it in a week.
	 * If the directory is correctly inaccessible, the notice will never appear.
	 *
	 * Registered on both `add_option_{$option}` (fires with `( $option, $value )`) and
	 * `update_option_{$option}` (fires with `( $old_value, $value, $option )`), so the arguments are
	 * ignored and typed loosely to tolerate either signature.
	 *
	 * @see Cron::get_dismissed_notice_option_name()
	 * @see BH_WP_Private_Uploads_Hooks::define_admin_notices_hooks()
	 * @see add_option()
	 * @see update_option()
	 *
	 * @param mixed $arg_1 Either the option name (`add_option`) or the old value (`update_option`).
	 * @param mixed $arg_2 The new option value.
	 * @param mixed $arg_3 The option name (`update_option` only).
	 */
	public function on_dismiss( $arg_1 = null, $arg_2 = null, $arg_3 = null ): void {

		$hook = ( new Cron( $this->api, $this->settings, $this->logger ) )->get_unsnooze_notice_cron_hook_name();

		wp_schedule_single_event( time() + constant( 'WEEK_IN_SECONDS' ), $hook );
	}
}
