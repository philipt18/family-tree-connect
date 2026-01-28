<?php
/**
 * Plugin Name: Family Tree Connect
 * Plugin URI: https://example.com/family-tree-connect
 * Description: A comprehensive family tree management system with profile merging, photo management, facial recognition, and multi-calendar support.
 * Version: 1.0.0
 * Author: Family Tree Connect
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: family-tree-connect
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FTC_VERSION', '1.0.0');
define('FTC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FTC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FTC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'FamilyTreeConnect\\';
    $base_dir = FTC_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Include core files
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-core.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-database.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-person.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-family.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-relationship.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-event.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-media.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-merge.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-notification.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-search.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-calendar.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-chart.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-export.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-facial-recognition.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-places.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-custom-fields.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-ajax.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-shortcodes.php';
require_once FTC_PLUGIN_DIR . 'includes/class-ftc-rest-api.php';

// Admin files
if (is_admin()) {
    require_once FTC_PLUGIN_DIR . 'admin/class-ftc-admin.php';
    require_once FTC_PLUGIN_DIR . 'admin/class-ftc-admin-settings.php';
}

// Public files
require_once FTC_PLUGIN_DIR . 'public/class-ftc-public.php';

/**
 * Initialize the plugin
 */
function ftc_init() {
    // Load text domain
    load_plugin_textdomain('family-tree-connect', false, dirname(FTC_PLUGIN_BASENAME) . '/languages/');
    
    // Initialize core
    FTC_Core::get_instance();
}
add_action('plugins_loaded', 'ftc_init');

/**
 * Activation hook
 */
function ftc_activate() {
    require_once FTC_PLUGIN_DIR . 'includes/class-ftc-activator.php';
    FTC_Activator::activate();
}
register_activation_hook(__FILE__, 'ftc_activate');

/**
 * Deactivation hook
 */
function ftc_deactivate() {
    require_once FTC_PLUGIN_DIR . 'includes/class-ftc-deactivator.php';
    FTC_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'ftc_deactivate');

/**
 * Uninstall hook is in uninstall.php
 */
