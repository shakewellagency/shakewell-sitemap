<?php
/**
 * Sitemap behaviour.
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap\Tests;

use Shakewell\Sitemap\Settings;
use Shakewell\Sitemap\Sitemap;
use WP_UnitTestCase;

require_once dirname( __DIR__ ) . '/Fixtures.php';

/**
 * One test per behaviour.
 */
final class SitemapTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		Fixtures::register();
		Fixtures::create();
		update_option( 'show_on_front', 'posts' );
	}

	public function tear_down(): void {
		unregister_post_type( 'book' );
		unregister_post_type( 'session' );
		parent::tear_down();
	}

	/**
	 * Settings that list every fixture type, ignore some strings and put each type in one file.
	 *
	 * @param array<string,mixed> $more Extra settings.
	 */
	private function listing( array $more = array() ): array {
		add_filter(
			'shakewell_sitemap_settings',
			static fn( $s ) => array_merge(
				$s,
				array(
					'post_types'     => array( 'post', 'page', 'book', 'session' ),
					'ignore'         => Fixtures::IGNORE,
					'per_page'       => 0,
					'exclude_hidden' => false,
				),
				$more
			)
		);
		return Settings::get();
	}

	private function render( array $settings, string $name ): ?string {
		$xml = ( new Sitemap( $settings ) )->render( $name );
		return null === $xml ? null : str_replace( home_url(), '{HOME}', $xml );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function expected_files(): array {
		return array(
			'index'            => array( 'index', 'index' ),
			'posts'            => array( 'post-type-post', 'post-type-post' ),
			'pages'            => array( 'post-type-page', 'post-type-page' ),
			'books'            => array( 'post-type-book', 'post-type-book' ),
			'sessions'          => array( 'post-type-session', 'post-type-session' ),
			'static front page' => array( 'post-type-page-static-front', 'post-type-page' ),
		);
	}

	/**
	 * @dataProvider expected_files
	 */
	public function test_output_matches_the_expected_files( string $fixture, string $name ): void {
		if ( 'post-type-page-static-front' === $fixture ) {
			update_option( 'show_on_front', 'page' );
		}
		$expected = file_get_contents( dirname( __DIR__ ) . "/fixtures/expected/{$fixture}.xml" );
		$this->assertSame( $expected, $this->render( $this->listing(), $name ) );
	}

	public function test_draft_and_private_content_is_excluded(): void {
		$xml = $this->render( Settings::get(), 'post-type-post' );
		$this->assertStringContainsString( 'hello-fx', $xml );
		$this->assertStringNotContainsString( 'draft-fx', $xml );
		$this->assertStringNotContainsString( 'private-fx', $xml );
	}

	public function test_password_protected_content_is_excluded_unless_allowed(): void {
		$this->assertStringNotContainsString( 'protected-fx', $this->render( Settings::get(), 'post-type-post' ) );
		$this->assertStringContainsString( 'protected-fx', $this->render( array_merge( Settings::get(), array( 'exclude_hidden' => false ) ), 'post-type-post' ) );
	}

	public function test_noindex_content_is_excluded_unless_allowed(): void {
		$this->assertStringNotContainsString( 'noindex-fx', $this->render( Settings::get(), 'post-type-post' ) );
		$this->assertStringContainsString( 'noindex-fx', $this->render( array_merge( Settings::get(), array( 'exclude_hidden' => false ) ), 'post-type-post' ) );
	}

	public function test_include_post_filter_can_exclude(): void {
		add_filter( 'shakewell_sitemap_include_post', static fn( $include, $id ) => 'second-fx' === get_post_field( 'post_name', $id ) ? false : $include, 10, 2 );
		$xml = $this->render( Settings::get(), 'post-type-post' );
		$this->assertStringNotContainsString( 'second-fx', $xml );
		$this->assertStringContainsString( 'hello-fx', $xml );
	}

	public function test_public_host_replaces_the_wordpress_host_everywhere(): void {
		$settings = array_merge( $this->listing(), array( 'public_url' => 'https://www.example.com' ) );
		$index    = $this->render( $settings, 'index' );
		$this->assertStringContainsString( '<loc>https://www.example.com/sitemap-post-type-post.xml</loc>', $index );
		$this->assertStringNotContainsString( '{HOME}', $index );

		$page = ( new Sitemap( array_merge( $settings, array( 'ignore' => '' ) ) ) )->render( 'post-type-page' );
		$this->assertStringContainsString( '<loc>https://www.example.com/</loc>', $page, 'front page' );
		$this->assertStringContainsString( '<loc>https://www.example.com/about-fx/team-fx/</loc>', $page );
		$this->assertStringNotContainsString( home_url(), $page );
	}

	public function test_entries_are_split_across_files(): void {
		$settings = array_merge( $this->listing(), array( 'per_page' => 2, 'exclude_hidden' => true ) );
		// Posts left after ignoring: hello, second, third = 2 files.
		$index = $this->render( $settings, 'index' );
		$this->assertStringContainsString( 'sitemap-post-type-post.xml', $index );
		$this->assertStringContainsString( 'sitemap-post-type-post-2.xml', $index );
		$this->assertStringNotContainsString( 'sitemap-post-type-post-3.xml', $index );

		$one = $this->render( $settings, 'post-type-post' );
		$two = $this->render( $settings, 'post-type-post-2' );
		$this->assertSame( 2, substr_count( $one, '<url>' ) );
		$this->assertSame( 1, substr_count( $two, '<url>' ) );
		$this->assertStringContainsString( 'third-fx', $two );
		$this->assertNull( $this->render( $settings, 'post-type-post-3' ) );
		$this->assertNull( $this->render( $settings, 'post-type-post-1' ) );
	}

	public function test_urls_are_escaped(): void {
		$xml = $this->render( $this->listing(), 'post-type-session' );
		$this->assertStringContainsString( '?a=1&#038;b=&#039;2&#039;</loc>', $xml );
		$this->assertStringNotContainsString( '&b=', $xml );
		$this->assertNotFalse( simplexml_load_string( $xml ), 'well-formed XML' );
	}

	public function test_cache_refreshes_when_a_post_is_published(): void {
		\Shakewell\Sitemap\Plugin::boot();
		$settings = Settings::get();
		$this->assertStringNotContainsString( 'fresh-fx', $this->render( $settings, 'post-type-post' ) );
		$this->assertNotFalse( get_transient( Sitemap::cache_key( 'post', 'post' ) ), 'built and cached' );

		self::factory()->post->create( array( 'post_name' => 'fresh-fx', 'post_status' => 'publish' ) );
		$this->assertStringContainsString( 'fresh-fx', $this->render( $settings, 'post-type-post' ) );
	}

	public function test_cache_refreshes_when_a_post_is_trashed(): void {
		\Shakewell\Sitemap\Plugin::boot();
		$settings = Settings::get();
		$this->assertStringContainsString( 'hello-fx', $this->render( $settings, 'post-type-post' ) );
		wp_trash_post( get_page_by_path( 'hello-fx', OBJECT, 'post' )->ID );
		$this->assertStringNotContainsString( 'hello-fx', $this->render( $settings, 'post-type-post' ) );
	}

	public function test_cache_is_rebuilt_when_settings_change(): void {
		$this->assertStringContainsString( 'second-fx', $this->render( Settings::get(), 'post-type-post' ) );
		$this->assertStringNotContainsString( 'second-fx', $this->render( array_merge( Settings::get(), array( 'ignore' => 'second-fx' ) ), 'post-type-post' ) );
	}

	public function test_taxonomy_sitemap_lists_terms_with_content(): void {
		$used = self::factory()->category->create( array( 'name' => 'Used', 'slug' => 'used-fx' ) );
		self::factory()->category->create( array( 'name' => 'Empty', 'slug' => 'empty-fx' ) );
		wp_set_post_categories( get_page_by_path( 'hello-fx', OBJECT, 'post' )->ID, array( $used ) );

		$settings = array_merge( Settings::get(), array( 'taxonomies' => array( 'category' ) ) );
		$xml      = $this->render( $settings, 'taxonomy-category' );
		$this->assertStringContainsString( 'used-fx', $xml );
		$this->assertStringNotContainsString( 'empty-fx', $xml );
		$this->assertStringContainsString( '<loc>{HOME}/sitemap-taxonomy-category.xml</loc>', $this->render( $settings, 'index' ) );
	}

	public function test_unknown_or_switched_off_types_are_not_found(): void {
		$settings = Settings::get(); // Posts and pages only.
		$this->assertNull( $this->render( $settings, 'post-type-book' ) );
		$this->assertNull( $this->render( $settings, 'post-type-nope' ) );
		$this->assertNull( $this->render( $settings, 'taxonomy-category' ) );
		$this->assertNull( $this->render( $settings, 'other' ) );
	}

	public function test_constant_style_values_are_normalised(): void {
		add_filter(
			'shakewell_sitemap_settings',
			static fn( $s ) => array_merge( $s, array( 'ignore' => array( 'a', 'b' ), 'public_url' => 'https://x.test/', 'per_page' => 999999 ) )
		);
		$s = Settings::get();
		$this->assertSame( 'a,b', $s['ignore'] );
		$this->assertSame( 'https://x.test', $s['public_url'] );
		$this->assertSame( 50000, $s['per_page'] );
	}

	public function test_sanitize_keeps_only_public_types_and_clean_values(): void {
		$s = Settings::sanitize(
			array(
				'post_types' => array( 'post', 'revision', 'nope' ),
				'ignore'     => '<b>x</b>,y',
				'public_url' => 'javascript:alert(1)',
				'per_page'   => '-5',
			)
		);
		$this->assertSame( array( 'post' ), $s['post_types'] );
		$this->assertSame( 'x,y', $s['ignore'] );
		$this->assertSame( '', $s['public_url'] );
		$this->assertSame( 5, $s['per_page'] );
		$this->assertFalse( $s['exclude_hidden'], 'an unchecked box saves as off' );
	}
}
