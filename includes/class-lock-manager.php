<?php
/**
 * Lock Manager Class
 * Handles locking/unlocking of thumbnail sizes per page
 * 
 * PURPOSE:
 * Prevents deletion of finalized thumbnails when optimizing other pages
 * 
 * METADATA STRUCTURE:
 * _pim_locks = array(
 *     '123_carousel-photo' => true,  // Page 123, size carousel-photo - LOCKED
 *     '456_hero' => true              // Page 456, size hero - LOCKED
 * )
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Lock_Manager {
    
    /**
     * Lock a thumbnail size for specific page
     */
    public function lock_size($image_id, $page_id, $size_name) {
        $locks = $this->get_locks($image_id);
        $lock_key = $this->get_lock_key($page_id, $size_name);
        
        $locks[$lock_key] = true;
        
        update_post_meta($image_id, '_pim_locks', $locks);
        
        error_log("🔒 Locked: Image #{$image_id}, Page #{$page_id}, Size: {$size_name}");
        
        return true;
    }
    
    /**
     * Unlock a thumbnail size for specific page
     */
    public function unlock_size($image_id, $page_id, $size_name) {
        $locks = $this->get_locks($image_id);
        $lock_key = $this->get_lock_key($page_id, $size_name);
        
        unset($locks[$lock_key]);
        
        update_post_meta($image_id, '_pim_locks', $locks);
        
        error_log("🔓 Unlocked: Image #{$image_id}, Page #{$page_id}, Size: {$size_name}");
        
        return true;
    }
    
    /**
     * Check if size is locked for specific page
     */
    public function is_locked($image_id, $page_id, $size_name) {
        $locks = $this->get_locks($image_id);
        $lock_key = $this->get_lock_key($page_id, $size_name);
        
        return isset($locks[$lock_key]);
    }
    
    /**
     * Get all locked sizes (across all pages)
     */
    public function get_all_locked_sizes($image_id) {
        $locks = $this->get_locks($image_id);
        
        $locked_sizes = array();
        foreach ($locks as $lock_key => $locked) {
            // Extract size_name from "123_carousel-photo"
            $parts = explode('_', $lock_key, 2);
            if (count($parts) === 2) {
                $locked_sizes[] = $parts[1];
            }
        }
        
        return array_unique($locked_sizes);
    }
    
    /**
     * Get locked status for current page
     */
    public function get_page_lock_status($image_id, $page_id) {
        $locks = $this->get_locks($image_id);
        
        $locked_sizes = array();
        foreach ($locks as $lock_key => $locked) {
            $parts = explode('_', $lock_key, 2);
            if (count($parts) === 2) {
                $lock_page_id = intval($parts[0]);
                $size_name = $parts[1];
                
                if ($lock_page_id === intval($page_id)) {
                    $locked_sizes[] = $size_name;
                }
            }
        }
        
        return $locked_sizes;
    }
    
    /**
     * Get all locks for an image
     */
    private function get_locks($image_id) {
        $locks = get_post_meta($image_id, '_pim_locks', true);
        return is_array($locks) ? $locks : array();
    }
    
    /**
     * Generate lock key
     */
    private function get_lock_key($page_id, $size_name) {
        return $page_id . '_' . $size_name;
    }
    
    /**
     * Cleanup orphaned locks (for deleted pages)
     * Called by WordPress Cron daily
     */
    public function cleanup_orphaned_locks() {
        global $wpdb;
        
        $cleaned_count = 0;
        
        // Get all attachments with locks
        $results = $wpdb->get_results("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_pim_locks'
        ");
        
        foreach ($results as $row) {
            $image_id = $row->post_id;
            $locks = maybe_unserialize($row->meta_value);
            
            if (!is_array($locks)) {
                continue;
            }
            
            $cleaned_locks = array();
            
            foreach ($locks as $lock_key => $locked) {
                // Extract page_id from "123_carousel-photo"
                $parts = explode('_', $lock_key, 2);
                if (count($parts) !== 2) {
                    continue;
                }
                
                $page_id = intval($parts[0]);
                
                // Check if page still exists
                if (get_post($page_id)) {
                    $cleaned_locks[$lock_key] = $locked; // Keep
                } else {
                    error_log("🧹 Cleaned orphaned lock: Image #{$image_id}, Lock: {$lock_key}");
                    $cleaned_count++;
                }
            }
            
            // Update metadata
            update_post_meta($image_id, '_pim_locks', $cleaned_locks);
        }
        
        error_log("🧹 Orphan cleanup complete: Removed {$cleaned_count} orphaned lock(s)");
        
        return $cleaned_count;
    }
}