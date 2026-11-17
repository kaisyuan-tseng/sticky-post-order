<?php
/**
 * Sticky Post Order — uninstall cleanup.
 *
 * Runs only when the plugin is deleted through wp-admin, not on deactivation.
 * Deactivation keeps the saved queue so re-activating restores the order.
 *
 * The plugin stores a single option holding the queue map. Its name is
 * filterable via `sticky_order_option_name`, but the filter (and the whole
 * plugin) is NOT loaded in the uninstall context, so we cannot call the helper
 * here. We therefore delete the known default name, the pre-2.0.0 legacy name,
 * and then sweep any custom `sticky_order_*` options by prefix.
 *
 * Note: the core `sticky_posts` option belongs to WordPress itself and is
 * deliberately left untouched.
 *
 * @package StickyOrder
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/* Current default name (2.0.0+). */
delete_option( 'sticky_order_queue_map' );

/* Legacy name from 1.0.0 — removed during the 2.0.0 contract hard-cut. */
delete_option( 'sticky_order_queues' );

/*
 * Sweep any custom names set through the `sticky_order_option_name` filter.
 * The prefix is unique to this plugin, so collateral damage is unlikely.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall cleanup: no WP API exists for prefix-based option deletion; no cache to invalidate at uninstall.
global $wpdb;
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( 'sticky_order_' ) . '%'
	)
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
