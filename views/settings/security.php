<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\Option;

/**
 * MVSecurity Template
 *
 * @category Template
 * @package  Mediavine Control Panel
 * @author   Mediavine
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.mediavine.com
 */

$option = Option::get_instance();

if ( $option->get_option_bool( 'block_mixed_content' ) ) {
	$option->update_option( 'enable_forced_ssl', false );
}
?>

<div class="option-group">
	<h3>Security Policy</h3>
	<div class="notice notice-warning notice-alt">
		<p><strong>Important: </strong>Before changing your security settings, make sure to follow <a href="https://help.mediavine.com/advanced/https-upgrading-your-csp-to-block-all-mixed-content-from-upgrade-insecure-requests" target="_blank">these steps</a> to avoid any headaches!</p>
	</div>
	<section class="mvoption">
		<?php if ( $option->get_option_bool( 'enable_forced_ssl' ) ) : ?>
			<div class="notice dismissable notice-info">
				<p>We've removed the <strong>upgrade-insecure-assets</strong> content security option as it was unreliable across different browsers. We now recommend using the option below.</p>
			</div>
		<?php endif; ?>

		<div class="option">
			<input id="<?php echo esc_attr( $option->get_key( 'block_mixed_content' ) ); ?>" name="<?php echo esc_attr( $option->get_key( 'block_mixed_content' ) ); ?>"
				<?php checked( $option->get_option_bool( 'block_mixed_content' ) ); ?> value="1" type="checkbox"/>
			&nbsp;<label for="<?php echo esc_attr( $option->get_key( 'block_mixed_content' ) ); ?>">Block Insecure Assets</label>
		</div>
		<div class="description">
			<p>Setting the <a href="https://help.mediavine.com/mediavine-learning-resources/force-all-ads-secure-with-a-content-security-policy" target="_blank">Content Security Policy</a> will tell modern web browsers what to do if they encounter a non-secure image, script or advertisement. Enable this feature if you want to block all insecure assets.</p>
		</div>
	</section>
</div>
