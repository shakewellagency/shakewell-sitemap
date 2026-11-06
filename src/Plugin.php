<?php
/**
 * Plugin bootstrap.
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the sitemap into WordPress: rewrite rules, serving, cache clearing and the settings screen.
 */
final class Plugin {

	public const QUERY_VAR = 'shakewell_sitemap';

	/**
	 * Registers hooks. Called on plugins_loaded.
	 */
	public static function boot(): void {
		// Priority 5, before core registers its sitemaps on init at 10.
		add_action( 'init', array( self::class, 'init' ), 5 );
		add_filter(
			'query_vars',
			static function ( array $vars ): array {
				$vars[] = self::QUERY_VAR;
				return $vars;
			}
		);
		add_action( 'template_redirect', array( self::class, 'serve' ), 0 );
		add_action( 'admin_menu', array( Settings::class, 'register' ) );
		add_action( 'transition_post_status', array( self::class, 'post_changed' ), 10, 3 );
		add_action( 'deleted_post', array( self::class, 'post_deleted' ), 10, 2 );
		foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
			add_action( $hook, array( self::class, 'term_changed' ), 10, 3 );
		}
	}

	/**
	 * Adds the rewrite rules and, unless switched off, removes core's sitemaps.
	 */
	public static function init(): void {
		if ( Settings::get()['disable_core'] ) {
			// Removing the init callback (not just the enabled filter) also drops core's rewrite rules.
			remove_action( 'init', 'wp_sitemaps_get_server' );
		}
		add_rewrite_rule( 'sitemap\.xml$', 'index.php?' . self::QUERY_VAR . '=index', 'top' );
		add_rewrite_rule( 'sitemap-((?:post-type|taxonomy)-[^/]+?)\.xml$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Flushes rewrite rules (activation).
	 */
	public static function flush_rules(): void {
		self::init();
		flush_rewrite_rules();
	}

	/**
	 * Prints a sitemap request, or a 404 for an unknown or switched-off one.
	 */
	public static function serve(): void {
		$name = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $name ) {
			return;
		}

		$xml = ( new Sitemap( Settings::get() ) )->render( $name );
		if ( null === $xml ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		status_header( 200 );
		header( 'Content-type: text/xml; charset=utf-8', true );
		header( 'X-Robots-Tag: noindex, follow', true );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value in the XML is escaped where it is built.
		exit;
	}

	/**
	 * Clears the cached list of the changed post's type, and of the taxonomies whose term counts it moves.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public static function post_changed( $new_status, $old_status, $post ): void {
		unset( $new_status, $old_status );
		self::clear( 'post', $post->post_type );
		foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
			self::clear( 'tax', $taxonomy );
		}
	}

	/**
	 * Clears the cached list of a deleted post's type.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public static function post_deleted( $post_id, $post ): void {
		unset( $post_id );
		self::post_changed( '', '', $post );
	}

	/**
	 * Clears the cached list of a taxonomy.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function term_changed( $term_id, $tt_id, $taxonomy ): void {
		unset( $term_id, $tt_id );
		self::clear( 'tax', $taxonomy );
	}

	/**
	 * Deletes one cached list.
	 *
	 * @param string $kind 'post' or 'tax'.
	 * @param string $name Post type or taxonomy.
	 */
	private static function clear( string $kind, string $name ): void {
		delete_transient( Sitemap::cache_key( $kind, $name ) );
	}
}
