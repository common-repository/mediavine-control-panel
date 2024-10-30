<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\MV_Control_Panel;
use Mediavine\MCP\Upstream;

?>


<?php if ( Upstream::has_just_left_launch_mode() && current_user_can( 'manage_options' ) ) : ?>
	<div class="option-group launch-mode-group">
		<div class="notice notice-success is-dismissible">
			<p>Congrats on launching with Mediavine! Make sure to clear your site cache/caching plugins if you have any and let your Launch Specialist know once you&rsquo;ve done this.</p>
		</div>
	</div>
<?php endif; ?>

<?php if ( Upstream::is_launch_mode_enabled() && current_user_can( 'manage_options' ) ) : ?>
<div class="option-group launch-mode-group" id="mv_control_launch_mode">
	<section class="mvoption">
		<div class="option">
			<button type="button" class="button button-secondary" id="mv_refresh_launch_mode">Refresh Mediavine Launch Status</button>
		</div>
		<div class="description">
			We&rsquo;re still preparing for launch with Mediavine, meaning ads aren&rsquo;t running and we aren&rsquo;t managing your Ads.txt for you. Once ads are live, a launch specialist will have you click this button.
		</div>
	</section>
	<?php if ( isset( $_GET['f_launch_exit'] ) ) : // phpcs:ignore ?>
		<section class="mvoption">
			<div class="option">
				<button type="button" class="button button-secondary" id="mv_disable_launch_mode">Disable Launch Mode</button>
			</div>
			<div class="description">
				Permanently exits launch mode and disables further checks.
			</div>
		</section>
	<?php endif; ?>
</div>
<?php endif; ?>
