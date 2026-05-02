<?php
/**
 * Plugin Name: Markdown Importer
 * Description: Import and manage articles from Markdown files with CTA blocks, images, and metadata.
 * Version: 1.0.0
 * Author: Bitcoin Marketplace
 * Text Domain: markdown-importer
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MI_VERSION', '1.0.0');
define('MI_PLUGIN_FILE', __FILE__);
define('MI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MI_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MI_PLUGIN_DIR . 'lib/Parsedown.php';
require_once MI_PLUGIN_DIR . 'includes/class-mi-autoload.php';

register_activation_hook(__FILE__, ['MI_Plugin', 'activate']);

add_action('plugins_loaded', ['MI_Plugin', 'instance']);
