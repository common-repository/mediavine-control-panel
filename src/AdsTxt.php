<?php
namespace Mediavine\MCP;

/**
 * Handles functionality related to ads.txt.
 */
class AdsTxt {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Tracks document root path.
	 *
	 * @var string|null
	 */
	public $document_root = null;

	/**
	 * Setup required values.
	 *
	 * @codeCoverageIgnore
	 */
	protected function __construct() {
		$this->document_root = $this->get_root_path();
	}

	/**
	 * Link functions to WP Lifecycle.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		add_action( 'mcp_left_launch_mode', array( $this, 'setup_after_launch' ) );

		// Don't do anything else if launch mode is enabled.
		if ( Upstream::is_launch_mode_enabled() ) {
			return;
		}

		// Remove potential Ads.txt Manager conflict.
		$this->remove_ads_txt_plugin_conflicts();

		add_action( 'init', array( $this, 'create_rewrites' ) );
		add_action( 'admin_notices', array( $this, 'validate_write_method' ) );
		add_action( 'get_ad_text_cron_event', array( $this, 'write_ad_text_file' ) );
		add_action( 'mcp_verify_ads_txt_health_event', array( $this, 'verify_ads_txt_health' ), 10 );
		add_action( 'wp_ajax_mv_recheck_adtext', array( $this, 'force_recheck_ads_txt_ajax' ) );
		add_action( 'wp_ajax_mv_adtext', array( $this, 'write_ad_text_ajax' ) );
		add_action( 'wp_ajax_mv_disable_adtext', array( $this, 'disable_ad_text_ajax' ) );
		add_action( 'wp_ajax_mv_enable_adtext', array( $this, 'enable_ad_text_ajax' ) );

		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_hosts' ) );
	}

	/**
	 * Do any actions necessary after leaving launch mode.
	 */
	public function setup_after_launch() {
		// Schedule a task to run hourly to verify the health of the ads.txt file/redirect.
		$this->setup_verify_ads_txt_health_task();

		// Don't proceed if we are not using the write ads.txt method.
		if ( ! $this->is_ads_txt_method_write() ) {
			return;
		}

		$this->write_ad_text_file();
		$this->add_ads_txt_writable_fallback_if_no_redirect();
	}

	/**
	 * Gets the root path for ads.txt file write
	 *
	 * @return string
	 */
	public function get_root_path() {
		$root_path = ABSPATH;
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
			$root_path = $_SERVER['DOCUMENT_ROOT'];
		}

		// Allow for root path override.
		if ( defined( 'MVCP_ROOT_PATH' ) ) {
			$root_path = MVCP_ROOT_PATH;
		}

		return trailingslashit( $root_path );
	}

	/**
	 * Uses filters to specifically prevent conflicts with other third-party plugins.
	 *
	 * We only unhook if we have a Mediavine Site ID and if ads.txt is enabled.
	 * Currently affects the following plugins:
	 * - Ads.txt Manager
	 * - Redirection
	 *
	 * @return void
	 */
	public function remove_ads_txt_plugin_conflicts() {
		// We only want to proceed if we have a site ID and ads.txt is enabled.
		if ( 'none' === $this->get_ads_txt_method() ) {
			return;
		}

		// Unhooks Ads.txt Manager plugin from affecting Ads.txt redirect.
		remove_action( 'init', 'tenup_display_ads_txt' );

		// Prevents Redirection plugin from overriding /ads.txt redirects.
		add_filter( 'redirection_url_target', array( $this, 'remove_redirection_ads_txt' ), 10, 2 );
	}

	/**
	 * Removes the /ads.txt redirect from the Redirection plugin if it exists.
	 *
	 * @param string $target_url Destination URL for a redirect.
	 * @param string $source_url Matched URL that triggers redirect.
	 * @return bool|string False if source is /ads.txt. Initial target if no match.
	 */
	public function remove_redirection_ads_txt( $target_url, $source_url ) {
		if ( '/ads.txt' === $source_url ) {
			$target_url = false;
		}

		return $target_url;
	}

	/**
	 * Retrieves the HTTP code of a URL through a cURL request.
	 *
	 * @param string $url The URL to retrieve.
	 *
	 * @return int
	 */
	public function get_curl_http_code( $url ) {
		// @todo: Refactor this to use Requests or WP_Http and make it unit-testable.
		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_getinfo, WordPress.WP.AlternativeFunctions.curl_curl_close
		$curl_handle = curl_init( $url );
		curl_setopt( $curl_handle, CURLOPT_RETURNTRANSFER, true );
		// Set a timeout (in seconds) for connection and execution. Total maximum time is the sum of both.
		curl_setopt( $curl_handle, CURLOPT_CONNECTTIMEOUT, 4 );
		curl_setopt( $curl_handle, CURLOPT_TIMEOUT, 4 );
		curl_exec( $curl_handle );
		$http_code = curl_getinfo( $curl_handle, CURLINFO_RESPONSE_CODE );
		curl_close( $curl_handle );
		// phpcs:enable

		return $http_code;
	}

	/**
	 * Checks if the relative home URL contains a subdirectory, by checking for a forward slash
	 *
	 * @return boolean
	 */
	public function does_home_url_contain_subdirectory() {
		return ( false !== strpos( home_url( null, 'relative' ), '/' ) );
	}

	/**
	 * Checks if the site has the ability to redirect txt files.
	 *
	 * Potentially expensive procedure, so we store the result as an option.
	 *
	 * @return boolean
	 */
	public function can_txt_files_be_redirected() {
		// Prevent recursion from timing out a server with our check.
		// phpcs:disable
		if ( isset( $_GET['mcp'] ) && 'checking-redirection' === $_GET['mcp'] ) {
			wp_die( 'If you got this error, then you are doing something you are not supposed to be doing.' );
		}
		// phpcs:enable

		// Have we performed and stored this check before?
		if ( Option::get_instance()->exists( 'txt_redirections_allowed' ) ) {
			return Option::get_instance()->get_option_bool( 'txt_redirections_allowed' );
		}

		// Limit the number of concurrent checks to 1 to avoid too many check requests at once.
		if ( Option::get_instance()->get_option_bool( 'txt_redirections_check_in_progress' ) ) {
			// Default to true while check is in progress to avoid writing ads.txt to server.
			// This will return a 404 for ads.txt temporarily (at most 8 seconds).
			return true;
		}

		// Does the WP home url have a subdirectory? If it does, we know we are not working with
		// the root domain and don't want to attempt to rely on WP to perform the redirects.
		if ( $this->does_home_url_contain_subdirectory() ) {
			Option::get_instance()->update_option( 'txt_redirections_allowed', false );
			Option::get_instance()->update_option( 'txt_redirections_check_in_progress', false );
			return false;
		}

		// Check that the server is not intercepting txt files before WordPress.
		$likely_has_no_duplicates  = uniqid( '/this-will-404-' );
		$definitely_a_404_txt_file = home_url() . $likely_has_no_duplicates . '.txt?mcp=checking-redirection';

		// Make sure we can perform this check. Some servers have weird adjustments to curl.
		// If we can't perform the check, then we take no chances and use the write method.
		// @todo: Refactor this to use Requests or WP_Http and make it unit-testable.
		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_getinfo' ) ) {
			Option::get_instance()->update_option( 'txt_redirections_allowed', false );
			Option::get_instance()->update_option( 'txt_redirections_check_in_progress', false );
			return false;
		}

		// Track that we currently have a check in progress and prevent other requests from triggering the check again.
		Option::get_instance()->update_option( 'txt_redirections_check_in_progress', true );

		/**
		 * Filters the http code. This filter is only used for phpunit testing.
		 *
		 * @param int $http_code
		 */
		// @todo: rename hook to follow standard naming pattern.
		$http_code = apply_filters( 'mv_cp_http_code', $this->get_curl_http_code( $definitely_a_404_txt_file ) );

		// If the url doesn't return a 500, then WP redirects don't work with txt files.
		// Don't proceed. We purposefully exit the page with `wp_die`, so we know it should be 500.
		if ( 500 !== $http_code ) {
			Option::get_instance()->update_option( 'txt_redirections_allowed', false );
			Option::get_instance()->update_option( 'txt_redirections_check_in_progress', false );

			return false;
		}
		Option::get_instance()->update_option( 'txt_redirections_allowed', true );
		Option::get_instance()->update_option( 'txt_redirections_check_in_progress', false );

		return true;
	}

	/**
	 * Checks if the Ads.txt method has been forced to write through an enabled setting.
	 *
	 * @codeCoverageIgnore
	 * @return boolean
	 */
	public function is_ads_txt_write_forced() {
		return Option::get_instance()->get_option_bool( 'ads_txt_write_forced' );
	}

	/**
	 * Gets the ads.txt method of retrieval.
	 *
	 * 'none' means MCP is not handling ads.txt.
	 * 'redirect' uses a 301 method.
	 * 'write' writes the ads.txt file to the domain root and schedules an event
	 *   to check Mediavine's servers and update ads.txt info accordingly.
	 *
	 * @param string $home_url The home url of the site to parse.
	 * @return string The ads.txt retrieval method. Valid values are 'none', 'redirect', or 'write'.
	 */
	public function get_ads_txt_method( $home_url = '' ) {
		// If site id is missing then MCP is not handling ads.txt.
		if ( empty( Option::get_instance()->get_option( 'site_id' ) ) ) {
			return 'none';
		}

		// If site is in launch mode then MCP is not handling ads.txt.
		if ( Upstream::is_launch_mode_enabled() ) {
			return 'none';
		}

		// If ads.txt is disabled in settings then MCP is not handling ads.txt.
		if ( ! $this->is_ads_txt_handling_enabled() ) {
			return 'none';
		}

		$ads_txt_method = 'redirect';

		if ( empty( $home_url ) ) {
			$home_url = home_url();
		}

		// Detect if home url is a subdirectory.
		// @todo: Refactor to use does_home_url_contain_subdirectory instead.
		$parsed_url = wp_parse_url( $home_url );
		if ( array_key_exists( 'path', $parsed_url ) ) {
			$ads_txt_method = 'write';
		}

		// Check that redirection of text files works.
		if ( ! $this->can_txt_files_be_redirected() ) {
			$ads_txt_method = 'write';
		}

		// Check if write method is forced by hidden setting.
		if ( $this->is_ads_txt_write_forced() ) {
			$ads_txt_method = 'write';
		}

		/**
		 * Filters the method used to retrieve ads.txt files.
		 *
		 * @param array $ads_txt_method Supports 'redirect' or 'write'
		 */
		$ads_txt_method = apply_filters( 'mv_cp_ads_txt_method', $ads_txt_method );

		// No need for an ads.txt file if we are rewriting
		// Don't remove if we are currently processing an ads.txt method check.
		if ( 'redirect' === $ads_txt_method && ! Option::get_instance()->get_option_bool( 'txt_redirections_check_in_progress' ) ) {
			$this->remove_adstxt();
			wp_clear_scheduled_hook( 'get_ad_text_cron_event' );
			$this->setup_verify_ads_txt_health_task();
		}

		return $ads_txt_method;
	}

	/**
	 * Checks if ads.txt is being managed by MCP.
	 *
	 * @codeCoverageIgnore
	 * @return boolean
	 */
	public function is_ads_txt_handling_enabled() {
		return ! Option::get_instance()->get_option_bool( 'adtext_disabled' );
	}

	/**
	 * Checks if writable ads.txt are enabled.
	 *
	 * @return boolean
	 */
	public function is_ads_txt_method_write() {
		return 'write' === $this->get_ads_txt_method();
	}

	/**
	 * Checks if redirected ads.txt are enabled.
	 *
	 * @return boolean
	 */
	public function is_ads_txt_method_redirect() {
		return 'redirect' === $this->get_ads_txt_method();
	}

	/**
	 * Adds scheduled event to write ads.txt file.
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
	public function add_ads_txt_write_event() {
		// Only proceed if scheduled event doesn't already exist.
		if ( false !== wp_next_scheduled( 'get_ad_text_cron_event' ) ) {
			return;
		}

		wp_schedule_event( time(), 'twicedaily', 'get_ad_text_cron_event' );
	}

	/**
	 * Schedules ads.txt write event if ads.txt is enabled and method is set to write.
	 *
	 * @return void
	 */
	public function add_ads_txt_writable_fallback_if_no_redirect() {
		// Check that ads.txt write method is valid and enabled.
		if ( ! $this->is_ads_txt_method_write() ) {
			return;
		}

		$this->add_ads_txt_write_event();
	}

	/**
	 * Defines a callback that redirects if it is on /ads.txt.
	 *
	 * @param \WP  $query Current WordPress environment instance (passed by reference).
	 * @param bool $should_return Whether the URL should be returned or redirect to immediately.
	 *
	 * @return string|void
	 */
	public function handle_parse_ads_txt_request( $query, $should_return = false ) {
		if ( ! Utility::check_parse_route( 'ads.txt', $query ) ) {
			return;
		}

		// Get URL from site ID and offering domain.
		$site_id         = Option::get_instance()->get_option( 'site_id' );
		$offering_domain = OfferingCheck::get_offering_domain();
		if ( ! empty( $site_id ) ) {
			$url = 'https://adstxt.' . $offering_domain . '/sites/' . $site_id . '/ads.txt';
			if ( $should_return ) {
				return $url;
			}
			Utility::fire_redirect( $url );
		}
	}

	/**
	 * Adds rewrite rules for directing ads.txt to mediavine servers.
	 */
	public function create_rewrites() {
		// Only add rewrite rule if checks pass.
		if ( ! $this->is_ads_txt_method_redirect() ) {
			return false;
		}

		add_action( 'parse_request', array( $this, 'handle_parse_ads_txt_request' ) );
		return true;
	}

	/**
	 * Validates that ads.txt is set up correctly when using the write method.
	 */
	public function validate_write_method() {
		// No need to validate if we are not using the write method.
		if ( ! $this->is_ads_txt_method_write() ) {
			return;
		}

		// Check cron event is setup if MCP is in charge of keeping the ads.txt up to date.
		if ( $this->is_ads_txt_handling_enabled() ) {
			if ( false === wp_next_scheduled( 'get_ad_text_cron_event' ) ) {
				$this->add_ads_txt_write_event();
				Option::get_instance()->update_option( 'validate_write_method_task_missing', true );
			} else {
				Option::get_instance()->update_option( 'validate_write_method_task_missing', false );
			}
		}

		// Check ads.txt file exists on server.
		if ( ! $this->has_ads_txt_file() ) {
			Option::get_instance()->update_option( 'validate_write_method_file_missing', true );
		} else {
			Option::get_instance()->update_option( 'validate_write_method_file_missing', false );
		}

		// Check ads.txt is not empty.
		if ( $this->has_ads_txt_file() && ! $this->has_contents() ) {
			Option::get_instance()->update_option( 'validate_write_method_file_empty', true );
		} else {
			Option::get_instance()->update_option( 'validate_write_method_file_empty', false );
		}
	}

	/**
	 * Creates the verify health task if it is not already running.
	 *
	 * @codeCoverageIgnore
	 */
	public function setup_verify_ads_txt_health_task() {
		if ( false === wp_next_scheduled( 'mcp_verify_ads_txt_health_event' ) ) {
			wp_schedule_event( gmdate( 'U' ), 'hourly', 'mcp_verify_ads_txt_health_event' );
		}
	}

	/**
	 * Verifies and validates the current implementation of ads.txt for the site.
	 */
	public function verify_ads_txt_health() {
		$method = $this->get_ads_txt_method();

		if ( 'none' === $method ) {
			// No need to continue if MCP is not actively managing ads.txt.
			return;
		}

		// Added for redundancy in case something is deleting scheduled tasks.
		$this->setup_verify_ads_txt_health_task();

		if ( 'write' === $method ) {
			$this->validate_write_method();
			return;
		}

		if ( 'redirect' === $method ) {
			// Verify the ads.txt file doesn't exist.
			if ( $this->has_ads_txt_file() ) {
				$this->remove_adstxt();
			}

			// Verify the redirect hook is in place or set a flag for debug if it is missing.
			if ( ! has_action( 'parse_request', array( $this, 'handle_parse_ads_txt_request' ) ) ) {
				Option::get_instance()->update_option( 'validate_redirect_hook_missing', true );
			} else {
				Option::get_instance()->update_option( 'validate_redirect_hook_missing', false );
			}
		}
	}

	/**
	 * Adds 'adstxt.mediavine.com' or 'adstxt.pubnation.com' to allowed hosts for redirects.
	 *
	 * @param array $hosts Existing list of allowed hosts.
	 * @return array Hosts
	 */
	public function allowed_hosts( $hosts ) {
		$offering_domain = OfferingCheck::get_offering_domain();
		$hosts[]         = 'adstxt.' . $offering_domain;
		return $hosts;
	}

	/**
	 * Gets the root home URL, allowing overrides.
	 *
	 * @return string
	 */
	public function get_root_url() {
		$root_url = get_home_url();
		if ( defined( 'MCP_ROOT_URL' ) ) {
			$root_url = MCP_ROOT_URL;
		}
		return $root_url;
	}

	/**
	 * Checks if ads.txt file exists.
	 *
	 * @return bool
	 */
	public function has_ads_txt_file() {
		return file_exists( realpath( $this->document_root . 'ads.txt' ) );
	}

	/**
	 * Checks if the ads.txt file has contents.
	 *
	 * @return bool
	 */
	public function has_contents() {
		return filesize( realpath( $this->document_root . 'ads.txt' ) ) > 0;
	}

	/**
	 * Removes the ads.txt file from the filesystem.
	 *
	 * @return bool
	 */
	public function remove_adstxt() {
		if ( true === $this->has_ads_txt_file() ) {
			return unlink( realpath( $this->document_root . 'ads.txt' ) );
		}
		return false;
	}

	/**
	 * Removes the ads.txt file from the filesystem, if it is empty.
	 */
	public function remove_if_empty() {
		if ( true === $this->has_ads_txt_file() ) {
			if ( ! $this->has_contents() ) {
				unlink( realpath( $this->document_root . 'ads.txt' ) );
			}
		}
	}

	/**
	 * Enables ads.txt functionality.
	 *
	 * @return bool[]
	 */
	public function enable_ad_text() {
		$worked = true;

		// Immediately write ads.txt file if we are using the write method.
		if ( 'write' === $this->get_ads_txt_method() ) {
			$worked = $this->write_ad_text_file();
			$this->remove_if_empty();
			$this->add_ads_txt_write_event();
		}

		Option::get_instance()->delete_option( 'adtext_disabled' );

		return array( 'success' => $worked );
	}

	/**
	 * Disables ads.txt scheduled task.
	 *
	 * @return bool
	 */
	public function disable_adstxt() {
		wp_clear_scheduled_hook( 'get_ad_text_cron_event' );
		return true;
	}

	/**
	 * Retrieves ads.txt file contents from Mediavine Dashboard server.
	 *
	 * @param null $slug The publisher site slug.
	 * @param bool $live_site Whether or not to retrieve from live site.
	 * @return bool|string
	 */
	public function get_ad_text( $slug = null, $live_site = false ) {
		if ( ! $slug ) {
			$slug = Option::get_instance()->get_option( 'site_id' );
		}

		$offering_domain = OfferingCheck::get_offering_domain();
		$url             = 'https://adstxt.' . $offering_domain . '/sites/' . $slug . '/ads.txt';

		if ( $live_site ) {
			$url = $this->get_root_url() . '/ads.txt';
		}

		$request = wp_remote_get( $url );

		// Try again with non-https if error (prevent cURL error 35: SSL connect error).
		if ( is_wp_error( $request ) && ! $live_site && ! empty( $request->errors['http_request_failed'] ) ) {
			$url     = 'http://adstxt.' . $offering_domain . '/sites/' . $slug . '/ads.txt';
			$request = wp_remote_get( $url );
		}

		$code    = wp_remote_retrieve_response_code( $request );
		$ad_text = wp_remote_retrieve_body( $request );

		if ( $code >= 200 && $code < 400 ) {
			return $ad_text;
		}

		return false;
	}

	/**
	 * Writes content to the ads.txt file on the filesystem.
	 *
	 * @param null $slug The publisher's site slug.
	 * @return bool|string|void
	 */
	public function write_ad_text_file( $slug = null ) {
		$ad_text = $this->get_ad_text( $slug );

		// Better failure messages.
		if ( false === $ad_text ) {
			return __( 'Cannot connect to Mediavine Ads.txt file.', 'mcp' );
		}
		if ( empty( $ad_text ) || strlen( $ad_text ) <= 0 ) {
			return __( 'Mediavine Ads.txt file empty.', 'mcp' );
		}

		// phpcs:disable
		$fp = fopen( $this->document_root . 'ads.txt', 'w' );
		fwrite( $fp, $ad_text );
		fclose( $fp );
		// phpcs:enable

		return true;
	}

	/**
	 * Forces a recheck of the ads.txt method.
	 */
	public function force_recheck_ads_txt_method() {
		Option::get_instance()->delete_option( 'txt_redirections_allowed' );
		$this->can_txt_files_be_redirected();
		return $this->get_ads_txt_method();
	}

	/**
	 * Defines an ajax callback to force a recheck of the ads.txt method.
	 */
	public function force_recheck_ads_txt_ajax() {
		check_ajax_referer( 'recheck-ad-text' );
		$method = $this->force_recheck_ads_txt_method();
		$data   = array( 'method' => $method );
		$this->respond_json_and_die( $data );
	}

	/**
	 * Defines an ajax callback to enable ads.txt support.
	 */
	public function enable_ad_text_ajax() {
		check_ajax_referer( 'enable-ad-text' );
		$data           = $this->enable_ad_text();
		$data['method'] = $this->get_ads_txt_method();
		$this->respond_json_and_die( $data );
	}

	/**
	 * Defines an ajax callback to disable ads.txt support.
	 */
	public function disable_ad_text_ajax() {
		check_ajax_referer( 'disable-ad-text' );
		$worked = $this->disable_adstxt();
		$this->remove_adstxt();
		Option::get_instance()->update_option( 'adtext_disabled', true );
		$data           = array( 'success' => $worked );
		$data['method'] = 'none';
		$this->respond_json_and_die( $data );
	}

	/**
	 * Defines an ajax callback to write ads.txt.
	 */
	public function write_ad_text_ajax() {
		check_ajax_referer( 'write-ad-text' );
		$worked = $this->write_ad_text_file();
		$this->remove_if_empty();
		$data = array( 'error' => $worked );
		if ( true === $worked ) {
			$data = array( 'success' => true );
			// Add a scheduled event if redirects are disabled.
			if ( 'write' === $this->get_ads_txt_method() ) {
				$this->add_ads_txt_write_event();
			}
		}
		$data['method'] = $this->get_ads_txt_method();
		$this->respond_json_and_die( $data );
	}

	/**
	 * Dumps JSON data to the page and ends WP request.
	 *
	 * @param array $data Data to dump to screen.
	 *
	 * @todo: Refactor to use best practices for this type of response.
	 */
	public function respond_json_and_die( $data ) {
		// Doing it in a custom way instead of using wp_send_json so that we
		// have more control over browser caching.
		try {
			header( 'Pragma: no-cache' );
			header( 'Cache-Control: no-cache' );
			header( 'Expires: Thu, 01 Dec 1994 16:00:00 GMT' );
			header( 'Connection: close' );
			header( 'Content-Type: application/json' );

			// The response body is optional.
			if ( isset( $data ) ) {
				echo wp_json_encode( $data );
			}
		} catch ( \Exception $e ) {
			header( 'Content-Type: text/plain' );
			echo esc_html( 'Exception in respond_and_die(...): ' . $e->getMessage() );
		}

		// Don't show any normal WP rendering output.
		wp_die( '', '', array( 'response' => null ) );
	}

	/**
	 * Reset all ads.txt options and logic back to initial plugin state.
	 */
	public function reset_ads_txt_handling() {
		wp_clear_scheduled_hook( 'get_ad_text_cron_event' );
		wp_clear_scheduled_hook( 'mcp_verify_ads_txt_health_event' );
		flush_rewrite_rules();
	}
}
