<?php
/**
 * Plugin Name:       Opti Analytics
 * Plugin URI:        https://example.com/opti-analytics
 * Description:       A custom sales analytics plugin for WooCommerce supporting dynamic custom fields and Excel exports.
 * Version:           0.0.1-beta
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Your Name
 * Text Domain:       opti-analytics
 * Domain Path:       /languages
 */

// Enforce strict typing to catch errors early.
declare(strict_types=1);

// Define our namespace to avoid function/class name conflicts.
namespace OptiAnalytics;

// Security Best Practice: Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define helpful plugin constants for paths and URLs.
define('OPTI_ANALYTICS_VERSION', '0.0.1-beta');
define('OPTI_ANALYTICS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OPTI_ANALYTICS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Require the main core class.
// Note: In the next step, we will replace this manual require with Composer's autoloader.
require_once OPTI_ANALYTICS_PLUGIN_DIR . 'includes/class-core.php';

/**
 * Bootstraps the plugin.
 * We hook this to 'plugins_loaded' to ensure all other plugins (like WooCommerce) are loaded first.
 */
function init(): void
{
    // Instantiate our main class
    $plugin_core = new Core();
    $plugin_core->run();
}
add_action('plugins_loaded', __NAMESPACE__ . '\init');