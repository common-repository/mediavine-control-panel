<?php
namespace Mediavine\MCP;

use Mediavine\MCP\Upstream;

/**
 * Handles functionality related to a publisher's offering.
 *
 * The 'offering' object, which is obtained from a publisher's data file,
 * contains the following properties: id, name, gam_network_code, and offering_code.
 * OfferingCheck utilizes name and offering_code.
 */
class OfferingCheck {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * The option stored with this (prefix +) name is a string.
	 *
	 * @var string
	 */
	const OFFERING_CODE_OPTION_SLUG = 'offering_code';

	/**
	 * The option stored with this (prefix +) name is a string.
	 *
	 * @var string
	 */
	const OFFERING_DOMAIN_OPTION_SLUG = 'offering_domain';

	/**
	 * The option stored with this (prefix +) name is a string.
	 *
	 * @var string
	 */
	const OFFERING_NAME_OPTION_SLUG = 'offering_name';

	/**
	 * Hook into WP lifecycle.
	 */
	public function init() {
		// Check and update offering name and offering code if site slug changes.
		add_action( 'update_option_mcp_site_id', array( $this, 'update_offering_from_upstream' ) );
		// Check and update offering when the site_id is first entered, too.
		add_action( 'add_option_mcp_site_id', array( $this, 'update_offering_from_upstream' ) );
		// Schedule a daily offering check.
		add_action( 'mcp_offering_check_event', array( $this, 'update_offering_from_upstream' ), 10 );
	}

	/**
	 * Setup default values for offering name and offering code. Called on plugin activation or update.
	 */
	public static function set_default_offering() {
		Option::get_instance()->update_option( self::OFFERING_CODE_OPTION_SLUG, 'mediavine' );
		Option::get_instance()->update_option( self::OFFERING_DOMAIN_OPTION_SLUG, 'mediavine.com' );
		Option::get_instance()->update_option( self::OFFERING_NAME_OPTION_SLUG, 'Mediavine' );
	}

	/**
	 * Check data from Upstream and update the publisher's stored
	 * offering values, if necessary.
	 */
	public function update_offering_from_upstream() {
		// If site_id is cleared, offering should default to 'mediavine'.
		$site_id = Option::get_instance()->get_option( 'site_id' );
		if ( empty( $site_id ) ) {
			$this->set_default_offering();
		}

		$data = Upstream::get_instance()->get_upstream();
		if ( ! empty( $data ) ) {
			$offering_code   = $data['offering']['offering_code'];
			$offering_domain = $data['offering']['offering_domain'];
			$offering_name   = $data['offering']['name'];

			// Normalize offering name for main offering.
			if ( 'Mediavine Ad Management' === $data['offering']['name'] ) {
				$offering_name = 'Mediavine';
			}

			Option::get_instance()->update_option( self::OFFERING_CODE_OPTION_SLUG, $offering_code );
			Option::get_instance()->update_option( self::OFFERING_DOMAIN_OPTION_SLUG, $offering_domain );
			Option::get_instance()->update_option( self::OFFERING_NAME_OPTION_SLUG, $offering_name );
		}
	}

	/**
	 * Get the publisher's stored offering code, or if none, the default offering code.
	 *
	 * @return string
	 */
	public static function get_offering_code() {
		$offering_code = Option::get_instance()->get_option( self::OFFERING_CODE_OPTION_SLUG, 'mediavine' );

		return $offering_code;
	}

	/**
	 * Get the publisher's stored offering domain, or if none, the default offering domain.
	 *
	 * @return string
	 */
	public static function get_offering_domain() {
		return Option::get_instance()->get_option( self::OFFERING_DOMAIN_OPTION_SLUG, 'mediavine.com' );
	}

	/**
	 * Get the publisher's stored offering name, or if none, the default offering name.
	 *
	 * @return string
	 */
	public static function get_offering_name() {
		$offering_name = Option::get_instance()->get_option( self::OFFERING_NAME_OPTION_SLUG, 'Mediavine' );

		return $offering_name;
	}

	/**
	 * Schedule a daily check of the publisher's offering data.
	 */
	public static function schedule_check_offering_task() {
		if ( false === wp_next_scheduled( 'mcp_offering_check_event' ) ) {
			wp_schedule_event( gmdate( 'U' ), 'daily', 'mcp_offering_check_event' );
		}
	}
}
