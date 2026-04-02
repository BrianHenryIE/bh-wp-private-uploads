<?php

namespace BrianHenryIE\WP_Private_Uploads\API;

use BrianHenryIE\WP_Private_Uploads\API\Media_Request;
use BrianHenryIE\WP_Private_Uploads\API_Interface;
use BrianHenryIE\WP_Private_Uploads\Private_Uploads_Settings_Interface;
use BrianHenryIE\WP_Private_Uploads\Unit_Testcase;
use Codeception\Stub\Expected;
use DateTimeImmutable;
use WP_Mock;

/**
 * @coversDefaultClass \BrianHenryIE\WP_Private_Uploads\API\API
 */
class API_Unit_Test extends Unit_Testcase {

	/**
	 * @covers ::get_last_checked_is_url_private
	 * @covers ::get_is_private_transient_name
	 */
	public function test_get_last_checked_is_url_private_happy_path(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		$happy_transient_value = new Is_Private_Result(
			url: 'success',
			is_private: true,
			http_response_code: 403,
			last_checked: new DateTimeImmutable(),
		);

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturn( $happy_transient_value );

		$result = $sut->get_last_checked_is_url_private();

		$this->assertEquals( 'success', $result?->url );
	}

	/**
	 * @covers ::get_last_checked_is_url_private
	 */
	public function test_get_last_checked_is_url_private_invalid_transient(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturn( 'bad value' );

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnTrue();

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}

	/**
	 * @covers ::get_last_checked_is_url_private
	 */
	public function test_get_last_checked_is_url_private_error_deserializing_transient(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andThrow( new \TypeError( 'Something went wrong' ) );

		WP_Mock::userFunction( 'delete_transient' )
				->once()
				->with( $is_private_transient_name );

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnTrue();

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}


	/**
	 * @covers ::schedule_single_check_is_url_private
	 */
	public function test_get_last_checked_is_url_private_schedules_new_check(): void {

		$settings = $this->makeEmpty(
			Private_Uploads_Settings_Interface::class,
			array(
				'get_plugin_slug'    => 'the-plugin-slug',
				'get_post_type_name' => 'the_post_type_name',
			)
		);
		$logger   = $this->logger;

		$sut = new API( $settings, $logger );

		$is_private_transient_name = 'bh_wp_private_uploads_the_post_type_name_is_private';

		WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( $is_private_transient_name )
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_get_scheduled_event' )
				->once()
				->andReturnFalse();

		WP_Mock::userFunction( 'wp_schedule_single_event' )
				->once()
				->with(
					\WP_Mock\Functions::type( 'int' ),
					'the_plugin_slug_private_uploads_check_url_the_post_type_name'
				);

		$result = $sut->get_last_checked_is_url_private();

		$this->assertNull( $result );
	}
}
