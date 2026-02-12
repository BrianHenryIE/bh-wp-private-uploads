<?php
/**
 * Not intended to be public functions. Do not rely on this API.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

if ( ! function_exists( __NAMESPACE__ . '\\get_plugin_name_from_slug' ) ) {
	function get_plugin_name_from_slug( string $plugin_slug ): string {

		require_once constant( 'ABSPATH' ) . 'wp-admin/includes/plugin.php';

		/** @var array<string,array{Name:string,PluginURI?:string,Version?:string,Description?:string,Author?:string,AuthorURI?:string,TextDomain?:string,DomainPath?:string,Network?:bool,RequiresWP?:string,RequiresPHP?:string,UpdateURI?:string}> $plugins */
		$plugins         = get_plugins();
		$plugin_basename = get_plugin_basename( $plugins, $plugin_slug );
		$plugin_name     = is_null( $plugin_basename )
			? str_hyphens_to_title_case( $plugin_slug )
			: $plugins[ $plugin_basename ]['Name'];

		return $plugin_name;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\get_plugin_basename' ) ) {
	/**
	 * @param array<string,array{Name:string}> $plugins
	 */
	function get_plugin_basename( array $plugins, string $plugin_slug ): ?string {

		foreach ( $plugins as $plugin_basename => $plugin_data ) {
			if ( explode( '/', $plugin_basename )[0] === $plugin_slug ) {
				return $plugin_basename;
			}
		}

		return null;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_underscores_to_hyphens' ) ) {
	function str_underscores_to_hyphens( string $string ): string {
		return str_replace( '_', '-', $string );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_hyphens_to_underscores' ) ) {
	function str_hyphens_to_underscores( string $string ): string {
		return str_replace( '-', '_', $string );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_underscores_to_title_case' ) ) {
	function str_underscores_to_title_case( string $string ): string {
		return ucwords( str_replace( '_', ' ', $string ) );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_hyphens_to_title_case' ) ) {
	/**
	 * Given "a-plugin-slug" returns "A Plugin Slug".
	 *
	 * NB: does not do 100% perfect title case, e.g. "masters-of-the-universe" returns "Masters Of The Universe".
	 *
	 * @param string $kebab_string A kebab-case string.
	 */
	function str_hyphens_to_title_case( string $kebab_string ): string {
		return ucwords( str_replace( '-', ' ', $kebab_string ) );
	}
}
