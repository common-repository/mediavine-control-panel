<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;

$option = Option::get_instance();
?>

<?php
// Only show this section if it is relevant to the current state of the publisher
// and the required `&mcp_hidden_settings` URL param flag has been passed.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['mcp_hidden_settings'] ) && Upstream::is_launch_mode_enabled() ) :
	?>

<div class="option-group">
	<h3>GPT Verification</h3>

	<section class="mvoption">
		<div class="option">
			<input
				id="<?php echo esc_attr( $option->get_key( 'enable_gpt_snippet' ) ); ?>"
				name="<?php echo esc_attr( $option->get_key( 'enable_gpt_snippet' ) ); ?>"
				<?php checked( true === $option->get_option_bool( 'enable_gpt_snippet' ) ); ?>
				value="1"
				type="checkbox"
			/>
			&nbsp;<label for="<?php echo esc_attr( $option->get_key( 'enable_gpt_snippet' ) ); ?>"><?php esc_html_e( 'Enable GPT Verification Insertion', 'mediavine' ); ?></label>
		</div>
		<div class="description">
			<p style="color: red;"><strong>Do Not Use this unless instructed by Mediavine Support</strong></p>
			<p>Enables the automatic insertion of the GPT verification code</p>
		</div>
	</section>
</div>

<?php else : ?>
	<?php // Add hidden values because otherwise the form saves missing registered options as empty values. ?>
	<input type="hidden" name="<?php echo esc_attr( $option->get_key( 'enable_gpt_snippet' ) ); ?>" value="<?php echo esc_attr( $option->get_option_bool( 'enable_gpt_snippet', false ) ); ?>"/>

<?php endif; ?>
