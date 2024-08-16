<?php
/**
 * Not intended to be public functions. Do nor rely on this API.
 */

namespace BrianHenryIE\WP_Private_Uploads;

function get_plugin_name_from_slug( string $plugin_slug ): string {

	require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';

	$plugins         = get_plugins();
	$plugin_basename = get_plugin_basename( $plugins, $plugin_slug );
	$plugin_name     = is_null( $plugin_basename )
		? str_hyphens_to_title_case( $plugin_slug )
		: $plugins[ $plugin_basename ]['Name'];

	return $plugin_name;
}

function get_plugin_basename( array $plugins, string $plugin_slug ): ?string {

	foreach ( $plugins as $plugin_basename => $plugin_data ) {
		if ( explode( '/', $plugin_basename )[0] === $plugin_slug ) {
			return $plugin_basename;
		}
	}

	return null;
}

function str_underscores_to_hyphens( string $string ): string {
	return str_replace( '_', '-', $string );
}

function str_hyphens_to_underscores( string $string ): string {
	return str_replace( '-', '_', $string );
}

function str_underscores_to_title_case( string $string ): string {
	return ucwords( str_replace( '_', ' ', $string ) );
}

function str_hyphens_to_title_case( string $string ): string {
	return ucwords( str_replace( '-', ' ', $string ) );
}
