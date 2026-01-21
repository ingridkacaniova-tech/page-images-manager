<?php
/**
 * Plugin Name: tripIMgo Page Images Manager
 * Description: Manage and regenerate thumbnails for images used on specific pages
 * Version: 2.5
 * Author: tripIMgo 
 * Text Domain: page-images-manager
 */

// ✅ DEVELOPMENT MODE - Force reload PHP files (disable OPcache)
if (defined('WP_DEBUG') && WP_DEBUG) {
    // Clear entire PHP OPcache
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    
    // Alternative: Invalidate only this plugin's files
    $plugin_dir = plugin_dir_path(__FILE__);
    if (function_exists('opcache_invalidate')) {
        foreach (glob($plugin_dir . 'includes/**/*.php') as $file) {
            opcache_invalidate($file, true);
        }
        foreach (glob($plugin_dir . 'includes/*.php') as $file) {
            opcache_invalidate($file, true);
        }
    }
    
    error_log('🔥 PIM: OPcache cleared (Development Mode)');
}

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PIM_VERSION', '2.5');
define('PIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIM_PLUGIN_URL', plugin_dir_url(__FILE__));

// ============================================
// ✅ FINAL REFACTORED: Load class files in correct order
// ============================================

// Core classes (no dependencies)
require_once PIM_PLUGIN_DIR . 'includes/class-size-helper.php';
require_once PIM_PLUGIN_DIR . 'includes/class-lock-manager.php';
require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-handler.php';
require_once PIM_PLUGIN_DIR . 'includes/class-image-extractor.php';
require_once PIM_PLUGIN_DIR . 'includes/class-image-renderer.php';
require_once PIM_PLUGIN_DIR . 'includes/class-debug-logger.php';

// ✅ Debug log reader (helper class)
require_once PIM_PLUGIN_DIR . 'includes/class-debug-log-reader.php';

// ✅ Generator classes (File Manager must be loaded before Generator)
require_once PIM_PLUGIN_DIR . 'includes/generators/class-thumbnail-file-manager.php';
require_once PIM_PLUGIN_DIR . 'includes/generators/class-thumbnail-generator.php';

// ✅ Generator classes (File Manager must be loaded before Generator)
require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-ghost-handler.php';
require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-source-manager.php';

// ✅ AJAX handler classes (depend on generators)
require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-images-v2.php';
require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-uploads.php';
require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-misc-v2.php';
require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-debug.php';

// Main plugin class
require_once PIM_PLUGIN_DIR . 'includes/class-page-images-manager.php';

// Initialize plugin
function pim_init() {
    new Page_Images_Manager();
}
add_action('plugins_loaded', 'pim_init');

// ============================================
// ✅ ISSUE 21: WordPress Cron Setup
// ============================================

/**
 * Schedule daily cleanup on plugin activation
 */
register_activation_hook(__FILE__, 'pim_schedule_cleanup');

function pim_schedule_cleanup() {
    if (!wp_next_scheduled('pim_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'pim_daily_cleanup');
        error_log('✅ PIM: Scheduled daily orphan cleanup cron job');
    }
}

/**
 * Run cleanup task
 */
add_action('pim_daily_cleanup', 'pim_run_daily_cleanup');

function pim_run_daily_cleanup() {
    error_log('🧹 PIM: Running daily orphan cleanup...');
    
    $lock_manager = new PIM_Lock_Manager();
    $cleaned_count = $lock_manager->cleanup_orphaned_locks();
    
    error_log("🧹 PIM: Daily cleanup complete - Removed {$cleaned_count} orphaned lock(s)");
}

/**
 * Unschedule cleanup on plugin deactivation
 */
register_deactivation_hook(__FILE__, 'pim_unschedule_cleanup');

function pim_unschedule_cleanup() {
    $timestamp = wp_next_scheduled('pim_daily_cleanup');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'pim_daily_cleanup');
        error_log('✅ PIM: Unscheduled daily cleanup cron job');
    }
}
