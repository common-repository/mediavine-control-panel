<?php
namespace Mediavine\MCP;

/**
 * Handles functionality related to checking WordPress and PHP versions.
 */
class VersionCheck {

	/**
	 * Checks for a minimum version.
	 *
	 * @param int|string $minimum Minimum version to check.
	 * @param int|string $compare 'php' to check against PHP, 'wp' to check against WP, or a specific value to check against.
	 *
	 * @return boolean True if the version is compatible
	 */
	public static function mcp_is_compatible_check( $minimum, $compare = 0 ) {
		if ( 'php' === $compare ) {
			$compare = PHP_VERSION;
		}
		if ( 'wp' === $compare ) {
			global $wp_version;
			$compare = $wp_version;
		}

		if ( version_compare( $compare, $minimum, '<' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Checks if MCP is compatible.
	 *
	 * @param boolean $return_errors Should the errors found be returned instead of false.
	 *
	 * @return boolean|array True if compatible. False or array of errors if not compatible
	 */
	public static function mcp_is_compatible( $return_errors = false ) {
		$minimum_wp    = '5.2';
		$deprecated_wp = '5.2';
		// Show a message if user is on 7.0 that they should upgrade.
		// The message appears on installs between the $minimum_php and $deprecated_php.
		// Anything below $deprecated_php will fail gracefully and not run.
		$minimum_php     = '7.3';
		$deprecated_php  = '7.2';
		$recommended_php = '8.0';
		$errors          = array();

		// Check if PHP version is compatible with MCP.
		if ( ! self::mcp_is_compatible_check( $minimum_php, 'php' ) ) {
			$errors['php']             = $minimum_php;
			$errors['recommended_php'] = $recommended_php;
		}

		// Check if PHP version is compatible with MCP.
		if ( ! self::mcp_is_compatible_check( $minimum_wp, 'wp' ) ) {
			$errors['wp'] = $minimum_wp;
		}

		// If we need to output errors to generate admin notices, check for deprecated versions as well.
		if ( $return_errors ) {
			if ( ! self::mcp_is_compatible_check( $deprecated_php, 'php' ) ) {
				$errors['deprecated_php']  = $deprecated_php;
				$errors['recommended_php'] = $recommended_php;
			}
			if ( ! self::mcp_is_compatible_check( $deprecated_wp, 'wp' ) ) {
				$errors['deprecated_wp'] = $deprecated_wp;
			}
		}

		if ( ! empty( $errors ) ) {
			if ( $return_errors ) {
				return $errors;
			}
			return false;
		}

		return true;
	}

	/**
	 * Displays a WordPress admin error notice.
	 *
	 * @param string $message Message to display in notice.
	 *
	 * @return void
	 */
	public static function mcp_admin_error_notice( $message ) {
		printf(
			'<div class="notice notice-error"><p>%1$s</p></div>',
			wp_kses(
				$message,
				array(
					'strong' => array(
						'style' => true,
					),
					'code'   => array(),
					'br'     => array(),
					'a'      => array(
						'href'   => true,
						'target' => true,
					),
					'span'   => array(
						'style' => true,
						'class' => true,
					),
				)
			)
		);
	}

	/**
	 * Adds incompatibility notices to admin if WP or PHP needs to be updated.
	 */
	public static function mcp_incompatible_notice() {
		$compatible_errors = self::mcp_is_compatible( true );
		$deactivate_plugin = false;
		if ( is_array( $compatible_errors ) ) {
			global $wp_version;

			// Incompatible PHP notice.
			if ( isset( $compatible_errors['php'] ) ) {
				$notice = sprintf(
				// translators: Required PHP version number; Recommended PHP version number; Current PHP version number; Link to learn about updating PHP.
					__( '<strong>Mediavine Control Panel</strong> requires PHP version %1$s or higher, but recommends %2$s or higher. This site is running PHP version %3$s.<br><br>%4$s.', 'mediavine' ),
					$compatible_errors['php'],
					$compatible_errors['recommended_php'],
					PHP_VERSION,
					'<a href="https://wordpress.org/support/update-php/" target="_blank">' . __( 'Learn about updating PHP', 'mediavine' ) . '</a>'
				);
				self::mcp_admin_error_notice( $notice );
				$deactivate_plugin = true;
			}

			// Incompatible WP notice.
			if ( isset( $compatible_errors['wp'] ) ) {
				if ( empty( $wp_version ) ) {
					// In rare cases, other plugins or themes may cause `$wp_version` to be empty.
					// Instead of preventing MCP from running, we want to only show a warning message.
					$notice = sprintf(
					// translators: Required WP version number.
						__( '<strong>Mediavine Control Panel</strong> requires WordPress %1$s or higher, but recommends the latest version. This site is currently running an unknown version of WordPress. This may be caused by other plugins, themes, or custom code preventing Mediavine Control Panel from accessing critical WordPress information. Mediavine Control Panel will continue to run but may have issues if you are not on the latest version of WordPress.', 'mediavine' ),
						$compatible_errors['wp']
					);
					self::mcp_admin_error_notice( $notice );
				} else {
					// Show the incompatible WP notice and prevent MCP from continuing.
					$notice = sprintf(
					// translators: Required WP version number; Current WP version number.
						__( '<strong>Mediavine Control Panel</strong> requires WordPress %1$s or higher, but recommends the latest version. This site is running WordPress %2$s. Please update WordPress to activate <strong>Mediavine Control Panel</strong>.<br><br>%3$s.', 'mediavine' ),
						$compatible_errors['wp'],
						$wp_version,
						'<a href="https://wordpress.org/support/article/updating-wordpress/" target="_blank">' . __( 'Learn about updating WordPress', 'mediavine' ) . '</a>'
					);
					self::mcp_admin_error_notice( $notice );
					$deactivate_plugin = true;
				}
			}

			// Deprecated PHP warning.
			if ( isset( $compatible_errors['deprecated_php'] ) ) {
				$notice = sprintf(
				// translators: Required PHP version number; Recommended PHP version number; Current PHP version number; Link to learn about updating PHP.
					__( 'A future version of <strong>Mediavine Control Panel</strong> will require PHP version %1$s, but recommends %2$s or higher. This site is running PHP version %3$s. To maintain compatibility with <strong>Mediavine Control Panel</strong>, please upgrade your PHP version.<br><br>%4$s.', 'mediavine' ),
					$compatible_errors['deprecated_php'],
					$compatible_errors['recommended_php'],
					PHP_VERSION,
					'<a href="https://wordpress.org/support/update-php/" target="_blank">' . __( 'Learn about updating PHP', 'mediavine' ) . '</a>'
				);
				self::mcp_admin_error_notice( $notice );
			}

			// Deprecated WP warning.
			if ( isset( $compatible_errors['deprecated_wp'] ) && ! empty( $wp_version ) ) {
				$notice  = '<div style="border-bottom: solid 3px #5ca2a8; font-size: 1.25em; padding-bottom: 1em; margin-bottom: 1em;">';
				$notice .= sprintf(
				// translators: Required WP version number; Current WP version number.
					__( '<strong>Mediavine Control Panel</strong> has a new feature that allows you to upload videos to your Mediavine Dashboard without leaving WordPress! This feature requires WordPress %1$s or higher. This site is running WordPress %2$s.', 'mediavine' ),
					$compatible_errors['deprecated_wp'],
					$wp_version
				);
				$notice .= '</div>';
				$notice .= sprintf(
				// translators: Date within styled tag; Required WP version number.
					__( 'Starting %1$s, WordPress %2$s will be required for all functionality, however keeping WordPress up-to-date at the latest version is still recommended. To maintain future compatibility with <strong>Mediavine Control Panel</strong>, please update WordPress.', 'mediavine' ),
					'<strong style="font-size: 1.2em;">' . __( 'June 2021', 'mediavine' ) . '</strong>',
					$compatible_errors['deprecated_wp']
				);
				$notice .= '<br><br><a href="https://wordpress.org/support/article/updating-wordpress/" target="_blank">' . __( 'Learn about updating WordPress', 'mediavine' ) . '</a>';
				$notice .= '<br><br><a href="' . admin_url( 'options-general.php?page=' . MV_Control_Panel::PLUGIN_DOMAIN ) . '">' . __( "If you need assistance, click this link and then click on the Intercom button in the bottom right to contact a member of Mediavine's support team.", 'mediavine' ) . '</a>';
				self::mcp_admin_error_notice( $notice );
			}

			// Should we deactivate the plugin?
			if ( $deactivate_plugin ) {
				self::mcp_admin_error_notice( __( '<strong>Mediavine Control Panel</strong> has been deactivated.', 'mediavine' ) );
				deactivate_plugins( MCP_PLUGIN_BASENAME );
			}
		}
	}
}
