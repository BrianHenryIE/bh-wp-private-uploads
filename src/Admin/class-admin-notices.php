<?php
/**
 * Show an admin notice when the "private" folder can be accessed publicly.
 * Also when it cannot be accessed by authorized users.
 */

namespace BrianHenryIE\WP_Private_Uploads\Admin;

use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use WPTRT\AdminNotices\Notices;

class Admin_Notices extends Notices {

	use LoggerAwareTrait;

	/** @var Private_Uploads_Settings_Interface  */
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

		$notice_id = $this->settings->get_plugin_slug() . '-private-uploads-public-url';

		if ( $is_private ) {
			// URL is private, no need to display admin notice (and no need to log this fact!).
			return;
		}

		$title   = '';
		$content = apply_filters( 'bh_wp_private_uploads_url_is_public_warning_' . $this->settings->get_plugin_slug(), "Private uploads directory at {$url} is publicly accessible.", $url );

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

		// TODO: On dismiss, add a cron job to delete the dismissal in a week.
		// i.e. you can sleep the warning but not hide it.
		$is_dismissed_option_name = "wptrt_notice_dismissed_{$notice_id}";

	}
}
