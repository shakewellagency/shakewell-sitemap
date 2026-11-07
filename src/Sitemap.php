<?php
/**
 * Builds the sitemap XML.
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the index and the per post type and per taxonomy files from the effective settings.
 */
final class Sitemap {

	private const NS_SITEMAP = 'http://www.sitemaps.org/schemas/sitemap/0.9';

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $settings Effective settings, see Settings::get().
	 */
	public function __construct( private array $settings ) {}

	/**
	 * Transient name of one cached entry list.
	 *
	 * @param string $kind 'post' or 'tax'.
	 * @param string $name Post type or taxonomy.
	 */
	public static function cache_key( string $kind, string $name ): string {
		return "shakewell_sitemap_{$kind}_{$name}";
	}

	/**
	 * Renders a sitemap by its name: index, post-type-<type>[-<page>] or taxonomy-<taxonomy>[-<page>].
	 *
	 * @param string $name Name from the URL.
	 * @return string|null XML, or null when there is no such sitemap.
	 */
	public function render( string $name ): ?string {
		$head = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

		if ( 'index' === $name ) {
			return $head . $this->index();
		}
		if ( ! preg_match( '/^(post-type|taxonomy)-(.+)$/', $name, $m ) ) {
			return null;
		}

		$kind   = 'post-type' === $m[1] ? 'post' : 'tax';
		$slug   = $m[2];
		$page   = 1;
		$paged  = false;
		$exists = static fn( string $s ): bool => 'post' === $kind ? post_type_exists( $s ) : taxonomy_exists( $s );
		if ( preg_match( '/^(.+)-(\d+)$/', $slug, $p ) && $exists( $p[1] ) ) {
			$slug  = $p[1];
			$page  = (int) $p[2];
			$paged = true;
		}
		if ( ! $exists( $slug ) || ! $this->enabled( $kind, $slug ) ) {
			return null;
		}

		$entries = $this->entries( $kind, $slug );
		// Page 1 has no number in its URL, so "-1" and "-0" are not valid.
		if ( $page < 1 || ( $paged && 1 === $page ) || $page > $this->page_count( $entries ) ) {
			return null;
		}

		$out = $head . '<urlset xmlns="' . self::NS_SITEMAP . '">' . "\n";
		if ( 1 === $page && 'post' === $kind && 'page' === $slug && 'posts' === get_option( 'show_on_front' ) ) {
			$out .= "\t<url>\n\t\t<loc>" . esc_url( $this->url( '/' ) ) . "</loc>\n\t</url>\n";
		}
		if ( $this->settings['per_page'] > 0 ) {
			$entries = array_slice( $entries, ( $page - 1 ) * $this->settings['per_page'], $this->settings['per_page'], true );
		}
		foreach ( $entries as $link => $date ) {
			$out .= "\t<url>\n\t\t<loc>" . esc_url( $link ) . "</loc>\n";
			if ( null !== $date ) {
				$out .= "\t\t<lastmod>" . esc_html( $date ) . "</lastmod>\n";
			}
			$out .= "\t</url>\n";
		}

		return $out . '</urlset>';
	}

	/**
	 * Renders the sitemap index.
	 */
	private function index(): string {
		global $wpdb;

		$out = '<sitemapindex xmlns="' . self::NS_SITEMAP . '">' . "\n";

		foreach ( array_keys( get_post_types( array( 'public' => true ) ) ) as $type ) {
			if ( ! $this->enabled( 'post', $type ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cheap single-row lookup, no core API for it.
			$modified = $wpdb->get_var( $wpdb->prepare( "SELECT post_modified_gmt FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY post_modified_gmt DESC LIMIT 1", $type ) );
			if ( null === $modified ) {
				continue; // Nothing published, so nothing to list.
			}
			$lastmod = gmdate( 'c', strtotime( $modified ) );
			foreach ( $this->file_names( 'post-type', 'post', $type ) as $file ) {
				$out .= "\t<sitemap>\n\t\t<loc>" . esc_url( $this->url( $file ) ) . "</loc>\n\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n\t</sitemap>\n";
			}
		}
		foreach ( array_keys( get_taxonomies( array( 'public' => true ) ) ) as $taxonomy ) {
			if ( ! $this->enabled( 'tax', $taxonomy ) ) {
				continue;
			}
			foreach ( $this->file_names( 'taxonomy', 'tax', $taxonomy ) as $file ) {
				$out .= "\t<sitemap>\n\t\t<loc>" . esc_url( $this->url( $file ) ) . "</loc>\n\t</sitemap>\n";
			}
		}

		return $out . '</sitemapindex>';
	}

	/**
	 * File names of one type's sitemap pages. Page 1 has no number.
	 *
	 * @param string $prefix 'post-type' or 'taxonomy'.
	 * @param string $kind   'post' or 'tax'.
	 * @param string $slug   Post type or taxonomy.
	 * @return string[]
	 */
	private function file_names( string $prefix, string $kind, string $slug ): array {
		$pages = $this->settings['per_page'] > 0 ? $this->page_count( $this->entries( $kind, $slug ) ) : 1;
		$names = array();
		for ( $i = 1; $i <= $pages; $i++ ) {
			$names[] = "sitemap-{$prefix}-{$slug}" . ( $i > 1 ? "-{$i}" : '' ) . '.xml';
		}
		return $names;
	}

	/**
	 * Number of files an entry list needs (at least one).
	 *
	 * @param array<string,string|null> $entries Entries.
	 */
	private function page_count( array $entries ): int {
		return $this->settings['per_page'] > 0 ? max( 1, (int) ceil( count( $entries ) / $this->settings['per_page'] ) ) : 1;
	}

	/**
	 * Whether the settings include this post type or taxonomy.
	 *
	 * @param string $kind 'post' or 'tax'.
	 * @param string $slug Post type or taxonomy.
	 */
	private function enabled( string $kind, string $slug ): bool {
		return in_array( $slug, 'post' === $kind ? $this->settings['post_types'] : $this->settings['taxonomies'], true );
	}

	/**
	 * Builds a site URL, on the public host when one is set.
	 *
	 * @param string $path Path, as for home_url().
	 */
	private function url( string $path ): string {
		return $this->publicise( home_url( $path ) );
	}

	/**
	 * Swaps the WordPress address at the start of a URL for the public one.
	 *
	 * @param string $url Absolute URL.
	 */
	private function publicise( string $url ): string {
		$home = untrailingslashit( home_url() );
		if ( '' !== $this->settings['public_url'] && str_starts_with( $url, $home ) ) {
			return $this->settings['public_url'] . substr( $url, strlen( $home ) );
		}
		return $url;
	}

	/**
	 * Sorted link => lastmod list of one type, cached in a transient.
	 *
	 * The cache carries a signature of everything that shapes it, so changing a setting or constant
	 * rebuilds it. Content changes clear it (see Plugin).
	 *
	 * @param string $kind 'post' or 'tax'.
	 * @param string $slug Post type or taxonomy.
	 * @return array<string,string|null>
	 */
	private function entries( string $kind, string $slug ): array {
		$key = self::cache_key( $kind, $slug );
		$sig = md5( (string) wp_json_encode( array( $this->settings['ignore'], $this->settings['public_url'], $this->settings['exclude_hidden'], home_url(), get_option( 'permalink_structure' ) ) ) );

		$cached = get_transient( $key );
		if ( is_array( $cached ) && ( $cached['sig'] ?? '' ) === $sig ) {
			return $cached['entries'];
		}

		$found   = 'post' === $kind ? $this->post_links( $slug ) : $this->term_links( $slug );
		$ignored = array_filter( explode( ',', $this->settings['ignore'] ) );
		$entries = array();
		foreach ( $found as $link => $date ) {
			foreach ( $ignored as $string ) {
				if ( false !== strpos( $link, $string ) ) {
					continue 2;
				}
			}
			$entries[ $link ] = $date;
		}
		ksort( $entries );

		set_transient( $key, compact( 'sig', 'entries' ), MONTH_IN_SECONDS );
		return $entries;
	}

	/**
	 * Published posts of a type as link => lastmod.
	 *
	 * @param string $type Post type.
	 * @return array<string,string|null>
	 */
	private function post_links( string $type ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result is cached in a transient by the caller.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_modified_gmt, post_password FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $type ) );
		if ( $this->settings['exclude_hidden'] ) {
			update_meta_cache( 'post', wp_list_pluck( $rows, 'ID' ) );
		}

		$links = array();
		foreach ( $rows as $row ) {
			$id = (int) $row->ID;
			if ( $this->settings['exclude_hidden'] && ( '' !== $row->post_password || $this->noindex( $id ) ) ) {
				continue;
			}
			/**
			 * Filters whether a published post is listed.
			 *
			 * @param bool   $include Whether to list it.
			 * @param int    $post_id Post ID.
			 * @param string $type    Post type.
			 */
			if ( ! apply_filters( 'shakewell_sitemap_include_post', true, $id, $type ) ) {
				continue;
			}
			$link           = esc_url( $this->publicise( (string) get_permalink( $id ) ) );
			$links[ $link ] = gmdate( 'c', strtotime( $row->post_modified_gmt ) );
		}
		return $links;
	}

	/**
	 * Terms of a taxonomy that have content, as link => null (terms carry no date).
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,null>
	 */
	private function term_links( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			)
		);
		$links = array();
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$link = get_term_link( $term );
			if ( is_string( $link ) ) {
				$links[ esc_url( $this->publicise( $link ) ) ] = null;
			}
		}
		return $links;
	}

	/**
	 * Whether Yoast SEO or Rank Math marks the post noindex.
	 *
	 * @param int $id Post ID.
	 */
	private function noindex( int $id ): bool {
		return '1' === (string) get_post_meta( $id, '_yoast_wpseo_meta-robots-noindex', true )
			|| in_array( 'noindex', (array) get_post_meta( $id, 'rank_math_robots', true ), true );
	}
}
