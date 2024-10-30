<?php

namespace Mediavine\MCP;

/**
 * Small Utility Class
 */
class Utility {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Remove null variables.
	 *
	 * @param array $array loopable variables.
	 *
	 * @return array
	 */
	public function filter_null( $array ) {
		return array_filter(
			$array,
			function ( $var ) {
				return ! is_null( $var );
			}
		);
	}

	/**
	 * Get value or return null.
	 *
	 * @param array  $array list of variables.
	 * @param string $index index being looked for.
	 *
	 * @return null|array
	 */
	public function get_or_null( $array, $index ) {
		if ( array_key_exists( $index, $array ) ) {
			return $array[ $index ];
		}

		return null;
	}

	/**
	 * Safely implodes an array that may contain nested arrays
	 *
	 * @todo: Remove need to use as a static function.
	 *
	 * @param string $glue what to place between imploded values.
	 * @param array  $orig_array the array to be imploded, may contain nested arrays.
	 * @return string   safely imploded array/multi-dimensional array
	 */
	public static function multi_implode( $glue = '', $orig_array = array() ) {
		foreach ( $orig_array as $ind => $value ) {
			if ( is_array( $value ) ) {
				$orig_array[ $ind ] = self::multi_implode( '', $value );
			}
		}

		return implode( $glue, $orig_array );
	}

	/**
	 * Checks that the parsed route matches a string
	 *
	 * @todo: Remove need to use as a static function.
	 *
	 * @param string $needle Value to search for.
	 * @param \WP    $query Current WordPress environment instance (passed by reference).
	 * @return bool Matching route
	 */
	public static function check_parse_route( $needle, $query ) {
		if ( ! property_exists( $query, 'query_vars' ) || ! is_array( $query->query_vars ) ) {
			return false;
		}

		$query_vars_as_string = self::multi_implode( '', $query->query_vars );
		$query_request        = ( ! empty( $query->request ) ) ? $query->request : '';

		if ( in_array( $needle, array( $query_vars_as_string, $query_request ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Process redirect after user hits the specific url.
	 *
	 * If user hits this and site id is missing will redirect to home.
	 *
	 * @todo: Remove need to use as a static function.
	 *
	 * @param string $url The URL to redirect to.
	 *
	 * @return string|void Return $url if testing and site_id set
	 */
	public static function fire_redirect( $url = '' ) {
		if ( ! empty( $url ) ) {
			// Return early when testing so headers aren't thrown.
			if ( defined( 'MV_TESTING_BYPASS_REDIRECTS' ) ) {
				return $url;
			}

			\wp_safe_redirect( $url, 301 );
			exit();
		}

		// Return early when testing so headers aren't thrown.
		if ( defined( 'MV_TESTING_BYPASS_REDIRECTS' ) ) {
			return;
		}

		\wp_safe_redirect( '/', 302 );
		exit();
	}

	/**
	 * Prints an admin error to the page. The text should be pre-translated.
	 *
	 * @todo: Remove need to use as a static function.
	 *
	 * @param string $notice Pre-translated message string.
	 */
	public static function print_error( $notice ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php print esc_html( $notice ); ?></p>
		</div>
		<?php
	}


}
