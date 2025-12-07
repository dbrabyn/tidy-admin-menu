<?php
/**
 * Uninstall handler for Tidy Admin Menu
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function tidy_admin_menu_uninstall() {
	global $wpdb;

	// Delete plugin options.
	delete_option( 'tidy_admin_menu_settings' );
	delete_option( 'tidy_admin_menu_order' );
	delete_option( 'tidy_admin_menu_hidden' );

	// Delete role-specific options.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tidy_admin_menu_role_%'"
	);

	// Delete user meta for all users.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->delete(
		$wpdb->usermeta,
		array( 'meta_key' => 'tidy_admin_menu_order' )
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	$wpdb->delete(
		$wpdb->usermeta,
		array( 'meta_key' => 'tidy_admin_menu_hidden' )
	);

	// Clean up multisite if applicable.
	if ( is_multisite() ) {
		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			switch_to_blog( $site_id );

			delete_option( 'tidy_admin_menu_settings' );
			delete_option( 'tidy_admin_menu_order' );
			delete_option( 'tidy_admin_menu_hidden' );

			// Delete role-specific options for this site.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tidy_admin_menu_role_%'"
			);

			restore_current_blog();
		}
	}

	// Clear any cached data.
	wp_cache_flush();
}

tidy_admin_menu_uninstall();
