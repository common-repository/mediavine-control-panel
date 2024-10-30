<?php
namespace Mediavine\MCP;

/**
 * Communicate with Mediavine Dashboard (home base) about account mode.
 *
 * 'Launch Mode' is a boolean state set upstream, as is 'MCM Approval'.
 * If in launch mode, block some features. If MCM isn't approved, modify tagging.
 * Periodically phone home to see if ether status has changed until site is launched.
 *
 * @todo: Convert this to fully use Singleton instead of having some static functions.
 *
 * @see https://www.notion.so/mediavine/e1e807d6f06440dd98f7f214e6361561
 * @package Mediavine\MCP
 */
class Upstream {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Source of truth; REST API location (root URL).
	 *
	 * @var string
	 */
	const API_ROOT = 'https://scripts.mediavine.com/tags/';

	/**
	 * The option stored with this (prefix +) name is a bool.
	 *
	 * @var string
	 */
	const LAUNCH_MODE_OPTION_SLUG = 'launch_mode';

	/**
	 * The option stored with this (prefix +) name is a STRING.
	 *
	 * @var string
	 */
	const MCM_CODE_OPTION_SLUG = 'mcm_code';

	/**
	 * The option stored with this (prefix +) name is a bool.
	 *
	 * @var string
	 */
	const MCM_APPROVAL_OPTION_SLUG = 'mcm_approval';

	/**
	 * The option stored with this (prefix +) name is a bool.
	 *
	 * @var string
	 */
	const GOOGLE_OPTION_SLUG = 'google';

	/**
	 * Hook event name for updating mode.
	 *
	 * @var string
	 */
	const MODE_EVENT_NAME = 'mv_mcp_check_mode';

	/**
	 * Hook event name for updating MCM status.
	 *
	 * @var string
	 */
	const MCM_EVENT_NAME = 'mv_mcp_check_mcm';

	/**
	 * Key for MCM code in the upstream API reply.
	 *
	 * Also known as the child publisher ID.
	 *
	 * @var string
	 */
	const MCM_CODE_UPSTREAM_SLUG = 'mcmNetworkCode';

	/**
	 * Key for MCM status in the upstream API reply.
	 *
	 * @var string
	 */
	const MCM_STATUS_UPSTREAM_SLUG = 'mcmStatusApproved';

	/**
	 * Key for launch mode in the upstream API reply.
	 *
	 * @var string
	 */
	const LAUNCH_MODE_UPSTREAM_SLUG = 'launch_mode';

	/**
	 * Key for 'google' setting in the upstream API reply.
	 *
	 * @var string
	 */
	const GOOGLE_UPSTREAM_SLUG = 'google';

	/**
	 * Whether site is in launch mode.
	 *
	 * @var null|bool
	 */
	protected static $is_launch_mode = null;

	/**
	 * Whether site is approved by Google.
	 *
	 * @var null|bool
	 */
	protected static $is_google_approved = null;

	/**
	 * Whether site is approved for MCM _and_ has a code.
	 *
	 * @var null|bool
	 */
	protected static $is_mcm_enabled = null;

	/**
	 * Account code for MCM. Also known as the child publisher ID.
	 *
	 * @var null|string
	 */
	protected static $mcm_code = null;

	/**
	 * Hook into WP lifecycle.
	 */
	public function init() {
		// Event handling.
		add_action( self::MODE_EVENT_NAME, array( $this, 'check_mode_task' ), 10 );
		add_action( self::MCM_EVENT_NAME, array( $this, 'check_mcm_task' ), 10 );
		add_filter( 'cron_schedules', array( $this, 'add_interval_to_scheduler' ) );

		// Ajax action handlers.
		add_action( 'wp_ajax_mv_disable_launch_mode', array( $this, 'clicked_disable_launch_mode_button' ) );
		add_action( 'wp_ajax_mv_refresh_launch_mode', array( $this, 'handle_refresh_launch_mode_button' ) );

		// Insert GPT snippets if we are still in launch mode but an MCM network code is available.
		if ( Option::get_instance()->get_option_bool( 'enable_gpt_snippet' ) && self::is_launch_mode_enabled() && ! empty( self::get_mcm_code() ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_gpt_script' ), 0 );
			// Second filter needed to add `async` to our script.
			add_filter( 'script_loader_tag', array( $this, 'async_gpt_script' ), 0, 2 );

			// Insert required empty div into body.
			add_action( 'wp_body_open', array( $this, 'insert_gpt_body_tag' ), 0 );
		}
	}

	/**
	 * Get web REST API URL for the upstream.
	 *
	 * @param string $slug Publisher's site slug.
	 * @return string Full REST API URL for specific account.
	 */
	public function get_mode_endpoint( $slug = '' ) {
		return self::API_ROOT . $slug . '.json';
	}

	/**
	 * Setup and run starting tasks necessary for being in launch mode.
	 *
	 * This function can be run after initial setup to ensure all necessary tasks
	 * are still setup and/or to force a launch mode check from Dashboard.
	 */
	public static function setup_launch_mode() {
		self::start_mode_checking();
		self::start_mcm_status_checking();
		// Force the first check.
		self::get_instance()->check_mode_task();
	}

	/**
	 * Maintains state for mode check to avoid multiple calcs per request.
	 *
	 * External API.
	 *
	 * @return bool
	 */
	public static function is_launch_mode_enabled() {
		if ( null === self::$is_launch_mode ) {
			self::$is_launch_mode = self::get_launch_mode();
		}

		return self::$is_launch_mode;
	}

	/**
	 * Maintains state for MCM enabled check.
	 *
	 * External API. Requires NOT launch mode, MCM approval, _and_ MCM code in valid format.
	 *
	 * @return bool
	 */
	public static function is_mcm_enabled() {
		if ( self::is_launch_mode_enabled() ) {
			// Override MCM because launch mode.
			return false;
		}

		if ( null === self::$is_mcm_enabled ) {
			self::$is_mcm_enabled = ( self::get_mcm_approval() && self::mcm_code() );
		}

		return self::$is_mcm_enabled;
	}

	/**
	 * Gets MCM code.
	 *
	 * @return string
	 */
	public static function mcm_code() {
		if ( null === self::$mcm_code ) {
			self::$mcm_code = self::get_mcm_code();
		}

		return self::$mcm_code;
	}

	/**
	 * Checks if google is approved.
	 *
	 * @return bool
	 */
	public static function is_google_enabled() {
		if ( null === self::$is_google_approved ) {
			self::$is_google_approved = self::get_google_approval();
		}

		return self::$is_google_approved;
	}

	/**
	 * Setter for Google-enabled. Used in tests.
	 *
	 * @param null|bool $is_enabled New value for Google-enabled. Null is reset.
	 * @return self
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public static function set_google_enabled( $is_enabled ) {
		// Validate input.
		if ( ! is_bool( $is_enabled ) && ! is_null( $is_enabled ) ) {
			throw new \Exception( 'Invalid value for launch mode.' );
		}

		self::$is_google_approved = $is_enabled;

		return self::get_instance();
	}

	/**
	 * Setter for MCM-enabled. Used in tests.
	 *
	 * @param null|bool $is_enabled New value for MCM-enabled. Null is reset.
	 * @return self
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public static function set_mcm_approval( $is_enabled ) {
		// Validate input.
		if ( ! is_bool( $is_enabled ) && ! is_null( $is_enabled ) ) {
			throw new \Exception( 'Invalid value for launch mode.' );
		}

		self::$is_mcm_enabled = $is_enabled;

		return self::get_instance();
	}

	/**
	 * Setter for MCM code. Used in tests.
	 *
	 * @param string $code New value for MCM code.
	 *
	 * @return self
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public static function set_mcm_code( $code ) {
		// Validate input.
		if ( ! is_string( $code ) && ! is_null( $code ) ) {
			throw new \Exception( 'Invalid value for mcm code.' );
		}

		self::$mcm_code = $code;

		return self::get_instance();
	}

	/**
	 * Setter for launch mode. Used in tests.
	 *
	 * @param null|bool $mode New value for launch mode. Null is reset.
	 *
	 * @return self
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public static function set_launch_mode( $mode ) {
		// Validate input.
		if ( ! is_bool( $mode ) && ! is_null( $mode ) ) {
			throw new \Exception( 'Invalid value for launch mode.' );
		}

		self::$is_launch_mode = $mode;

		return self::get_instance();
	}

	/**
	 * Clears the internal static cache for launch mode.
	 */
	public static function clear_launch_mode_cache() {
		self::set_launch_mode( null );
	}

	/**
	 * Detect whether we exited launch mode while refreshing the status.
	 *
	 * @return bool
	 */
	public static function has_just_left_launch_mode() {
		if ( true === self::is_launch_mode_enabled() ) {
			return false;
		}

		if ( true === Option::get_instance()->get_option_bool( 'seen_launch_success_message', false ) ) {
			return false;
		}

		Option::get_instance()->update_option( 'seen_launch_success_message', true );
		return true;
	}

	/**
	 * Calculate whether account mode is 'launch'.
	 *
	 * Assume a site is in launch mode if there is no explicit confirmation from upstream that it is not.
	 *
	 * @return bool Whether 'launch mode' is currently enabled.
	 */
	public static function get_launch_mode() {
		// Retrieve stored option. Defaults to true.
		return Option::get_instance()->get_option_bool( self::LAUNCH_MODE_OPTION_SLUG, true );
	}

	/**
	 * Calculate whether account has Google approval.
	 *
	 * Defaults to false if no value is stored.
	 *
	 * @return bool Whether Google has approved the site.
	 */
	public static function get_google_approval() {
		return Option::get_instance()->get_option_bool( self::GOOGLE_OPTION_SLUG );
	}

	/**
	 * Calculate whether account has MCM approval.
	 *
	 * Defaults to false if no value is stored.
	 *
	 * @return bool
	 */
	public static function get_mcm_approval() {
		return Option::get_instance()->get_option_bool( self::MCM_APPROVAL_OPTION_SLUG );
	}

	/**
	 * Gets the MCM code, if any.
	 *
	 * @return string Validated MCM code or blank.
	 */
	public static function get_mcm_code() {
		return Option::get_instance()->get_option( self::MCM_CODE_OPTION_SLUG );
	}

	/**
	 * Ajax handler for clicking "Disable Launch Mode" button in WP Dashboard.
	 */
	public function clicked_disable_launch_mode_button() {
		check_ajax_referer( 'disable-launch-mode' );
		$this->disable_launch_mode();
	}

	/**
	 * Turn off launch mode.
	 *
	 * @return self
	 * @throws \Exception Possible exception from nested functions.
	 */
	public function disable_launch_mode() {
		$this->update_launch_mode( false );
		$this->set_launch_mode( false );
		self::end_mode_checking();
		return $this;
	}

	/**
	 * Update locally stored value for launch mode.
	 *
	 * @param bool $is_launch_mode New value to save to DB for launch mode.
	 * @return self
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function update_launch_mode( $is_launch_mode ) {
		// Validate input.
		if ( ! is_bool( $is_launch_mode ) ) {
			throw new \Exception( 'Invalid value for launch mode.' );
		}

		Option::get_instance()->update_option( self::LAUNCH_MODE_OPTION_SLUG, $is_launch_mode );

		return $this;
	}

	/**
	 * Ajax handler for clicking "Refresh Launch Mode" button in admin settings.
	 */
	public function handle_refresh_launch_mode_button() {
		check_ajax_referer( 'refresh-launch-mode' );

		// Force the synchronous launch mode check.
		self::get_instance()->check_mode_task();
		// Clear status so that it gets populated again.
		self::clear_launch_mode_cache();

		wp_send_json_success(
			[
				'launch_mode' => self::get_launch_mode(),
			],
			200
		);
	}

	/**
	 * Update locally stored value for MCM approval.
	 *
	 * @param bool $is_approved Whether site is MCM approved.
	 * @return $this
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function update_mcm_approval( $is_approved ) {
		// Validate input.
		if ( ! is_bool( $is_approved ) ) {
			throw new \Exception( 'Invalid value for MCM Approval.' );
		}

		// Update stored option.
		Option::get_instance()->update_option( self::MCM_APPROVAL_OPTION_SLUG, $is_approved );

		return $this;
	}

	/**
	 * Update locally stored value for MCM code.
	 *
	 * @param string $code Publisher's MCM code.
	 * @return $this
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function update_mcm_code( $code ) {
		// Validate input (alphanumeric string).
		if ( ! $this->validate_mcm_code( $code ) ) {
			throw new \Exception( 'Invalid value for MCM Code.' );
		}

		// Update stored option.
		Option::get_instance()->update_option( self::MCM_CODE_OPTION_SLUG, $code );

		return $this;
	}

	/**
	 * Update locally stored value for Google approval.
	 *
	 * @param bool $is_approved Whether publisher is google approved.
	 * @return $this
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function update_google_approval( $is_approved ) {
		// Validate input.
		if ( ! is_bool( $is_approved ) ) {
			throw new \Exception( 'Invalid value for Google Approval.' );
		}

		// Update stored option.
		Option::get_instance()->update_option( self::GOOGLE_OPTION_SLUG, $is_approved );

		return $this;
	}

	/**
	 * Get raw data from the upstream provider and format it as an array.
	 *
	 * @param string $endpoint URL of upstream.
	 * @return array Empty on error.
	 */
	public function get_data_from_upstream( $endpoint ) {
		// Call Dashboard API.
		$response = wp_remote_get( $endpoint );

		// Bail on any errors.
		if ( is_wp_error( $response ) ) {
			// @todo throw an exception for these
			return array();
		}
		if ( 399 < wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}

		// Parse the JSON response into an array.
		$upstream_settings = json_decode( wp_remote_retrieve_body( $response ), true );

		// Make sure we have a sensical reply.
		if ( ! is_array( $upstream_settings ) || count( $upstream_settings ) < 5 ) {
			return array();
		}

		return $upstream_settings;
	}

	/**
	 * Re-check our launch mode from upstream.
	 *
	 * Called by WordPress hook via cron.
	 */
	public function check_mode_task() {
		$data = $this->get_upstream();
		$this->do_mode_update( $data );
		// Also do an MCM update while we're at it.
		$this->do_mcm_update( $data );
	}

	/**
	 * Re-check our MCM status from upstream.
	 *
	 * Called by WordPress hook via cron.
	 */
	public function check_mcm_task() {
		$data = $this->get_upstream();
		$this->do_mcm_update( $data );
	}

	/**
	 * Fetch data from upstream.
	 *
	 * @return array
	 */
	public function get_upstream() {
		$site_slug = Option::get_instance()->get_option( 'site_id' );
		if ( empty( $site_slug ) ) {
			return array();
		}

		$endpoint = $this->get_mode_endpoint( $site_slug );

		return $this->get_data_from_upstream( $endpoint );
	}

	/**
	 * Use upstream data to update launch mode.
	 *
	 * @param array $data API data array.
	 * @return self|void
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function do_mode_update( $data ) {
		// Skip if request failed or launch mode data is missing / invalid.
		if ( ! isset( $data[ self::LAUNCH_MODE_UPSTREAM_SLUG ] ) || ! is_bool( $data[ self::LAUNCH_MODE_UPSTREAM_SLUG ] ) ) {
			return;
		}

		// Set new launch mode.
		$this->update_launch_mode( $data[ self::LAUNCH_MODE_UPSTREAM_SLUG ] );

		if ( true === $data[ self::LAUNCH_MODE_UPSTREAM_SLUG ] ) {
			self::start_mode_checking(); // Launch Mode means we'll need to know when it ends.
		} else {
			self::end_mode_checking(); // This is a one-way process, so stop checking.
		}

		return $this;
	}

	/**
	 * Use upstream data to update MCM status.
	 *
	 * @param array $data API data array.
	 * @return self|void
	 * @throws \Exception Throws exception on invalid passed parameter.
	 */
	public function do_mcm_update( $data ) {
		// Skip if any data is missing / invalid.
		if ( ! $this->validate_mcm_data( $data ) ) {
			return;
		}

		// Set new MCM code & status.
		$this->update_mcm_code( $data[ self::MCM_CODE_UPSTREAM_SLUG ] );
		$this->update_mcm_approval( $data[ self::MCM_STATUS_UPSTREAM_SLUG ] );
		// Kludge the Google update in here for now.
		// @todo: Separate MCM and Google checks logically.
		$this->update_google_approval( $data[ self::GOOGLE_UPSTREAM_SLUG ] );

		if ( true === $data[ self::MCM_STATUS_UPSTREAM_SLUG ] ) {
			self::end_mcm_status_checking();
		} else {
			self::start_mcm_status_checking();
		}

		return $this;
	}

	/**
	 * Validate whether MCM-related data is present and valid.
	 *
	 * @param array $data Upstream data to validate.
	 * @return bool Whether the data passed validation.
	 */
	public function validate_mcm_data( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		// Null values are acceptable, so we only need to confirm the key exists.
		if ( ! array_key_exists( self::MCM_CODE_UPSTREAM_SLUG, $data ) ) {
			return false;
		}

		if ( ! $this->validate_mcm_code( $data[ self::MCM_CODE_UPSTREAM_SLUG ] ) ) {
			return false;
		}

		if ( ! isset( $data[ self::MCM_STATUS_UPSTREAM_SLUG ] ) ) {
			return false;
		}

		if ( ! is_bool( $data[ self::MCM_STATUS_UPSTREAM_SLUG ] ) ) {
			return false;
		}

		if ( ! isset( $data[ self::GOOGLE_UPSTREAM_SLUG ] ) ) {
			return false;
		}

		if ( ! is_bool( $data[ self::GOOGLE_UPSTREAM_SLUG ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate whether MCM code is an acceptable format.
	 *
	 * @param string $code Code to validate.
	 * @return bool
	 */
	public static function validate_mcm_code( $code ) {
		// Null is allowed as a valid value.
		if ( is_null( $code ) ) {
			return true;
		}
		if ( ! is_string( $code ) ) {
			return false;
		}
		if ( strlen( $code ) > 32 || strlen( $code ) < 3 ) {
			return false;
		}
		if ( 1 === preg_match( '/[^A-Za-z0-9]/', $code ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Ensures WP Cron has needed interval definitions.
	 *
	 * @param array $array An array of non-default cron schedule arrays.
	 *
	 * @return array
	 */
	public function add_interval_to_scheduler( $array ) {
		$array['quarter_hourly'] = array(
			'interval' => MINUTE_IN_SECONDS * 15,
			'display'  => __( 'Every 15 Minutes' ),
		);
		return $array;
	}

	/**
	 * Start checking for mode from upstream.
	 */
	public static function start_mode_checking() {
		if ( false === wp_next_scheduled( self::MODE_EVENT_NAME ) ) {
			wp_schedule_event( gmdate( 'U' ), 'quarter_hourly', self::MODE_EVENT_NAME );
		}
	}

	/**
	 * Stop checking for mode from upstream.
	 */
	public static function end_mode_checking() {
		/**
		 * Hook that triggers when site leaves launch mode.
		 *
		 * @since 2.9.0
		 */
		do_action( 'mcp_left_launch_mode' );

		wp_clear_scheduled_hook( self::MODE_EVENT_NAME );
	}

	/**
	 * Scrub all record of our mode checking.
	 */
	public static function reset_upstream_checking() {
		Option::get_instance()->delete_option( self::LAUNCH_MODE_OPTION_SLUG );
		wp_clear_scheduled_hook( self::MODE_EVENT_NAME );
		wp_clear_scheduled_hook( self::MCM_EVENT_NAME );
	}

	/**
	 * Start checking for mode from upstream.
	 */
	public static function start_mcm_status_checking() {
		if ( false === wp_next_scheduled( self::MCM_EVENT_NAME ) ) {
			wp_schedule_event( gmdate( 'U' ), 'quarter_hourly', self::MCM_EVENT_NAME );
		}
	}

	/**
	 * Start checking for mode from upstream.
	 */
	public static function end_mcm_status_checking() {
		wp_clear_scheduled_hook( self::MCM_EVENT_NAME );
	}

	/**
	 * Returns the template for the GPT injected javascript.
	 *
	 * @codeCoverageIgnore
	 */
	public function get_gpt_snippet_template() {
		return "
			window.googletag = window.googletag || {cmd: []};
			googletag.cmd.push(function() {
				googletag.pubads().disableInitialLoad();
				googletag.enableServices();
				const slot = googletag.defineSlot(
				  '/%s,%s/verification', [1, 1], 'mcm-verification-slot'
				).addService(googletag.pubads());
				googletag.display('mcm-verification-slot');
				googletag.pubads().refresh([slot]);
			});
		";
	}

	/**
	 * Defines a callback to render the GPT javascript to the header.
	 */
	public function enqueue_gpt_script() {
		wp_enqueue_script( 'mcp-gpt-header', 'https://securepubads.g.doubleclick.net/tag/js/gpt.js', array(), MV_Control_Panel::VERSION, false );
		$snippet = $this->get_gpt_snippet_template();
		$snippet = sprintf( $snippet, $this->get_parent_publisher_id(), self::get_mcm_code() );
		wp_add_inline_script( 'mcp-gpt-header', $snippet );
	}

	/**
	 * Alters the embedded GPT script with an `async` attribute.
	 *
	 * @param string $tag The script tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 */
	public function async_gpt_script( $tag, $handle ) {
		if ( 'mcp-gpt-header' !== $handle ) {
			return $tag;
		}

		return str_replace( ' src', ' async src', $tag );
	}

	/**
	 * Outputs the empty div required for GPT verification.
	 */
	public function insert_gpt_body_tag() {
		print '<div id="mcm-verification-slot"></div>';
	}

	/**
	 * Returns the Parent Publisher ID based on the offering code.
	 *
	 * @param string $offering_code The offering code value for the parent publisher.
	 *
	 * @return string
	 */
	public function get_parent_publisher_id( $offering_code = '' ) {
		if ( empty( $offering_code ) ) {
			$offering_code = OfferingCheck::get_offering_code();
		}

		// @todo: Pull this dynamically from offering['gam_network_code'] instead.
		if ( 'pubnation' === $offering_code ) {
			return '22794612459';
		}

		// Default to the Mediavine parent publisher ID.
		return '1030006';
	}
}
