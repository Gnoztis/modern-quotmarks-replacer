<?php
/**
 * Plugin Name:       Modern Quotmarks Replacer
 * Description:       Disables wptexturize so quotation marks, dashes and ellipses stay as typed in every WordPress output: content, titles, comments, feeds, REST and the admin.
 * Version:           1.1.1
 * Requires at least: 7.0
 * Tested up to:      7.1
 * Requires PHP:      8.4
 * Author:            Gnostis
 * Author URI:        https://github.com/Gnoztis
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/Gnoztis/modern-quotmarks-replacer
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
 * Plugin version. Kept in sync with the plugin header and README.md.
 *
 * @since 1.1.0
 */
const VERSION = '1.1.1';

\define( 'MQMR_VERSION', VERSION );
\define( 'MQMR_FILE', __FILE__ );
\define( 'MQMR_DIR', \plugin_dir_path( __FILE__ ) );

/**
 * Main plugin bootstrap class.
 *
 * Disables the WordPress `wptexturize` typography in two layers: the global
 * `run_wptexturize` switch, which covers every hook and every direct call,
 * and the historical per-hook detach pass kept for compatibility with the
 * `mqmr_target_filters` filter and with code that inspects `has_filter()`.
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
	 * The list mirrors the `wptexturize` registrations of WordPress 7.0
	 * (`wp-includes/default-filters.php`) plus hooks that older versions and
	 * third-party code still use; detaching a callback that is not attached is
	 * a no-op. Keys are filter tags, values are priorities. Keeping the real
	 * priority is required — otherwise `remove_filter()` silently does nothing.
	 *
	 * Detaching is the compatibility layer, not the guarantee: the global
	 * `run_wptexturize` switch in {@see Plugin::register()} covers hooks that
	 * are missing here and hooks registered after this pass has run.
	 *
	 * @since 1.0.0
	 * @var array<string, int>
	 */
	private const TARGET_FILTERS = [
		// Post and comment content.
		'the_title'                     => self::WPTEXTURIZE_PRIORITY,
		'the_content'                   => self::WPTEXTURIZE_PRIORITY,
		'the_excerpt'                   => self::WPTEXTURIZE_PRIORITY,
		'the_post_thumbnail_caption'    => self::WPTEXTURIZE_PRIORITY,
		'comment_text'                  => self::WPTEXTURIZE_PRIORITY,
		'comment_author'                => self::WPTEXTURIZE_PRIORITY,
		// Titles, headings and the document title.
		'single_post_title'             => self::WPTEXTURIZE_PRIORITY,
		'single_cat_title'              => self::WPTEXTURIZE_PRIORITY,
		'single_tag_title'              => self::WPTEXTURIZE_PRIORITY,
		'single_month_title'            => self::WPTEXTURIZE_PRIORITY,
		'wp_title'                      => self::WPTEXTURIZE_PRIORITY,
		'document_title'                => self::WPTEXTURIZE_PRIORITY,
		// Terms and the link manager.
		'term_name'                     => self::WPTEXTURIZE_PRIORITY,
		'term_description'              => self::WPTEXTURIZE_PRIORITY,
		'category_description'          => self::WPTEXTURIZE_PRIORITY,
		'get_the_post_type_description' => self::WPTEXTURIZE_PRIORITY,
		'link_name'                     => self::WPTEXTURIZE_PRIORITY,
		'link_description'              => self::WPTEXTURIZE_PRIORITY,
		'link_notes'                    => self::WPTEXTURIZE_PRIORITY,
		// Site title and widgets.
		'bloginfo'                      => self::WPTEXTURIZE_PRIORITY,
		'widget_title'                  => self::WPTEXTURIZE_PRIORITY,
		'widget_text'                   => self::WPTEXTURIZE_PRIORITY,
		'widget_text_content'           => self::WPTEXTURIZE_PRIORITY,
		'widget_block_content'          => self::WPTEXTURIZE_PRIORITY,
		// Navigation menus.
		'nav_menu_attr_title'           => self::WPTEXTURIZE_PRIORITY,
		'nav_menu_description'          => self::WPTEXTURIZE_PRIORITY,
		// Category lists and the embed template.
		'list_cats'                     => self::WPTEXTURIZE_PRIORITY,
		'the_excerpt_embed'             => self::WPTEXTURIZE_PRIORITY,
		// Legacy hooks kept for older core versions and site code.
		'excerpt_more'                  => self::WPTEXTURIZE_PRIORITY,
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
	 * The global `run_wptexturize` switch is added immediately, while the
	 * plugin file is being included. `wptexturize()` applies that filter only
	 * on its first call in a request and caches the result, so a switch added
	 * later would be ignored if anything has already produced text.
	 *
	 * The per-hook detach pass stays on `init` at PHP_INT_MAX, by which time
	 * core, themes and plugins have registered their callbacks.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Global switch: every wptexturize() call returns the text unchanged,
		// whatever hook or direct call triggered it. PHP_INT_MAX runs last, so
		// a third-party callback cannot turn the typography back on.
		\add_filter( 'run_wptexturize', '__return_false', PHP_INT_MAX );

		\add_action( 'init', [ $this, 'disable_wptexturize' ], PHP_INT_MAX );

		// Native WordPress updates from the GitHub releases and tags.
		( new GitHub_Updater() )->register_hooks();
	}

	/**
	 * Detach `wptexturize` from every targeted filter.
	 *
	 * This is the compatibility layer behind `mqmr_target_filters`, not the
	 * guarantee: the global `run_wptexturize` switch in {@see Plugin::register()}
	 * already makes every `wptexturize()` call a no-op. Detaching is kept so
	 * that site code inspecting `has_filter()` keeps seeing the expected state
	 * and so that the filter can still extend the list.
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
		$filters = \apply_filters( 'mqmr_target_filters', self::TARGET_FILTERS );

		if ( ! \is_array( $filters ) ) {
			$filters = self::TARGET_FILTERS;
		}

		foreach ( $filters as $tag => $priority ) {
			if ( ! \is_string( $tag ) || '' === $tag ) {
				continue;
			}

			if ( false === \has_filter( $tag, 'wptexturize' ) ) {
				continue;
			}

			\remove_filter( $tag, 'wptexturize', $this->normalize_priority( $priority ) );

			// A theme or plugin may have attached the same callback at a
			// different priority; remove that instance as well.
			$remaining = \has_filter( $tag, 'wptexturize' );
			if ( false !== $remaining ) {
				\remove_filter( $tag, 'wptexturize', (int) $remaining );
			}
		}
	}

	/**
	 * Priority from the filter, as an integer `remove_filter()` accepts.
	 *
	 * `mqmr_target_filters` is site code: it may return a numeric string, and
	 * `remove_filter()` raises a TypeError for anything but an int on PHP 8.
	 * Non-numeric values fall back to the core priority.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $priority Value from the filter.
	 */
	private function normalize_priority( $priority ): int {
		if ( \is_int( $priority ) ) {
			return $priority;
		}

		if ( \is_string( $priority ) && \is_numeric( $priority ) ) {
			return (int) $priority;
		}

		return self::WPTEXTURIZE_PRIORITY;
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

// Updater: native WordPress update mechanism, GitHub as the source.
require_once MQMR_DIR . 'includes/class-mqmr-github-updater.php';

// Bootstrap.
Plugin::boot()->register();