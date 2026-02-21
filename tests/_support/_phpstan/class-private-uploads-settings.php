<?php

namespace BrianHenryIE\WP_Private_Uploads;

class Private_Uploads_Settings_Implementation implements Private_Uploads_Settings_Interface {
	use Private_Uploads_Settings_Trait;

	public function get_plugin_slug(): string
	{
		return 'this-is-just-for-phpstan';
	}
}
