<?php
/**
 * Tests for I18n. Tests load_plugin_textdomain.
 *
 * @package BH_WP_Private_Uploads
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads\WP_Includes;

/**
 * Class BH_WP_Private_Uploads_Test
 *
 * @see I18n
 */
class I18n_Integration_Test extends \BrianHenryIE\WP_Private_Uploads\WPUnit_Testcase {

	/**
	 * AFAICT, this will fail until a translation has been added.
	 *
	 * @see load_plugin_textdomain()
	 * @see https://gist.github.com/GaryJones/c8259da3a4501fd0648f19beddce0249
	 */
	public function test_load_plugin_textdomain() {

		$this->markTestSkipped( 'Needs one translation before test might pass.' );

		global $plugin_root_dir;

		$this->assertTrue( file_exists( $plugin_root_dir . '/languages/' ), '/languages/ folder does not exist.' );

		// Seems to fail because there are no translations to load.
		$this->assertTrue( is_textdomain_loaded( 'bh-wp-private-uploads' ), 'i18n text domain not loaded.' );
	}
}
