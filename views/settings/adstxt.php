<?php
// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This plugin requires WordPress' );
}

use Mediavine\MCP\AdsTxt;
use Mediavine\MCP\Option;
use Mediavine\MCP\Upstream;

/**
 * MVAdtext Template
 *
 * @category Template
 * @package  Mediavine Control Panel
 * @author   Mediavine
 * @license  http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @link     https://www.mediavine.com
 */

$ads_txt = AdsTxt::get_instance();
$option  = Option::get_instance();
?>

<?php if ( ! empty( $option->get_option( 'site_id' ) ) && ! Upstream::is_launch_mode_enabled() ) : ?>

<div class="option-group">
	<h3>Ads.txt</h3>

	<section class="mvoption">
		<div id="mcp_adstxt" class="<?php print esc_attr( $ads_txt->is_ads_txt_handling_enabled() ? 'mcp-adstxt-enabled' : 'mcp-adstxt-disabled' ); ?>  <?php print esc_attr( 'mcp-adstxt-method-' . $ads_txt->get_ads_txt_method() ); ?>">
			<p>We support the <a href="https://help.mediavine.com/advanced/setting-up-your-adstxt-file" target="_blank">Ads.txt</a> feature, protecting your site against ad fraud.</p>

			<div id="mv_adstxt_notifications" class="mcp-margin-bottom">
			</div>

			<div id="mv_adstxt_recheck_method_wrapper" class="mcp-display-on-enabled">
				<p><button type="button" class="button button-secondary" id="mv_adstxt_recheck_method">Force recheck of ads.txt method</button></p>
			</div>

			<div id="mv_manual_update_ads_txt" class="mcp-display-on-enabled">
				<div class="notice notice-alt">
					<div class="mcp-display-on-write">
						<p>Your site is using the following method: <a href="/ads.txt" target="_blank">Ads.txt file added to your site</a></p>
						<p>By default we'll keep this up to date for you, but sometimes settings on your host's server prevent this update from happening automatically, and you'll need to push the "Update Ads.txt" button below.</p>
					</div>
					<div class="mcp-display-on-redirect">
						<p>Your site is using the following method: <a href="/ads.txt" target="_blank">Redirecting your ads.txt to our servers</a></p>
					</div>

					<div class="option mcp-full-width-option mcp-display-on-write">
						<div id="mv_adstxt_div">
							<p><button type="button" class="button button-secondary" id="mv_adstxt_sync">Update Ads.txt</button></p>
						</div>
					</div>
				</div>
			</div>

			<div id="mv_enable_adstxt_parent" class="mcp-display-on-disabled">
				<div class="notice notice-alt mcp-space-top">
					<h4>Enable ads.txt support</h4>
					<p>This option will enable ads.txt support for your site, if you previously disabled this feature.</p>
				</div>
				<div class="option mcp-full-width-option">
					<div id="mv_enable_adstxt_div">
						<button type="button" class="button button-secondary" id="mv_enable_adstxt">Enable Ads.txt</button>
					</div>
				</div>
			</div>


			<div id="mv_disable_adstxt_parent" class="mcp-display-on-enabled mcp-danger-zone">
				<div class="mcp-space-top">
					<h4>DANGER: Disable ads.txt support</h4>
					<p>This option will disable ads.txt support for your site and will remove the ads.txt file.</p>
					<h4>Do Not Use this unless instructed by Mediavine Support</h4>
				</div>
				<div class="option mcp-full-width-option">
					<div id="mv_disable_adstxt_div">
						<button type="button" class="button button-secondary" id="mv_disable_adstxt">Disable Ads.txt</button>
					</div>
				</div>
			</div>
		</div>

		<?php
		// Hide this setting behind an appended `&mcp_hidden_settings` URL param flag.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['mcp_hidden_settings'] ) ) :
			?>
		</section>
		<section class="mvoption">
			<div class="option">
				<input
					id="<?php echo esc_attr( $option->get_key( 'ads_txt_write_forced' ) ); ?>"
					name="<?php echo esc_attr( $option->get_key( 'ads_txt_write_forced' ) ); ?>"
					<?php checked( true === $ads_txt->is_ads_txt_write_forced() ); ?>
					value="1"
					type="checkbox"
				/>
				&nbsp;<label for="<?php echo esc_attr( $option->get_key( 'ads_txt_write_forced' ) ); ?>"><?php esc_html_e( 'Force Ads.txt Write Method', 'mediavine' ); ?></label>
			</div>
			<div class="description">
			<p style="color: red;"><strong>Do Not Use this unless instructed by Mediavine Support</strong></p>
				<p>Forces the old method where a physical Ads.txt file is created and updated twice a day. This method doesn't keep the ads.txt file as up-to-date, but should be used if there's a conflict preventing the Ads.txt redirect from working properly.</p>
			</div>
		<?php else : ?>
			<?php // Add hidden values because otherwise the form saves missing registered options as empty values. ?>
			<input type="hidden" name="<?php echo esc_attr( $option->get_key( 'ads_txt_write_forced' ) ); ?>" value="<?php echo esc_attr( $option->get_option_bool( 'ads_txt_write_forced', false ) ); ?>"/>
		<?php endif; ?>
	</section>
</div>

<?php else : ?>
	<input type="hidden" name="<?php echo esc_attr( $option->get_key( 'ads_txt_write_forced' ) ); ?>" value="<?php echo esc_attr( $option->get_option_bool( 'ads_txt_write_forced', false ) ); ?>"/>
<?php endif; ?>
