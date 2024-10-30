<?php
namespace Mediavine\MCP;

/**
 * Handles functionality related to the mv_debug endpoint.
 */
class Debug {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Constructor for initializing state and dependencies.
	 */
	public function __construct() {
		$this->init_plugin_actions();
	}

	/**
	 * Init actions against WP Hooks.
	 */
	public function init_plugin_actions() {
		add_action( 'wp_ajax_mv_debug', array( $this, 'dump_wp_data' ) );
		add_action( 'wp_ajax_nopriv_mv_debug', array( $this, 'dump_wp_data' ) );
	}

	/**
	 * Single call for WP blog info dunp.
	 */
	public function dump_wp_data() {
		$vars = array(
			// blog.
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'wpurl'           => get_bloginfo( 'wpurl' ),
			'url'             => get_bloginfo( 'url' ),
			'language'        => get_bloginfo( 'language' ),
			'charset'         => get_bloginfo( 'charset' ),
			'version'         => get_bloginfo( 'version' ),
			// php environment.
			'php_version'     => PHP_VERSION,
			'php_disabled_fn' => ini_get( 'disable_functions' ),
			'php_disabled_cl' => ini_get( 'disable_classes' ),
		);

		$vars['debug'] = array();

		$theme                                = wp_get_theme();
		$vars['debug']['theme']               = array();
		$vars['debug']['theme']['Name']       = $theme->get( 'Name' );
		$vars['debug']['theme']['ThemeURI']   = $theme->get( 'ThemeURI' );
		$vars['debug']['theme']['Version']    = $theme->get( 'Version' );
		$vars['debug']['theme']['TextDomain'] = $theme->get( 'TextDomain' );
		$vars['debug']['theme']['DomainPath'] = $theme->get( 'DomainPath' );

		$ads_txt_method = AdsTxt::get_instance()->get_ads_txt_method();

		$vars['mcp_settings'] = array(
			'mcp_ads_txt_write_forced'               => Option::get_instance()->get_option( 'ads_txt_write_forced' ),
			'mcp_adunit_name'                        => Option::get_instance()->get_option( 'adunit_name' ),
			'mcp_block_mixed_content'                => Option::get_instance()->get_option( 'block_mixed_content' ),
			'mcp_disable_admin_ads'                  => Option::get_instance()->get_option( 'disable_admin_ads' ),
			'mcp_enable_forced_ssl'                  => Option::get_instance()->get_option( 'enable_forced_ssl' ),
			'mcp_enable_web_story_ads'               => Option::get_instance()->get_option( 'enable_web_story_ads' ),
			'mcp_google'                             => Option::get_instance()->get_option( 'google' ),
			'mcp_has_loaded_before'                  => Option::get_instance()->get_option( 'has_loaded_before' ),
			'mcp_include_script_wrapper'             => Option::get_instance()->get_option( 'include_script_wrapper' ),
			'mcp_launch_mode'                        => Option::get_instance()->get_option( 'launch_mode' ),
			'mcp_mcm_approval'                       => Option::get_instance()->get_option( 'mcm_approval' ),
			'mcp_mcm_code'                           => Option::get_instance()->get_option( 'mcm_code' ),
			'mcp_site_id'                            => Option::get_instance()->get_option( 'site_id' ),
			'mcp_offering_code'                      => Option::get_instance()->get_option( 'offering_code' ),
			'mcp_offering_domain'                    => Option::get_instance()->get_option( 'offering_domain' ),
			'mcp_offering_name'                      => Option::get_instance()->get_option( 'offering_name' ),
			'mcp_seen_launch_success_message'        => Option::get_instance()->get_option( 'seen_launch_success_message' ),
			'mcp_txt_redirections_allowed'           => Option::get_instance()->get_option( 'txt_redirections_allowed' ),
			'mcp_version'                            => Option::get_instance()->get_option( 'version' ),
			'mcp_video_sitemap_enabled'              => Option::get_instance()->get_option( 'video_sitemap_enabled' ),
			'mcp_validate_write_method_task_missing' => Option::get_instance()->get_option( 'validate_write_method_task_missing' ),
			'mcp_validate_write_method_file_missing' => Option::get_instance()->get_option( 'validate_write_method_file_missing' ),
			'mcp_validate_write_method_file_empty'   => Option::get_instance()->get_option( 'validate_write_method_file_empty' ),
			'mcp_validate_redirect_hook_missing'     => Option::get_instance()->get_option( 'validate_redirect_hook_missing' ),
			'ads_txt_method'                         => $ads_txt_method,
		);

		$cron_names = array(
			'get_ad_text_cron_event',
			'mv_mcp_check_mode',
			'mv_mcp_check_mcm',
			'mcp_verify_ads_txt_health_event',
			'mcp_offering_check_event',
		);

		$vars['mcp_cron'] = array();
		foreach ( $cron_names as $cron_name ) {
			$vars['mcp_cron'][ $cron_name ] = wp_get_scheduled_event( $cron_name );
		}

		$vars['debug']['plugins'] = $this->get_installed_plugins();

		$this->respond_json_and_die( $this->array_decode_entities( $vars ) );
	}

	/**
	 * Decode HTML Entities.
	 *
	 * @param array $array entities for html decode.
	 */
	public function array_decode_entities( $array ) {
		$new_array = array();

		foreach ( $array as $key => $string ) {
			if ( is_string( $string ) ) {
				$new_array[ $key ] = html_entity_decode( $string, ENT_QUOTES );
			} else {
				$new_array[ $key ] = $string;
			}
		}

		return $new_array;
	}

	/**
	 * List installed Plugins.
	 */
	public function get_installed_plugins() {
		$plugins             = array();
		$plugins['active']   = array();
		$plugins['inactive'] = array();

		foreach ( get_plugins() as $key => $plugin ) {
			$plugin['path']   = $key;
			$plugin['status'] = is_plugin_active( $key ) ? 'Active' : 'Inactive';

			if ( is_plugin_active( $key ) ) {
				$plugins['active'][] = $plugin;
			} else {
				$plugins['inactive'][] = $plugin;
			}
		}

		return $plugins;
	}

	/**
	 * Format JSON data and respond to request.
	 *
	 * @param array $data data for json encode.
	 */
	public function respond_json_and_die( $data ) {
		try {
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-cache' );
			header( 'Expires: Thu, 01 Dec 1994 16:00:00 GMT' );
			header( 'Connection: close' );

			header( 'Content-Type: application/json' );

			// response body is optional.
			if ( isset( $data ) ) {
				// adapt_json_encode will handle data escape.
				echo wp_json_encode( $data );
			}
		} catch ( \Exception $e ) {
			header( 'Content-Type: text/plain' );
			echo 'Exception in respond_and_die(...): ' . esc_html( $e->getMessage() );
		}

		die();
	}
}
