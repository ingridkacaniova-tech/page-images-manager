<?php
/**
 * Search Helper Class
 * 
 * Handles all search operations in Elementor data
 * Centralized recursive search logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Search_Helper {
    
    /**
     * ✅ Check if exact disk_filename exists in Elementor data
     * Used for orphan file detection
     */
    public function is_filename_used_in_elementor($disk_filename, $page_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return false;
        }
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return false;
        }
        
        return $this->search_filename_in_data($data, $disk_filename);
    }
    
    /**
     * ✅ Search for exact filename in nested Elementor data
     */
    private function search_filename_in_data($data, $disk_filename) {
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => $value) {
            // Check URL fields
            if ($key === 'url' && is_string($value)) {
                if (strpos($value, $disk_filename) !== false) {
                    return true;
                }
            }
            
            // Recurse
            if (is_array($value)) {
                if ($this->search_filename_in_data($value, $disk_filename)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * ✅ Search for image_id in Elementor data
     * Returns all occurrences with their context
     */
    public function find_image_in_elementor($image_id, $page_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return array();
        }
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return array();
        }
        
        $found = array();
        $this->search_image_id_recursive($data, $image_id, $found);
        return $found;
    }
    
    /**
     * ✅ Recursive search for image_id
     */
    private function search_image_id_recursive($data, $image_id, &$found) {
        if (!is_array($data)) {
            return;
        }
        
        foreach ($data as $key => $value) {
            // Found image_id
            if ($key === 'id' && intval($value) === $image_id) {
                $found[] = array(
                    'id' => $image_id,
                    'context' => $data
                );
            }
            
            // Recurse
            if (is_array($value)) {
                $this->search_image_id_recursive($value, $image_id, $found);
            }
        }
    }

        /**
     * Replace URLs in Elementor data
     */
    private function replace_urls_in_elementor($data, $image_id, $thumbnail_urls) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value) && isset($value['id']) && intval($value['id']) === $image_id) {
                $source = $this->detect_source_from_key($key);
                if (isset($thumbnail_urls[$source])) {
                    $value['url'] = $thumbnail_urls[$source];
                }
            }

            if (in_array($key, array('carousel', 'gallery', 'wp_gallery')) && is_array($value)) {
                foreach ($value as &$item) {
                    if (isset($item['id']) && intval($item['id']) === $image_id) {
                        $source = isset($thumbnail_urls['carousel']) ? 'carousel' : 'gallery';
                        if (isset($thumbnail_urls[$source])) {
                            $item['url'] = $thumbnail_urls[$source];
                        }
                    }
                }
            }

            if (is_array($value)) {
                $value = $this->replace_urls_in_elementor($value, $image_id, $thumbnail_urls);
            }
        }

        return $data;
    }
}
