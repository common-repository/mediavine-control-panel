<?php

namespace Mediavine\MCP;

/**
 * Handles operations related to saving, managing, and retrieving options for
 * the Mediavine Control Panel.
 */
class Option {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * A list of settings that should be editable via the admin settings form.
	 *
	 * @var string[]
	 */
	protected $admin_form_settings = array(
		'include_script_wrapper',
		'site_id',
		'disable_admin_ads',
		'has_loaded_before',
		'ads_txt_write_forced',
		'video_sitemap_enabled',
		'enable_forced_ssl',
		'block_mixed_content',
		'enable_web_story_ads',
		'enable_gpt_snippet',
	);

	/**
	 * Plugin Option Group.
	 *
	 * @var string
	 */
	public $option_group = 'mcp';

	/**
	 * Plugin Prefix.
	 *
	 * @var string
	 */
	public $setting_prefix = 'mcp_';

	/**
	 * Initialize on instantiation.
	 *
	 * @codeCoverageIgnore
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Perform initialization of hooks.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Gets an option from wp_option DB table.
	 *
	 * @param string $name The option name minus prefix.
	 * @param false  $default_value The default value to return if option is not set yet.
	 *
	 * @return mixed
	 */
	public function get_option( $name, $default_value = false ) {
		$key = self::get_key( $name );
		return get_option( $key, $default_value );
	}

	/**
	 * Gets option and converts it to boolean for cleaner checking.
	 *
	 * Should only be used when the option value is explicitly true or false.
	 * Non-booleans option values are considered true if they are not empty.
	 *
	 * @param string $name The option name minus prefix.
	 * @param false  $default_value The default value to return if option is not set yet.
	 *
	 * @return bool
	 */
	public function get_option_bool( $name, $default_value = false ) {
		$string = $this->get_option( $name, $default_value );
		return ! empty( $string );
	}

	/**
	 * Wraps the WordPress `update_option` to prepend our prefix and perform other logic.
	 *
	 * @param string $name The option name.
	 * @param mixed  $value The option value.
	 * @param null   $autoload Whether or not this option should be autoloaded.
	 */
	public function update_option( $name, $value, $autoload = null ) {
		$key = self::get_key( $name );

		// For some unknown reason, WordPress does not save NEW options to the
		// database when the value is boolean `false`, so we add a workaround to
		// make sure it gets saved.
		if ( false === $value ) {
			$value = '';
		}

		update_option( $key, $value, $autoload );
	}

	/**
	 * Wraps the WordPress `delete_option` to prepend our prefix.
	 *
	 * @param string $name The option name.
	 */
	public function delete_option( $name ) {
		$key = self::get_key( $name );
		delete_option( $key );
	}

	/**
	 * Check if the option name exists in the database at all - regardless of value saved.
	 *
	 * @param string $name The option name.
	 *
	 * @return bool
	 */
	public function exists( $name ) {
		$key   = self::get_key( $name );
		$value = get_option( $key );
		return ( false !== $value );
	}

	/**
	 * Get key name by adding prefix and provided string.
	 *
	 * @param string $setting_name the name of the setting minus prefix.
	 *
	 * @return string
	 */
	public function get_key( $setting_name ) {
		return $this->setting_prefix . $setting_name;
	}

	/**
	 * Load and register admin configuration form settings.
	 *
	 * Only settings/options that need to editable on the WP admin settings form
	 * need to be registered here.
	 */
	public function register_settings() {
		foreach ( $this->admin_form_settings as $key ) {
			register_setting( $this->option_group, $this->setting_prefix . $key );
		}
	}

	/**
	 * Deletes site-specific options back to a fresh install.
	 */
	public function reset_site_specific_options() {
		$this->delete_option( 'txt_redirections_allowed' );
		$this->delete_option( 'txt_redirections_check_in_progress' );
		$this->delete_option( 'adtext_disabled' );
		$this->delete_option( 'ads_txt_write_forced' );
		$this->delete_option( 'adunit_name' );
		$this->delete_option( 'mcm_code' );
		$this->delete_option( 'mcm_approval' );
		$this->delete_option( 'google' );
		$this->delete_option( 'enable_gpt_snippet' );
		$this->delete_option( 'seen_launch_success_message' );
	}
}
