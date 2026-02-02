<?php
/**
 * Image Extractor Class
 * Handles extraction of images from various sources
 * NO HTML RENDERING - only data extraction   
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Image_Extractor {
    // Global scan data post_id: "scan" in Nokia T9 = 7777222266
    const GLOBAL_SCAN_POST_ID = 7777222266;
    private $image_details = array();  // ← NEW: Store detailed info

    /**
     * Load images from saved scan data (_pim_page_usage)
     * Called by "Load Images" button
     * Returns hierarchical structure from database
     */
    public function load_images_from_saved_data($page_id) {
        // 🔍 DIAGNOSTIC: Check if _pim_page_usage interferes
        error_log("\n🔍 === DIAGNOSTIC: EXTRACT START ===");
        
        // ✅ LOG CALLSTACK HNEĎ NA ZAČIATKU
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        error_log("📞 EXTRACT CALL STACK:");
        foreach ($bt as $i => $trace) {
            $func = isset($trace['function']) ? $trace['function'] : 'unknown';
            $class = isset($trace['class']) ? $trace['class'] . '::' : '';
            $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
            $line = isset($trace['line']) ? $trace['line'] : '?';
            
            // ✅ LOG ARGUMENTS!
            $args = isset($trace['args']) ? $trace['args'] : array();
            $args_str = '';
            if (!empty($args)) {
                $args_str = ' | Args: ' . print_r($args, true);
            }
            
            error_log("   #{$i} {$class}{$func}() in {$file}:{$line}{$args_str}");
        }
        
        error_log("Page ID parameter received: {$page_id}");
        error_log("Page ID type: " . gettype($page_id));
        error_log("Page ID: {$page_id}");
        
        // Check image #10152 specifically
        // $usage = get_post_meta(10152, '_pim_page_usage', true);
        // if ($usage) {
        //     error_log("⚠️ Image #10152 has _pim_page_usage:");
        //     error_log(print_r($usage, true));
        // } else {
        //     error_log("✅ Image #10152 has NO _pim_page_usage (normal extraction will run)");
        // }
        error_log("🔍 === END DIAGNOSTIC ===\n");
        $page = get_post($page_id);
        if (!$page) {
            return new WP_Error('invalid_page', 'Page not found');
        }
        
        error_log("\n📊 === LOADING PAGE IMAGES (FROM SAVED SCAN) ===");
        error_log("Page ID: {$page_id}");
        
        $image_ids = array();
        $image_sources = array();
        $missing_images = array();
        $debug_info = array();
        
        // ✅ STEP 1: Try to load from saved scan data (_pim_page_usage)
        $images_found_in_scan = $this->load_from_saved_scan($page_id, $image_ids, $image_sources, $missing_images);
        
        if ($images_found_in_scan) {
            error_log("✅ Loaded {$images_found_in_scan} images from saved scan");
            $debug_info['loaded_from_scan'] = true;
            $debug_info['scan_images_count'] = $images_found_in_scan;
            
            // ✅ Categorize loaded images (check which exist on disk)
            $valid_images = array();
            $missing_files = array();
            
            foreach ($image_ids as $id) {
                $file_path = get_attached_file($id);
                if ($file_path && file_exists($file_path)) {
                    $valid_images[] = $id;
                } else {
                    $missing_files[] = $id;
                }
            }
            
            error_log("📊 After categorization: valid=" . count($valid_images) . ", missing=" . count($missing_files));
            $debug_info['loaded_from_scan'] = true;
            $debug_info['scan_images_count'] = $images_found_in_scan;
        } else {
            // ❌ NO SCAN DATA - User must run scan first
            error_log("❌ No saved scan data - user must run 'Collect Images from All Pages' first");
            
            return array(
                'error' => 'no_scan_data',
                'message' => 'Please run "Collect Images from All Pages & Save" first.',
                'page_usage_data' => array(),
                'orphaned_files' => array(),
                'duplicates' => array(),
                'debug_info' => array('loaded_from_scan' => false),
                'count' => 0
            );
        }
        // Remove duplicates
        $image_ids = array_unique($image_ids);
        
        // ✅ Fill missing sources with 'unknown' ONLY at the end
        foreach ($image_ids as $id) {
            if (!isset($image_sources[$id]) || empty($image_sources[$id])) {
                $image_sources[$id] = array('unknown');
            }
        }
                
        // Process missing images
        $missing_image_ids = array();
        foreach ($missing_images as $missing) {
            $missing_id = $missing['id'];
            if (!in_array($missing_id, $valid_images)) {
                $missing_image_ids[$missing_id] = $missing;
            }
        }
        
        // ✅ OPTIMIZED: Load ALL global scan data in ONE query (duplicates + orphaned_files)
        $scan_data = get_post_meta(self::GLOBAL_SCAN_POST_ID, '_pim_scan_data', true);
        
        $duplicates = isset($scan_data['duplicates']) && is_array($scan_data['duplicates']) 
            ? $scan_data['duplicates'] 
            : array();
        
        $orphan_files = isset($scan_data['orphaned_files']) && is_array($scan_data['orphaned_files']) 
        ? $scan_data['orphaned_files'] 
        : array();

        // ✅ FIX ORPHANED PATHS: prema puj local paths na web URLs pre renderer
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']);
        $base_url = trailingslashit($upload_dir['baseurl']);

        foreach ($orphan_files as &$orphan) {
            if (!empty($orphan['file_url']) && strpos($orphan['file_url'], $base_dir) === 0) {
                $relative = substr($orphan['file_url'], strlen($base_dir));
                $orphan['file_url'] = $base_url . ltrim($relative, '/');
            }
        }
        unset($orphan);

        error_log("📋 Loaded from global scan data (1 query): " . count($duplicates) . " duplicates, " . count($orphan_files) . " orphaned files (paths fixed)");

        
        // ✅ REMOVED: supplemental sources loading (will be fetched ON-DEMAND in UI)
        // Supplemental sources are NOT needed for "Load Images" - only for detail modals
        
        // Remove duplicates in sources
        foreach ($image_sources as $id => $sources) {
            $image_sources[$id] = array_unique($sources);
        }
        
        error_log('PIM DEBUG: Before return, image_details = ' . print_r($this->image_details, true));
        error_log('PIM DEBUG: valid_images = ' . print_r($valid_images, true));

        // Return data
        error_log("\n🔧 === BUILDING PAGE USAGE STRUCTURE ===");

        $page_usage_structure = $this->build_page_usage_structure(
            $page_id,
            $valid_images,
            $missing_files,
            $missing_image_ids,
            $orphan_files
        );

        error_log("✅ Page usage structure built successfully");

        return array(
            'page_usage_data' => array(
                $page_id => $page_usage_structure['page_data']
            ),
            'orphaned_files' => $orphan_files,
            'duplicates' => $duplicates,
            'scan_summary' => array_merge(
                $debug_info,
                array('count' => count($valid_images) + count($missing_files) + count($missing_image_ids) + count($orphan_files))
            )
        );
    }
    
    private function load_from_saved_scan($page_id, &$image_ids, &$image_sources, &$missing_images) {
        global $wpdb;
        
        $query = "
            SELECT post_id, meta_value
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_pim_page_usage'
        ";
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            error_log("No _pim_page_usage found in database");
            return 0;
        }
        
        error_log("Checking " . count($results) . " images for page #" . $page_id . " usage");
        
        $count = 0;
        $page_id_int = intval($page_id);
        
        foreach ($results as $row) {
            $image_id = intval($row->post_id);
            $page_usage = maybe_unserialize($row->meta_value);
            
            if (!is_array($page_usage) || !isset($page_usage[$page_id_int])) {
                continue;
            }
            
            $page_data = $page_usage[$page_id_int];
            
            $image_ids[] = $image_id;
            
            if (!isset($image_sources[$image_id])) {
                $image_sources[$image_id] = array();
            }
            
            if (!isset($this->image_details[$image_id])) {
                $this->image_details[$image_id] = array();
            }
            
            // Process existing_images
            if (isset($page_data['existing_images'])) {
                foreach ($page_data['existing_images'] as $img) {
                    $source = $img['source'] ?? '';
                    if (!empty($source) && !in_array($source, $image_sources[$image_id])) {
                        $image_sources[$image_id][] = $source;
                    }
                    
                    $this->image_details[$image_id][] = array(
                        'id' => $image_id,
                        'size_name' => $img['size_name'] ?? '',
                        'elementor_id' => $img['elementor_id'] ?? '',
                        'source' => $img['source'] ?? '',
                        'file_url' => $img['file_url'] ?? ''
                    );
                }
            }
            
            // Process missing_in_files
            if (isset($page_data['missing_in_files'])) {
                foreach ($page_data['missing_in_files'] as $img) {
                    $source = $img['source'] ?? '';
                    if (!empty($source) && !in_array($source, $image_sources[$image_id])) {
                        $image_sources[$image_id][] = $source;
                    }
                    
                    $this->image_details[$image_id][] = array(
                        'id' => $image_id,
                        'size_name' => $img['size_name'] ?? '',
                        'elementor_id' => $img['elementor_id'] ?? '',
                        'source' => $img['source'] ?? '',
                        'file_url' => $img['file_url'] ?? ''
                    );
                }
            }
            
            // Process missing_in_database
            if (isset($page_data['missing_in_database'])) {
                foreach ($page_data['missing_in_database'] as $img) {
                    $missing_images[] = array(
                        'id' => $img['id'] ?? 0,
                        'url' => $img['file_url'] ?? '',
                        'source' => $img['source'] ?? '',
                        'size_name' => $img['size_name'] ?? '',
                        'elementor_id' => $img['elementor_id'] ?? ''
                    );
                }
            }
            
            error_log("  Image #" . $image_id . ": " . count($this->image_details[$image_id]) . " uses");
            $count++;
        }
        
        error_log("Total images for page #" . $page_id . ": " . $count);
        
        return $count;
    }
    
    private function build_page_usage_structure($page_id, $valid_images, $missing_files, $missing_image_ids, $orphan_files) {
        error_log("🔨 Building page usage structure for page #{$page_id}");
        
        $page_data = array(
            'existing_images' => array(),
            'missing_in_files' => array(),
            'missing_in_database' => array()
        );
        
        $orphaned_files = array();
        
        error_log("📋 Processing " . count($valid_images) . " existing images");
        foreach ($valid_images as $image_id) {
            // DEBUG: Guard against non-integer IDs
            if (!is_int($image_id) && !is_numeric($image_id)) {
                error_log("❌ existing_images loop: image_id is not numeric - type: " . gettype($image_id) . ", value: " . print_r($image_id, true));
                continue;
            }
            
            $image_id = intval($image_id);
            
            if (!isset($this->image_details[$image_id])) {
                error_log("⚠️ Image #{$image_id} has no details, skipping");
                continue;
            }
            
            // DEBUG: Check if image_details is array
            if (!is_array($this->image_details[$image_id])) {
                error_log("❌ Image #{$image_id}: image_details is not array - type: " . gettype($this->image_details[$image_id]));
                continue;
            }
            
            foreach ($this->image_details[$image_id] as $use) {
                // DEBUG: Guard each use entry
                if (!is_array($use)) {
                    error_log("❌ Image #{$image_id}: use entry is not array - type: " . gettype($use));
                    continue;
                }
                
                $page_data['existing_images'][] = array(
                    'id' => $image_id,
                    'size_name' => isset($use['size_name']) ? $use['size_name'] : '',
                    'elementor_id' => isset($use['elementor_id']) ? $use['elementor_id'] : '',
                    'source' => isset($use['source']) ? $use['source'] : '',
                    'file_url' => isset($use['file_url']) ? $use['file_url'] : ''
                );
            }
        }
        error_log("✅ existing_images section built: " . count($page_data['existing_images']) . " entries");

        
        error_log("📋 Processing " . count($missing_files) . " missing files");
        foreach ($missing_files as $image_id) {
            // DEBUG: Guard against non-integer IDs
            if (!is_int($image_id) && !is_numeric($image_id)) {
                error_log("❌ missing_files loop: image_id is not numeric - type: " . gettype($image_id) . ", value: " . print_r($image_id, true));
                continue;
            }
            
            $image_id = intval($image_id);
            
            if (!isset($this->image_details[$image_id])) {
                error_log("⚠️ Missing file #{$image_id} has no details, skipping");
                continue;
            }
            
            // DEBUG: Check if image_details is array
            if (!is_array($this->image_details[$image_id])) {
                error_log("❌ Missing file #{$image_id}: image_details is not array - type: " . gettype($this->image_details[$image_id]));
                continue;
            }
            
            foreach ($this->image_details[$image_id] as $use) {
                // DEBUG: Guard each use entry
                if (!is_array($use)) {
                    error_log("❌ Missing file #{$image_id}: use entry is not array - type: " . gettype($use));
                    continue;
                }
                
                $page_data['missing_in_files'][] = array(
                    'id' => $image_id,
                    'size_name' => isset($use['size_name']) ? $use['size_name'] : '',
                    'elementor_id' => isset($use['elementor_id']) ? $use['elementor_id'] : '',
                    'source' => isset($use['source']) ? $use['source'] : '',
                    'file_url' => isset($use['file_url']) ? $use['file_url'] : ''
                );
            }
        }
        error_log("✅ missing_in_files section built: " . count($page_data['missing_in_files']) . " entries");

        
        error_log("📋 Processing " . count($missing_image_ids) . " missing in database");
        foreach ($missing_image_ids as $missing_id => $missing_data) {
            // DEBUG: Validate missing_data structure
            if (!is_array($missing_data)) {
                error_log("❌ missing_in_database: missing_data is not array for id {$missing_id} - type: " . gettype($missing_data));
                continue;
            }
            
            $page_data['missing_in_database'][] = array(
                'id' => $missing_id,
                'size_name' => '',
                'elementor_id' => isset($missing_data['elementor_id']) ? $missing_data['elementor_id'] : '',
                'source' => '',
                'file_url' => isset($missing_data['url']) ? $missing_data['url'] : ''
            );
        }
        error_log("✅ missing_in_database section built: " . count($page_data['missing_in_database']) . " entries");

        
        return array(
            'page_data' => $page_data,
            'orphaned_files' => $orphaned_files
        );
    }
    
    public function collect_base_data_from_page($page_id) {
        error_log("\n🔍 === COLLECT BASE DATA FROM PAGE START (Page #{$page_id}) ===");
        
        $page = get_post($page_id);
        if (!$page) {
            error_log("❌ Page not found");
            return new WP_Error('invalid_page', 'Page not found');
        }
        
        $image_ids = array();
        $image_sources = array();
        $missing_images = array();
        $debug_info = array();
        
        $this->image_details = array();
        
        $this->extract_from_content($page, $image_ids, $image_sources, $debug_info);
        
        if (class_exists('\Elementor\Plugin')) {
            $this->extract_from_elementor_actual($page_id, $image_ids, $image_sources, $missing_images, $debug_info);
        }
        
        $debug_urls = array();
        $this->extract_from_html($page_id, $image_ids, $debug_urls, $image_sources);
        $debug_info['html_found_urls'] = count($debug_urls);
        
        $image_ids = array_unique($image_ids);
        
        foreach ($image_ids as $id) {
            if (!isset($image_sources[$id]) || empty($image_sources[$id])) {
                $image_sources[$id] = array('unknown');
            }
        }
        
        $valid_images = array();
        $missing_files = array();
        
        foreach ($image_ids as $id) {
            $id = intval($id);
            if ($id > 0) {
                $post = get_post($id);
                if ($post && $post->post_type === 'attachment') {
                    $file_path = get_attached_file($id);
                    if ($file_path && file_exists($file_path)) {
                        $valid_images[] = $id;
                    } else {
                        $missing_files[] = $id;
                    }
                }
            }
        }
        
        $missing_image_ids = array();
        foreach ($missing_images as $missing) {
            $missing_id = $missing['id'];
            if (!in_array($missing_id, $valid_images)) {
                $missing_image_ids[$missing_id] = $missing;
            }
        }
        
        $duplicate_handler = new PIM_Duplicate_Handler();
        $duplicates = $duplicate_handler->find_duplicates($valid_images, $missing_image_ids);
        
        $orphan_files = $this->find_orphan_files($page_id, $valid_images, $missing_image_ids);
        
        foreach ($valid_images as $image_id) {
            $supplemental = get_post_meta($image_id, '_pim_supplemental_sources', true);
            
            if (is_array($supplemental) && !empty($supplemental)) {
                if (!isset($image_sources[$image_id])) {
                    $image_sources[$image_id] = array();
                }
                
                $image_sources[$image_id] = array_merge(
                    $image_sources[$image_id],
                    $supplemental
                );
                
                error_log("✅ Image #{$image_id}: Added supplemental sources: " . implode(', ', $supplemental));
            }
        }
        
        foreach ($image_sources as $id => $sources) {
            $image_sources[$id] = array_unique($sources);
        }
        
        error_log("📊 Scan complete: " . count($valid_images) . " valid, " . count($missing_files) . " missing files");
        
        $page_usage_structure = $this->build_page_usage_structure(
            $page_id,
            $valid_images,
            $missing_files,
            $missing_image_ids,
            $orphan_files
        );
        
        error_log("🔍 === COLLECT BASE DATA FROM PAGE END ===\n");
        
        return array(
            'page_usage_data' => array(
                $page_id => $page_usage_structure['page_data']
            ),
            'orphaned_files' => $page_usage_structure['orphaned_files'],
            'duplicates' => $duplicates,
            'scan_summary' => array_merge(
                $debug_info,
                array('count' => count($valid_images) + count($missing_files) + count($missing_image_ids) + count($orphan_files))
            )
        );
    }
    
    private function extract_from_content($page, &$image_ids, &$image_sources, &$debug_info) {
        $content = $page->post_content;
        
        preg_match_all('/wp-image-(\d+)/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $id) {
                $image_ids[] = $id;
                if (!isset($image_sources[$id])) {
                    $image_sources[$id] = array();
                }
                $image_sources[$id][] = 'content';
            }
            $debug_info['content_wp_image'] = count($matches[1]);
        }
        
        preg_match_all('/attachment_id["\s]*[:=]["\s]*(\d+)/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $id) {
                $image_ids[] = $id;
                if (!isset($image_sources[$id])) {
                    $image_sources[$id] = array();
                }
                $image_sources[$id][] = 'content';
            }
            $debug_info['content_attachment_id'] = count($matches[1]);
        }
    }

    
    private function extract_from_elementor_actual($page_id, &$image_ids, &$image_sources, &$missing_images, &$debug_info) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (!$elementor_data) return;
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) return;
        
        $elementor_ids = array();
        
        $this->extract_all_elementor_images($data, $elementor_ids, $missing_images, $image_sources, $debug_info);
        
        $image_ids = array_merge($image_ids, $elementor_ids);
        $debug_info['elementor_found'] = count($elementor_ids);
        $debug_info['elementor_missing'] = count($missing_images);
    }
    
    private function extract_all_elementor_images($data, &$image_ids, &$missing_images, &$image_sources, &$debug_info = array()) {
        if (!is_array($data)) return;
        
        foreach ($data as $value) {
            if (!is_array($value)) continue;
            
            $widget_type = $value['widgetType'] ?? '';
            $settings = $value['settings'] ?? array();
            $elementor_id = $value['id'] ?? '';
            
            if ($widget_type && !isset($debug_info['widgets_found'])) {
                $debug_info['widgets_found'] = array();
            }
            if ($widget_type) {
                if (!isset($debug_info['widgets_found'][$widget_type])) {
                    $debug_info['widgets_found'][$widget_type] = 0;
                }
                $debug_info['widgets_found'][$widget_type]++;
            }
            
            if (isset($settings['image']) && is_array($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'image', $elementor_id);
                    $debug_info['generic_image_field_count'] = ($debug_info['generic_image_field_count'] ?? 0) + 1;
                }
            }
            
            if ($widget_type === 'image') {
                $debug_info['image_widget_count'] = ($debug_info['image_widget_count'] ?? 0) + 1;
            }
            
            if ($widget_type === 'image-box') {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'image', $elementor_id);
                }
            }
            
            if (in_array($widget_type, ['image-carousel', 'gallery', 'media-carousel', 'tripimgo-carousel'])) {
                $carousel_fields = ['carousel', 'gallery', 'wp_gallery'];
                foreach ($carousel_fields as $field) {
                    if (isset($settings[$field]) && is_array($settings[$field])) {
                        $carousel_count = count($settings[$field]);
                        $debug_info['carousel_items_found'] = ($debug_info['carousel_items_found'] ?? 0) + $carousel_count;
                        
                        foreach ($settings[$field] as $item) {
                            if (isset($item['id']) || isset($item['url'])) {
                                $this->add_image_from_field($item, $image_ids, $missing_images, $image_sources, 'carousel', $elementor_id);
                            }
                        }
                    }
                }
            }
            
            $bg_fields = ['background_image', 'background_image_mobile', 'background_overlay_image', 'background_overlay_image_mobile', 'background_slideshow_gallery'];
            
            foreach ($bg_fields as $field) {
                if (isset($settings[$field])) {
                    if (isset($settings[$field]['id']) || isset($settings[$field]['url'])) {
                        $source = $this->detect_background_source($value, $settings);
                        $this->add_image_from_field($settings[$field], $image_ids, $missing_images, $image_sources, $source, $elementor_id);
                        
                        if ($source === 'hero') {
                            $debug_info['hero_count'] = ($debug_info['hero_count'] ?? 0) + 1;
                        } else {
                            $debug_info['background_count'] = ($debug_info['background_count'] ?? 0) + 1;
                        }
                    } elseif (is_array($settings[$field])) {
                        foreach ($settings[$field] as $item) {
                            if (isset($item['id']) || isset($item['url'])) {
                                $this->add_image_from_field($item, $image_ids, $missing_images, $image_sources, 'background', $elementor_id);
                                $debug_info['background_count'] = ($debug_info['background_count'] ?? 0) + 1;
                            }
                        }
                    }
                }
            }
            
            if ($widget_type === 'video' && isset($settings['image_overlay'])) {
                if (isset($settings['image_overlay']['id']) || isset($settings['image_overlay']['url'])) {
                    $this->add_image_from_field($settings['image_overlay'], $image_ids, $missing_images, $image_sources, 'video-poster', $elementor_id);
                }
            }
            
            if ($widget_type === 'testimonial' && isset($settings['testimonial_image'])) {
                if (isset($settings['testimonial_image']['id']) || isset($settings['testimonial_image']['url'])) {
                    $this->add_image_from_field($settings['testimonial_image'], $image_ids, $missing_images, $image_sources, 'avatar', $elementor_id);
                }
            }
            
            if (in_array($widget_type, ['team-member', 'person']) && isset($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'avatar', $elementor_id);
                }
            }
            
            if (in_array($widget_type, ['icon-box', 'icon-list'])) {
                if (isset($settings['image'])) {
                    if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                        $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'icon', $elementor_id);
                    }
                }
                if (isset($settings['icon_list']) && is_array($settings['icon_list'])) {
                    foreach ($settings['icon_list'] as $item) {
                        if (isset($item['image'])) {
                            if (isset($item['image']['id']) || isset($item['image']['url'])) {
                                $this->add_image_from_field($item['image'], $image_ids, $missing_images, $image_sources, 'icon', $elementor_id);
                            }
                        }
                    }
                }
            }
            
            if ($widget_type === 'call-to-action' && isset($settings['bg_image'])) {
                if (isset($settings['bg_image']['id']) || isset($settings['bg_image']['url'])) {
                    $this->add_image_from_field($settings['bg_image'], $image_ids, $missing_images, $image_sources, 'background', $elementor_id);
                }
            }
            
            if ($widget_type === 'price-table' && isset($settings['ribbon_image'])) {
                if (isset($settings['ribbon_image']['id']) || isset($settings['ribbon_image']['url'])) {
                    $this->add_image_from_field($settings['ribbon_image'], $image_ids, $missing_images, $image_sources, 'icon', $elementor_id);
                }
            }
            
            if (in_array($widget_type, ['logo', 'site-logo']) && isset($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'logo', $elementor_id);
                }
            }
            
            $this->search_settings_for_images($settings, $image_ids, $missing_images, $image_sources, $elementor_id);
            
            if (isset($value['elements'])) {
                $this->extract_all_elementor_images($value['elements'], $image_ids, $missing_images, $image_sources, $debug_info);
            }
        }
    }
    
    private function detect_background_source($element, $settings) {
        $element_type = $element['elType'] ?? '';
        $css_classes = $settings['_css_classes'] ?? '';
        if (stripos($css_classes, 'hero') !== false) {
            return 'hero';
        }
        $element_id = $settings['_element_id'] ?? '';
        if (stripos($element_id, 'hero') !== false) {
            return 'hero';
        }
        return 'background';
    }
    
    private function search_settings_for_images($settings, &$image_ids, &$missing_images, &$image_sources, $elementor_id = '') {
        if (!is_array($settings)) return;
        
        foreach ($settings as $key => $value) {
            if (in_array($key, ['background_image', 'image', 'carousel', 'gallery'])) {
                continue;
            }
            
            if (is_array($value) && isset($value['id']) && isset($value['url'])) {
                $url = $value['url'];
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url)) {
                    $this->add_image_from_field($value, $image_ids, $missing_images, $image_sources, 'other', $elementor_id);
                }
            }
            
            if (is_array($value)) {
                $this->search_settings_for_images($value, $image_ids, $missing_images, $image_sources, $elementor_id);
            }
        }
    }
    
    private function add_image_from_field($field, &$image_ids, &$missing_images, &$image_sources, $source, $elementor_id = '') {
        $id = intval($field['id'] ?? 0);
        $url = $field['url'] ?? '';
        
        if ($id <= 0) {
            // No ID provided - skip (we can't work without ID)
            return;
        }
        
        // ✅ Check if ID exists and is valid attachment
        $post = get_post($id);
        
        // ✅ SPLIT CONDITIONS for precise logging
        if (!$post) {
            error_log("❌ ID {$id} does NOT EXIST in database (get_post returned NULL)");
            error_log("   URL: {$url}");
            error_log("   → Saving to missing_in_database (user will decide via Link & Generate)");
            
            // ✅ Keep original ID - save to missing_in_database
            $missing_images[] = array(
                'id' => $id,
                'url' => $url,
                'source' => $source,
                'elementor_id' => $elementor_id
            );
            return;
            
        } elseif ($post->post_type !== 'attachment') {
            error_log("❌ ID {$id} EXISTS but is NOT an attachment!");
            error_log("   post_type: {$post->post_type}");
            error_log("   post_title: {$post->post_title}");
            error_log("   URL: {$url}");
            error_log("   → Saving to missing_in_database");
            
            // ✅ Keep original ID - save to missing_in_database
            $missing_images[] = array(
                'id' => $id,
                'url' => $url,
                'source' => $source,
                'elementor_id' => $elementor_id
            );
            return;
            
        } else {
            // ✅ ID exists AND is attachment - perfect!
            error_log("✅ ID {$id} is VALID attachment (post_type: {$post->post_type})");
        }
        
        // ✅ SPECIAL LOGGING FOR IMG_2510
        if ($id == 10152 || $id == 5175) {
            error_log("🎯 IMG_2510 FOUND IN ELEMENTOR!");
            error_log("  ID: {$id}");
            error_log("  Source: {$source}");
            error_log("  URL: {$url}");
            error_log("  Elementor ID: {$elementor_id}");
        }
        
        // ✅ Add to existing_images
        $image_ids[] = $id;
        if (!isset($image_sources[$id])) {
            $image_sources[$id] = array();
        }
        $image_sources[$id][] = $source;
        
        if (!isset($this->image_details[$id])) {
            $this->image_details[$id] = array();
        }
        
        $size_helper = new PIM_Size_Helper();
        $image_meta = wp_get_attachment_metadata($id);
        $size_name = $size_helper->match_size_from_url($url);
        
        $this->image_details[$id][] = array(
            'id' => $id,
            'size_name' => $size_name,
            'elementor_id' => $elementor_id,
            'source' => $source,
            'file_url' => $url
        );
        
        // ✅ IMG_2510: Log after adding
        if ($id == 10152 || $id == 5175) {
            error_log("  ✅ Added to existing_images! Sources: " . print_r($image_sources[$id], true));
        }
    }
    
    private function find_attachment_by_url($url) {
        global $wpdb;
        
        if (empty($url)) return 0;
        
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
            '%' . $wpdb->esc_like($filename) . '%'
        ));
        
        if ($attachment_id) return intval($attachment_id);
        
        $filename_clean = preg_replace('/-scaled(-\d+)?/', '', $filename);
        if ($filename_clean !== $filename) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
                '%' . $wpdb->esc_like($filename_clean) . '%'
            ));
            
            if ($attachment_id) return intval($attachment_id);
        }
        
        $filename_no_dims = preg_replace('/-\d+x\d+/', '', $filename_clean);
        if ($filename_no_dims !== $filename_clean) {
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1",
                '%' . $wpdb->esc_like($filename_no_dims) . '%'
            ));
            
            if ($attachment_id) return intval($attachment_id);
        }
        
        return 0;
    }
    
    private function extract_from_html($page_id, &$image_ids, &$debug_urls, &$image_sources) {
        $post = get_post($page_id);
        if (!$post) return;
        
        $page_url = get_permalink($page_id);
        $response = wp_remote_get($page_url, array('timeout' => 15));
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $html = wp_remote_retrieve_body($response);
        } else {
            $html = apply_filters('the_content', $post->post_content);
        }
        
        preg_match_all('/<img[^>]+(src|data-src)=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches);
        if (empty($matches[2])) return;
        
        $image_urls = array_unique($matches[2]);
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        
        global $wpdb;
        foreach ($image_urls as $image_url) {
            $debug_urls[] = $image_url;
            if (strpos($image_url, $base_url) === false) continue;
            
            $filename = basename(parse_url($image_url, PHP_URL_PATH));
            
            $attachment_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta pm
                INNER JOIN $wpdb->posts p ON p.ID = pm.post_id
                WHERE pm.meta_key = '_wp_attachment_metadata'
                AND p.post_type = 'attachment'
                AND (pm.meta_value LIKE %s OR p.guid LIKE %s)
                LIMIT 1",
                '%' . $wpdb->esc_like($filename) . '%',
                '%' . $wpdb->esc_like($filename) . '%'
            ));
            
            if (!$attachment_id) {
                $filename_no_scaled = preg_replace('/-scaled(-\d+)?/', '', $filename);
                $attachment_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta pm
                    INNER JOIN $wpdb->posts p ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_wp_attachment_metadata'
                    AND p.post_type = 'attachment'
                    AND (pm.meta_value LIKE %s OR p.guid LIKE %s)
                    LIMIT 1",
                    '%' . $wpdb->esc_like($filename_no_scaled) . '%',
                    '%' . $wpdb->esc_like($filename_no_scaled) . '%'
                ));
            }
            
            if ($attachment_id && wp_attachment_is_image($attachment_id)) {
                $image_ids[] = $attachment_id;
                
                if (!isset($image_sources[$attachment_id])) {
                }
            }
        }
    }
    
    private function find_orphan_files($page_id, $valid_images, $missing_image_ids) {
        $orphans = array();
        
        $page_date = get_post_field('post_date', $page_id);
        $year = date('Y', strtotime($page_date));
        $month = date('m', strtotime($page_date));
        
        $upload_dir = wp_upload_dir();
        $page_upload_path = $upload_dir['basedir'] . '/' . $year . '/' . $month;
        
        if (!is_dir($page_upload_path)) {
            return $orphans;
        }
        
        $patterns = array(
            $page_upload_path . '/*.jpg',
            $page_upload_path . '/*.jpeg',
            $page_upload_path . '/*.png',
            $page_upload_path . '/*.gif',
            $page_upload_path . '/*.webp'
        );
        
        $all_files = array();
        foreach ($patterns as $pattern) {
            $files = glob($pattern);
            if ($files) {
                $all_files = array_merge($all_files, $files);
            }
        }
        
        $used_files = array();
        
        foreach ($valid_images as $image_id) {
            $main_file = get_attached_file($image_id);
            if ($main_file) {
                $used_files[] = $main_file;
            }
            
            $metadata = wp_get_attachment_metadata($image_id);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                $dir = dirname($main_file);
                foreach ($metadata['sizes'] as $size_data) {
                    if (isset($size_data['file'])) {
                        $used_files[] = $dir . '/' . $size_data['file'];
                    }
                }
            }
        }
        
        foreach ($missing_image_ids as $missing_data) {
            $url = $missing_data['url'];
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            if (file_exists($file_path)) {
                $used_files[] = $file_path;
            }
        }
        
        foreach ($all_files as $file) {
            if (!in_array($file, $used_files)) {
                $orphans[] = array(
                    'id' => md5($file),
                    'size_name' => '',
                    'elementor_id' => '',
                    'source' => '',
                    'file_url' => $file,
                    'file_size' => filesize($file)
                );
            }
        }
        
        return $orphans;
    }
}