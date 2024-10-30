<?php
namespace Mediavine\MCP\Video;

use Mediavine\MCP\Option;
use Mediavine\MCP\OfferingCheck;

/**
 * Handles general Video functionality for MCP.
 */
class Video {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Reference to featured video.
	 *
	 * @var VideoFeatured
	 */
	protected $featured;

	/**
	 * Hooks to be run on class instantiation.
	 */
	public function init() {
		// This will also init VideoPlaylist indirectly.
		$this->featured = VideoFeatured::get_instance();

		add_shortcode( 'mv_video', array( $this, 'video_script_shortcode' ) );
	}

	/**
	 * Add position styles to the default WP safe styles filter.
	 *
	 * WordPress blocks all position styles when running thorugh `wp_kses` of any sort,
	 * so we need to allow those styles.
	 *
	 * @param array $styles WP Safe styles.
	 * @return array WP safe styles with postion styles added
	 */
	public function add_position_styles( $styles ) {
		$position_styles = array(
			'position',
			'top',
			'bottom',
			'left',
			'right',
		);

		return array_merge( $styles, $position_styles );
	}

	/**
	 * Create the markup for embedded Mediavine Videos
	 *
	 * @param  array $settings contains necessary variables for creation of embed.
	 * @return string HTML to render div tag for Mediavine Videos
	 */
	public function video_markup_template( $settings ) {
		if ( empty( $settings['key'] ) ) {
			return '';
		}

		// Don't output video tag if Relevanssi search result.
		if ( is_search() && function_exists( 'relevanssi_init' ) ) {
			return '';
		}

		// Output placeholder if admin ads are disabled and user has admin rights.
		if ( Option::get_instance()->get_option_bool( 'disable_admin_ads' ) && current_user_can( 'edit_posts' ) ) {
			$offering_name = OfferingCheck::get_offering_name();
			$placeholder   = '
			<div class="mv-video-id-placeholder" style="height:0;padding-top:56.25%;position:relative;background:#000;">
				<div style="position:absolute;top:0;bottom:0;left:0;right:0;display:flex;justify-content:center;align-items:center;">
					<div style="text-align:center;color:#fff;">
						<strong style="display:block;font-size:1.1em;">' . esc_html( $offering_name ) . __( ' Video Placeholder', 'mediavine' ) . '</strong>
					</div>
					<div style="text-align:center;color:#fff;">' . __( 'Video only displays when ad script wrapper is loaded on page', 'mediavine' ) . '</div>
				</div>
			</div>';

			// We need to modify the safe styles added so `wp_kses` doesn't remove them.
			add_filter( 'safe_style_css', array( $this, 'add_position_styles' ) );

			return $placeholder;
		}

		$settings_markup    = ' data-video-id="' . esc_attr( $settings['key'] ) . '"';
		$requested_settings = array(
			'ratio',
			'volume',
			'sticky',
			'disable_optimize',
			'disable_autoplay',
			'jsonld',
			'featured',
		);
		foreach ( $requested_settings as $setting ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				$settings_markup .= ' ' . trim( $settings[ $setting ] );
			}
		}

		// No need to escape `$settings_markup` again as they have already been escaped.
		return '<div class="mv-video-target mv-video-id-' . esc_attr( $settings['key'] ) . '"' . $settings_markup . '></div>';
	}

	/**
	 * Helper function to normalize video shortcode attributes.
	 *
	 * Sets undefined values to false and changes single attribute values to associative attributes
	 *
	 * @param array $attributes Attributes array to be normalized.
	 * @return array Normalized attributes array
	 */
	public function normalize_attributes( $attributes ) {
		foreach ( $attributes as $key => &$value ) {
			if ( 'undefined' === $value ) {
				$value = 'false';
			}

			// Fixes issue where attributes were added as a single attribute, rather than a key value attribute.
			$normalized_atts = array(
				'sticky',
				'doNotOptimizePlacement',
				'doNotAutoplayNorOptimizePlacement',
			);
			if ( in_array( $value, $normalized_atts, true ) ) {
				// Only replace value if it doesn't already exist.
				if ( ! isset( $attributes[ $value ] ) ) {
					$attributes[ $value ] = 'true';
				}
				unset( $attributes[ $key ] );
			}
		}

		return $attributes;
	}

	/**
	 * Returns the allowed HTML for video markup to be used in wp_kses.
	 *
	 * @return array
	 */
	public function allowed_video_html() {
		return array(
			'div'    => array(
				'id'                        => array(),
				'class'                     => array(),
				'data-video-id'             => array(),
				'data-playlist-id'          => array(),
				'data-value'                => array(),
				'data-sticky'               => array(),
				'data-autoplay'             => array(),
				'data-ratio'                => array(),
				'data-volume'               => array(),
				'data-disable-auto-upgrade' => array(),
				'data-disable-optimize'     => array(),
				'data-disable-autoplay'     => array(),
				'data-disable-jsonld'       => array(),
				'data-force-optimize'       => array(),
				'style'                     => array(),
			),
			'strong' => array(
				'style' => array(),
			),
		);
	}

	/**
	 * Render markup via shortcode to display Mediavine Videos.
	 *
	 * @param  array $attributes Attributes from post shortcode.
	 * @return string HTML to render div and script tag for Mediavine Videos
	 */
	public function video_script_shortcode( $attributes ) {
		if ( is_admin() ) {
			return '';
		}

		if ( empty( $attributes['key'] ) ) {
			return '';
		}

		// Normalize attributes.
		$attributes = $this->normalize_attributes( $attributes );

		$settings = array(
			'disable_optimize' => '',
			'disable_autoplay' => '',
			'sticky'           => '',
			'ratio'            => '',
			'jsonld'           => '',
			'volume'           => 'data-volume="70"',
		);

		if ( isset( $attributes['key'] ) ) {
			$settings['key'] = esc_attr( $attributes['key'] );
		}

		if ( isset( $attributes['sticky'] ) && ( 'true' === $attributes['sticky'] ) ) {
			$settings['sticky']           = 'data-sticky="1" data-autoplay="1"';
			$settings['disable_optimize'] = 'data-disable-optimize="1"';
		}

		if ( isset( $attributes['donotoptimizeplacement'] ) && ( 'true' === $attributes['donotoptimizeplacement'] ) ) {
			$settings['disable_optimize'] = 'data-disable-optimize="1"';
		}

		if ( isset( $attributes['donotautoplaynoroptimizeplacement'] ) && ( 'true' === $attributes['donotautoplaynoroptimizeplacement'] ) ) {
			$settings['disable_optimize'] = 'data-disable-optimize="1"';
			$settings['disable_autoplay'] = 'data-disable-autoplay="1"';
		}

		if (
			isset( $attributes['jsonld'] ) &&
			( 'false' === $attributes['jsonld'] )
		) {
			$settings['jsonld'] = 'data-disable-jsonld="true"';
		}

		if ( isset( $attributes['ratio'] ) ) {
			$settings['ratio'] = 'data-ratio="' . esc_attr( $attributes['ratio'] ) . '"';
		}

		if ( isset( $attributes['volume'] ) ) {
			$settings['volume'] = 'data-volume="' . esc_attr( $attributes['volume'] ) . '"';
		}

		$template = $this->video_markup_template( $settings );

		return wp_kses( $template, $this->allowed_video_html() );
	}
}
