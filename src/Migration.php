<?php

namespace Mediavine\MCP;

use Mediavine\MCP\Migrations\Migrate_2_9_0;
use Mediavine\MCP\Migrations\Migrate_2_10_0;
use Mediavine\MCP\Migrations\Migrate_2_10_3;

/**
 * Handles MCP plugin migrations from version to version.
 */
class Migration {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * A mapping of old option keys to their new names.
	 *
	 * @var string[]
	 */
	protected $old_key_map = array(
		'MVCP_site_id'                    => 'mcp_site_id',
		'MVCP_include_script_wrapper'     => 'mcp_include_script_wrapper',
		'MVCP_disable_admin_ads'          => 'mcp_disable_admin_ads',
		'MVCP_has_loaded_before'          => 'mcp_has_loaded_before',
		'MVCP_video_sitemap_enabled'      => 'mcp_video_sitemap_enabled',
		'MVCP_enable_forced_ssl'          => 'mcp_enable_forced_ssl',
		'MVCP_block_mixed_content'        => 'mcp_block_mixed_content',
		'MVCP_enable_web_story_ads'       => 'mcp_enable_web_story_ads',
		'MVCP_ads_txt_write_forced'       => 'mcp_ads_txt_write_forced',
		'mv_mcp_adunit_name'              => 'mcp_adunit_name',
		'mv_mcp_txt_redirections_allowed' => 'mcp_txt_redirections_allowed',
		'mv_mcp_version'                  => 'mcp_version',
		'_mv_mcp_adtext_disabled'         => 'mcp_adtext_disabled',
	);

	/**
	 * Allows setting a flag to temporarily disable the old option key mapping.
	 *
	 * @var bool
	 */
	protected $skip_old_key_map = false;

	/**
	 * Auto-initialize hooks during class instantiation.
	 *
	 * @codeCoverageIgnore
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Add callback hooks.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		// Add hooks for `get_option` to redirect old keys to new keys.
		foreach ( $this->old_key_map as $old_key => $new_key ) {
			add_filter( 'pre_option_' . $old_key, array( $this, 'get_option_by_old_key' ), 10, 3 );
		}
	}

	/**
	 * Gets the plugin version saved in the database, if any.
	 *
	 * Attempts to fall back to older option keys if new format is not found.
	 *
	 * @return string|false
	 */
	public function get_db_version() {
		$version = Option::get_instance()->get_option( 'version' );

		// Fallback to check old version key just in case new format isn't set in DB.
		if ( false === $version ) {
			if ( ! has_filter( 'pre_option_mv_mcp_version' ) ) {
				return get_option( 'mv_mcp_version' );
			}
			// Temporarily disable redirect filter pre_option_mv_mcp_version.
			global $wp_filter;
			$filters = $wp_filter['pre_option_mv_mcp_version'];
			unset( $wp_filter['pre_option_mv_mcp_version'] );
			$version = get_option( 'mv_mcp_version' );
			// Re-enable filter pre_option_mv_mcp_version.
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_filter['pre_option_mv_mcp_version'] = $filters;
		}

		return $version;
	}

	/**
	 * Check if a migration is needed based on plugin version numbers.
	 *
	 * @return bool
	 */
	public function is_migration_required() {
		$db_version = self::get_db_version();

		// If no db version is set, then this is a new install and won't need any migrations.
		if ( empty( $db_version ) ) {
			return false;
		}

		return version_compare( $db_version, MV_Control_Panel::VERSION, '!=' );
	}

	/**
	 * Run pending migrations, if any, to update the database/schema to the
	 * latest version of MCP.
	 */
	public function migrate_to_latest_version() {
		if ( ! self::is_migration_required() ) {
			return;
		}

		$db_version = self::get_db_version();

		/**
		 * Add a hook so that other plugins/themes can update their functionality based on MCP updates.
		 *
		 * @param string $old_version The version of MCP we are upgrading from.
		 * @param string $new_version The version of MCP we are upgrading to.
		 *
		 * @since 2.9.0
		 */
		do_action( 'mcp_pre_migrate_to_latest_version', $db_version, MV_Control_Panel::VERSION );

		$this->skip_old_key_map = true;

		// @todo: Make this dynamic so we don't check version explicitly but instead check a range of versions.
		if ( version_compare( $db_version, '2.9.0', '<' ) ) {
			$migration = new Migrate_2_9_0();
			$migration->run_migration();
		}

		if ( version_compare( $db_version, '2.10.0', '<' ) ) {
			$migration = new Migrate_2_10_0();
			$migration->run_migration();
		}

		if ( version_compare( $db_version, '2.10.3', '<' ) ) {
			$migration = new Migrate_2_10_3();
			$migration->run_migration();
		}

		// Clear the cache to prevent old option values from persisting.
		wp_cache_flush();
		// Not all cache backends listen to 'flush'.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );

		$this->skip_old_key_map = false;

		// These tasks should be run after every update.

		// Delete values that should be regenerated.
		Option::get_instance()->delete_option( 'adunit_name' );
		Option::get_instance()->delete_option( 'txt_redirections_allowed' );

		// Set default offering data as a failsafe for the upstream check.
		if ( empty( Option::get_instance()->get_option( OfferingCheck::OFFERING_CODE_OPTION_SLUG ) ) ) {
			OfferingCheck::set_default_offering();
		}

		// Check and update offering data from upstream.
		OfferingCheck::get_instance()->update_offering_from_upstream();

		// Schedule event to recheck offering data.
		OfferingCheck::schedule_check_offering_task();

		// Verify current status of ads.txt and perform any steps needed to fix.
		AdsTxt::get_instance()->verify_ads_txt_health();

		if ( Upstream::is_launch_mode_enabled() ) {
			// Site is in launch mode, so ensure all necessary tasks for launch mode are setup.
			Upstream::setup_launch_mode();
		}

		if ( ! Upstream::is_launch_mode_enabled() ) {
			AdsTxt::get_instance()->add_ads_txt_writable_fallback_if_no_redirect();
			AdsTxt::get_instance()->setup_verify_ads_txt_health_task();
		}

		/**
		 * Add a hook so that other plugins/themes can update their functionality based on MCP updates.
		 *
		 * @param string $old_version The version of MCP we are upgrading from.
		 * @param string $new_version The version of MCP we are upgrading to.
		 *
		 * @since 2.9.0
		 */
		do_action( 'mcp_post_migrate_to_latest_version', $db_version, MV_Control_Panel::VERSION );
	}

	/**
	 * Implements filter `pre_option_[option]` to redirect `get_option` calls
	 * for specific keys to their new renamed keys.
	 *
	 * @param mixed  $pre_option The value to return instead of the option value.
	 * @param string $option The option name.
	 * @param mixed  $default The fallback value to return if the option does not exist.
	 *
	 * @return false|mixed
	 */
	public function get_option_by_old_key( $pre_option, $option, $default ) {
		if ( $this->skip_old_key_map ) {
			return false;
		}

		if ( $this->is_migration_required() ) {
			// Don't redirect old keys to new keys if there is a pending migration.
			return false;
		}

		if ( array_key_exists( $option, $this->old_key_map ) ) {
			trigger_error(
				sprintf(
					'Use of option key %1$s is deprecated. Use %2$s instead.',
					esc_attr( $option ),
					esc_attr( $this->old_key_map[ $option ] )
				),
				E_USER_DEPRECATED
			);
			return get_option( $this->old_key_map[ $option ], $default );
		}

		return false;
	}
}
