<?php
/**
 * Deterministic content shared by the tests.
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap\Tests;

/**
 * Posts, pages, books and sessions with fixed slugs and dates.
 */
final class Fixtures {

	public const IGNORE = 'skip-me,/hidden/';

	/**
	 * Registers the custom post types and a clean permalink structure.
	 */
	public static function register(): void {
		global $wp_rewrite;
		update_option( 'permalink_structure', '/%postname%/' );
		$wp_rewrite->init();
		create_initial_taxonomies();
		register_post_type(
			'book',
			array(
				'public'  => true,
				'label'   => 'Books',
				'rewrite' => array( 'slug' => 'books' ),
			)
		);
		register_post_type(
			'session',
			array(
				'public'  => true,
				'label'   => 'Sessions',
				'rewrite' => array( 'slug' => 'sessions' ),
			)
		);
		$wp_rewrite->flush_rules( false );
		// A query string with an ampersand and a quote, to prove escaping.
		add_filter(
			'post_type_link',
			static function ( $link, $post ) {
				return 'ampersand-session' === $post->post_name ? $link . "?a=1&b='2'" : $link;
			},
			10,
			2
		);
	}

	/**
	 * Removes every post of the fixture types, then creates the fixtures.
	 */
	public static function create(): void {
		$all = get_posts(
			array(
				'post_type'   => array( 'post', 'page', 'book', 'session' ),
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);
		foreach ( $all as $id ) {
			wp_delete_post( $id, true );
		}

		$rows = array(
			// type, slug, status, date, extra.
			array( 'post', 'hello-fx', 'publish', '2024-01-10 09:00:00', array() ),
			array( 'post', 'second-fx', 'publish', '2024-03-05 13:30:00', array() ),
			array( 'post', 'third-fx', 'publish', '2024-04-01 08:15:00', array() ),
			array( 'post', 'skip-me-post', 'publish', '2024-02-01 00:00:00', array() ),
			array( 'post', 'draft-fx', 'draft', '2024-02-02 00:00:00', array() ),
			array( 'post', 'private-fx', 'private', '2024-02-03 00:00:00', array() ),
			array( 'post', 'protected-fx', 'publish', '2024-02-04 00:00:00', array( 'post_password' => 'secret' ) ),
			array( 'post', 'noindex-fx', 'publish', '2024-02-05 00:00:00', array( 'meta_input' => array( '_yoast_wpseo_meta-robots-noindex' => '1' ) ) ),
			array( 'page', 'about-fx', 'publish', '2024-05-01 10:00:00', array() ),
			array( 'page', 'contact-fx', 'publish', '2024-05-02 10:00:00', array() ),
			array( 'page', 'draft-page-fx', 'draft', '2024-05-03 10:00:00', array() ),
			array( 'book', 'dune-fx', 'publish', '2024-06-01 11:00:00', array() ),
			array( 'book', 'emma-fx', 'publish', '2024-06-02 11:00:00', array() ),
			array( 'session', 'keynote-fx', 'publish', '2024-07-01 18:00:00', array() ),
			array( 'session', 'skip-me-session', 'publish', '2024-07-02 18:00:00', array() ),
			array( 'session', 'ampersand-session', 'publish', '2024-07-03 18:00:00', array() ),
		);

		$about = 0;
		foreach ( $rows as $row ) {
			list( $type, $slug, $status, $date, $extra ) = $row;
			$id = wp_insert_post(
				array_merge(
					array(
						'post_type'     => $type,
						'post_name'     => $slug,
						'post_title'    => $slug,
						'post_status'   => $status,
						'post_date'     => $date,
						'post_date_gmt' => $date,
					),
					$extra
				)
			);
			if ( 'about-fx' === $slug ) {
				$about = $id;
			}
		}
		// A child page, so hierarchical permalinks are covered.
		wp_insert_post(
			array(
				'post_type'     => 'page',
				'post_name'     => 'team-fx',
				'post_title'    => 'team-fx',
				'post_status'   => 'publish',
				'post_parent'   => $about,
				'post_date'     => '2024-05-04 10:00:00',
				'post_date_gmt' => '2024-05-04 10:00:00',
			)
		);
	}
}
