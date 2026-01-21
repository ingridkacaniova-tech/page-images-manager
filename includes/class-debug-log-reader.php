<?php
/**
 * Debug Log Reader Class
 * Handles debug log file operations and rendering
 * 
 * ✅ Renamed from debug-log-viewer.php for naming convention
 * ✅ Contains ALL helper logic (reading, stats, rendering)
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Debug_Log_Reader {
    
    /**
     * Load debug log data
     */
    public function load_log() {
        $log_path = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_path)) {
            return new WP_Error('not_found', 'Debug log file not found. Make sure WP_DEBUG_LOG is enabled in wp-config.php');
        }
        
        // Read last 1000 lines
        $lines = $this->read_last_lines($log_path, 1000);
        
        if (empty($lines)) {
            return new WP_Error('empty', 'Debug log is empty');
        }
        
        // Calculate stats
        $stats = $this->calculate_log_stats($lines);
        
        // Get total line count
        $total_lines = count(file($log_path));
        
        // Render HTML using template
        $html = $this->render_debug_log_html($lines, $stats, $total_lines);
        
        return array(
            'html' => $html,
            'stats' => $stats
        );
    }
    
    /**
     * Clear debug log file
     */
    public function clear_log() {
        $log_path = WP_CONTENT_DIR . '/debug.log';
        
        if (!file_exists($log_path)) {
            return new WP_Error('not_found', 'Debug log file not found');
        }
        
        // Clear log file
        file_put_contents($log_path, '');
        
        return array(
            'message' => 'Debug log cleared successfully'
        );
    }
    
    /**
     * Read last N lines from a file efficiently
     */
    private function read_last_lines($file, $lines = 1000) {
        $handle = @fopen($file, 'r');
        if (!$handle) {
            return array();
        }
        
        $line_buffer = array();
        $buffer_size = 4096;
        
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);
        
        $buffer = '';
        
        while ($pos > 0 && count($line_buffer) < $lines) {
            $read_size = min($buffer_size, $pos);
            $pos -= $read_size;
            fseek($handle, $pos);
            
            $chunk = fread($handle, $read_size);
            $buffer = $chunk . $buffer;
            
            $split = explode("\n", $buffer);
            
            if (count($split) > 1) {
                $buffer = array_shift($split);
                $line_buffer = array_merge($split, $line_buffer);
            }
        }
        
        if (!empty($buffer)) {
            array_unshift($line_buffer, $buffer);
        }
        
        fclose($handle);
        
        return array_slice($line_buffer, -$lines);
    }
    
    /**
     * Render HTML using template
     */
    private function render_debug_log_html($lines, $stats, $total_lines) {
        // Pass data to template
        $data = array(
            'lines' => $lines,
            'stats' => $stats,
            'total_lines' => $total_lines
        );
        
        ob_start();
        include PIM_PLUGIN_DIR . 'templates/debug/debug-log-viewer.php';
        return ob_get_clean();
    }
    
    /**
     * Calculate statistics from debug log
     */
    private function calculate_log_stats($lines) {
        $stats = array(
            'total' => count($lines),
            'preselecting' => 0,
            'semantic' => 0,
            'success' => 0,
            'errors' => 0,
            'warnings' => 0,
            'images' => 0
        );
        
        $image_ids = array();
        
        foreach ($lines as $line) {
            if (stripos($line, 'PRESELECTING') !== false) {
                $stats['preselecting']++;
            }
            if (stripos($line, 'Semantic match') !== false) {
                $stats['semantic']++;
            }
            if (strpos($line, '✅') !== false) {
                $stats['success']++;
            }
            if (strpos($line, '❌') !== false) {
                $stats['errors']++;
            }
            if (strpos($line, '⚠️') !== false) {
                $stats['warnings']++;
            }
            
            // Extract unique image IDs
            if (preg_match('/Image ID: (\d+)/', $line, $match)) {
                $image_ids[$match[1]] = true;
            }
        }
        
        $stats['images'] = count($image_ids);
        
        return $stats;
    }
}