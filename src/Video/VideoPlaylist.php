<?php
namespace Mediavine\MCP\Video;

use Mediavine\MCP\Option;

/**
 * Handles functionality related to video playlists.
 */
class VideoPlaylist {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Creates and initializes class.
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Hooks to be run on class instantiation.
	 */
	public function init() {
		add_shortcode( 'mv_playlist', array( $this, 'playlist_script_shortcode' ) );
	}

	/**
	 * Create the markup for embedded Mediavine Playlists.
	 *
	 * @param  array $settings contains necessary variables for creation of embed.
	 * @return string HTML to render div tag for Mediavine Playlists
	 */
	public function playlist_markup_template( $settings ) {
		if ( empty( $settings['id'] ) ) {
			return '';
		}

		// Don't output video tag if Relevanssi search result.
		if ( is_search() && function_exists( 'relevanssi_init' ) ) {
			return '';
		}

		// Output placeholder if admin ads are disabled and user has admin rights.
		if ( Option::get_instance()->get_option_bool( 'disable_admin_ads' ) && current_user_can( 'edit_posts' ) ) {
			$placeholder = '
			<div class="mv-playlist-id-placeholder" style="height:0;padding-top:56.25%;position:relative;background:#000;">
				<div style="position:absolute;top:0;bottom:0;left:0;right:0;display:flex;justify-content:center;align-items:center;">
					<div style="text-align:center;color:#fff;">
						<strong style="display:block;font-size:1.1em;">' . __( 'Mediavine Playlist Placeholder', 'mediavine' ) . '</strong>' . __( 'Playlist only displays when ad script wrapper is loaded on page', 'mediavine' ) .
					'</div>
				</div>
			</div>';

			// We need to modify the safe styles added so `wp_kses` doesn't remove them.
			add_filter( 'safe_style_css', array( Video::get_instance(), 'add_position_styles' ) );

			return $placeholder;
		}

		$settings_markup    = ' data-playlist-id="' . esc_attr( $settings['id'] ) . '"';
		$requested_settings = array(
			'sticky',
			'autoplay',
			'disable_auto_upgrade',
			'volume',
			'jsonld',
			'ratio',
			'featured',
		);
		foreach ( $requested_settings as $setting ) {
			if ( ! empty( $settings[ $setting ] ) ) {
				$settings_markup .= ' ' . trim( $settings[ $setting ] );
			}
		}

		// No need to escape `$settings_markup` again as they have already been escaped.
		return '<div class="mv-video-target mv-playlist-id-' . esc_attr( $settings['id'] ) . '"' . $settings_markup . '></div>';
	}

	/**
	 * Checks if param isset and equals string of 'true'.
	 *
	 * We need to be this precise when working with WordPress shortcodes.
	 *
	 * @param string $param Value to check.
	 *
	 * @return bool
	 */
	public function isset_and_true( $param ) {
		if (
			isset( $param ) &&
			'true' === $param
		) {
			return true;
		}

		return false;
	}

	/**
	 * Render markup via shortcode to display Mediavine Playlist.
	 *
	 * @param  array $attributes Attributes from post shortcode.
	 * @return string HTML to render div and script tag for Mediavine Playlist
	 */
	public function playlist_script_shortcode( $attributes ) {
		if ( is_admin() ) {
			return '';
		}

		if ( empty( $attributes['id'] ) ) {
			return '';
		}

		$settings = array(
			'sticky'               => '',
			'autoplay'             => '',
			'disable_auto_upgrade' => '',
			'volume'               => 'data-volume="70"',
			'jsonld'               => 'data-disable-jsonld="1"',
			'ratio'                => '',
		);

		if ( isset( $attributes['id'] ) ) {
			$settings['id'] = esc_attr( $attributes['id'] );
		}

		if ( $this->isset_and_true( $attributes['donotoptimizeplacement'] ) ) {
			$settings['sticky']   = 'data-sticky="1"';
			$settings['autoplay'] = 'data-autoplay="1"';
		}

		if ( $this->isset_and_true( $attributes['donotautoplaynoroptimizeplacement'] ) ) {
			$settings['disable_auto_upgrade'] = 'data-disable-auto-upgrade="1"';
		}

		if ( isset( $attributes['volume'] ) ) {
			$settings['volume'] = 'data-volume="' . esc_attr( $attributes['volume'] ) . '"';
		}

		if ( $this->isset_and_true( $attributes['jsonld'] ) ) {
			$settings['jsonld'] = '';
		}

		if ( isset( $attributes['ratio'] ) ) {
			$settings['ratio'] = 'data-ratio="' . esc_attr( $attributes['ratio'] ) . '"';
		}

		$template = $this->playlist_markup_template( $settings );

		return wp_kses( $template, Video::get_instance()->allowed_video_html() );
	}
}
