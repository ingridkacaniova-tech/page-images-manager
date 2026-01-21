<?php
/**
 * Duplicate Source Manager Class
 * Handles custom source management
 * 
 * ✅ TODO 51: Custom source storage and retrieval
 */

if (!defined('ABSPATH')) exit;

class PIM_Duplicate_Source_Manager {
    
    /**
     * ✅ TODO 51: Get custom sources from options
     */
    public function get_custom_sources() {
        $custom_sources = get_option('_pim_custom_sources', array());
        return is_array($custom_sources) ? $custom_sources : array();
    }
    
    /**
     * ✅ TODO 51: Save custom source
     */
    public function save_custom_source($source_name) {
        $source_name = sanitize_text_field($source_name);
        
        if (empty($source_name)) {
            return false;
        }
        
        $custom_sources = $this->get_custom_sources();
        
        if (!in_array($source_name, $custom_sources)) {
            $custom_sources[] = $source_name;
            update_option('_pim_custom_sources', $custom_sources);
            
            error_log("✅ Saved custom source: {$source_name}");
            return true;
        }
        
        return false;
    }
}