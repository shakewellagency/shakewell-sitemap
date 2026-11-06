=== Shakewell Sitemap ===
Contributors: shakewell
Tags: sitemap, xml sitemap, seo, headless
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A small XML sitemap for normal and headless WordPress sites: choose post types and taxonomies, ignore paths, publish a different host, split into files.

== Description ==

Shakewell Sitemap serves `/sitemap.xml` (an index) and one sitemap per post type or taxonomy you choose, at `/sitemap-post-type-<type>.xml` and `/sitemap-taxonomy-<taxonomy>.xml`. It has no ads, no tracking and no locked features.

* Lists published content only. Drafts, pending, private and trashed content is never listed.
* Leaves out password protected content and content marked noindex by Yoast SEO or Rank Math (switchable).
* Leaves out any URL containing a string you list.
* Can publish a different host from WordPress's own, for headless sites.
* Splits large types into several files (up to 50000 entries each).
* Caches each list for a month and refreshes it when content or terms change.
* Sends `X-Robots-Tag: noindex, follow` on the sitemap files, so the files themselves stay out of search results.

It deliberately does not add images or alternate languages, does not ping search engines (Google and Bing retired their ping endpoints), and does not write a `robots.txt` line. Add `Sitemap: https://example.com/sitemap.xml` to your robots.txt yourself.

= Relation to WordPress's own sitemap =

WordPress core serves `wp-sitemap.xml`. By default this plugin switches core's sitemap off completely (its URLs return 404) so search engines see one sitemap. Untick "Turn off the built-in wp-sitemap.xml" (or set `SHAKEWELL_SITEMAP_DISABLE_CORE` to `false`) to run both side by side; they use different URLs.

= Settings =

Settings > Sitemap. Every setting can also be fixed in `wp-config.php` with a constant, or in code with the `shakewell_sitemap_settings` filter. A constant overrides the saved value; the filter overrides both.

* Post types: `SHAKEWELL_SITEMAP_POST_TYPES`, an array of slugs. Default `post` and `page`.
* Taxonomies: `SHAKEWELL_SITEMAP_TAXONOMIES`, an array of slugs. Default none. Only terms with published content are listed.
* Ignored URL strings: `SHAKEWELL_SITEMAP_IGNORE`, a comma separated string or an array. A URL containing any of them is left out. Strings are matched exactly, so write `a,b`, not `a, b`.
* Public site URL: `SHAKEWELL_SITEMAP_PUBLIC_URL`, for example `https://www.example.com`. It replaces the WordPress address at the start of every URL. Empty by default.
* Entries per file: `SHAKEWELL_SITEMAP_PER_PAGE`, 0 to 50000. Default 1000. 0 means one file per type with no limit.
* Hidden content: `SHAKEWELL_SITEMAP_EXCLUDE_HIDDEN`, default `true`. `false` lists password protected and noindex content too.
* Built-in sitemap: `SHAKEWELL_SITEMAP_DISABLE_CORE`, default `true`.

= Filters =

* `shakewell_sitemap_settings( $settings )`: the effective settings array, keys `post_types`, `taxonomies`, `ignore`, `public_url`, `per_page`, `exclude_hidden`, `disable_core`.
* `shakewell_sitemap_include_post( $include, $post_id, $post_type )`: return `false` to leave a published post out.

= Headless use =

Set the public site URL to the address visitors use. Every `<loc>`, including the index entries and the home page entry, then starts with that address instead of WordPress's. Serve `/sitemap.xml` and `/sitemap-*.xml` from the front end by proxying them from WordPress, or point search engines at the WordPress address directly.

= Paging =

With entries per file set, page 1 keeps the plain name (`sitemap-post-type-post.xml`) and later pages are numbered (`sitemap-post-type-post-2.xml`). The index lists every page. A page number that does not exist returns 404.

= External services =

None. The plugin makes no outside requests.

= Moving from Merivale Sitemap =

This plugin replaces the Merivale sitemap plugin (`merivale-sitemap`) and produces the same files, URLs, order and XML for Merivale's settings. Merivale needs these settings:

* `define( 'SHAKEWELL_SITEMAP_PER_PAGE', 0 );` One file per type, as before.
* `define( 'SHAKEWELL_SITEMAP_EXCLUDE_HIDDEN', false );` Merivale Sitemap listed password protected content and did not read noindex flags.
* Post types and ignored strings: copy them from the old option, once:
`wp eval '$o = get_option( "merivale_sitemap", array() ); update_option( "shakewell_sitemap", array( "post_types" => $o["post_types"] ?? array(), "taxonomies" => array(), "ignore" => $o["url_ignore"] ?? "", "public_url" => "", "per_page" => 0, "exclude_hidden" => false, "disable_core" => true ) );'`
* Leave the public site URL empty: Merivale's Next.js site swaps the WordPress host itself.

Then deactivate Merivale Sitemap, activate Shakewell Sitemap, run `wp rewrite flush`, and compare `/sitemap.xml` and each `/sitemap-post-type-*.xml` before and after. What differs: the plugin no longer pings Google and Bing on save; a sitemap URL for a type that is not switched on, or a page number that does not exist, returns 404 instead of a list; an empty post type is left out of the index instead of causing a PHP warning.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it with Composer.
2. Activate it from the Plugins screen. Activation refreshes the permalink rules.
3. Choose what to list under Settings > Sitemap.

== Frequently Asked Questions ==

= Why does /sitemap.xml show a 404? =

Permalinks must be on (anything but "Plain"), and the rules need a refresh: run `wp rewrite flush` or save Settings > Permalinks.

= Why is a post missing? =

It is not published, it is password protected or noindex, its URL contains an ignored string, its post type is not switched on, or the cached list has not refreshed yet. Saving the post or any setting change refreshes it.

= Can I add the sitemap to robots.txt? =

Yes, add the line `Sitemap: https://example.com/sitemap.xml` to your robots.txt.

= Where do I report a security problem? =

Please do not open a public issue. Follow SECURITY.md in the plugin's repository.

== Changelog ==

= 0.1.0 =
* First release. Based on Merivale Sitemap 1.1.0 with identical output for the same settings, generalised with settings, constants and filters, taxonomy sitemaps, paging, a public host and a safe cache.
