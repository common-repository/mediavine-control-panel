<?php
namespace Mediavine\MCP;

/**
 * Handles functionality related to Ad Settings block.
 */
class AdSettings {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Hooks to be run on class instantiation
	 *
	 * @return void
	 */
	public function init() {
		add_shortcode( 'mv_ad_settings', array( $this, 'ad_settings_shortcode' ) );
	}

	/**
	 * Normalizes the attributes after they have been added by gutenberg
	 *
	 * @param array $atts An array of attributes to normalize.
	 * @return array
	 */
	public function normalize_attributes( $atts ) {
		if ( ! empty( $atts['embedcode'] ) ) {
			$atts['embedcode'] = urldecode( $atts['embedcode'] );
		}

		return $atts;
	}

	/**
	 * Render markup via shortcode to control Mediavine ad settings
	 *
	 * @param  array $atts Attributes from post shortcode.
	 * @return string HTML to render div for Mediavine ad settings
	 */
	public function ad_settings_shortcode( $atts ) {
		if ( is_admin() ) {
			return '';
		}

		if ( empty( $atts['embedcode'] ) ) {
			return '';
		}

		$atts = $this->normalize_attributes( $atts );

		// Don't output if past expires date.
		if ( ! empty( $atts['disableuntil'] ) && gmdate( 'Y-m-d' ) > $atts['disableuntil'] ) {
			return '';
		}

		return wp_kses(
			$atts['embedcode'],
			array(
				'div' => array(
					'id'                                => array(),
					'data-blocklist-leaderboard'        => array(),
					'data-blocklist-sidebar-atf'        => array(),
					'data-blocklist-sidebar-btf'        => array(),
					'data-blocklist-content-desktop'    => array(),
					'data-blocklist-content-mobile'     => array(),
					'data-blocklist-adhesion-mobile'    => array(),
					'data-blocklist-adhesion-tablet'    => array(),
					'data-blocklist-adhesion-desktop'   => array(),
					'data-blocklist-recipe'             => array(),
					'data-blocklist-auto-insert-sticky' => array(),
					'data-blocklist-in-image'           => array(),
					'data-blocklist-chicory'            => array(),
					'data-blocklist-zergnet'            => array(),
					'data-expires-at'                   => array(),
				),
			)
		);
	}
}
