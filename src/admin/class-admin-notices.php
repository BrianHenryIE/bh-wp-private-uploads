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
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WPTRT\AdminNotices\Notices;
use function BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens;

class Admin_Notices extends Notices {

	use LoggerAwareTrait;

	protected Private_Uploads_Settings_Interface $settings;

	protected API_Interface $api;

	public function __construct( API_Interface $api, Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->settings = $settings;
		$this->api      = $api;
	}

	/**
	 * @hooked admin_notices
	 */
	public function admin_notices(): void {

		// This _should_ be returning the transient value.
		$is_private_result = $this->api->check_and_update_is_url_private();

		$url        = $is_private_result['url'];
		$is_private = $is_private_result['is_private'];

		if ( false !== $is_private ) {
			// URL is private, no need to display admin notice (and no need to log this fact!).
			return;
		}

		$notice_id = sprintf(
			'%s-private-uploads-url-is-public',
			str_underscores_to_hyphens( $this->settings->get_post_type_name() )
		);

		$title   = '';
		$href    = '<a href="' . esc_url( $url ) . '">' . esc_url( $url ) . '</a>';
		$content = sprintf( __( 'Private uploads directory at %s is publicly accessible.', 'bh-wp-private-uploads' ), $href );
		$content = apply_filters( 'bh_wp_private_uploads_url_is_public_warning_' . $this->settings->get_post_type_name(), $content, $url );

		// ID must be globally unique because it is the css id that will be used.
		$this->add(
			$notice_id,
			$title,   // The title for this notice.
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
	 * @hooked update_option_wptrt_notice_dismissed_<posttype-name>_private_uploads_public_url
	 * @see update_option()
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function on_dismiss( $old_value, $value, string $option ): void {

		$hook = "private_uploads_unsnooze_dismissed_notice_{$this->settings->get_post_type_name()}";

		wp_schedule_single_event( time() + WEEK_IN_SECONDS, $hook );
	}
}
