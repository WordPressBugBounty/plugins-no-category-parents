=== No Category Parents ===
Contributors: milardovich
Tags: categories, category parents, category base, permalinks, seo
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://milardovich.com.ar/

Removes the mandatory 'Category Base' and every parent category from your category permalinks.

== Description ==

This plugin completely removes the mandatory 'Category Base' and all the parents from your
category permalinks (e.g. `/category/parent-category/my-category/` becomes `/my-category/`).

It also shortens post permalinks when you use the `/%category%/` permastruct, so a post filed
under a deeply nested category still gets a short URL.

If a post or page already uses the same slug as a category, the post keeps the clean URL and
the category is served from `/my-category-cat/` instead.

== Installation ==

1. Install and activate the plugin through the 'Plugins' menu in WordPress.
2. That's it. Your categories are now reachable at http://mysite.com/my-category/

Deactivating the plugin restores the stock WordPress permalinks.

== Frequently Asked Questions ==

= My category URLs did not change =

Go to Settings > Permalinks and press Save once. That rebuilds the rewrite rules.

= Do old links with the parent path still work? =

Yes. `/parent-category/my-category/` keeps resolving to the same archive.

== Changelog ==

= 0.3.0 =
* Compatible with WordPress 7.0 and PHP 8.
* Fixed: parent categories were only removed one level deep. A category nested two or more
  levels down (parent > child > grandchild) kept its immediate parent in the URL. Links are
  now rebuilt from the term instead of being regex-patched, so any depth works.
* Fixed: PHP 8 warnings ("Undefined array key 0", "Attempt to read property ID on null")
  when building permalinks.
* Fixed: the rewrite rules were flushed on *every* page load, rebuilding and re-saving the
  whole rule set on each request. Flushing now happens once, only after a category changes.
  Measured on a local WordPress 7.0 install: ~197 ms/request before, ~114 ms/request after.
* Added: category feeds (`/my-category/feed/`) now resolve.
* Changed: all functions are prefixed with `ncp_`. Earlier versions declared generic names
  such as `filter_category()` and `my_flush_rules()` in the global namespace, which could
  collide with a theme or another plugin.
* Changed: the category base is now forced empty through a filter instead of being written
  to the database on every request.
* Added: `ncp_collision_suffix` and `ncp_post_link_category` filters.
* Removed: leftover debug code and an unused `id` query var.

= 0.2.4.1 =
* Quickfix: fixed pagination bug

= 0.2.4 =
* Tested up to WP 4.1
* Fixed pagination problem (special thanks to Lukáš Wojnar).

= 0.2.3 =
* Changed some links.
* Fixed "empty category" problem (special thanks to absolutex).

= 0.2.2 =
* In 0.2.1 when the "Category Base" field wasn't empty the plugin didn't work. Now the
  "Category Base" field will automatically be empty when you activate the plugin.

= 0.2.1 =
* Minor changes in the comments.

= 0.2 =
* The plugin now works with the permastruct /%category%/ and also replaces the post permalinks.
* Other minor fixes.

== Upgrade Notice ==

= 0.3.0 =
Fixes deeply nested categories, removes a rewrite flush that ran on every page load, and adds
WordPress 7.0 / PHP 8 compatibility.
