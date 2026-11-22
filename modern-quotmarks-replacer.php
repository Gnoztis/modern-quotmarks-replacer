<?php
/**
 * Plugin Name:       Modern Quotmarks Replacer
 * Description:       Disables wptexturize so all quotation marks and suspension points stay in half-width form. Built for PHP 8.4 and WordPress 6+.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      8.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       modern-quotmarks-replacer
 * Domain Path:       /languages
 *
 * @package ModernQuotmarksReplacer
 */

declare( strict_types = 1 );

namespace ModernQuotmarksReplacer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap class.
 *
 * Removes the WordPress `wptexturize` filter from a curated list of content
 * and metadata hooks so that typographic "smart quotes", dashes and ellipses
 * are not applied to front-end output.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Default priority used by WordPress core for `wptexturize`.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const WPTEXTURIZE_PRIORITY = 10;

	/**
	 * Hooks (filters) from which `wptexturize` should be detached.
	 *
	 * Keys are filter tags, values are priorities. Preserving the original
	 * priority is critical — otherwise `remove_filter()` silently does nothing.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	private const TARGET_FILTERS = [
		'the_title'             => self::WPTEXTURIZE_PRIORITY,
		'the_content'           => self::WPTEXTURIZE_PRIORITY,
		'the_excerpt'           => self::WPTEXTURIZE_PRIORITY,
		'single_post_title'     => self::WPTEXTURIZE_PRIORITY,
		'comment_author'        => self::WPTEXTURIZE_PRIORITY,
		'comment_text'          => self::WPTEXTURIZE_PRIORITY,
		'link_description'      => self::WPTEXTURIZE_PRIORITY,
		'bloginfo'              => self::WPTEXTURIZE_PRIORITY,
		'term_description'      => self::WPTEXTURIZE_PRIORITY,
		'category_description'  => self::WPTEXTURIZE_PRIORITY,
		'widget_title'          => self::WPTEXTURIZE_PRIORITY,
		'widget_text'           => self::WPTEXTURIZE_PRIORITY,
		'widget_text_content'   => self::WPTEXTURIZE_PRIORITY,
		'widget_block_content'  => self::WPTEXTURIZE_PRIORITY,
		'excerpt_more'          => self::WPTEXTURIZE_PRIORITY,
	];

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use {@see Plugin::boot()}.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Boot the plugin. Idempotent.
	 *
	 * @since 1.0.0
	 */
	public static function boot(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * Runs late on `init` so that third-party code registering `wptexturize`
	 * (via `add_filter`) has already executed and can be reliably removed.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		\add_action( 'init', [ $this, 'disable_wptexturize' ], PHP_INT_MAX );
		\add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Load translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain(): void {
		\load_plugin_textdomain(
			'modern-quotmarks-replacer',
			false,
			\dirname( \plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Detach `wptexturize` from every targeted filter.
	 *
	 * @since 1.0.0
	 */
	public function disable_wptexturize(): void {
		/**
		 * Filter the list of hooks from which `wptexturize` should be removed.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, int> $filters Map of filter tag => priority.
		 */
		$filters = (array) \apply_filters( 'mqmr_target_filters', self::TARGET_FILTERS );

		foreach ( $filters as $tag => $priority ) {
			if ( ! \is_string( $tag ) || '' === $tag ) {
				continue;
			}

			$priority = \is_int( $priority ) ? $priority : self::WPTEXTURIZE_PRIORITY;

			if ( \has_filter( $tag, 'wptexturize' ) ) {
				\remove_filter( $tag, 'wptexturize', $priority );
			}
		}
	}

	/**
	 * Prevent cloning of the singleton.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		\_doing_it_wrong( __FUNCTION__, \esc_html__( 'Singleton cannot be cloned.', 'modern-quotmarks-replacer' ), '1.0.0' );
	}

	/**
	 * Prevent unserialization of the singleton.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		\_doing_it_wrong( __FUNCTION__, \esc_html__( 'Singleton cannot be unserialized.', 'modern-quotmarks-replacer' ), '1.0.0' );
	}
}

// Bootstrap.
Plugin::boot()->register();