<?php

namespace Mediavine\MCP;

/**
 * Handles functionality related to CSP.
 */
class Security {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Security constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->init_plugin_actions();
	}

	/**
	 * Initialize plugin hooks.
	 *
	 * @codeCoverageIgnore
	 */
	public function init_plugin_actions() {
		add_action( 'send_headers', array( $this, 'send_headers' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Adds a CSP header to pages.
	 */
	public function send_headers() {
		// Don't send CSP headers if on Customizer.
		$customizer = false;
		if ( function_exists( 'is_customize_preview' ) && is_customize_preview() ) {
			$customizer = true;
		}

		if ( Option::get_instance()->get_option_bool( 'block_mixed_content' ) && ! $customizer ) {
			// The catch here should only happen during unit tests and not during a normal WP bootstrap.
			try {
				header( 'Content-Security-Policy: block-all-mixed-content' );
			} catch ( \Exception $e ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Shows a notice in admin if users have an invalid combination of settings.
	 */
	public function admin_notices() {
		$option = Option::get_instance();
		if ( $option->get_option_bool( 'enable_forced_ssl' ) && ! $option->get_option_bool( 'block_mixed_content' ) ) {
			echo '<div class="notice notice-warning is-dismissible">
            <p><strong>Mediavine Control Panel</strong> &raquo; Your Content Security Policy is no longer supported. Please <a href="options-general.php?page=' . esc_attr( MV_Control_Panel::PLUGIN_DOMAIN ) . '">update your security settings</a>.</p>
            </div>';
		}
	}
}
