<?php

namespace Mediavine\MCP;

/**
 * Handles functionality related to registering menu callbacks and loading
 * assets required by menu callbacks.
 */
class Menu {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Auto initialize menu hooks.
	 *
	 * @codeCoverageIgnore
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Perform initialization of menu callbacks.
	 *
	 * @codeCoverageIgnore
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_page_access_denied', array( $this, 'redirect_legacy' ) );
	}

	/**
	 * Add MCP settings page to admin menu.
	 */
	public function admin_menu() {
		// Add a top level link to the settings page.
		$hook = add_menu_page(
			'Support',
			'Support',
			'manage_options',
			MV_Control_Panel::PLUGIN_DOMAIN,
			'',
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( MCP_PLUGIN_DIR . '/assets/img/icon-mcp-menu.svg' ) )
		);
		// Only load the settings assets on the settings page.
		add_action( 'load-' . $hook, array( $this, 'load_settings_assets' ) );

		// Add a Settings section sub item link to the settings page.
		add_options_page(
			'Mediavine Control Panel',
			'Mediavine Control Panel',
			'edit_posts',
			MV_Control_Panel::PLUGIN_DOMAIN,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Called only on the settings page to hook into enqueue assets.
	 *
	 * @codeCoverageIgnore
	 */
	public function load_settings_assets() {
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_settings_assets',
			)
		);
	}

	/**
	 * Register and enqueue settings page assets.
	 */
	public function enqueue_settings_assets() {
		// Add launch mode settings javascript.
		$url    = MCP_PLUGIN_URL . 'assets/js/admin-launch-settings.js';
		$handle = MV_Control_Panel::PLUGIN_DOMAIN . '-admin-launch-settings-script';
		wp_register_script( $handle, $url, array(), MV_Control_Panel::VERSION, true );
		wp_localize_script(
			$handle,
			'mcpLaunchSettings',
			array(
				'refreshLaunchModeNonce' => wp_create_nonce( 'refresh-launch-mode' ),
				'disableLaunchModeNonce' => wp_create_nonce( 'disable-launch-mode' ),
			)
		);
		wp_enqueue_script( $handle );

		// Add adstxt settings javascript.
		$url    = MCP_PLUGIN_URL . 'assets/js/admin-adstxt-settings.js';
		$handle = MV_Control_Panel::PLUGIN_DOMAIN . '-admin-adstxt-settings-script';
		wp_register_script( $handle, $url, array(), MV_Control_Panel::VERSION, true );
		wp_localize_script(
			$handle,
			'mcpAdsTxtSettings',
			array(
				'recheckAdTextNonce' => wp_create_nonce( 'recheck-ad-text' ),
				'writeAdTextNonce'   => wp_create_nonce( 'write-ad-text' ),
				'enableAdTextNonce'  => wp_create_nonce( 'enable-ad-text' ),
				'disableAdTextNonce' => wp_create_nonce( 'disable-ad-text' ),
			)
		);
		wp_enqueue_script( $handle );

		// Add admin settings page stylesheet.
		$url    = MCP_PLUGIN_URL . 'assets/css/admin-settings.css';
		$handle = MV_Control_Panel::PLUGIN_DOMAIN . '-admin-settings-styles';
		wp_register_style( $handle, $url, array(), MV_Control_Panel::VERSION );
		wp_enqueue_style( $handle );
	}

	/**
	 * Render settings admin page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html( 'You do not have sufficient permissions to access this page.' ) );
		}

		include MCP_PLUGIN_DIR . '/views/settings.php';
	}

	/**
	 * Initialize admin UI.
	 *
	 * @codeCoverageIgnore
	 */
	public function admin_init() {
		add_filter(
			'plugin_action_links_' . MCP_PLUGIN_BASENAME,
			array(
				$this,
				'add_action_links',
			)
		);
	}

	/**
	 * Adds links to plugins page row for MCP.
	 *
	 * @param array $links WP array of links used for admin menus.
	 */
	public function add_action_links( $links ) {
		return array_merge(
			$links,
			array(
				'<a href="' . admin_url( 'options-general.php?page=' . MV_Control_Panel::PLUGIN_DOMAIN ) . '">Settings</a>',
				'<a href="https://help.mediavine.com/">Support</a>',
			)
		);
	}

	/**
	 * Redirect old versions of the admin settings page to the new settings page.
	 */
	public function redirect_legacy() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$url = strtolower( admin_url( $_SERVER['REQUEST_URI'] ) );
		if ( false === strpos( $url, '/wp-admin/options-general.php?page=mediavine_amp_settings' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( '/options-general.php?page=' . MV_Control_Panel::PLUGIN_DOMAIN ) );
		exit();
	}
}
