<?php
namespace Mediavine\MCP\Video;

use Mediavine\MCP\Option;
use Mediavine\MCP\Utility;

/**
 * Handles functionality related to the Video Sitemap.
 */
class VideoSitemap {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Link functions to WP Lifecycle.
	 */
	public function init() {
		add_action( 'init', array( $this, 'create_rewrites' ) );
		add_action( 'update_option_mcp_video_sitemap_enabled', 'flush_rewrite_rules' );

		add_filter( 'allowed_redirect_hosts', array( $this, 'allowed_hosts' ) );
	}

	/**
	 * Adds 'sitemaps.mediavine.com' to allowed hosts for redirects.
	 *
	 * @param array $hosts Existing host array.
	 * @return array Hosts
	 */
	public function allowed_hosts( $hosts ) {
		$hosts[] = 'sitemaps.mediavine.com';

		return $hosts;
	}

	/**
	 * Detects setting for video sitemap.
	 *
	 * @return bool
	 */
	public static function is_video_sitemap_enabled() {
		return Option::get_instance()->get_option_bool( 'video_sitemap_enabled' );
	}

	/**
	 * Adds rewrite rules for catching 'mv-video-sitemap'.
	 */
	public function create_rewrites() {
		if ( $this::is_video_sitemap_enabled() ) {
			add_action( 'parse_request', array( $this, 'parse_sitemap_route' ) );
		}
	}

	/**
	 * Gets the sitemap URL from the site ID.
	 *
	 * @return string|null Sitemap URL based of the ID. Null if no site id.
	 */
	public function get_sitemap_url() {
		$url     = null;
		$site_id = Option::get_instance()->get_option( 'site_id' );
		if ( ! empty( $site_id ) ) {
			$url = 'https://sitemaps.mediavine.com/sites/' . $site_id . '/video-sitemap.xml';
		}

		return $url;
	}

	/**
	 * Parse sitemap route to identify if it should be pass to `fire_redirect()`.
	 *
	 * @param \WP $query Current WordPress environment instance (passed by reference).
	 * @return bool
	 */
	public function parse_sitemap_route( $query ) {
		if ( ! Utility::check_parse_route( 'mv-video-sitemap', $query ) ) {
			return false;
		}

		// Get URL from site ID.
		$url = $this->get_sitemap_url();
		if ( ! empty( $url ) ) {
			Utility::fire_redirect( $url );
		}

		return true;
	}
}
