=== Sticky Post Order ===
Contributors: kaisyuan-tseng
Tags: sticky, order, sort, queue, rest, headless
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
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
* Exposes two read-only REST fields, `sticky_order_position` and `sticky_order_queue`, so a
  headless front end gets the order with the post list — no extra request, no slug join.
* Appends newly stickied posts to the tail in reverse-date order; they never jump ahead.
* Drops posts automatically when they are unstickied or unpublished. No stale IDs to clean.
* Lists stickied posts that match no group at the bottom of the admin page, so a post
  never silently disappears.
* Disabling the plugin simply removes the REST fields; consumers fall back to date order.

The plugin contains no site-specific term names, post types, or copy. Groups, labels,
capability, menu location, option name, and post types are all injected through filters,
so the directory can be copied to any other site as is. Admin UI strings use gettext:
source msgids are English and a Simplified Chinese catalog is bundled in `/languages`.
Any site may override the labels through the `sticky_order_labels` filter.

== Installation ==

1. Put the `sticky-order` directory into `wp-content/plugins/`.
2. Activate "Sticky Post Order" on the Plugins screen.
3. Declare groups with `sticky_order_groups`, or leave it empty for single-queue mode.
4. Consume `sticky_order_position` in the front end and rebuild static output after reordering.

== Frequently Asked Questions ==

= Does deactivating the plugin lose the saved order? =

No. Deactivation only removes the REST fields, so consumers fall back to date order; the
queue option is kept. Re-activating restores the order. The option is deleted only when
the plugin is deleted, via `uninstall.php`.

= What language is the admin UI in? =

Source strings are English; a Simplified Chinese catalog is bundled in `/languages` and
loads automatically when the site locale is `zh_CN`. On other locales the UI is English
unless you override it with the `sticky_order_labels` filter. Keep the plugin folder and
its `languages/` directory together when deploying.

= How do I set up groups? =

Return a `slug => label` map from the `sticky_order_groups` filter, where each slug matches
a term slug in the taxonomy from `sticky_order_group_taxonomy` (`category` by default).
Leave it empty for a single shared queue. A site-specific example lives in this site's
`wp-content/mu-plugins/sticky-order-config.php`.

= I upgraded from 1.0.0 — where did my saved order go? =

2.0.0 is a hard cut: the storage option was renamed from `sticky_order_queues` to
`sticky_order_queue_map` and existing order data is not migrated. After upgrading, visit
the Sticky Order page under Posts and save once to rebuild the queue. The old option is
cleaned up when the plugin is uninstalled. The headless front end must also switch to the
renamed REST fields `sticky_order_position` and `sticky_order_queue`.

== Hooks ==

`sticky_order_groups` (array)
  Group map `array( slug => label )`; slugs match term slugs. Empty = single-queue mode.

`sticky_order_group_taxonomy` (string)
  Taxonomy used for grouping. Default `category`.

`sticky_order_post_types` (array)
  Post types included. Default `array( 'post' )`.

`sticky_order_option_name` (string)
  Option holding the queues. Default `sticky_order_queue_map`.

`sticky_order_capability` (string)
  Capability required for the admin page. Default `edit_others_posts`.

`sticky_order_menu_parent` (string)
  Parent admin menu. Default `edit.php`.

`sticky_order_labels` (array)
  Admin UI copy. Missing keys fall back to defaults.

== REST ==

Two read-only fields are added to the post response:

`sticky_order_position` (integer|null)
  Position in its queue, 0-based. `null` when the post is not queued. In grouped mode
  this is the position within that group; it is not comparable across groups.

`sticky_order_queue` (string|null)
  Key of the owning queue; `default` in single-queue mode.

Posts that are not stickied return `null` for both fields, which tells the front end to
fall back to date order.

== Storage ==

Option `sticky_order_queue_map`, for example:

    array(
        'section-a' => array( 28 ),        // array order = queue order
        'section-b' => array( 60, 61 ),
    )

Only IDs that are currently stickied and published are used; everything else is
filtered out at read time.

== Changelog ==

= 2.0.0 =
* Normalized to WordPress Coding Standards; added `uninstall.php`, `LICENSE.txt` and a
  full i18n catalog (English source msgids + bundled Simplified Chinese PO/MO).
* Hard-cut contract rename (no aliases, no data migration): REST fields
  `sticky_order_rank` → `sticky_order_position` and `sticky_order_group` →
  `sticky_order_queue`; storage option `sticky_order_queues` → `sticky_order_queue_map`.
  Headless consumers must update their field names and rebuild static output.
* Completed readme with FAQ and this changelog; filled missing plugin headers
  (`License URI`, `Domain Path`).

= 1.0.0 =
* Initial release: ordered sticky queues by taxonomy group, admin drag-and-drop sorting,
  two read-only REST fields.
