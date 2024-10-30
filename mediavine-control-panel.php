<?php
/**
 * Primary file for MCP.
 *
 * @category     WordPress_Plugin
 * @package      Mediavine Control Panel
 * @author       Mediavine
 * @license      http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link         https://www.mediavine.com
 *
 * Plugin Name: Mediavine Control Panel
 * Plugin URI: https://www.mediavine.com/
 * Description: Manage your ads, analytics and more with our lightweight plugin!
 * Version: 2.10.5
 * Requires at least: 5.2
 * Requires PHP: 7.3
 * Author: Mediavine
 * Author URI: https://www.mediavine.com
 * Text Domain: mcp
 * License: GPL2
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

// Autoload via Composer.
require_once __DIR__ . '/vendor/autoload.php';

add_action( 'admin_notices', '\Mediavine\MCP\VersionCheck::mcp_incompatible_notice' );

if ( \Mediavine\MCP\VersionCheck::mcp_is_compatible() ) {
	// @todo: Refactor to include trailing slash to be consistent with MCP_PLUGIN_URL.
	if ( ! defined( 'MCP_PLUGIN_DIR' ) ) {
		define( 'MCP_PLUGIN_DIR', __DIR__ );
	}
	if ( ! defined( 'MCP_PLUGIN_FILE' ) ) {
		define( 'MCP_PLUGIN_FILE', __FILE__ );
	}
	// Gets set as {directory-name}/mediavine-control-panel.php.
	if ( ! defined( 'MCP_PLUGIN_BASENAME' ) ) {
		define( 'MCP_PLUGIN_BASENAME', plugin_basename( MCP_PLUGIN_FILE ) );
	}
	// Public facing URL for the plugin, with trailing slash.
	if ( ! defined( 'MCP_PLUGIN_URL' ) ) {
		define( 'MCP_PLUGIN_URL', plugin_dir_url( MCP_PLUGIN_FILE ) );
	}

	if ( class_exists( '\Mediavine\MCP\MV_Control_Panel' ) ) {
		// instantiate the plugin class.
		\Mediavine\MCP\MV_Control_Panel::get_instance();
	}
}
