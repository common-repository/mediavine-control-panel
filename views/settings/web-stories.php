<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;
use Mediavine\MCP\ThirdParty\WebStories;

$option = Option::get_instance();

if ( WebStories::get_instance()->has_web_stories() && ! Upstream::is_launch_mode_enabled() ) :
	?>

	<div class="option-group">
		<h3>Web Stories Settings</h3>
		<section class="mvoption">
			<div class="option">
				<input id="<?php echo esc_attr( $option->get_key( 'enable_web_story_ads' ) ); ?>" name="<?php echo esc_attr( $option->get_key( 'enable_web_story_ads' ) ); ?>"
					<?php checked( $option->get_option_bool( 'enable_web_story_ads', true ) ); ?> value="1" type="checkbox"/>
				&nbsp;<label for="<?php echo esc_attr( $option->get_key( 'enable_web_story_ads' ) ); ?>">Enable Web Story Ads</label>
			</div>
			<div class="description">
				<p>Displays ads on <a href="https://wordpress.org/plugins/web-stories/" target="_blank">Web Stories</a></p>
			</div>
		</section>
	</div>

<?php else : ?>
	<?php // Add default values because otherwise the form saves missing registered options as empty strings. ?>
	<input type="hidden" name="<?php echo esc_attr( $option->get_key( 'enable_web_story_ads' ) ); ?>" value="<?php echo esc_attr( $option->get_option_bool( 'enable_web_story_ads', true ) ); ?>"/>
<?php endif; ?>
