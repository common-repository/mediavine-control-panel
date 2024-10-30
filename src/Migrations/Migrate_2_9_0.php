<?php

namespace Mediavine\MCP\Migrations;

use Mediavine\MCP\Option;

/**
 * Perform updates and migration of data/schema for version 2.9.0
 */
class Migrate_2_9_0 extends Migrate {

	/**
	 * Performs the tasks required to move to MCP 2.9.0.
	 */
	public function run_migration() {
		$option = Option::get_instance();

		// Update all previous `MVCP_` option keys to `mcp_`.
		$string_options = array(
			'site_id',
		);

		foreach ( $string_options as $option_key ) {
			$db_value = get_option( 'MVCP_' . $option_key );
			// Skip if the value is not found in the database.
			if ( false === $db_value ) {
				continue;
			}

			$option->update_option( $option_key, $db_value );
			delete_option( 'MVCP_' . $option_key );
		}

		$bool_options = array(
			'include_script_wrapper',
			'disable_admin_ads',
			'has_loaded_before',
			'video_sitemap_enabled',
			'enable_forced_ssl',
			'block_mixed_content',
			'enable_web_story_ads',
			'ads_txt_write_forced',
		);

		foreach ( $bool_options as $option_key ) {
			$db_value = get_option( 'MVCP_' . $option_key );
			// Skip if the value is not found in the database.
			if ( false === $db_value ) {
				continue;
			}

			// Convert `true` values to true (boolean).
			if ( 'true' === $db_value ) {
				$db_value = true;
			}

			// Convert `false` values to false (boolean).
			if ( 'false' === $db_value ) {
				$db_value = false;
			}

			$option->update_option( $option_key, $db_value );
			delete_option( 'MVCP_' . $option_key );
		}

		// Update previous options with the correct key pattern, but using an
		// unneeded JSON object notation.
		$json_options = array(
			'launch_mode',
			'mcm_code',
			'mcm_approval',
			'google',
		);

		foreach ( $json_options as $option_key ) {
			$db_value = get_option( 'mcp_' . $option_key );
			// Skip if the value is not found in the database.
			if ( false === $db_value ) {
				continue;
			}

			// Only perform conversion if value is actually a JSON object.
			if ( ! is_string( $db_value ) || strpos( $db_value, '{' ) !== 0 ) {
				continue;
			}

			$json = json_decode( $db_value, true );
			if ( ! is_array( $json ) ) {
				continue;
			}

			$value = '';
			if ( array_key_exists( 'value', $json ) ) {
				$value = $json['value'];
			}

			$option->update_option( $option_key, $value );
			// No need to delete this one as it is using the same key.
		}

		// Update other options using different patterns.
		$odd_balls = array(
			'mv_mcp_adunit_name'              => 'adunit_name',
			'mv_mcp_txt_redirections_allowed' => 'txt_redirections_allowed',
			'mv_mcp_version'                  => 'version',
			'_mv_mcp_adtext_disabled'         => 'adtext_disabled',
		);

		foreach ( $odd_balls as $old_key => $option_key ) {
			$db_value = get_option( $old_key );
			// Skip if the value is not found in the database.
			if ( false === $db_value ) {
				continue;
			}

			$option->update_option( $option_key, $db_value );
			delete_option( $old_key );
		}

		// Delete options we are no longer using.
		$deletions = array(
			'MVCP_analytics_code',
			'MVCP_ad_frequency',
			'MVCP_ad_offset',
			'MVCP_ua_code',
			'MVCP_use_analytics',
			'MVCP_disable_amphtml_link',
			'MVCP_disable_in_content',
			'MVCP_disable_sticky',
			'MVCP_disable_amp_consent',
		);

		foreach ( $deletions as $deletion ) {
			delete_option( $deletion );
		}
	}

}
