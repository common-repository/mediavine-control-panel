<?php

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;

/**
 * Settings Template File
 *
 * @category Template
 * @package  mediavine-control-panel
 * @author   mediavine
 */

$option                = Option::get_instance();
$has_loaded_before     = $option->get_option_bool( 'has_loaded_before' );
$has_script_wrapper    = $option->get_option_bool( 'include_script_wrapper', 1 );
$script_wrapper_key    = $option->get_key( 'include_script_wrapper' );
$disable_admin_ads     = $option->get_option_bool( 'disable_admin_ads' );
$disable_admin_ads_key = $option->get_key( 'disable_admin_ads' );

if ( ! $has_loaded_before && ! empty( $option->get_option( 'site_id' ) ) ) {
	$has_script_wrapper = true;
}

?>

<form method="post" action="options.php">
	<?php settings_fields( $option->option_group ); ?>
	<?php do_settings_sections( $option->option_group ); ?>
	<h2 class="mv-head">Mediavine Settings</h2>
	<hr/>

	<?php include 'settings/launch-mode.php'; ?>

	<div class="option-group">
		<div id="MVControlPanel"></div>
	</div>

	<?php
	// Only display authentication if user cannot manage settings.
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '</form>';
		return;
	}
	?>

	<div class="option-group">
		<h3>General Settings</h3>

		<section class="mvoption">
			<div class="option">
				<label class="opt" for="<?php echo esc_attr( $option->get_key( 'site_id' ) ); ?>">Mediavine Site Id &nbsp;</label>
				<input type="text" name="<?php echo esc_attr( $option->get_key( 'site_id' ) ); ?>" value="<?php echo esc_attr( $option->get_option( 'site_id' ) ); ?>"/>
			</div>
			<div class="description">
				The unique identifier Mediavine has given your blog. This can be found in the Ad Setup tab of your <a href="https://dashboard.mediavine.com" target="_blank">Mediavine Dashboard,</a> and will look like this: food-fanatic, my-baking-addiction, etc
			</div>
		</section>
		<section class="mvoption">
			<input type="hidden" name="<?php echo esc_attr( $option->get_key( 'has_loaded_before' ) ); ?>" value="1">
			<div class="option">
			<label for="true-<?php echo esc_attr( $script_wrapper_key ); ?>" style="display: block; padding-bottom: 15px;">
				<input id="true-<?php echo esc_attr( $script_wrapper_key ); ?>" name="<?php echo esc_attr( $script_wrapper_key ); ?>"
					<?php checked( true === $has_script_wrapper ); ?> value="1" type="radio"/>
					Include Script Wrapper
				</label>
				<label for="false-<?php echo esc_attr( $script_wrapper_key ); ?>" style="display: block;">
					<input id="false-<?php echo esc_attr( $script_wrapper_key ); ?>" name="<?php echo esc_attr( $script_wrapper_key ); ?>" <?php checked( false === $has_script_wrapper ); ?> value="" type="radio"/>
					Exclude Script Wrapper
				</label>
			</div>
			<div class="description">
				Your script wrapper controls your Mediavine ads. If you do not have a script wrapper, you do not have ads.
				Please keep this enabled unless you are specifically asked to disable it by Mediavine support staff.
			</div>
		</section>
		<section class="mvoption">
			<div class="option">
				<input id="<?php echo esc_attr( $disable_admin_ads_key ); ?>" name="<?php echo esc_attr( $disable_admin_ads_key ); ?>"
					<?php checked( true === $disable_admin_ads ); ?> value="1" type="checkbox"/>
				&nbsp;<label for="<?php echo esc_attr( $disable_admin_ads_key ); ?>">Disable Admin Ads</label>
			</div>
			<div class="description">
				Disables all ads while editing your content. This option is recommended when using page or post builders.
			</div>
		</section>
	</div>

	<?php include 'settings/web-stories.php'; ?>
	<?php include 'settings/security.php'; ?>
	<?php include 'settings/video-sitemap.php'; ?>
	<?php include 'settings/adstxt.php'; ?>
	<?php include 'settings/gpt.php'; ?>

	<?php submit_button(); ?>

	<div class="option-group">
		<div id="MVIntercomWidget"></div>
	</div>

</form>
