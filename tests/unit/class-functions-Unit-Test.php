<?php
/**
 * Tests for the plugin's `functions.php`.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads_Test_Plugin;

use Codeception\Test\Unit;

/**
 * @see includes/functions.php
 */
class Functions_Unit_Test extends Unit {

	/**
	 * Test get_plugin_basename() finds the correct plugin basename from a slug.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\get_plugin_basename
	 */
	public function test_get_plugin_basename_finds_plugin(): void {
		$plugins = array(
			'my-plugin/my-plugin.php'  => array(
				'Name' => 'My Plugin',
			),
			'another-plugin/index.php' => array(
				'Name' => 'Another Plugin',
			),
		);

		$result = \BrianHenryIE\WP_Private_Uploads\get_plugin_basename( $plugins, 'my-plugin' );

		$this->assertEquals( 'my-plugin/my-plugin.php', $result );
	}

	/**
	 * Test get_plugin_basename() returns null when plugin is not found.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\get_plugin_basename
	 */
	public function test_get_plugin_basename_returns_null_when_not_found(): void {
		$plugins = array(
			'my-plugin/my-plugin.php' => array(
				'Name' => 'My Plugin',
			),
		);

		$result = \BrianHenryIE\WP_Private_Uploads\get_plugin_basename( $plugins, 'nonexistent-plugin' );

		$this->assertNull( $result );
	}

	/**
	 * Test get_plugin_basename() handles plugins with different main file names.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\get_plugin_basename
	 */
	public function test_get_plugin_basename_with_different_main_file(): void {
		$plugins = array(
			'my-plugin/index.php' => array(
				'Name' => 'My Plugin',
			),
		);

		$result = \BrianHenryIE\WP_Private_Uploads\get_plugin_basename( $plugins, 'my-plugin' );

		$this->assertEquals( 'my-plugin/index.php', $result );
	}

	/**
	 * Test str_underscores_to_hyphens() converts underscores to hyphens.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens
	 */
	public function test_str_underscores_to_hyphens(): void {
		$input    = 'my_plugin_name';
		$expected = 'my-plugin-name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_underscores_to_hyphens() handles strings without underscores.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens
	 */
	public function test_str_underscores_to_hyphens_no_underscores(): void {
		$input    = 'mypluginname';
		$expected = 'mypluginname';

		$result = \BrianHenryIE\WP_Private_Uploads\str_underscores_to_hyphens( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_underscores() converts hyphens to underscores.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_underscores
	 */
	public function test_str_hyphens_to_underscores(): void {
		$input    = 'my-plugin-name';
		$expected = 'my_plugin_name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_underscores( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_underscores() handles strings without hyphens.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_underscores
	 */
	public function test_str_hyphens_to_underscores_no_hyphens(): void {
		$input    = 'mypluginname';
		$expected = 'mypluginname';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_underscores( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_underscores_to_title_case() converts underscores to title case.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case
	 */
	public function test_str_underscores_to_title_case(): void {
		$input    = 'my_plugin_name';
		$expected = 'My Plugin Name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_underscores_to_title_case() handles single word.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case
	 */
	public function test_str_underscores_to_title_case_single_word(): void {
		$input    = 'plugin';
		$expected = 'Plugin';

		$result = \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_underscores_to_title_case() handles multiple underscores.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case
	 */
	public function test_str_underscores_to_title_case_multiple_underscores(): void {
		$input    = 'my__plugin__name';
		$expected = 'My  Plugin  Name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_underscores_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_title_case() converts hyphens to title case.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case
	 */
	public function test_str_hyphens_to_title_case(): void {
		$input    = 'my-plugin-name';
		$expected = 'My Plugin Name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_title_case() handles single word.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case
	 */
	public function test_str_hyphens_to_title_case_single_word(): void {
		$input    = 'plugin';
		$expected = 'Plugin';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_title_case() does not produce perfect title case for articles.
	 *
	 * As noted in the docblock, this function does not do 100% perfect title case.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case
	 */
	public function test_str_hyphens_to_title_case_with_articles(): void {
		$input    = 'masters-of-the-universe';
		$expected = 'Masters Of The Universe';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_title_case() handles multiple consecutive hyphens.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case
	 */
	public function test_str_hyphens_to_title_case_multiple_hyphens(): void {
		$input    = 'my--plugin--name';
		$expected = 'My  Plugin  Name';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test str_hyphens_to_title_case() handles empty string.
	 *
	 * @covers \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case
	 */
	public function test_str_hyphens_to_title_case_empty_string(): void {
		$input    = '';
		$expected = '';

		$result = \BrianHenryIE\WP_Private_Uploads\str_hyphens_to_title_case( $input );

		$this->assertEquals( $expected, $result );
	}
}
