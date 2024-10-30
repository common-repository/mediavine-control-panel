<?php
namespace Mediavine\MCP\Video;

/**
 * Handles functionality related to featured videos.
 */
class VideoFeatured {

	/**
	 * Reference to static singleton self.
	 *
	 * @property self $instance
	 */
	use \Mediavine\MCP\Traits\Singleton;

	/**
	 * Reference to the video playlist.
	 *
	 * @var VideoPlaylist
	 */
	protected $playlist;

	/**
	 * Tracks if markup generation was run.
	 *
	 * @var bool
	 */
	private $attempted_markup_generation = false;

	/**
	 * Stores full markup for the featured video.
	 *
	 * @var string|null
	 */
	private $featured_video_markup;

	/**
	 * Creates and initializes class.
	 */
	protected function __construct() {
		$this->init();
	}

	/**
	 * Hooks to be run on class instantiation.
	 */
	public function init() {
		$this->playlist = VideoPlaylist::get_instance();

		add_action( 'loop_start', array( $this, 'add_featured_video' ) );
	}

	/**
	 * Adds the featured video to the archive or post
	 *
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 */
	public function add_featured_video( $query ) {
		// Must be main query.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Add featured video if single post/page.
		if ( is_singular() ) {
			$this->add_singular_video();
		}
	}

	/**
	 * Gets the markup for the featured video of a term.
	 *
	 * Markup is only retrieved if markup has never been previously generated during page render.
	 *
	 * @param int $term_id The id of the term.
	 * @return string HTML markup for featured video of term
	 */
	public function get_taxonomy_video_markup( $term_id ) {
		// Only proceed if markup has never been generated.
		if ( $this->attempted_markup_generation ) {
			return '';
		}

		// Get selected video data.
		$taxonomy_video = get_term_meta( $term_id, 'mv_category_video_settings', true );

		// Must have video data.
		if ( empty( $taxonomy_video ) ) {
			return null;
		}
		$video_data = json_decode( $taxonomy_video, true );
		$markup     = null;

		// Do we have a video.
		if ( 'video' === $video_data['type'] ) {
			$settings = array(
				'key'              => $video_data['slug'],
				'disable_optimize' => '',
				'disable_autoplay' => '',
				'featured'         => 'data-force-optimize="true"',
				'jsonld'           => 'data-disable-jsonld="true"',
				'ratio'            => '',
				'sticky'           => '',
				'volume'           => 'data-volume="70"',
			);
			$markup   = Video::get_instance()->video_markup_template( $settings );

			// Or do we have a playlist.
		} elseif ( 'playlist' === $video_data['type'] ) {
			$settings = array(
				'id'                   => $video_data['slug'],
				'autoplay'             => '',
				'disable_auto_upgrade' => '',
				'featured'             => 'data-force-optimize="true"',
				'jsonld'               => 'data-disable-jsonld="1"',
				'ratio'                => '',
				'sticky'               => '',
				'volume'               => 'data-volume="70"',
			);
			$markup   = $this->playlist->playlist_markup_template( $settings );
		}

		$this->attempted_markup_generation = true;

		return $markup;
	}

	/**
	 * Checks if a Create video outputs a Mediavine video
	 *
	 * @param int $create_id Creation ID to check.
	 * @return boolean True if the card outputs a Mediavine video
	 */
	public function has_rendered_mv_video( $create_id ) {
		// We only have something to parse if the Create plugin is active with the correct function available.
		if ( function_exists( 'mv_create_get_creation' ) ) {
			$creation = mv_create_get_creation( $create_id, true );

			// Only proceed if we have video data.
			if ( ! empty( $creation->video ) ) {
				$video_data = json_decode( $creation->video, true );
			}

			// Was the video set to be included in the card's markup.
			if ( ! empty( $video_data['include'] ) ) {
				return true;
			}
		}

		// Nothing was found.
		return false;
	}

	/**
	 * Checks if a post contains a Create card that outputs a Mediavine video
	 *
	 * @param string $content Content to check for Create shortcodes.
	 * @return boolean True if the post outputs a Mediavine video
	 */
	public function has_mv_create_video( $content ) {
		// Does the Create shortcode exist on the page?
		if ( ! has_shortcode( $content, 'mv_create' ) && ! has_shortcode( $content, 'mv_recipe' ) ) {
			return false;
		}

		// Find full shortcode so we can pull id to check for video.
		$pattern = get_shortcode_regex();
		if (
			preg_match_all( '/' . $pattern . '/s', $content, $matches ) &&
			array_key_exists( 2, $matches ) &&
			(
				in_array( 'mv_create', $matches[2], true ) ||
				in_array( 'mv_recipe', $matches[2], true )
			)
		) {
			list( $full_shortcodes, $empty, $handles ) = $matches;

			$create_id = null;

			foreach ( $handles as $i => $handle ) {
				// Check for Create shortcode.
				if ( 'mv_create' === $handle || 'mv_recipe' === $handle ) {
					$shortcode_atts = shortcode_parse_atts( $full_shortcodes[ $i ] );

					// Pull key from shortcode with key.
					if ( ! empty( $shortcode_atts['key'] ) ) {
						$create_id = $shortcode_atts['key'];
					}

					// If no key, check for post_id which was part of original mv_recipe shortcode.
					if ( empty( $create_id ) && ! empty( $shortcode_atts['post_id'] ) ) {
						$create_id = $shortcode_atts['post_id'];
					}

					// Look through Create data to get video.
					if ( $create_id ) {
						$has_video = $this->has_rendered_mv_video( $create_id );

						// Return early if a video is found.
						if ( $has_video ) {
							return true;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a post contains a WPRM recipe that outputs a Mediavine video
	 *
	 * @param string $content Content to check for WPRM shortcodes.
	 * @return boolean True if the post outputs a Mediavine video
	 */
	public function has_wprm_video( $content ) {
		// Only bother moving forward if WPRM is activated.
		if ( ! class_exists( 'WPRM_Recipe_Manager' ) ) {
			return false;
		}

		// Do we need to parse through the WPRM fallback code?
		if (
			strpos( $content, '<!--WPRM Recipe ' ) &&
			method_exists( 'WPRM_Fallback_Recipe', 'replace_fallback_with_shortcode' )
		) {
			$content = \WPRM_Fallback_Recipe::replace_fallback_with_shortcode( $content );
		}

		// We run through the shortcodes because not all recipes will have Gutenberg comments,
		// so we'd miss content if we only relied on that.
		$pattern = get_shortcode_regex();
		if (
			preg_match_all( '/' . $pattern . '/s', $content, $matches ) &&
			array_key_exists( 2, $matches ) &&
			in_array( 'wprm-recipe', $matches[2], true )
		) {
			list( $full_shortcodes, $empty, $handles ) = $matches;

			$wprm_id = null;

			foreach ( $handles as $i => $handle ) {
				// Check for WPRM shortcode.
				if ( 'wprm-recipe' !== $handle ) {
					continue;
				}

				$shortcode_atts = shortcode_parse_atts( $full_shortcodes[ $i ] );

				// Pull id from shortcode.
				if ( ! empty( $shortcode_atts['id'] ) ) {
					$wprm_id = $shortcode_atts['id'];
				}

				// The hyphen in the wprm shortcode changes the shortcode parsing on the,
				// so we will perform an indexed arrays as a fallback.
				if ( array_values( $shortcode_atts ) === $shortcode_atts ) {
					foreach ( $shortcode_atts as $shortcode_att ) {
						// Find string that starts with id to get the id.
						if ( 0 === strpos( $shortcode_att, 'id="' ) ) {
							// Just pull the digits.
							$wprm_id = filter_var( $shortcode_att, FILTER_SANITIZE_NUMBER_INT );

							// We found it so break out.
							break;
						}
					}
				}

				// Look through WPRM data to get video.
				if ( $wprm_id ) {
					$video_data = get_post_meta( $wprm_id, 'wprm_video_embed', true );

					// Does a MV video exist within the video data?
					if ( ! empty( $video_data ) && $this->has_mediavine_video_markup( $video_data ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Checks if a post contains a Tasty recipe that outputs a Mediavine video
	 *
	 * @param string $content Post content.
	 * @param int    $post_id Post ID.
	 * @return boolean True if the post outputs a Mediavine video
	 */
	public function has_tasty_video( $content, $post_id ) {
		// Only bother moving forward if Tasty is activated.
		if ( ! class_exists( 'Tasty_Recipes' ) ) {
			return false;
		}

		// Check if Tasty recipe(s) exist on page.
		if (
			method_exists( 'Tasty_Recipes', 'has_recipe' ) &&
			method_exists( 'Tasty_Recipes', 'get_recipe_ids_from_content' ) &&
			\Tasty_Recipes::has_recipe( $post_id )
		) {
			$tasty_recipes = \Tasty_Recipes::get_recipe_ids_from_content( $content );
		}

		// No videos found if no tasty recipes.
		if ( empty( $tasty_recipes ) ) {
			return false;
		}

		// Run through all found recipes checking for a Mediavine video.
		foreach ( $tasty_recipes as $recipe_id ) {
			$video_url = get_post_meta( $recipe_id, 'video_url', true );

			// If we found it, return true.
			if ( strpos( $video_url, 'mediavine.com' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if a Mediavine video markup exists within string.
	 *
	 * @param string $content The content to check.
	 *
	 * @return boolean
	 */
	public function has_mediavine_video_markup( $content ) {
		// Was a manual video div added to the page content?
		if ( strpos( $content, 'mv-video-id-' ) ) {
			return true;
		}

		// Was a manual playlist div added to the page content?
		if ( strpos( $content, 'mv-playlist-id-' ) ) {
			return true;
		}

		// Does an old video div exist on the page?
		if ( strpos( $content, 'mediavine-video__target' ) ) {
			return true;
		}

		// Does a REALLY old video div exist on the page?
		if ( strpos( $content, 'src="//scripts.mediavine.com/videos' ) ) {
			return true;
		}

		// Does a REALLY REALLY old video div exist on the page?
		if ( strpos( $content, 'src="//video.mediavine.com/videos' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if mediavine video currently exists on page.
	 *
	 * @param string $content Page content with shortcodes.
	 * @return boolean Does a mediavine video already exist on the page
	 */
	public function has_mediavine_video( $content ) {
		// Is there a video shortcode on the page?
		if ( has_shortcode( $content, 'mv_video' ) ) {
			return true;
		}

		// Is there a playlist shortcode on the page?
		if ( has_shortcode( $content, 'mv_playlist' ) ) {
			return true;
		}

		// Does a manually added Mediavine video exist on the page?
		if ( $this->has_mediavine_video_markup( $content ) ) {
			return true;
		}

		// Does a Create shortcode exist on the page and contains a video?
		if ( $this->has_mv_create_video( $content ) ) {
			return true;
		}

		// Does a WPRM shortcode exist on the page and contains a video?
		if ( $this->has_wprm_video( $content ) ) {
			return true;
		}

		// Does a Tasty shortcode exist on the page and contains a video?
		if ( $this->has_tasty_video( $content, get_the_ID() ) ) {
			return true;
		}

		/**
		 * Filters whether a Mediavine video is on the page.
		 *
		 * Because we cannot account for every single possibility, we open
		 * a filter to developers so they can adjust if needed.
		 *
		 * @param bool $has_mv_video Does a Mediavine video already exist on the page?
		 * @param string $content Post/page content
		 */
		$has_mv_video = apply_filters( 'mv_cp_has_mediavine_video_div', false, $content );

		// No video found (unless filtered).
		return $has_mv_video;
	}

	/**
	 * Adds the featured video markup to a singular post.
	 *
	 * Markup is added by passing markup to specific WordPress hooks,
	 * and will be output on first run hook only.
	 */
	public function add_singular_video() {
		// Make sure we aren't blocked by a password.
		if ( ! empty( post_password_required() ) ) {
			return;
		}

		// Do nothing if we already have a video or playlist added.
		if ( $this->has_mediavine_video( get_the_content() ) ) {
			return;
		}

		// Get all categories of post.
		$categories = get_the_category();
		if ( ! is_array( $categories ) ) {
			return;
		}

		// Loop through each category to find first featured video/playlist.
		foreach ( $categories as $category ) {
			if ( ! empty( $category->term_id ) ) {
				// Get selected video markup.
				$markup = $this->get_taxonomy_video_markup( $category->term_id );

				if ( ! empty( $markup ) ) {
					// Make markup available for output through hooks.
					$this->featured_video_markup = $markup;

					// Order of hooks to attempt to render markup.
					$hooks = array(
						'tha_entry_content_before',
						'genesis_before_entry_content',
						'the_content',
					);

					/**
					 * Filters order of hooks to output featured video on singular posts.
					 *
					 * When the first hooks is run, the featured video markup will be removed
					 * to prevent the code from appearing anywhere else on the page
					 *
					 * @param array $hooks Order of hooks to output featured video
					 */
					$hooks = apply_filters( 'mv_cp_featured_video_hooks_order_singular', $hooks );

					// Loop through hooks.
					foreach ( $hooks as $hook ) {
						add_filter( $hook, array( $this, 'display_featured_video' ) );
					}

					// We have what we need so return before checking any more categories.
					return;
				}
			}
		}
	}

	/**
	 * Outputs featured video markup to assigned hook.
	 *
	 * Echos markup on all hooks besides when run against `the_content`,
	 * in the which case it prepends the markup to the content.
	 *
	 * @param string $content Content markup if running though a hook that has content.
	 * @return string
	 */
	public function display_featured_video( $content = null ) {
		// Only proceed if we have featured video content.
		if ( empty( $this->featured_video_markup ) ) {
			return $content;
		}

		// Some plugins, such as Rank Math, run some of our display filters in wp_head
		// Return early if we are doing wp_head, so we output with the next hook.
		if ( doing_filter( 'wp_head' ) ) {
			return $content;
		}

		// If running `the_content` filter, we need to adjust the content.
		if ( doing_filter( 'the_content' ) ) {
			$content = wp_kses( $this->featured_video_markup, Video::get_instance()->allowed_video_html() ) . $content;
			// Else we need to echo the video markup.
		} else {
			echo wp_kses( $this->featured_video_markup, Video::get_instance()->allowed_video_html() );
		}

		// Clear featured video variable so we only have one possible output on a page.
		$this->featured_video_markup = null;

		return $content;
	}
}
