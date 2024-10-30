<?php
namespace Mediavine\MCP\ThirdParty;

use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;
use Mediavine\MCP\OfferingCheck;

/**
 * Adds functionality related to Web Stories advertisements.
 */
class WebStories {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;



	/**
	 * Hooks to be run on class instantiation.
	 */
	public function init() {
		if ( Upstream::is_launch_mode_enabled() ) {
			return;
		}

		add_action( 'web_stories_print_analytics', array( $this, 'output_mv_web_stories_ads' ) );
	}

	/**
	 * Checks if Web Stories plugin is installed.
	 *
	 * @return bool
	 */
	public function has_web_stories() {
		// Attempt to determine by class name and use a backup if this external class is renamed in the future.
		if ( class_exists( 'Web_Stories_Compatibility' ) ) {
			return true;
		}

		// Use `is_plugin_active` if we are far enough in the bootstrap when plugins are loaded.
		if ( function_exists( 'is_plugin_active' ) ) {
			return is_plugin_active( 'web-stories/web-stories.php' );
		}

		return false;
	}

	/**
	 * Gets the <amp-story-auto-ads> markup for Web Stories.
	 *
	 * @param string $slot Slot path for ads, including Mediavine ID and site adunit.
	 * @return string
	 */
	public function get_amp_story_auto_ads_markup( $slot ) {
		$slug = Option::get_instance()->get_option( 'site_id' );

		return '
		<amp-story-auto-ads>
			<script type="application/json">
				{
					"ad-attributes": {
						"type": "doubleclick",
						"data-slot": "' . $slot . '",
						"json": {
							"targeting": {
								"slot": "web_story",
								"google": "1",
								"amp": "1",
								"site": "' . $slug . '"
							}
						}
					}
				}
			</script>
		</amp-story-auto-ads>';
	}

	/**
	 * Gets the <amp-consent> markup for Web Stories.
	 *
	 * @return string
	 */
	public function get_amp_consent_markup() {
		return '
		<amp-consent id="myConsent" layout="nodisplay">
			<script type="application/json">
			{
				"consents": {
					"myConsent": {
						"consentInstanceId": "mv-amp-story-consent",
						"promptIfUnknownForGeoGroup": "eu",
						"promptUI": "consentUI"
					}
				},
				"consentRequired": true
			}
			</script>
			<amp-story-consent id="consentUI" layout="nodisplay">
				<script type="application/json">
					{
						"title": "We need your help!",
						"message": "This site and certain third parties would like to set cookies and access and collect data to provide you with personalized content and advertisements. If you would like this personalized experience, simply click \"accept\". If you would like to opt-out of this data collection, please click \"decline\" to continue without personalization.",
						"vendors": ["Mediavine"]
					}
				</script>
			</amp-story-consent>
		</amp-consent>';
	}

	/**
	 * Gets the `<amp-geo>` json
	 *
	 * @return string
	 */
	public function get_geo_json() {
		return '<script type="application/json">
		{
			"ISOCountryGroups": {
				"eu": ["at", "be", "bg", "cy", "cz", "de", "dk", "ee", "es", "fi", "fr", "gb", "gr", "hu", "hr", "ie", "it", "lt", "lu", "lv", "mt", "nl", "pl", "pt", "ro", "se", "si", "sk", "uk"]
			}
		}
	</script>';
	}

	/**
	 * Gets the `<amp-geo>` markup
	 *
	 * @return string
	 */
	public function get_amp_geo_markup() {
		return '<amp-geo layout="nodisplay">' . $this->get_geo_json() . '</amp-geo>';
	}

	/**
	 * Build ad slot tag.
	 *
	 * Format without MCM: /1030006/$adunit/amp
	 * Format with MCM:  /1030006,$mcmCode/$adunit/amp
	 *
	 * @param string $adunit_name The internal ad unit name.
	 * @return string
	 */
	public function get_ad_slot( $adunit_name ) {
		// Decide whether to include the MCM insert.
		$mcm_insert = '';
		if ( Upstream::is_mcm_enabled() ) {
			$mcm_insert = ',' . Upstream::mcm_code();
		}

		return '/' . Upstream::get_instance()->get_parent_publisher_id() . $mcm_insert . '/' . $adunit_name . '/amp';
	}

	/**
	 * Special HTML tags to allow in the output.
	 *
	 * @return array
	 */
	public function get_allowed_tags() {
		return array(
			'amp-consent'        => array(
				'id'     => true,
				'layout' => true,
			),
			'amp-geo'            => array(
				'layout' => true,
			),
			'amp-story-auto-ads' => true,
			'amp-story-consent'  => array(
				'id'     => true,
				'layout' => true,
			),
			'script'             => array(
				'type' => true,
			),
		);
	}

	/**
	 * Assemble the HTML components for Amp Web Stories ads.
	 *
	 * @param string $slot Slot path for ads, including Mediavine ID and site adunit.
	 * @return string HTML output.
	 */
	public function get_final_markup( $slot ) {
		return $this->get_amp_story_auto_ads_markup( $slot ) .
			$this->get_amp_geo_markup() . $this->get_amp_consent_markup();
	}

	/**
	 * Outputs markup for Web Stories Ads.
	 */
	public function output_mv_web_stories_ads() {
		// Only move forward if Web Stories ads are enabled.
		if ( ! Option::get_instance()->get_option_bool( 'enable_web_story_ads', true ) ) {
			return;
		}

		if ( Upstream::is_launch_mode_enabled() ) {
			return;
		}

		if ( ! Upstream::is_google_enabled() ) {
			return;
		}

		// Make sure we have an adunit name before proceeding.
		$adunit_name = $this->get_adunit_name();
		if ( empty( $adunit_name ) ) {
			return;
		}

		$slot = $this->get_ad_slot( $adunit_name );

		echo wp_kses( $this->get_final_markup( $slot ), $this->get_allowed_tags() );
	}

	/**
	 * Gets the adunit name for a Mediavine publisher based on site slug.
	 *
	 * The value is cached in an option. The option is cleared with every
	 * reactivation and update of MCP, as well as with core WP updates.
	 *
	 * @return string
	 */
	public function get_adunit_name() {
		// No adunit without the site slug.
		$slug = Option::get_instance()->get_option( 'site_id' );
		if ( empty( $slug ) ) {
			return '';
		}

		// Attempt to pull from cached previous data.
		$adunit_name = Option::get_instance()->get_option( 'adunit_name' );
		if ( ! empty( $adunit_name ) ) {
			return $adunit_name;
		}

		// We need to pull the value from the publisher's data.
		$offering_code = OfferingCheck::get_offering_code();
		$url           = 'https://scripts.' . $offering_code . '.com/tags/' . $slug . '.json';

		/**
		 * Filters the endpoint url used to retrieve adunit name.
		 *
		 * @param array $url Supports any valid full URL.
		 */
		$url      = apply_filters( 'mv_cp_adunit_endpoint_url', $url );
		$response = wp_remote_get( $url );

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['adunit'] ) ) {
			$adunit_name = $body['adunit'];

			// Store as option.
			Option::get_instance()->update_option( 'adunit_name', $adunit_name );
		}

		return $adunit_name;
	}
}
