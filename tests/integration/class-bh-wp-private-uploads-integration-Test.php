<?php
/**
 * Tests for BH_WP_Private_Uploads main setup class. Tests the actions are correctly added.
 *
 * @package BH_WP_Private_Uploads
 * @author  BrianHenryIE <BrianHenryIE@gmail.com>
 */

namespace BrianHenryIE\WP_Private_Uploads;

use BrianHenryIE\WP_Private_Uploads\Admin\Admin_Notices;
use WP_Hook;

/**
 * Class Develop_Test
 */
class BH_WP_Private_Uploads_Integration_Test extends WPUnit_Testcase {

	/**
	 * Verify admin_enqueue_scripts action is correctly added for styles, at priority 10.
	 */
	public function test_action_admin_enqueue_scripts_styles(): void {

		$action_name       = 'admin_init';
		$expected_priority = 9;
		$class_type        = Admin_Notices::class;
		$method_name       = 'admin_notices';

		$function_is_hooked = $this->is_function_hooked_on_action( $class_type, $method_name, $action_name, $expected_priority );

		$this->assertNotFalse( $function_is_hooked );
	}

	/**
	 * Verify admin_enqueue_scripts action is added for scripts, at priority 10.
	 */
	public function test_action_admin_enqueue_scripts_scripts(): void {

		$action_name       = 'admin_notices';
		$expected_priority = 10;
		$class_type        = Admin_Notices::class;
		$method_name       = 'the_notices';

		$function_is_hooked = $this->is_function_hooked_on_action( $class_type, $method_name, $action_name, $expected_priority );

		$this->assertNotFalse( $function_is_hooked );
	}

	protected function is_function_hooked_on_action( string $class_type, string $method_name, string $action_name, int $expected_priority = 10 ): bool {

		/** @var array<string,WP_Hook> $wp_filter */
		global $wp_filter;

		$this->assertArrayHasKey( $action_name, $wp_filter, "$method_name definitely not hooked to $action_name" );

		/** @var WP_Hook $actions_hooked */
		$actions_hooked = $wp_filter[ $action_name ];

		$this->assertArrayHasKey( $expected_priority, $actions_hooked, "$method_name definitely not hooked to $action_name priority $expected_priority" );

		/** @var array<string, array{function:string|array{0:class-string,1:string}|array{0:object,1:string},accepted_args:int}> $callbacks */
		$callbacks = $actions_hooked->callbacks[ $expected_priority ];

		$hooked_method = null;
		foreach ( $callbacks as $action ) {
			$action_function = $action['function'];
			if ( is_array( $action_function ) ) {
				if ( $action_function[0] instanceof $class_type ) {
					if ( $method_name === $action_function[1] ) {
						$hooked_method = $action_function[1];
						break;
					}
				}
			}
		}

		$this->assertNotNull( $hooked_method, "No methods on an instance of $class_type hooked to $action_name" );

		$this->assertEquals( $method_name, $hooked_method, "Unexpected method name for $class_type class hooked to $action_name" );

		return true;
	}
}
