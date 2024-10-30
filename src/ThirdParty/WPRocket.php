<?php

namespace Mediavine\MCP\ThirdParty;

use Mediavine\MCP\OfferingCheck;

/**
 * Handles integration between MCP and WP Rocket.
 */
class WPRocket {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Hooks to be run on class instantiation.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		add_filter( 'rocket_delay_js_exclusions', array( $this, 'add_rocket_js_exclusions' ) );
		add_filter( 'rocket_exclude_defer_js', array( $this, 'add_rocket_js_exclusions' ) );
		add_filter( 'rocket_defer_inline_exclusions', array( $this, 'add_rocket_js_exclusions' ) );
		add_filter( 'rocket_minify_excluded_external_js', array( $this, 'add_rocket_js_exclusions_by_domain' ) );
	}

	/**
	 * Exclude scripts from WP Rocket JS delay and defer.
	 *
	 * @param array $excluded List of excluded JS config.
	 *
	 * @return array
	 */
	public function add_rocket_js_exclusions( $excluded = array() ) {
		// Fail gracefully in case WP Rocket decides to change how the parameter
		// gets passed in the future.
		if ( ! is_array( $excluded ) ) {
			return $excluded;
		}

		$excluded[] = 'mediavine';
		$excluded[] = 'pubnation';
		$excluded[] = 'social-pug';

		$offering_domain = OfferingCheck::get_offering_domain();
		$offering_domain = str_ireplace( '.com', '', $offering_domain );
		if ( ! in_array( $offering_domain, $excluded, true ) ) {
			$excluded[] = $offering_domain;
		}

		return $excluded;
	}

	/**
	 * Exclude scripts from WP Rocket JS combine and minify.
	 *
	 * @param array $excluded List of excluded JS domains.
	 *
	 * @return array
	 */
	public function add_rocket_js_exclusions_by_domain( $excluded = array() ) {
		// Fail gracefully in case WP Rocket decides to change how the parameter
		// gets passed in the future.
		if ( ! is_array( $excluded ) ) {
			return $excluded;
		}

		$excluded[] = 'mediavine.com';
		$excluded[] = 'pubnation.com';
		$excluded[] = 'social-pug.com';

		$offering_domain = OfferingCheck::get_offering_domain();
		if ( ! in_array( $offering_domain, $excluded, true ) ) {
			$excluded[] = $offering_domain;
		}

		return $excluded;
	}
}
