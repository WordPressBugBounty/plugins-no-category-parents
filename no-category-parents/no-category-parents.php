<?php
/*
Plugin Name: No Category Parents
Plugin URI: https://wordpress.org/plugins/no-category-parents/
Description: Removes the category base and every parent category from your category permalinks. It also works for post permalinks when using the /%category%/ permastruct.
Version: 0.3.0
Requires at least: 6.0
Requires PHP: 7.4
Author: Sergio Milardovich
Author URI: https://milardovich.com.ar/
Donate link: https://milardovich.com.ar/
Text Domain: no-category-parents
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

/*
	Based on "WP No Category Base" code -> http://wordpresssupplies.com/

	Copyright 2009-2026  Sergio Milardovich

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NCP_FLUSH_FLAG = 'ncp_needs_flush';

/**
 * Suffix appended to a category slug when a post or page already owns that slug.
 */
function ncp_collision_suffix() {
	/**
	 * Filters the suffix used to disambiguate a category whose slug collides
	 * with a post or page slug. Kept as "-cat" for backwards compatibility.
	 *
	 * @param string $suffix
	 */
	return apply_filters( 'ncp_collision_suffix', '-cat' );
}

/**
 * Whether some post or page already answers to this slug.
 *
 * The result is cached per request: rebuilding the rewrite rules asks this
 * question once per category, and every category link asks it again.
 */
function ncp_slug_taken_by_post( $slug ) {
	static $cache = array();

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$posts = get_posts(
		array(
			'name'                   => $slug,
			'post_type'              => array( 'post', 'page' ),
			'post_status'            => 'publish',
			'numberposts'            => 1,
			'fields'                 => 'ids',
			'suppress_filters'       => false,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);

	$cache[ $slug ] = ! empty( $posts );

	return $cache[ $slug ];
}

/**
 * The single path segment a category should live under: its own slug, plus the
 * collision suffix when a post or page already claims it.
 */
function ncp_category_slug( $term ) {
	$slug = $term->slug;

	if ( ncp_slug_taken_by_post( $slug ) ) {
		$slug .= ncp_collision_suffix();
	}

	return $slug;
}

/*
 * ---------------------------------------------------------------------------
 * Permalinks
 * ---------------------------------------------------------------------------
 */

/**
 * Rebuild category links as /slug/ instead of /category/parent/child/.
 *
 * Building the URL from scratch is what makes this work at any nesting depth.
 * Versions up to 0.2.4.1 rewrote the string WordPress had already produced and
 * left the immediate parent behind whenever a category had a grandparent.
 */
add_filter( 'term_link', 'ncp_filter_term_link', 10, 3 );
function ncp_filter_term_link( $termlink, $term, $taxonomy ) {
	if ( 'category' !== $taxonomy ) {
		return $termlink;
	}

	// Plain permalinks (?cat=12) have nothing to strip.
	if ( ! get_option( 'permalink_structure' ) ) {
		return $termlink;
	}

	return home_url( user_trailingslashit( ncp_category_slug( $term ), 'category' ) );
}

/**
 * Keep WordPress from expanding %category% itself, so it does not prepend the
 * whole parent chain. ncp_filter_post_link() fills the placeholder in instead.
 */
add_filter( 'pre_post_link', 'ncp_filter_pre_post_link' );
function ncp_filter_pre_post_link( $permalink ) {
	return str_replace( '%category%', '%ncp_category%', $permalink );
}

/**
 * Replace our placeholder with the post's primary category slug only.
 */
add_filter( 'post_link', 'ncp_filter_post_link', 10, 2 );
function ncp_filter_post_link( $permalink, $post ) {
	if ( false === strpos( $permalink, '%ncp_category%' ) ) {
		return $permalink;
	}

	$category = 'uncategorized';

	$cats = get_the_category( $post->ID );
	if ( $cats ) {
		// Lowest term ID wins, matching core's own %category% behaviour.
		usort(
			$cats,
			static function ( $a, $b ) {
				return $a->term_id <=> $b->term_id;
			}
		);
		$category = $cats[0]->slug;
	}

	/**
	 * Filters the category slug used in a post permalink.
	 *
	 * @param string  $category Slug that will replace %category%.
	 * @param WP_Post $post
	 */
	$category = apply_filters( 'ncp_post_link_category', $category, $post );

	return str_replace( '%ncp_category%', $category, $permalink );
}

/*
 * ---------------------------------------------------------------------------
 * Rewrite rules
 * ---------------------------------------------------------------------------
 */

/**
 * Force an empty category base without writing to the database on every load.
 */
add_filter( 'option_category_base', 'ncp_force_empty_category_base' );
function ncp_force_empty_category_base( $value ) {
	return '';
}

/**
 * Teach WordPress to resolve the flattened category URLs.
 *
 * One exact-match rule per category (plus pagination and feeds), and the legacy
 * "any path ending in a category slug" fallback that older links may rely on.
 * Categories whose slug is taken by a post are only reachable through their
 * suffixed form, so the post keeps its own URL.
 */
add_filter( 'rewrite_rules_array', 'ncp_insert_rewrite_rules' );
function ncp_insert_rewrite_rules( $rules ) {
	global $wp_rewrite;

	$pagination = $wp_rewrite->pagination_base;
	$feeds      = '(' . implode( '|', $wp_rewrite->feeds ) . ')';

	$new = array();

	// Generic escape hatch: /anything-cat/ always resolves to a category.
	$suffix = preg_quote( ncp_collision_suffix(), '#' );

	$new[ '(.+?)' . $suffix . '/?$' ]                                 = 'index.php?category_name=$matches[1]';
	$new[ '(.+?)' . $suffix . '/' . $pagination . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
	$new[ '(.+?)' . $suffix . '/feed/?' . $feeds . '?/?$' ]            = 'index.php?category_name=$matches[1]&feed=$matches[2]';

	$categories = get_categories( array( 'hide_empty' => false ) );

	foreach ( $categories as $category ) {
		// The -cat rules above already cover this one; claiming the bare slug
		// here would steal the URL from the post that owns it.
		if ( ncp_slug_taken_by_post( $category->slug ) ) {
			continue;
		}

		$slug = preg_quote( $category->slug, '#' );

		$new[ '(' . $slug . ')/?$' ]                                     = 'index.php?category_name=$matches[1]';
		$new[ '(' . $slug . ')/' . $pagination . '/?([0-9]{1,})/?$' ]     = 'index.php?category_name=$matches[1]&paged=$matches[2]';
		$new[ '(' . $slug . ')/feed/?' . $feeds . '?/?$' ]                = 'index.php?category_name=$matches[1]&feed=$matches[2]';

		// Legacy links that still carry the parent path.
		$new[ '.+?/(' . $slug . ')/?$' ]                                 = 'index.php?category_name=$matches[1]';
		$new[ '.+?/(' . $slug . ')/' . $pagination . '/?([0-9]{1,})/?$' ] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
	}

	return $new + $rules;
}

/*
 * ---------------------------------------------------------------------------
 * Flushing
 * ---------------------------------------------------------------------------
 */

/**
 * Mark the rules as stale. Flushing right here would be wrong: these hooks fire
 * once per row during an import, and flush_rules() rebuilds the whole table.
 */
function ncp_schedule_flush() {
	update_option( NCP_FLUSH_FLAG, 1 );
}
add_action( 'created_category', 'ncp_schedule_flush' );
add_action( 'edited_category', 'ncp_schedule_flush' );
add_action( 'delete_category', 'ncp_schedule_flush' );

/**
 * Do the deferred flush, at most once, and only when something changed.
 *
 * Versions up to 0.2.4.1 called flush_rules() on every single wp_loaded, which
 * regenerated and re-saved every rewrite rule on every page view.
 */
add_action( 'wp_loaded', 'ncp_maybe_flush_rules' );
function ncp_maybe_flush_rules() {
	if ( ! get_option( NCP_FLUSH_FLAG ) ) {
		return;
	}

	delete_option( NCP_FLUSH_FLAG );

	global $wp_rewrite;
	$wp_rewrite->flush_rules( false );
}

register_activation_hook( __FILE__, 'ncp_activate' );
function ncp_activate() {
	update_option( 'category_base', '' );
	update_option( NCP_FLUSH_FLAG, 1 );
}

register_deactivation_hook( __FILE__, 'ncp_deactivate' );
function ncp_deactivate() {
	delete_option( NCP_FLUSH_FLAG );

	// Drop our filters first so this rebuilds the stock rules.
	remove_filter( 'rewrite_rules_array', 'ncp_insert_rewrite_rules' );
	remove_filter( 'option_category_base', 'ncp_force_empty_category_base' );

	global $wp_rewrite;
	$wp_rewrite->flush_rules( false );
}
