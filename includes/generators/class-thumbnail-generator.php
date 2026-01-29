<?php
/**
 * Thumbnail Generator Class (REFACTORED)
 * Handles thumbnail generation, scaling, and Elementor integration
 * 
 * RESPONSIBILITIES:
 * ✓ Generovanie thumbnailov
 * ✓ Scaling obrázkov
 * ✓ Update Elementor URLs
 */

if (!defined('ABSPATH')) exit;

class PIM_Thumbnail_Generator {
    private $lock_manager;
    private $file_manager;

    public function __construct() {
        $this->lock_manager = new PIM_Lock_Manager();
        $this->file_manager = new PIM_Thumbnail_File_Manager();
    }

    /**
     * Regenerate thumbnails - WITH HIERARCHICAL LOGGING
     * @param bool $skip_scaling Set to true if file is already scaled
     */
    public function regenerate_thumbnails($image_id, $source_mappings, $page_id = 0, $skip_scaling = false) {
        PIM_Debug_Logger::enter('regenerate_thumbnails', array(
            'image_id' => $image_id,
            'page_id' => $page_id,
            'skip_scaling' => $skip_scaling,
            'source_mappings' => $source_mappings
        ));

        if (!$image_id || empty($source_mappings)) {
            PIM_Debug_Logger::error('Invalid parameters');
            PIM_Debug_Logger::exit_function('regenerate_thumbnails');
            return new WP_Error('invalid_data', 'Invalid image ID or source mappings');
        }

        $file = get_attached_file($image_id);
        PIM_Debug_Logger::file_op('Get main file', $file);

        if (!$file || !file_exists($file)) {
            PIM_Debug_Logger::error('File not found');
            PIM_Debug_Logger::exit_function('regenerate_thumbnails');
            return new WP_Error('file_not_found', 'File not found');
        }

        PIM_Debug_Logger::success('Main file validated');

        // ✅ NEW: Get ALL sizes from _pim_page_usage (cross-page protection)
        $page_usage = get_post_meta($image_id, '_pim_page_usage', true);
        $protected_sizes = array();
        
        if (is_array($page_usage) && !empty($page_usage)) {
            PIM_Debug_Logger::log('Found _pim_page_usage data', array(
                'pages_count' => count($page_usage)
            ));
            
            // Extract all unique sizes from ALL pages
            foreach ($page_usage as $pid => $uses) {
                foreach ($uses as $use) {
                    if (isset($use['size_name'])) {
                        $protected_sizes[] = $use['size_name'];
                    }
                }
            }
            
            $protected_sizes = array_unique($protected_sizes);
            
            PIM_Debug_Logger::log('Protected sizes (from all pages)', array(
                'count' => count($protected_sizes),
                'sizes' => $protected_sizes
            ));
        } else {
            PIM_Debug_Logger::warning('No _pim_page_usage found - no cross-page protection!');
        }

        // ✅ LEGACY: Also get locked sizes (backwards compatibility)
        $locked_sizes = $this->lock_manager->get_all_locked_sizes($image_id);
        PIM_Debug_Logger::log('Locked sizes (legacy system)', $locked_sizes);

        // ✅ UNION: protected + locked
        $all_protected = array_unique(array_merge($protected_sizes, $locked_sizes));
        PIM_Debug_Logger::log('Final protected sizes (union)', array(
            'count' => count($all_protected),
            'sizes' => $all_protected
        ));

        // Delete unlocked thumbnails
        PIM_Debug_Logger::log('Deleting unlocked thumbnails...');
        $this->file_manager->delete_unlocked_thumbnails($image_id, $file, $all_protected);

        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Handle scaling
        if (!$skip_scaling) {
            PIM_Debug_Logger::log('Checking if scaling needed (threshold: 2560px)...');
            $scaled_file = $this->ensure_scaled_version($file, $image_id);
            $source_file = $scaled_file ? $scaled_file : $file;
            PIM_Debug_Logger::log('Source file for thumbnails', array(
                'file' => basename($source_file),
                'was_scaled' => (bool)$scaled_file
            ));
        } else {
            $source_file = $file;
            PIM_Debug_Logger::log('Skipping scaling (file already scaled)');
        }

        $metadata = wp_get_attachment_metadata($image_id);
        if (!$metadata) {
            PIM_Debug_Logger::warning('No metadata found, creating new array');
            $metadata = array('sizes' => array());
        }

        // ✅ Extract unique sizes from source_mappings (user selection)
        $sizes_to_generate = array_unique(array_values($source_mappings));
        $sizes_to_generate = array_filter($sizes_to_generate, function($size) {
            return $size !== 'non-standard';
        });

        // ✅ UNION: user selection + protected sizes
        $sizes_to_generate = array_unique(array_merge($sizes_to_generate, $all_protected));

        PIM_Debug_Logger::log('Final sizes to generate (UNION)', array(
            'count' => count($sizes_to_generate),
            'sizes' => array_values($sizes_to_generate)
        ));

        // Generate each size
        foreach ($sizes_to_generate as $size_name) {
            PIM_Debug_Logger::log("Generating: {$size_name}");
            $result = $this->generate_single_size($source_file, $metadata, $size_name);
            if ($result) {
                PIM_Debug_Logger::success("Generated: {$size_name}");
            } else {
                PIM_Debug_Logger::warning("Failed to generate: {$size_name}");
            }
        }

        // Save metadata
        PIM_Debug_Logger::log('Saving metadata...');
        wp_update_attachment_metadata($image_id, $metadata);

        // Force reload
        wp_cache_delete($image_id, 'post_meta');
        $fresh_metadata = wp_get_attachment_metadata($image_id);
        PIM_Debug_Logger::success('Metadata saved and reloaded');

        // Verify on disk
        $this->file_manager->verify_thumbnails_on_disk($image_id, $fresh_metadata, $sizes_to_generate);

        // Update Elementor if needed
        if ($page_id > 0) {
            PIM_Debug_Logger::log("Updating Elementor URLs for page #{$page_id}");
            $this->update_elementor_urls($image_id, $source_mappings, $page_id);
        }

        PIM_Debug_Logger::exit_function('regenerate_thumbnails', array(
            'success' => true,
            'generated_count' => count($sizes_to_generate)
        ));

        return true;
    }

    /**
     * Generate single thumbnail size
     */
    public function generate_single_size($file, &$metadata, $size_name) {
        $sizes = wp_get_registered_image_subsizes();
        
        if (!isset($sizes[$size_name])) {
            error_log("⚠️ Size not registered: {$size_name}");
            return false;
        }

        $size_data = $sizes[$size_name];
        $editor = wp_get_image_editor($file);
        
        if (is_wp_error($editor)) {
            error_log("❌ Image editor error for {$file}");
            return false;
        }

        $width = $size_data['width'] ?? 0;
        $height = $size_data['height'] ?? 0;
        $crop = $size_data['crop'] ?? false;

        if ($width > 0 || $height > 0) {
            $editor->resize($width, $height, $crop);
        }

        $path_info = pathinfo($file);
        $basename = $path_info['filename'];
        $ext = $path_info['extension'];
        $dir = $path_info['dirname'];

        $new_filename = $basename . '-' . $width . 'x' . $height . '.' . $ext;
        $new_file_path = $dir . '/' . $new_filename;

        $resized = $editor->save($new_file_path);

        if (is_wp_error($resized)) {
            error_log("❌ Save failed: " . $resized->get_error_message());
            return false;
        }

        if (!isset($metadata['sizes'])) {
            $metadata['sizes'] = array();
        }

        $metadata['sizes'][$size_name] = array(
            'file' => wp_basename($resized['path']),
            'width' => $resized['width'],
            'height' => $resized['height'],
            'mime-type' => $resized['mime-type']
        );

        return true;
    }

    /**
     * Ensure -scaled version exists for large images
     */
    public function ensure_scaled_version($file, $image_id) {
        $image_editor = wp_get_image_editor($file);
        if (is_wp_error($image_editor)) {
            return null;
        }

        $size = $image_editor->get_size();
        $threshold = apply_filters('big_image_size_threshold', 2560);

        // Check if image is larger than threshold
        if ($size['width'] <= $threshold && $size['height'] <= $threshold) {
            return null;
        }

        // Check if -scaled version already exists
        $path_info = pathinfo($file);
        $scaled_filename = $path_info['filename'] . '-scaled.' . $path_info['extension'];
        $scaled_file_path = $path_info['dirname'] . '/' . $scaled_filename;

        if (file_exists($scaled_file_path)) {
            // Delete orphaned original if exists
            if (file_exists($file) && $file !== $scaled_file_path) {
                @unlink($file);
                error_log("🗑️ Deleted orphaned original: " . basename($file));
            }
            return $scaled_file_path;
        }

        // Create NEW -scaled version
        $scaled_file = $this->apply_scaling_and_cleanup($file, $image_id);
        
        if ($scaled_file && $scaled_file !== $file) {
            $metadata = wp_get_attachment_metadata($image_id);
            $metadata['original_image'] = basename($file);
            wp_update_attachment_metadata($image_id, $metadata);
            update_attached_file($image_id, $scaled_file);
        }

        return $scaled_file;
    }

    /**
     * Apply scaling using WordPress native functions
     * ✅ SAVE original dimensions BEFORE scaling
     */
    public function apply_scaling_and_cleanup($file_path, $attachment_id) {
        error_log("🔧 apply_scaling_and_cleanup START");

        if (!$attachment_id || !file_exists($file_path)) {
            return $file_path;
        }

        // ✅ GET ORIGINAL DIMENSIONS BEFORE SCALING
        $input_size = @getimagesize($file_path);
        if ($input_size) {
            $original_width = $input_size[0];
            $original_height = $input_size[1];
            error_log("📐 Original dimensions: {$original_width}×{$original_height}");
        } else {
            $original_width = 0;
            $original_height = 0;
        }

        // Filter to keep only thumbnail during scaling
        add_filter('intermediate_image_sizes_advanced', function($sizes) {
            return isset($sizes['thumbnail']) ? array('thumbnail' => $sizes['thumbnail']) : array();
        }, 999);

        add_filter('big_image_size_threshold', function() {
            return 2560;
        }, 9999);

        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
        
        // ✅ SAVE ORIGINAL DIMENSIONS to metadata
        if ($original_width > 0 && $original_height > 0) {
            $metadata['original_width'] = $original_width;
            $metadata['original_height'] = $original_height;
            error_log("💾 Saved original dimensions to meta {$original_width}×{$original_height}");
        }
        
        wp_update_attachment_metadata($attachment_id, $metadata);

        $final_file = get_attached_file($attachment_id);

        // Delete original if it still exists
        if (isset($metadata['original_image']) && file_exists($file_path) && $final_file !== $file_path) {
            @unlink($file_path);
            error_log("✅ Deleted original after scaling");
        }

        // Update GUID
        if ($final_file !== $file_path && strpos(basename($final_file), '-scaled') !== false) {
            $upload_dir = wp_upload_dir();
            $new_guid = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $final_file);
            
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                array('guid' => $new_guid),
                array('ID' => $attachment_id),
                array('%s'),
                array('%d')
            );
        }

        remove_all_filters('intermediate_image_sizes_advanced', 999);
        remove_all_filters('big_image_size_threshold', 9999);

        error_log("✅ apply_scaling_and_cleanup END");
        return $final_file;
    }

    /**
     * Update Elementor URLs for this image
     */
    private function update_elementor_urls($image_id, $source_mappings, $page_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return;
        }

        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return;
        }

        $thumbnail_urls = $this->get_thumbnail_urls($image_id, $source_mappings);
        $updated_data = $this->replace_urls_in_elementor($data, $image_id, $thumbnail_urls);

        update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($updated_data)));

        if (class_exists('\\Elementor\\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    /**
     * Get thumbnail URLs based on source mappings
     */
    private function get_thumbnail_urls($image_id, $source_mappings) {
        $urls = array();
        $metadata = wp_get_attachment_metadata($image_id);
        
        // ✅ FIX: Use get_attached_file() to preserve -scaled suffix
        $file_path = get_attached_file($image_id);
        if (!$file_path) {
            error_log("❌ get_thumbnail_urls: No file path for image #{$image_id}");
            return $urls;
        }
        
        $upload_dir = wp_upload_dir();
        $base_dir = dirname($file_path);
        $base_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $base_dir);

        foreach ($source_mappings as $source => $size_name) {
            if (isset($metadata['sizes'][$size_name]['file'])) {
                $urls[$source] = $base_url . '/' . $metadata['sizes'][$size_name]['file'];
            } else {
                // ✅ Fallback: use main file (preserves -scaled)
                $urls[$source] = $base_url . '/' . basename($file_path);
            }
        }

        return $urls;
    }

    /**
     * Detect source type from key
     */
    private function detect_source_from_key($key) {
        $key_lower = strtolower($key);
        
        if (stripos($key_lower, 'carousel') !== false) return 'carousel';
        if (stripos($key_lower, 'gallery') !== false) return 'gallery';
        if (stripos($key_lower, 'hero') !== false) return 'hero';
        if (stripos($key_lower, 'background') !== false) return 'background';
        
        return 'image';
    }

    /**
     * Create attachment from URL (legacy support)
     */
    public function create_attachment_from_url($image_url, $page_id) {
        // This method stays here for backwards compatibility
        // but could be moved to file manager if needed
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $file_array = array(
            'name' => basename($image_url),
            'tmp_name' => $tmp
        );

        $id = media_handle_sideload($file_array, $page_id);
        
        if (is_wp_error($id)) {
            @unlink($file_array['tmp_name']);
            return $id;
        }

        return array(
            'attachment_id' => $id,
            'url' => wp_get_attachment_url($id)
        );
    }
}