<?php
/**
 * Removes plugin data on uninstall.
 *
 * Only the plugin's own update-check transients are deleted; the plugin stores
 * no options, tables or metadata. The shared core transient (update_plugins)
 * is left alone, because other plugins keep their entries there. On multisite
 * every site stores its own copy, so each site is cleaned in turn.
 *
 * @package ModernQuotmarksReplacer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! function_exists( 'mqmr_uninstall_site' ) ) {
	/**
	 * Deletes the plugin data of the current site.
	 *
	 * The transient names are literals on purpose: uninstall.php runs on its
	 * own, before the plugin code is loaded.
	 */
	function mqmr_uninstall_site() {
		// Update-check cache created by includes/class-mqmr-github-updater.php.
		delete_transient( 'mqmr_github_release' );
		delete_transient( 'mqmr_github_release_error' );
	}
}

if ( is_multisite() ) {
	$mqmr_sites = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( (array) $mqmr_sites as $mqmr_site_id ) {
		switch_to_blog( (int) $mqmr_site_id );
		mqmr_uninstall_site();
		restore_current_blog();
	}
} else {
	mqmr_uninstall_site();
}
