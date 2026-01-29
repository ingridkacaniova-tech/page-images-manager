<?php
/**
 * Size Helper Class
 * Handles thumbnail size configuration and matching logic
 * 
 * COMPLETE FIX for Issue 8:
 * - Fixed semantic matching (background → hero, image → teaser-photo, etc.)
 * - Fixed filename detection for scaled images (IMG_2510-scaled-250x0.jpeg)
 * - Prioritizes semantic matches over substring matches
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Size_Helper {
    
    /**
     * Get custom thumbnail sizes configuration
     */
    public function get_custom_sizes() {
        $all_sizes = wp_get_registered_image_subsizes();
        return array_filter(array(
            'hero' => $all_sizes['hero'] ?? null,
            'carousel-photo' => $all_sizes['carousel-photo'] ?? null,
            'standard-page-photo' => $all_sizes['standard-page-photo'] ?? null,
            'teaser-photo' => $all_sizes['teaser-photo'] ?? null,
            'page-background' => $all_sizes['page-background'] ?? null,
            'small-carousel-cards' => $all_sizes['small-carousel-cards'] ?? null
        ));
    }
    
    /**
     * ✅ FIXED: Detect thumbnails for SCALED images correctly
     */
    public function detect_existing_thumbnails($image_id, $image_meta) {
        $existing = array();
        
        // ✅ EXTRA LOGGING FOR IMG_2510 (ID: 10152)
        $is_img_2510 = ($image_id == 10152);
        
        if ($is_img_2510) {
            error_log("\n🎯 === SPECIAL DEBUG FOR IMG_2510 (ID: 10152) ===");
        }
        
        // ✅ WHO CALLED ME?
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = isset($backtrace[1]) ? $backtrace[1]['function'] : 'unknown';
        $caller_class = isset($backtrace[1]) ? $backtrace[1]['class'] : 'unknown';
        $caller_file = isset($backtrace[0]) ? basename($backtrace[0]['file']) : 'unknown';
        $caller_line = isset($backtrace[0]) ? $backtrace[0]['line'] : 'unknown';
        
        error_log("\n🔍 === DETECTING THUMBNAILS FOR IMAGE #{$image_id} ===");
        error_log("📞 CALLED FROM: {$caller_class}::{$caller}() in {$caller_file}:{$caller_line}");
        
        if ($is_img_2510) {
            error_log("📞 CALL STACK:");
            foreach ($backtrace as $i => $trace) {
                $func = isset($trace['function']) ? $trace['function'] : 'unknown';
                $class = isset($trace['class']) ? $trace['class'] . '::' : '';
                $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
                $line = isset($trace['line']) ? $trace['line'] : '?';
                error_log("   #{$i} {$class}{$func}() in {$file}:{$line}");
            }
        }
        
        $file_path = get_attached_file($image_id);
        if (!$file_path) {
            error_log("❌ detect_existing_thumbnails: No file path for #{$image_id}");
            return $existing;
        }
        
        if (!file_exists($file_path)) {
            error_log("❌ detect_existing_thumbnails: File doesn't exist: " . $file_path);
            return $existing;
        }
        
        $dir = dirname($file_path);
        $custom_sizes = $this->get_custom_sizes();
        
        error_log("📁 Directory: " . $dir);
        error_log("📄 Main file: " . basename($file_path));
        
        // ✅ IMG_2510: Show metadata BEFORE processing
        if ($is_img_2510) {
            error_log("📋 RAW METADATA FOR IMG_2510:");
            error_log(print_r($image_meta, true));
        }
        
        // Method 1: Check metadata (fast but can be outdated)
        if (isset($image_meta['sizes']) && is_array($image_meta['sizes'])) {
            error_log("📋 Metadata has " . count($image_meta['sizes']) . " size(s) registered");
            
            if ($is_img_2510) {
                error_log("📋 IMG_2510 METADATA SIZES:");
                foreach ($image_meta['sizes'] as $size => $data) {
                    error_log("  - {$size}: {$data['file']} ({$data['width']}x{$data['height']})");
                }
            }
            
            foreach ($custom_sizes as $size_name => $size_data) {
                if (isset($image_meta['sizes'][$size_name]['file'])) {
                    $thumbnail_file = $image_meta['sizes'][$size_name]['file'];
                    $thumbnail_path = $dir . '/' . $thumbnail_file;
                    
                    // Verify file actually exists on disk
                    if (file_exists($thumbnail_path)) {
                        $existing[] = $size_name;
                        error_log("✅ [METADATA] Found: {$size_name} → " . $thumbnail_file);
                    } else {
                        error_log("⚠️ [METADATA] Missing on disk: {$size_name} → " . $thumbnail_file);
                    }
                }
            }
        } else {
            error_log("⚠️ No sizes array in metadata");
        }
        
        // Method 2: Scan filesystem for missing sizes
        // ✅ FIX: Use actual filename as base (don't strip -scaled)
        $path_info = pathinfo($file_path);
        $base_name = $path_info['filename']; // Keep -scaled if present!
        $extension = $path_info['extension'];
        
        error_log("🔍 Scanning filesystem for: {$base_name}-*x*." . $extension);
        
        // ✅ IMG_2510: Scan ALL files on disk
        if ($is_img_2510) {
            $pattern = $dir . '/IMG_2510-scaled-*';
            $all_files = glob($pattern);
            error_log("📁 ALL IMG_2510-scaled-* FILES ON DISK:");
            foreach ($all_files as $f) {
                $exists = file_exists($f) ? '✅' : '❌';
                error_log("  {$exists} " . basename($f));
            }
        }
        
        foreach ($custom_sizes as $size_name => $size_data) {
            // Skip if already found in metadata
            if (in_array($size_name, $existing)) {
                continue;
            }
            
            $width = $size_data['width'] ?? 0;
            $height = $size_data['height'] ?? 0;
            
            // Build expected filename using ACTUAL base name
            $expected_filename = $base_name . '-' . $width . 'x' . $height . '.' . $extension;
            $expected_path = $dir . '/' . $expected_filename;
            
            if ($is_img_2510) {
                error_log("   🔎 Checking {$size_name} ({$width}x{$height}): " . $expected_filename);
            } else {
                error_log("   🔎 Checking: " . $expected_filename);
            }
            
            // Check if file exists
            if (file_exists($expected_path)) {
                $existing[] = $size_name;
                error_log("✅ [FILESYSTEM] Found: {$size_name} → " . $expected_filename);
            } else {
                if ($is_img_2510) {
                    error_log("❌ [FILESYSTEM] NOT FOUND: {$expected_filename}");
                }
            }
        }
        
        error_log("📊 Total existing thumbnails: " . count($existing) . " → " . implode(', ', $existing));
        
        if ($is_img_2510) {
            error_log("🎯 === END IMG_2510 DEBUG ===\n");
        } else {
            error_log("🔍 === END DETECTION ===\n");
        }
        
        return $existing;
    }
    
    /**
     * ✅ FIXED: Prioritize semantic matches
     */
    public function find_matching_size($existing_sizes, $source) {
        if (empty($existing_sizes)) {
            error_log("⚠️ find_matching_size: No existing sizes for source '{$source}'");
            return null;
        }
        
        error_log("🔍 Trying to match source '{$source}' against: " . implode(', ', $existing_sizes));
        
        // ✅ STEP 1: Try semantic match FIRST (highest priority)
        $semantic_match = $this->get_semantic_match($source);
        if ($semantic_match) {
            foreach ($semantic_match as $preferred_size) {
                if (in_array($preferred_size, $existing_sizes)) {
                    error_log("✅ Semantic match: {$preferred_size} ← {$source}");
                    return $preferred_size;
                }
            }
        }
        
        // ✅ STEP 2: Try direct substring match (fallback)
        foreach ($existing_sizes as $size_name) {
            if ($this->size_matches_source_substring($size_name, $source)) {
                error_log("✅ Substring match: {$size_name} ← {$source}");
                return $size_name;
            }
        }
        
        error_log("❌ No match found for source: {$source}");
        return null;
    }
    
    /**
     * ✅ NEW: Semantic mapping rules (source → preferred thumbnail sizes)
     */
    private function get_semantic_match($source) {
        $source_lower = strtolower($source);
        
        $mappings = array(
            'hero' => array('hero'),
            'background' => array('hero', 'page-background'),
            'carousel' => array('carousel-photo'),
            'gallery' => array('carousel-photo'),
            'image' => array('standard-page-photo', 'teaser-photo'),
            'content' => array('standard-page-photo'),
            'avatar' => array('teaser-photo'),
            'icon' => array('teaser-photo'),
            'logo' => array('teaser-photo'),
            'video-poster' => array('standard-page-photo'),
            'other' => array('standard-page-photo'),
            'unknown' => array('standard-page-photo', 'teaser-photo')
        );
        
        return $mappings[$source_lower] ?? null;
    }
    
    /**
     * ✅ RENAMED: Substring matching (less reliable, used as fallback)
     */
    private function size_matches_source_substring($size_name, $source) {
        $size_lower = strtolower($size_name);
        $source_lower = strtolower($source);
        
        // Direct substring match
        if (strpos($size_lower, $source_lower) !== false) {
            return true;
        }
        
        // Reverse substring match
        if (strpos($source_lower, $size_lower) !== false) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if a size name matches a source type (for backwards compatibility)
     */
    public function size_matches_source($size_name, $source) {
        // Try semantic match first
        $semantic = $this->get_semantic_match($source);
        if ($semantic && in_array($size_name, $semantic)) {
            return true;
        }
        
        // Fallback to substring match
        return $this->size_matches_source_substring($size_name, $source);
    }
    
    /**
     * Get preselected size for a source on INITIAL load
     */
    public function get_preselected_size($image_id, $source, $image_meta = null) {
        error_log("\n🎯 === GET_PRESELECTED_SIZE ===");
        error_log("🆔 Image ID: {$image_id}");
        error_log("📍 Source: {$source}");
        
        // Get metadata if not provided
        if ($image_meta === null) {
            $image_meta = wp_get_attachment_metadata($image_id);
            error_log("📥 Fetched metadata from database");
        }
        
        // No metadata = no preselection possible
        if (!$image_meta) {
            error_log("❌ No metadata available");
            error_log("🎯 === RESULT: null ===\n");
            return null;
        }
        
        // Log what we have in metadata
        if (isset($image_meta['sizes'])) {
            error_log("📋 Metadata has sizes: " . implode(', ', array_keys($image_meta['sizes'])));
        } else {
            error_log("⚠️ Metadata has no 'sizes' array");
        }
        
        // Detect existing thumbnails (uses improved detection)
        $existing_thumbnails = $this->detect_existing_thumbnails($image_id, $image_meta);
        
        if (empty($existing_thumbnails)) {
            error_log("❌ No existing thumbnails found - selecting Non-Standard");
            error_log("🎯 === RESULT: non-standard ===\n");
            return 'non-standard';
        }
        
        // Find matching size for this source
        $matched_size = $this->find_matching_size($existing_thumbnails, $source);
        
        if ($matched_size) {
            error_log("✅ PRESELECTING: {$matched_size} for source {$source}");
            error_log("🎯 === RESULT: {$matched_size} ===\n");
            return $matched_size;
        }
        
        error_log("❌ No matching size found for source");
        error_log("🎯 === RESULT: null ===\n");
        return null;
    }
    
    /**
     * Get size dimensions as formatted string
     */
    public function get_size_dimensions($size_name) {
        $custom_sizes = $this->get_custom_sizes();
        
        if (isset($custom_sizes[$size_name])) {
            $width = $custom_sizes[$size_name]['width'] ?? 0;
            $height = $custom_sizes[$size_name]['height'] ?? 0;
            return $width . '×' . $height;
        }
        
        return 'unknown';
    }
    
    /**
     * Validate if size name exists in our custom sizes
     */
    public function is_valid_size($size_name) {
        $custom_sizes = $this->get_custom_sizes();
        return isset($custom_sizes[$size_name]);
    }
    
    public function match_size_from_url($url) {
        if (empty($url)) {
            return '';
        }
        
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        if (preg_match('/-(\d+)x(\d+)\.(jpg|jpeg|png|gif|webp)$/i', $filename, $matches)) {
            $width = intval($matches[1]);
            $height = intval($matches[2]);
            
            $hardcoded_sizes = array(
                '1920x1080' => 'hero',
                '250x0' => 'carousel-photo',
                '768x0' => 'standard-page-photo',
                '355x0' => 'teaser-photo',
                '2560x0' => 'page-background',
                '510x640' => 'small-carousel-cards'
            );
            
            $key = $width . 'x' . $height;
            if (isset($hardcoded_sizes[$key])) {
                return $hardcoded_sizes[$key];
            }
            
            foreach ($hardcoded_sizes as $dim => $name) {
                list($w, $h) = explode('x', $dim);
                if ($width == $w && $h == 0) {
                    return $name;
                }
            }
            
            return "non-standard-{$width}x{$height}";
        }
        
        return '';
    }
}