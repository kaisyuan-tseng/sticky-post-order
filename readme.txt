=== Sticky Post Order ===
Contributors: kaisyuan-tseng
Tags: sticky, order, sort, queue, rest, headless
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn sticky posts into ordered queues: sticky decides which posts are included, this plugin decides their order — independent of publish dates.

== Description ==

WordPress core exposes sticky as a boolean. A post is either stickied or not, and the
order among stickied posts still comes from the publish date. Promoting an older post
therefore means backdating it, which also changes the date shown on the article, its
position in archives, and the related-posts list.

This plugin splits membership from order. Membership still uses the native sticky
checkbox in the post editor; order lives in a separate option and is edited by dragging
rows on the admin page under Posts. Publish dates are never touched.

What it actually does:

* Groups queues by any taxonomy, one independent queue per group. With no groups
  configured, all stickied posts share a single queue.
* Exposes two read-only REST fields, `sticky_order_rank` and `sticky_order_group`, so a
  headless front end gets the order with the post list — no extra request, no slug join.
* Appends newly stickied posts to the tail in reverse-date order; they never jump ahead.
* Drops posts automatically when they are unstickied or unpublished. No stale IDs to clean.
* Lists stickied posts that match no group at the bottom of the admin page, so a post
  never silently disappears.
* Disabling the plugin simply removes the REST fields; consumers fall back to date order.

The plugin contains no site-specific term names, post types, or copy. Groups, labels,
capability, menu location, option name, and post types are all injected through filters,
so the directory can be copied to any other site as is. Default admin UI labels are
Chinese; replace them wholesale with `sticky_order_labels` on English installations.

== Installation ==

1. Put the `sticky-order` directory into `wp-content/plugins/`.
2. Activate "Sticky Post Order" on the Plugins screen.
3. Declare groups with `sticky_order_groups`, or leave it empty for single-queue mode.
4. Consume `sticky_order_rank` in the front end and rebuild static output after reordering.

== Hooks ==

`sticky_order_groups` (array)
  Group map `array( slug => label )`; slugs match term slugs. Empty = single-queue mode.

`sticky_order_group_taxonomy` (string)
  Taxonomy used for grouping. Default `category`.

`sticky_order_post_types` (array)
  Post types included. Default `array( 'post' )`.

`sticky_order_option_name` (string)
  Option holding the queues. Default `sticky_order_queues`.

`sticky_order_capability` (string)
  Capability required for the admin page. Default `edit_others_posts`.

`sticky_order_menu_parent` (string)
  Parent admin menu. Default `edit.php`.

`sticky_order_labels` (array)
  Admin UI copy. Missing keys fall back to defaults.

== REST ==

Two read-only fields are added to the post response:

`sticky_order_rank` (integer|null)
  Position in its queue, 0-based. `null` when the post is not queued. In grouped mode
  this is the position within that group; it is not comparable across groups.

`sticky_order_group` (string|null)
  Key of the owning queue; `default` in single-queue mode.

Posts that are not stickied return `null` for both fields, which tells the front end to
fall back to date order.

== Storage ==

Option `sticky_order_queues`, for example:

    array(
        'section-a' => array( 28 ),        // array order = queue order
        'section-b' => array( 60, 61 ),
    )

Only IDs that are currently stickied and published are used; everything else is
filtered out at read time.
