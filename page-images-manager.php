<?php
/**
 * Plugin Name: tripIMgo Page Images Manager
 * Description: Manage and regenerate thumbnails for images used on specific pages
 * Version: 3.0
 * Author: tripIMgo 
 * Text Domain: page-images-manager
 */



if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants  
define('PIM_VERSION', '3.0');
define('PIM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIM_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize plugin
function pim_init() {
    // Skip loading on frontend
    if (!is_admin() && !defined('DOING_CRON') && (!defined('DOING_AJAX') || !DOING_AJAX)) {
        return;
    }
    
    // Load core classes
    require_once PIM_PLUGIN_DIR . 'includes/class-size-helper.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-lock-manager.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-handler.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-image-extractor.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-image-renderer.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-debug-logger.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-debug-log-reader.php';
    require_once PIM_PLUGIN_DIR . 'includes/generators/class-thumbnail-file-manager.php';
    require_once PIM_PLUGIN_DIR . 'includes/generators/class-thumbnail-generator.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-ghost-handler.php';
    require_once PIM_PLUGIN_DIR . 'includes/class-duplicate-source-manager.php';
    
    // Load AJAX handlers for admin (they register hooks)
    require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-images.php';
    require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-uploads.php';
    require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-misc.php';
    require_once PIM_PLUGIN_DIR . 'includes/ajax/class-ajax-handler-debug.php';
    
    // Main plugin class
    require_once PIM_PLUGIN_DIR . 'includes/class-page-images-manager.php';
    
    // Clear OPcache in development mode (so we see file changes immediately)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        static $opcache_cleared = false;
        
        if (!$opcache_cleared) {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $opcache_cleared = true;
        }
    }
    
    // Initialize plugin
    new Page_Images_Manager();
}
add_action('plugins_loaded', 'pim_init');

// ============================================
// ✅ ISSUE 21: WordPress Cron Setup
// ============================================

/**
 * Schedule daily cleanup on plugin activation
 */
if (!function_exists('pim_schedule_cleanup')) {
    register_activation_hook(__FILE__, 'pim_schedule_cleanup');
    
    function pim_schedule_cleanup() {
        if (!wp_next_scheduled('pim_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'pim_daily_cleanup');
            error_log('✅ PIM: Scheduled daily orphan cleanup cron job');
        }
    }
}

/**
 * Run cleanup task
 */
if (!function_exists('pim_run_daily_cleanup')) {
    add_action('pim_daily_cleanup', 'pim_run_daily_cleanup');
    
    function pim_run_daily_cleanup() {
        error_log('🧹 PIM: Running daily orphan cleanup...');
        
        $lock_manager = new PIM_Lock_Manager();
        $cleaned_count = $lock_manager->cleanup_orphaned_locks();
        
        error_log("🧹 PIM: Daily cleanup complete - Removed {$cleaned_count} orphaned lock(s)");
    }
}

/**
 * Unschedule cleanup on plugin deactivation
 */
if (!function_exists('pim_unschedule_cleanup')) {
    register_deactivation_hook(__FILE__, 'pim_unschedule_cleanup');
    
    function pim_unschedule_cleanup() {
        $timestamp = wp_next_scheduled('pim_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pim_daily_cleanup');
            error_log('✅ PIM: Unscheduled daily cleanup cron job');
        }
    }
}
