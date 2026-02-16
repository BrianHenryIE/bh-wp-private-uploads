<?php
/**
 * Not intended to be public functions. Do not rely on this API.
 *
 * @package brianhenryie/bh-wp-private-uploads
 */

namespace BrianHenryIE\WP_Private_Uploads;

if ( ! function_exists( __NAMESPACE__ . '\\get_plugin_name_from_slug' ) ) {
	/**
	 * Given `a-plugin-slug` returns the `Name` header from that plugin directory's main plugin file.
	 *
	 * The `Name` is the only required header in a plugin so will never be missing or empty.
	 *
	 * @param string $plugin_slug The plugin whose name is needed.
	 */
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
	 * Given `a-plugin-slug` finds the full `a-plugin-slug/a-plugin-slug.php` main plugin file for the
	 * `wp-content/plugins/a-plugin-slug` directory.
	 *
	 * @used-by get_plugin_name_from_slug()
	 *
	 * @param array<string,array{Name:string}> $plugins The {@see get_plugins()} array.
	 * @param string                           $plugin_slug The plugin slug (directory) we are searching for.
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
	/**
	 * Given "a_plugin_slug" returns "a-plugin-slug".
	 *
	 * @param string $snake_string A snake-case string.
	 */
	function str_underscores_to_hyphens( string $snake_string ): string {
		return str_replace( '_', '-', $snake_string );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_hyphens_to_underscores' ) ) {
	/**
	 * Given "a-plugin-slug" returns "a_plugin_slug".
	 *
	 * @param string $kebab_string A kebab-case string.
	 */
	function str_hyphens_to_underscores( string $kebab_string ): string {
		return str_replace( '-', '_', $kebab_string );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\\str_underscores_to_title_case' ) ) {
	/**
	 * Given "a_plugin_slug" returns "A Plugin Slug".
	 *
	 * NB: does not do 100% perfect title case, e.g. "masters_of_the_universe" returns "Masters Of The Universe".
	 *
	 * @param string $snake_string A snake-case string.
	 */
	function str_underscores_to_title_case( string $snake_string ): string {
		return ucwords( str_replace( '_', ' ', $snake_string ) );
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
