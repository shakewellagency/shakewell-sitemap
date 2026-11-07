<?php
/**
 * Plugin Name:       Shakewell Sitemap
 * Plugin URI:        https://github.com/shakewellagency/shakewell-sitemap
 * Description:       A small XML sitemap for normal and headless WordPress sites: post types, taxonomies, ignored paths, the public host and entries per file.
 * Version:           0.1.1
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Shakewell
 * Author URI:        https://shakewell.agency
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       shakewell-sitemap
 *
 * @package Shakewell\Sitemap
 */

namespace Shakewell\Sitemap;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.1.1';
const FILE    = __FILE__;

require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Sitemap.php';
require_once __DIR__ . '/src/Plugin.php';

add_action( 'plugins_loaded', array( Plugin::class, 'boot' ) );
register_activation_hook( __FILE__, array( Plugin::class, 'flush_rules' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
