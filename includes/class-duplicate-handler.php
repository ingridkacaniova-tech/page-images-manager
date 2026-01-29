<?php
/**
 * Duplicate Handler Class (REFACTORED)
 * Handles detection and merging of duplicate images
 * 
 * ✅ SPLIT: Ghost handling → PIM_Duplicate_Ghost_Handler
 * ✅ SPLIT: Custom sources → PIM_Duplicate_Source_Manager
 * ✅ CORE: Duplicate detection + Link & Generate workflow
 */

if (!defined('ABSPATH')) exit;

class PIM_Duplicate_Handler {
    
    private $ghost_handler;
    private $source_manager;
    
    public function __construct() {
        $this->ghost_handler = new PIM_Duplicate_Ghost_Handler();
        $this->source_manager = new PIM_Duplicate_Source_Manager();
    }
    
    /**
     * ✅ Find duplicates: 1 SQL query + PHP grouping
     */
    public function find_duplicates($valid_images, $missing_images) {
        global $wpdb;
        
        $duplicates = array();
        
        error_log("\n🔍 === DUPLICATE DETECTION START ===");
        
        $query = "
            SELECT p.ID, pm.meta_value AS file_path
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_wp_attached_file'
            ORDER BY pm.meta_value
        ";
        
        $all_attachments = $wpdb->get_results($query);
        
        if (empty($all_attachments)) {
            error_log("⚠️ No attachments found in database");
            return $duplicates;
        }
        
        error_log("📊 Loaded " . count($all_attachments) . " attachments from database");
        
        // Group attachments by base filename
        $grouped_by_basename = array();
        
        foreach ($all_attachments as $row) {
            $attachment_id = intval($row->ID);
            $file_path = $row->file_path;
            
            $basename = $this->get_base_filename($file_path);
            
            if (empty($basename) || $this->is_generic_filename($basename)) {
                continue;
            }
            
            if (!isset($grouped_by_basename[$basename])) {
                $grouped_by_basename[$basename] = array();
            }
            
            $grouped_by_basename[$basename][] = $attachment_id;
        }
        
        error_log("📋 Grouped into " . count($grouped_by_basename) . " unique base filenames");
        
        // Find duplicates
        $total_duplicates = 0;
        
        foreach ($grouped_by_basename as $basename => $attachment_ids) {
            if (count($attachment_ids) < 2) {
                continue;
            }
            
            $primary_id = null;
            $duplicate_ids = array();
            
            foreach ($attachment_ids as $att_id) {
                if (in_array($att_id, $valid_images)) {
                    if ($primary_id === null) {
                        $primary_id = $att_id;
                    } else {
                        $duplicate_ids[] = $att_id;
                    }
                }
            }
            
            if ($primary_id !== null) {
                foreach ($attachment_ids as $att_id) {
                    if ($att_id !== $primary_id && !in_array($att_id, $duplicate_ids)) {
                        $duplicate_ids[] = $att_id;
                    }
                }
                
                if (!empty($duplicate_ids)) {
                    $duplicates[$primary_id] = array();
                    
                    foreach ($duplicate_ids as $dup_id) {
                        $duplicates[$primary_id][] = array(
                            'missing_id' => $dup_id,
                            'missing_url' => wp_get_attachment_url($dup_id),
                            'source' => 'duplicate'
                        );
                    }
                    
                    error_log("✅ DUPLICATE: Attachment #{$primary_id} ({$basename}) has " . count($duplicate_ids) . " duplicate(s): " . implode(', ', $duplicate_ids));
                    $total_duplicates++;
                }
            }
        }
        
        error_log("📊 Result: {$total_duplicates} attachment(s) on current page with duplicates");
        
        // ✅ CHECK FOR MISSING IDs (missing in database) that match existing images
        error_log("\n🔍 === CHECKING MISSING IDs FOR DUPLICATES ===");
        if (!empty($missing_images)) {
            error_log("📋 Processing " . count($missing_images) . " missing images");
            
            foreach ($missing_images as $missing_id => $missing_data) {
                $missing_url = is_array($missing_data) ? ($missing_data['url'] ?? '') : '';
                
                if (empty($missing_url)) {
                    continue;
                }
                
                // Extract filename from URL
                $missing_filename = basename(parse_url($missing_url, PHP_URL_PATH));
                $missing_basename = $this->get_base_filename($missing_filename);
                
                error_log("  🔎 Missing ID #{$missing_id}: basename={$missing_basename}");
                
                // Find matching valid image by basename
                foreach ($valid_images as $valid_id) {
                    $valid_file = get_attached_file($valid_id);
                    if (!$valid_file) continue;
                    
                    $valid_basename = $this->get_base_filename(basename($valid_file));
                    
                    if ($valid_basename === $missing_basename) {
                        // Found match!
                        if (!isset($duplicates[$valid_id])) {
                            $duplicates[$valid_id] = array();
                        }
                        
                        $duplicates[$valid_id][] = array(
                            'missing_id' => $missing_id,
                            'missing_url' => $missing_url,
                            'source' => 'missing_in_database'
                        );
                        
                        error_log("  ✅ FOUND DUPLICATE: Missing ID #{$missing_id} matches existing #{$valid_id}");
                        $total_duplicates++;
                        break;
                    }
                }
            }
        }
        
        error_log("🔍 === END MISSING ID CHECK ===");
        error_log("📊 Final result: {$total_duplicates} total duplicates found\n");
        error_log("🔍 === DUPLICATE DETECTION END ===\n");
        
        return $duplicates;
    }
    
    /**
     * ✅ Delegate to ghost handler
     */
    public function find_ghost_duplicates($primary_id, $duplicate_ids, $page_id) {
        return $this->ghost_handler->find_ghost_duplicates($primary_id, $duplicate_ids, $page_id);
    }
    
    public function get_duplicate_details($duplicate_id, $primary_id = 0, $page_id = 0) {
        return $this->ghost_handler->get_duplicate_details($duplicate_id, $primary_id, $page_id);
    }
    
    public function delete_ghost_duplicates($ghost_ids) {
        return $this->ghost_handler->delete_ghost_duplicates($ghost_ids);
    }
    
    public function get_ghost_files($primary_id, $duplicate_ids, $page_id) {
        return $this->ghost_handler->get_ghost_files($primary_id, $duplicate_ids, $page_id);
    }
    
    /**
     * ✅ Delegate to source manager
     */
    public function get_custom_sources() {
        return $this->source_manager->get_custom_sources();
    }
    
    public function save_custom_source($source_name) {
        return $this->source_manager->save_custom_source($source_name);
    }
    
    /**
     * ✅ Link and generate with ghost files cleanup
     */
    public function link_and_generate($primary_id, $duplicate_ids, $source_mappings, $page_id) {
        $timestamp = date('H:i:s');
        error_log("\n🔗 === LINK & GENERATE START [{$timestamp}] ===");
        
        if (is_string($duplicate_ids)) {
            $duplicate_ids = json_decode($duplicate_ids, true);
        }
        
        if (is_string($source_mappings)) {
            $source_mappings = json_decode($source_mappings, true);
        }
        
        if (!$primary_id || empty($duplicate_ids) || !$page_id) {
            error_log("❌ Validation failed - missing required data");
            return new WP_Error('invalid_data', 'Invalid merge data');
        }
        
        // Get ghost files BEFORE merge
        $ghost_files = $this->get_ghost_files($primary_id, $duplicate_ids, $page_id);
        error_log("👻 Found " . count($ghost_files) . " ghost files to delete");
        
        global $wpdb;
        $elementor_data = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1",
            $page_id
        ));
        
        $data = json_decode($elementor_data, true);
        
        if (!is_array($data)) {
            error_log("❌ JSON decode failed");
            return new WP_Error('invalid_data', 'Invalid Elementor data');
        }
        
        // Replace IDs
        $updated_data = $this->replace_image_ids($data, $duplicate_ids, $primary_id);
        error_log("✅ IDs replaced in Elementor data");
        
        // Generate thumbnails
        $generator = new PIM_Thumbnail_Generator();
        $result = $generator->regenerate_thumbnails($primary_id, $source_mappings, $page_id);
        
        if (is_wp_error($result)) {
            error_log("❌ Thumbnail generation failed: " . $result->get_error_message());
            return $result;
        }
        
        error_log("✅ Thumbnails generated");
        
        // Update URLs
        $updated_data = $this->update_thumbnail_urls($updated_data, $primary_id, $source_mappings);
        error_log("✅ URLs updated");
        
        // Save Elementor data
        update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($updated_data)));
        error_log("✅ Elementor data saved");
        
        // Clear cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            error_log("✅ Elementor cache cleared");
        }
        
        // Delete ghost files
        $deleted_ghost_files = 0;
        if (!empty($ghost_files)) {
            $deleted_ghost_files = $this->ghost_handler->delete_ghost_files($primary_id, $ghost_files);
            error_log("✅ Deleted {$deleted_ghost_files} ghost files");
        }
        
        $unique_sizes = array_unique(array_values($source_mappings));
        
        $message = sprintf(
            '✅ Linked %d duplicate ID(s) to #%d, generated %d thumbnail size(s)!',
            count($duplicate_ids),
            $primary_id,
            count($unique_sizes)
        );
        
        if ($deleted_ghost_files > 0) {
            $message .= sprintf(' Cleaned up %d ghost file(s).', $deleted_ghost_files);
        }
        
        error_log("✅ === LINK & GENERATE COMPLETE ===\n");
        
        return array(
            'merged_count' => count($duplicate_ids),
            'primary_id' => $primary_id,
            'generated_sizes' => $unique_sizes,
            'deleted_ghost_files' => $deleted_ghost_files,
            'message' => $message
        );
    }
    
    /**
     * Helper methods
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
    
    private function is_generic_filename($basename) {
        if (strlen($basename) < 3) return true;
        
        $generic_patterns = array(
            '/^Screenshot/i', '/^Screen.?Shot/i', '/^Untitled/i',
            '/^Image$/i', '/^photo$/i', '/^picture$/i', '/^download$/i', '/^file$/i'
        );
        
        foreach ($generic_patterns as $pattern) {
            if (preg_match($pattern, $basename)) return true;
        }
        
        return false;
    }
    
    private function replace_image_ids($data, $old_ids, $new_id) {
        if (!is_array($data)) return $data;
        
        foreach ($data as $key => &$value) {
            if ($key === 'id' && in_array($value, $old_ids)) {
                $value = $new_id;
            } elseif (is_array($value)) {
                $value = $this->replace_image_ids($value, $old_ids, $new_id);
            }
        }
        
        return $data;
    }
    
    private function update_thumbnail_urls($data, $image_id, $source_mappings) {
        if (!is_array($data)) return $data;
        
        $thumbnail_urls = $this->get_thumbnail_urls($image_id, $source_mappings);
        
        foreach ($data as $key => &$value) {
            if (is_array($value) && isset($value['id']) && intval($value['id']) === $image_id) {
                $source = $this->detect_source_from_context($data, $key);
                if (isset($thumbnail_urls[$source])) {
                    $value['url'] = $thumbnail_urls[$source];
                }
            }
            
            if (in_array($key, array('carousel', 'gallery', 'wp_gallery')) && is_array($value)) {
                foreach ($value as &$item) {
                    if (isset($item['id']) && intval($item['id']) === $image_id) {
                        $source = 'carousel';
                        if (isset($thumbnail_urls[$source])) {
                            $item['url'] = $thumbnail_urls[$source];
                        }
                    }
                }
            }
            
            if (is_array($value)) {
                $value = $this->update_thumbnail_urls($value, $image_id, $source_mappings);
            }
        }
        
        return $data;
    }
    
    private function get_thumbnail_urls($image_id, $source_mappings) {
        $urls = array();
        $metadata = wp_get_attachment_metadata($image_id);
        $base_url = dirname(wp_get_attachment_url($image_id));
        
        foreach ($source_mappings as $source => $size_name) {
            if (isset($metadata['sizes'][$size_name]['file'])) {
                $urls[$source] = $base_url . '/' . $metadata['sizes'][$size_name]['file'];
            } else {
                $urls[$source] = wp_get_attachment_url($image_id);
            }
        }
        
        return $urls;
    }
    
    private function detect_source_from_context($data, $current_key) {
        if (stripos($current_key, 'background') !== false) return 'background';
        if (stripos($current_key, 'hero') !== false) return 'hero';
        if (stripos($current_key, 'carousel') !== false) return 'carousel';
        if (stripos($current_key, 'gallery') !== false) return 'gallery';
        
        if (isset($data['widgetType'])) {
            $widget = $data['widgetType'];
            if (stripos($widget, 'carousel') !== false) return 'carousel';
            if (stripos($widget, 'gallery') !== false) return 'gallery';
        }
        
        return 'image';
    }
    
    public function get_duplicate_info($image_id, $duplicates) {
        if (!isset($duplicates[$image_id])) return null;
        
        $info = array(
            'count' => count($duplicates[$image_id]),
            'sources' => array(),
            'ids' => array()
        );
        
        foreach ($duplicates[$image_id] as $duplicate) {
            $info['sources'][] = $duplicate['source'];
            $info['ids'][] = $duplicate['missing_id'];
        }
        
        $info['sources'] = array_unique($info['sources']);
        
        return $info;
    }
}