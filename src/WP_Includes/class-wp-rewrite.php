<?php

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

class WP_Rewrite {
	use LoggerAwareTrait;

	protected Private_Uploads_Settings_Interface $settings;

	public function __construct( Private_Uploads_Settings_Interface $settings, LoggerInterface $logger ) {
		$this->setLogger( $logger );
		$this->settings = $settings;
	}

	/**
	 * @hooked init
	 */
	public function register_rewrite_rule(): void {

		$path = WP_CONTENT_DIR . '/uploads/' . $this->settings->get_uploads_subdirectory_name() . '/';

		$relative_path = str_replace( ABSPATH, '', $path );

		// TODO: Maybe this should be `.+` instead of `.*` – then it will only redirect for files and subfolders.
		// i.e. admins get a ~"no such file" message when browsing to the folder rather than a file.
		$regex = "{$relative_path}(.*)$";
		$query = "index.php?{$this->settings->get_plugin_slug()}-private-uploads-file=$1";

		/** @var \WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$wp_rewrite->add_external_rule( $regex, $query );

		// TODO: Check is the rule saved or added each time? If it is saved, log this info message the first time it is saved.

		// TODO: Also delete the transient if this is the first time the rule is added.
	}

}
