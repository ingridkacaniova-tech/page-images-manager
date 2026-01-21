<?php
/**
 * AJAX Handler - Debug Log
 * Handles debug log loading and clearing (AJAX only)
 */

if (!defined('ABSPATH')) exit;

class PIM_Ajax_Handler_Debug {
    
    private $log_reader;
    
    public function __construct() {
        $this->log_reader = new PIM_Debug_Log_Reader();
    }
    
    /**
     * Register AJAX hooks
     */
    public function register_hooks() {
        add_action('wp_ajax_load_debug_log', array($this, 'load_debug_log'));
        add_action('wp_ajax_clear_debug_log', array($this, 'clear_debug_log'));
        add_action('wp_ajax_log_js_stack', array($this, 'log_js_stack'));
    }
    
    /**
     * AJAX handler for loading debug log
     */
    public function load_debug_log() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('load_debug_log');

        check_ajax_referer('pim_load_debug_log', 'nonce');
        
        $data = $this->log_reader->load_log();
        
        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
            return;
        }
        
        wp_send_json_success($data);
    }

    /**
     * ✅ Log JS stack trace (with proper JSON response)
     */
    public function log_js_stack() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('log_js_stack');

        check_ajax_referer('page_images_manager', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $stack = isset($_POST['stack']) ? substr($_POST['stack'], 0, 5000) : '';
        
        // ✅ Use PIM_Debug_Logger
        if (!empty($stack)) {
            PIM_Debug_Logger::log($message, array('data' => $stack));
        } else {
            PIM_Debug_Logger::log($message);
        }
        
        wp_send_json_success(array('logged' => true));
    }
    
    /**
     * AJAX handler for clearing debug log
     */
    public function clear_debug_log() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('clear_debug_log');

        check_ajax_referer('pim_load_debug_log', 'nonce');
        
        $result = $this->log_reader->clear_log();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
            return;
        }
        
        wp_send_json_success($result);
    }
}
