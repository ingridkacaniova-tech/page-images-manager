<?php
/**
 * Duplicate Ghost Handler Class
 * Handles ghost duplicates and ghost files detection/cleanup
 * 
 * ✅ TODO 50A: Ghost duplicate IDs (unused in Elementor)
 * ✅ TODO 50B: Ghost files (no DB record)
 */

if (!defined('ABSPATH')) exit;

class PIM_Duplicate_Ghost_Handler {
    
    /**
     * ✅ TODO 50A: Find ghost duplicates (not used in Elementor data)
     */
    public function find_ghost_duplicates($primary_id, $duplicate_ids, $page_id) {
        error_log("\n👻 === GHOST DUPLICATE DETECTION START ===");
        error_log("Primary ID: {$primary_id}");
        error_log("Duplicate IDs: " . implode(', ', $duplicate_ids));
        error_log("Page ID: {$page_id}");
        
        // Get Elementor data
        global $wpdb;
        $elementor_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1",
            $page_id
        ));
        
        if (empty($elementor_data)) {
            error_log("⚠️ No Elementor data found");
            return array();
        }
        
        $ghosts = array();
        
        foreach ($duplicate_ids as $dup_id) {
            // Check if this ID exists in Elementor data
            $pattern = '"id":' . $dup_id;
            if (strpos($elementor_data, $pattern) === false) {
                $ghosts[] = $dup_id;
                error_log("👻 GHOST found: #{$dup_id} (not in Elementor)");
            } else {
                error_log("✅ Used: #{$dup_id} (found in Elementor)");
            }
        }
        
        error_log("📊 Found " . count($ghosts) . " ghost duplicate(s)");
        error_log("👻 === GHOST DUPLICATE DETECTION END ===\n");
        
        return $ghosts;
    }
    
    /**
     * ✅ TODO 50B: Get detailed info for duplicate ID
     * ✅ FIX: Ghost IDs inherit page info from primary ID
     */
    public function get_duplicate_details($duplicate_id, $primary_id = 0, $page_id = 0) {
        global $wpdb;
        
        error_log("\n📋 === GET DUPLICATE DETAILS for #{$duplicate_id} ===");
        error_log("Primary ID: {$primary_id}");
        error_log("Page ID: {$page_id}");
        
        // Try to find which page uses this duplicate ID
        $query = $wpdb->prepare("
            SELECT p.ID as page_id, p.post_title as page_name, pm.meta_value as elementor_data
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE pm.meta_key = '_elementor_data'
            AND pm.meta_value LIKE %s
            AND p.post_status = 'publish'
            LIMIT 1
        ", '%"id":' . intval($duplicate_id) . '%');
        
        $result = $wpdb->get_row($query);
        
        // ✅ FIX: If not found (ghost), inherit from primary ID's page
        if (!$result && $page_id > 0) {
            $page = get_post($page_id);
            if ($page) {
                error_log("✅ Ghost ID - inheriting page info from primary ID");
                $result = (object) array(
                    'page_id' => $page_id,
                    'page_name' => $page->post_title,
                    'elementor_data' => ''
                );
            }
        }
        
        if (!$result) {
            error_log("⚠️ No page found for #{$duplicate_id}");
            return array(
                'id' => $duplicate_id,
                'page_id' => 0,
                'page_name' => 'Unknown',
                'reason' => 'not found',
                'existing_source' => ''
            );
        }
        
        // Determine reason
        $post = get_post($duplicate_id);
        $file = get_attached_file($duplicate_id);
        
        $reason = '';
        if (!$post || $post->post_type !== 'attachment') {
            $reason = 'missing in database';
        } elseif (!$file || !file_exists($file)) {
            $reason = 'missing file';
        } else {
            $reason = 'duplicate';
        }
        
        // Try to detect source from Elementor context
        $existing_source = '';
        if (!empty($result->elementor_data)) {
            $existing_source = $this->detect_source_from_elementor($duplicate_id, $result->elementor_data);
        }
        
        $details = array(
            'id' => $duplicate_id,
            'page_id' => $result->page_id,
            'page_name' => $result->page_name,
            'reason' => $reason,
            'existing_source' => $existing_source
        );
        
        error_log("✅ Details: " . json_encode($details));
        error_log("📋 === END GET DUPLICATE DETAILS ===\n");
        
        return $details;
    }
    
    /**
     * ✅ TODO 50B: Detect source from Elementor context
     */
    private function detect_source_from_elementor($image_id, $elementor_data) {
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return '';
        }
        
        $source = $this->search_for_image_context($data, $image_id);
        return $source ?: '';
    }
    
    /**
     * Recursively search for image and detect its context
     */
    private function search_for_image_context($data, $image_id, $parent_widget = '') {
        if (!is_array($data)) {
            return null;
        }
        
        foreach ($data as $key => $value) {
            // Check if this is our image
            if ($key === 'id' && intval($value) === intval($image_id)) {
                // Found it! Return parent context
                if ($parent_widget) {
                    return $parent_widget;
                }
                return 'image';
            }
            
            // Track widget type
            $current_widget = $parent_widget;
            if (isset($value['widgetType'])) {
                $widget_type = $value['widgetType'];
                
                if (stripos($widget_type, 'carousel') !== false) {
                    $current_widget = 'carousel';
                } elseif (stripos($widget_type, 'gallery') !== false) {
                    $current_widget = 'gallery';
                } elseif (stripos($widget_type, 'hero') !== false) {
                    $current_widget = 'hero';
                }
            }
            
            // Check for background images
            if (in_array($key, ['background_image', 'background_overlay_image'])) {
                $current_widget = 'background';
            }
            
            // Recurse
            if (is_array($value)) {
                $result = $this->search_for_image_context($value, $image_id, $current_widget);
                if ($result) {
                    return $result;
                }
            }
        }
        
        return null;
    }
    
    /**
     * ✅ TODO 50A: Delete ghost duplicates
     */
    public function delete_ghost_duplicates($ghost_ids) {
        error_log("\n🗑️ === DELETE GHOST DUPLICATES START ===");
        error_log("Ghost IDs to delete: " . implode(', ', $ghost_ids));
        
        $deleted_count = 0;
        $deleted_files = array();
        
        foreach ($ghost_ids as $ghost_id) {
            $file = get_attached_file($ghost_id);
            
            // Delete from database
            $result = wp_delete_attachment($ghost_id, true);
            
            if ($result) {
                $deleted_count++;
                $deleted_files[] = basename($file);
                error_log("✅ Deleted ghost #{$ghost_id}: " . basename($file));
            } else {
                error_log("❌ Failed to delete ghost #{$ghost_id}");
            }
        }
        
        error_log("📊 Deleted {$deleted_count}/{" . count($ghost_ids) . "} ghost duplicates");
        error_log("🗑️ === DELETE GHOST DUPLICATES END ===\n");
        
        return array(
            'deleted_count' => $deleted_count,
            'deleted_files' => $deleted_files,
            'message' => sprintf('Deleted %d ghost duplicate(s)', $deleted_count)
        );
    }
    
    /**
     * ✅ TODO 50B: Get ghost files (files without DB records on current page)
     */
    public function get_ghost_files($primary_id, $duplicate_ids, $page_id) {
        $file = get_attached_file($primary_id);
        if (!$file) {
            return array();
        }
        
        $dir = dirname($file);
        $basename = $this->get_base_filename(basename($file));
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        // Find all files with this basename
        $pattern = $dir . '/' . $basename . '*.' . $extension;
        $all_files = glob($pattern);
        
        $ghost_files = array();
        $known_ids = array_merge(array($primary_id), $duplicate_ids);
        
        foreach ($all_files as $file_path) {
            $filename = basename($file_path);
            
            // Check if this file belongs to any known attachment
            $belongs_to_known = false;
            foreach ($known_ids as $id) {
                $id_file = get_attached_file($id);
                if ($id_file && basename($id_file) === $filename) {
                    $belongs_to_known = true;
                    break;
                }
            }
            
            if (!$belongs_to_known) {
                $ghost_files[] = $filename;
            }
        }
        
        return $ghost_files;
    }
    
    /**
     * ✅ TODO 50B: Delete ghost files from disk
     */
    public function delete_ghost_files($primary_id, $ghost_files) {
        error_log("\n🗑️ === DELETE GHOST FILES START ===");
        
        $file = get_attached_file($primary_id);
        if (!$file) {
            error_log("❌ Cannot get primary file path");
            return 0;
        }
        
        $dir = dirname($file);
        $deleted_count = 0;
        
        foreach ($ghost_files as $filename) {
            $file_path = $dir . '/' . $filename;
            
            if (file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $deleted_count++;
                    error_log("✅ Deleted ghost file: {$filename}");
                } else {
                    error_log("❌ Failed to delete: {$filename}");
                }
            } else {
                error_log("⚠️ Ghost file not found: {$filename}");
            }
        }
        
        error_log("📊 Deleted {$deleted_count}/{" . count($ghost_files) . "} ghost files");
        error_log("🗑️ === DELETE GHOST FILES END ===\n");
        
        return $deleted_count;
    }
    
    /**
     * Extract base filename (without dimensions, -scaled, etc.)
     */
    private function get_base_filename($path) {
        $filename = basename($path);
        $filename = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $filename);
        $filename = preg_replace('/-scaled/', '', $filename);
        $filename = preg_replace('/-\d+x\d+/', '', $filename);
        $filename = preg_replace('/-\d+$/', '', $filename);
        $filename = preg_replace('/-(rotated|cropped|edited|resized|compressed)/', '', $filename);
        return $filename;
    }
}