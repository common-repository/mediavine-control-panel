<?php

namespace Mediavine\MCP;

use Mediavine\MCP\ThirdParty\WebStories;
use Mediavine\MCP\ThirdParty\WPRocket;
use Mediavine\MCP\Video\Video;
use Mediavine\MCP\Video\VideoSitemap;

/**
 * Primary class for MCP.
 *
 * @category     WordPress_Plugin
 * @package      Mediavine Control Panel
 * @author       Mediavine
 * @license      http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link         https://www.mediavine.com
 */
class MV_Control_Panel {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	const VERSION = '2.10.5';

	/**
	 * This gets updated automatically in WriteVersionTask.
	 */
	const DB_VERSION = '2.10.5';

	const PLUGIN_DOMAIN = 'mv_control_panel';

	const PREFIX = '_mv_';

	/**
	 * Holds attributes for the script tag.
	 *
	 * @var array
	 */
	protected $script_attrs = array();


	/**
	 * Constructor for initializing state and dependencies.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		add_filter( 'script_loader_tag', array( $this, 'filter_script_loader' ), 10, 2 );
		$this->init();
	}

	/**
	 * Sets up the plugin and initialize all required functionality.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		$this->init_views();
		$this->load_extensions();

		// Init OfferingCheck.
		OfferingCheck::get_instance()->init();

		// Set hook for checking for launch mode (if the custom event is active).
		Upstream::get_instance()->init();

		// We init Ads_Txt so that it can handle tasks needed leaving launch mode.
		AdsTxt::get_instance()->init();

		// Sites in launch mode should not enable any features but the web wrapper and Dashboard auth.
		if ( ! Upstream::is_launch_mode_enabled() ) {
			VideoSitemap::get_instance()->init();
		}

		// Installation and deactivation hooks.
		register_activation_hook( MCP_PLUGIN_FILE, array( $this, 'primary_plugin_activation' ) );
		add_action( 'plugins_loaded', array( $this, 'check_if_plugin_updated' ), 10, 2 );
		register_deactivation_hook( MCP_PLUGIN_FILE, array( $this, 'plugin_deactivation' ) );

		// Perform tasks necessary after a WP core update.
		add_action( '_core_updated_successfully', array( $this, 'handle_post_core_update' ) );

		// Clear site-specific settings when the site slug is changed.
		add_action( 'update_option_mcp_site_id', array( $this, 'handle_site_id_change' ), 10, 2 );

		// This will also init VideoFeatured and VideoPlaylist indirectly.
		Video::get_instance()->init();
		AdSettings::get_instance()->init();

		$mcp_admin = new AdminInit();
		$mcp_admin->init();

		// Check for third party plugins and apply integration functionality.
		add_action( 'plugins_loaded', array( $this, 'setup_third_party' ) );
	}

	/**
	 * Utility for generation of correct script tag.
	 *
	 * @param string $tag html tag of script output.
	 * @param string $handle wp id of script for enqueue.
	 */
	public function filter_script_loader( $tag, $handle ) {
		if ( array_key_exists( $handle, $this->script_attrs ) ) {
			foreach ( $this->script_attrs[ $handle ] as $attr_name => $value ) {
				if ( '__SINGLE__' === $value ) {
					$tag = str_replace( ' src', " {$attr_name} src", $tag );
				} else {
					$tag = str_replace( ' src', " {$attr_name}=\"{$value}\" src", $tag );
				}
			}
		}

		return $tag;
	}

	/**
	 * Runs at activation of MCP plugin
	 *
	 * @codeCoverageIgnore
	 */
	public function primary_plugin_activation() {
		$this->plugin_activation();
		flush_rewrite_rules();
	}

	/**
	 * Actions to be run when plugin is activated or updated.
	 */
	public function plugin_activation() {
		// Set the version in the DB if it is not already set.
		if ( empty( Migration::get_instance()->get_db_version() ) ) {
			Option::get_instance()->update_option( 'version', self::VERSION );
		}

		// Set default offering data if it's not already set.
		if ( empty( Option::get_instance()->get_option( OfferingCheck::OFFERING_CODE_OPTION_SLUG ) ) ) {
			OfferingCheck::set_default_offering();
		}

		// Scheduled event to recheck offering data.
		OfferingCheck::schedule_check_offering_task();

		// Turn on upstream checking as fallback to make sure we get at least one good reply.
		Upstream::setup_launch_mode();

		if ( ! Upstream::is_launch_mode_enabled() ) {
			AdsTxt::get_instance()->add_ads_txt_writable_fallback_if_no_redirect();
			AdsTxt::get_instance()->setup_verify_ads_txt_health_task();
		}

		AdsTxt::get_instance()->verify_ads_txt_health();
	}

	/**
	 * Checks if MCP version has been updated and runs any necessary migrations.
	 */
	public function check_if_plugin_updated() {
		$mv_migration = Migration::get_instance();
		if ( ! $mv_migration->is_migration_required() ) {
			Option::get_instance()->update_option( 'version', self::VERSION );
			return true;
		}

		$mv_migration->migrate_to_latest_version();

		Option::get_instance()->update_option( 'version', self::VERSION );
		return false;
	}

	/**
	 * Performs actions before the plugin deactivates.
	 */
	public function plugin_deactivation() {
		// Failsafe to disable Launch Mode (will re-check when re-enabled).
		Upstream::reset_upstream_checking();
		wp_clear_scheduled_hook( 'get_ad_text_cron_event' );
		wp_clear_scheduled_hook( 'mcp_verify_ads_txt_health_event' );
		wp_clear_scheduled_hook( 'mcp_offering_check_event' );
		flush_rewrite_rules();
	}

	/**
	 * Handles tasks after WordPress core is updated.
	 */
	public function handle_post_core_update() {
		Option::get_instance()->delete_option( 'adunit_name' );
		Option::get_instance()->delete_option( 'txt_redirections_allowed' );
		// Verify current status of ads.txt and perform any steps needed to fix.
		AdsTxt::get_instance()->verify_ads_txt_health();
	}

	/**
	 * Reset the state of the plugin when the site ID is changed.
	 *
	 * @param mixed $old_value The old value for site ID (site slug).
	 * @param mixed $new_value The new value for site ID (site slug).
	 */
	public function handle_site_id_change( $old_value, $new_value ) {
		// Do nothing when going from an empty value.
		if ( empty( $old_value ) ) {
			return;
		}

		// Verify the value actually changed.
		if ( $old_value === $new_value ) {
			return;
		}

		AdsTxt::get_instance()->reset_ads_txt_handling();
		Option::get_instance()->reset_site_specific_options();
		Upstream::reset_upstream_checking();
		Upstream::setup_launch_mode();

		if ( ! Upstream::is_launch_mode_enabled() ) {
			AdsTxt::get_instance()->add_ads_txt_writable_fallback_if_no_redirect();
			AdsTxt::get_instance()->setup_verify_ads_txt_health_task();
		}

		AdsTxt::get_instance()->verify_ads_txt_health();
	}

	/**
	 * Load admin settings views.
	 *
	 * @codeCoverageIgnore
	 */
	public function init_views() {
		Menu::get_instance();
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'post_class', array( $this, 'add_post_class' ), 10, 3 );
	}

	/**
	 * Add class 'mv-content-wrapper' to all posts' wrappers for ad targeting.
	 *
	 * @param array  $classes Classes to be used.
	 * @param string $class Class being added when filter triggered. (Not used).
	 * @param int    $post_id Current post. (Not used).
	 * @return array Classes to be used.
	 */
	public function add_post_class( $classes, $class, $post_id ) {
		if ( is_singular() && ! in_array( 'mv-content-wrapper', $classes, true ) ) {
			$classes[] = 'mv-content-wrapper';
		}
		return $classes;
	}

	/**
	 * Initialize classes we need setup for later.
	 *
	 * @codeCoverageIgnore
	 */
	private function load_extensions() {
		Security::get_instance();
		Debug::get_instance();
		Migration::get_instance();
	}

	/**
	 * Checks if ads should be disabled for a request.
	 */
	public function should_disable_ads() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		if ( Option::get_instance()->get_option_bool( 'disable_admin_ads' ) ) {
			return true;
		}

		// @todo: Update this to use `class_exists` or `method_exists` instead of `is_plugin_active` to remove dependency on plugin.php.
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$page_builders = array(
			'divi-builder/divi-builder.php',
			'thrive-visual-editor/thrive-visual-editor.php',
			'elementor/elementor.php',
			'live-composer-page-builder/live-composer-page-builder.php',
		);
		foreach ( $page_builders as $item ) {
			if ( is_plugin_active( $item ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create Script Enqueue array.
	 *
	 * @param array $opts options for script enqueue.
	 * @return array
	 */
	public function build_script_enqueue( $opts ) {
		$util = Utility::get_instance();
		// @todo: why are there so many script enqueue methods? can we consolidate?
		$handle          = $opts['handle'];
		$src             = $opts['src'];
		$deps            = $util->get_or_null( $opts, 'deps' );
		$ver             = $util->get_or_null( $opts, 'ver' );
		$in_footer       = $util->get_or_null( $opts, 'in_footer' );
		$attr            = $util->get_or_null( $opts, 'attr' );
		$wp_enqueue_args = $util->filter_null( array( $handle, $src, $deps, $ver, $in_footer ) );
		if ( is_array( $attr ) ) {
			$this->script_attrs[ $handle ] = $attr;
		}
		return $wp_enqueue_args;
	}


	/**
	 * Function to add built enqueue to wp enqueue.
	 *
	 * @param array $opts options for script enqueue.
	 * @codeCoverageIgnore
	 */
	public function mv_enqueue_script( $opts ) {
		$wp_enqueue_args = $this->build_script_enqueue( $opts );
		call_user_func_array( 'wp_enqueue_script', $wp_enqueue_args );
	}

	/**
	 * Enqueue Mediavine Script Wrapper.
	 */
	public function enqueue_scripts() {
		$option          = Option::get_instance();
		$offering_domain = OfferingCheck::get_offering_domain();
		$site_id         = $option->get_option( 'site_id' );
		$use_wrapper     = $option->get_option_bool( 'include_script_wrapper', true );
		$customizer      = false;

		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			$customizer = true;
		}

		if ( $site_id && $use_wrapper && ! $customizer && ! $this->should_disable_ads() ) {
			$this->mv_enqueue_script(
				array(
					'handle' => 'mv-script-wrapper',
					'src'    => 'https://scripts.' . $offering_domain . '/tags/' . $site_id . '.js',
					'attr'   => array(
						'async'          => 'async',
						'data-noptimize' => '1',
						// This disables Cloudflare Rocket Loader.
						// @see https://support.cloudflare.com/hc/en-us/articles/200169436-How-can-I-have-Rocket-Loader-ignore-specific-JavaScripts- .
						'data-cfasync'   => 'false',
					),
				)
			);

		}
	}

	/**
	 * Check for third party plugins and apply integration functionality.
	 *
	 * @codeCoverageIgnore
	 */
	public function setup_third_party() {
		if ( defined( 'WP_ROCKET_VERSION' ) ) {
			WPRocket::get_instance()->init();
		}

		if ( WebStories::get_instance()->has_web_stories() ) {
			WebStories::get_instance()->init();
		}
	}
}
