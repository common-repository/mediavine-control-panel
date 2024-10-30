<?php
namespace Mediavine\MCP;

/**
 * Handles functionality related to the admin-side React app layer of MCP.
 *
 * @todo: Consolidate admin menu callbacks/enqueues with Menu.
 */
class AdminInit {
	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Tracks the nonce key to be used.
	 *
	 * @var string
	 */
	protected $nonce_key;

	/**
	 * AdminInit constructor.
	 */
	public function __construct() {
		$this->nonce_key = __NAMESPACE__ . '_nonce';
	}

	/**
	 * Initialize the React admin layer.
	 */
	public function init() {
		add_action( 'media_buttons', array( $this, 'video_shortcode_div' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_filter( 'tiny_mce_before_init', array( $this, 'add_tmce_stylesheet' ) );
		add_action( 'category_edit_form', array( $this, 'category_edit_form' ), 10 );
		add_action( 'edited_category', array( $this, 'save_category_meta' ), 10, 2 );
		add_action( 'create_category', array( $this, 'save_category_meta' ), 10, 2 );
		// `block_categories` was deprecated in WP 5.8+
		$hook_name = version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ? 'block_categories_all' : 'block_categories';
		add_filter( $hook_name, array( $this, 'block_categories' ), 10, 1 );
		add_filter( 'format_for_editor', array( $this, 'add_key_to_playlist_atts' ) );
	}

	/**
	 * Setup data for wp_localize_script.
	 *
	 * @return array
	 */
	private static function localize() {
		$user = wp_get_current_user();

		$idstring = \base64_encode(
			wp_json_encode(
				array(
					'login' => $user->user_login,
					'id'    => $user->ID,
				)
			)
		);

		return array(
			'root'              => esc_url_raw( rest_url() ),
			'nonce'             => wp_create_nonce( 'wp_rest' ),
			'asset_url'         => self::assets_url() . 'ui/build/',
			'admin_url'         => esc_url_raw( admin_url() ),
			'platform_auth_url' => 'https://localhost:3000/#auth=' . $idstring . '&redirect=' . esc_url_raw( admin_url() . 'options-general.php?page=' . MV_Control_Panel::PLUGIN_DOMAIN ),
			'platform_api_root' => 'https://publisher-identity.mediavine.com/',
		);
	}

	/**
	 * Reliably return the base directory for plugin, important in order to enqueue files elsewhere.
	 *
	 * @return string plugin directory url based on this plugin directory
	 */
	public static function assets_url() {
		return MCP_PLUGIN_URL . 'admin/';
	}

	/**
	 * Sets up scripts for admin UI.
	 */
	public function admin_enqueue() {
		// Globally unique handle for script
		// @todo: Handle shouldn't have filename.
		$handle = '/mv-mcp.js';

		// Get script URL, or local URL if in dev mode.
		$script_url = self::assets_url() . 'ui/build/app.build.' . MV_Control_Panel::VERSION . '.js';
		if ( apply_filters( 'mv_mcp_dev_mode', false ) ) {
			$script_url = '//localhost:3001/app.build.' . MV_Control_Panel::VERSION . '.js';
		}

		// Get correct dependencies based of if we're in Gutenberg or not.
		$deps = array();
		if ( function_exists( 'is_gutenberg_page' ) && is_gutenberg_page() ) {
			$deps = array_merge( $deps, array( 'wp-plugins', 'wp-i18n', 'wp-element' ) );
		}

		// Register script.
		wp_register_script( MV_Control_Panel::PLUGIN_DOMAIN . $handle, $script_url, $deps, MV_Control_Panel::VERSION, true );
		wp_localize_script( MV_Control_Panel::PLUGIN_DOMAIN . $handle, 'mvMCPApiSettings', self::localize() );
		wp_enqueue_script( MV_Control_Panel::PLUGIN_DOMAIN . $handle );

		// Pull Proxima Nova from CDN using correct protocol.
		$proxima_nova_cdn = 'http://cdn.mediavine.com/fonts/ProximaNova/stylesheet.css';
		if ( is_ssl() ) {
			$proxima_nova_cdn = 'https://cdn.mediavine.com/fonts/ProximaNova/stylesheet.css';
		}

		// This handle should match other plugins so we only render one copy.
		wp_enqueue_style( 'mv-font/proxima-nova', $proxima_nova_cdn, array(), MV_Control_Panel::VERSION );
	}

	/**
	 * Generates the video shortcode containers.
	 *
	 * @param string $id The section ID.
	 */
	public function video_shortcode_div( $id ) {
		if ( 'content' !== $id ) {
			return;
		}
		?>
			<div data-shortcode="mv_video"></div>
			<div data-shortcode="mv_playlist"></div>
		<?php
	}

	/**
	 * Adds a stylesheet that applies styles to Classic Editor WYSIWYG MV shortcodes.
	 *
	 * @param array $mce_init Settings for MCE.
	 *
	 * @return array
	 */
	public function add_tmce_stylesheet( $mce_init ) {
		if ( empty( $mce_init['content_css'] ) ) {
			$mce_init['content_css'] = '';
		}
		$mce_init['content_css'] .= ', ' . self::assets_url() . 'ui/public/mcp-tinymce.css?' . MV_Control_Panel::VERSION;

		return $mce_init;
	}

	/**
	 * Gets the value of a field after validating nonce.
	 *
	 * @param string     $field Key to check from $_POST array.
	 * @param string|int $action Should give context to what is taking place and be the same when nonce was created.
	 * @return string|void|null $value
	 */
	private function field_value( $field, $action = -1 ) {
		if ( empty( $_POST[ $this->nonce_key ] ) ) {
			return null;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST[ $this->nonce_key ] ) ),
			$action
		) ) {
			die();
		}

		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : null;

		if ( empty( $value ) ) {
			return null;
		}

		return $value;
	}

	/**
	 * Renders a div for the React app for category settings to render from.
	 *
	 * The React app will render an input with name="mv_category_video_settings"
	 * which is submitted via the form. The result is a JSON blob matching one of
	 * the following formats:
	 *
	 * - Video: { slug: 'slug', title: 'Title', type: 'video' }
	 * - Playlist: { slug: 1, title: 'Title', type: 'playlist' }
	 * - Up next playlist: { slug: 'playlist_upnext', title: 'Up Next Playlist', type: 'playlist' }
	 *
	 * If the user selects "none" as an option, the form value is an empty string.
	 *
	 * @param \WP_Term $category Current taxonomy term object.
	 */
	public function category_edit_form( $category ) {
		$meta = get_term_meta( $category->term_id, 'mv_category_video_settings', true );
		?>
			<div
				id="mv-category-settings"
				data-mv-initial-value="<?php echo esc_attr( $meta ); ?>"
			></div>
			<?php wp_nonce_field( 'category_video', $this->nonce_key ); ?>
		<?php
	}

	/**
	 * Callback to save Category video settings when a user saves a category.
	 *
	 * @param int $term_id The category's term ID that should be updated.
	 *
	 * @return bool True if the update was called. False if the nonce checks fails.
	 */
	public function save_category_meta( $term_id ) {
		if ( empty( $_POST[ $this->nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->nonce_key ] ) ), 'category_video' ) ) {
			return false;
		}

		$value = $this->field_value( 'mv_category_video_settings', 'category_video' );
		update_term_meta( $term_id, 'mv_category_video_settings', $value );
		return true;
	}

	/**
	 * Defines the categories for blocks.
	 *
	 * @param array $categories List of block categories.
	 *
	 * @return array
	 */
	public function block_categories( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'mediavine-control-panel',
					'title' => __( 'Mediavine Control Panel', 'mediavine' ),
					'icon'  => '',
				),
				array(
					'slug'  => 'mediavine-video',
					'title' => __( 'Mediavine Video', 'mediavine' ),
					'icon'  => 'video',
				),
			)
		);
	}

	/**
	 * Adds the 'key' attribute to mv_playlist shortcodes that don't have it.
	 * Enables edit/delete of existing playlists in Classic Editor.
	 *
	 * @param string $text The formatted text content of the editor.
	 *
	 * @return string
	 */
	public function add_key_to_playlist_atts( $text ) {
		$shortcode = 'mv_playlist';
		if ( has_shortcode( $text, $shortcode ) !== true ) {
			return $text;
		}

		if ( preg_match_all( '/' . get_shortcode_regex() . '/s', $text, $matches )
			&& array_key_exists( 2, $matches )
			&& in_array( $shortcode, $matches[2], true ) ) {
			$playlists = $matches[0];

			if ( ! empty( $playlists ) ) {
				foreach ( $playlists as $index => $playlist ) {
					preg_match( '/id="([A-Za-z0-9]+)"/', $matches[3][ $index ], $playlist_ids );

					if ( strpos( $playlist, 'key="' ) !== false ) {
						$updated_shortcode = $playlist;
					} else {
						$replace           = ' key="' . esc_attr( $playlist_ids[1] ) . '" ratio="';
						$updated_shortcode = str_ireplace( ' ratio="', $replace, $playlist );
					}
					$shortcode_text[ $playlist ] = $updated_shortcode;
				}
			}
		}

		$new_text = str_ireplace( array_keys( $shortcode_text ), $shortcode_text, $text );
		return $new_text;
	}

}
