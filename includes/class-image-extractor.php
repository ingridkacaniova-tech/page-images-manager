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
    
    /**
     * Extract all images from a page
     * Returns data array, NOT HTML
     */
    public function extract_all_images($page_id) {
        $page = get_post($page_id);
        if (!$page) {
            return new WP_Error('invalid_page', 'Page not found');
        }
        
        $image_ids = array();
        $image_sources = array();
        $missing_images = array();
        $debug_info = array();
        
        // Extract from content
        $this->extract_from_content($page, $image_ids, $image_sources, $debug_info);
        
        // Extract from Elementor
        if (class_exists('\Elementor\Plugin')) {
            $this->extract_from_elementor($page_id, $image_ids, $image_sources, $missing_images, $debug_info);
        }
        
        // Extract from HTML (NOW with source tracking!)
        $debug_urls = array();
        $this->extract_from_html($page_id, $image_ids, $debug_urls, $image_sources);
        $debug_info['html_found_urls'] = count($debug_urls);
        
        // Remove duplicates
        $image_ids = array_unique($image_ids);
        
        // ✅ Fill missing sources with 'unknown' ONLY at the end
        foreach ($image_ids as $id) {
            if (!isset($image_sources[$id]) || empty($image_sources[$id])) {
                $image_sources[$id] = array('unknown');
            }
        }
        
        // Categorize images
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
        
        // Process missing images
        $missing_image_ids = array();
        foreach ($missing_images as $missing) {
            $missing_id = $missing['id'];
            if (!in_array($missing_id, $valid_images)) {
                $missing_image_ids[$missing_id] = $missing;
            }
        }
        
        // Find duplicates
        $duplicate_handler = new PIM_Duplicate_Handler();
        $duplicates = $duplicate_handler->find_duplicates($valid_images, $missing_image_ids);
        
        // ✅ Find orphan files (files on disk, not in DB, not in Elementor)
        $orphan_files = $this->find_orphan_files($page_id, $valid_images, $missing_image_ids);
        
        // ✅ Add supplemental sources (RECOVERED - FILL IN)
        foreach ($valid_images as $image_id) {
            $supplemental = get_post_meta($image_id, '_pim_supplemental_sources', true);
            
            if (is_array($supplemental) && !empty($supplemental)) {
                if (!isset($image_sources[$image_id])) {
                    $image_sources[$image_id] = array();
                }
                
                // Merge supplemental sources
                $image_sources[$image_id] = array_merge(
                    $image_sources[$image_id],
                    $supplemental
                );
                
                error_log("✅ Image #{$image_id}: Added supplemental sources: " . implode(', ', $supplemental));
            }
        }

        // Remove duplicates in sources
        foreach ($image_sources as $id => $sources) {
            $image_sources[$id] = array_unique($sources);
        }

        // ✅ Find orphan files (files on disk, not in DB, not in Elementor)
        $orphan_files = $this->find_orphan_files($page_id, $valid_images, $missing_image_ids);
        
        // Return data
        return array(
            'valid_images' => $valid_images,
            'missing_files' => $missing_files,
            'missing_image_ids' => $missing_image_ids,
            'orphan_files' => $orphan_files,  // ✅ NEW
            'image_sources' => $image_sources,
            'duplicates' => $duplicates,
            'debug_info' => $debug_info,
            'count' => count($valid_images) + count($missing_files) + count($missing_image_ids) + count($orphan_files)
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
    
    private function extract_from_elementor($page_id, &$image_ids, &$image_sources, &$missing_images, &$debug_info) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (!$elementor_data) return;
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) return;
        
        $elementor_ids = array();
        
        // Extract from all widget types
        $this->extract_all_elementor_images($data, $elementor_ids, $missing_images, $image_sources, $debug_info);
        
        $image_ids = array_merge($image_ids, $elementor_ids);
        $debug_info['elementor_found'] = count($elementor_ids);
        $debug_info['elementor_missing'] = count($missing_images);
    }
    
    /**
     * Extract ALL images from Elementor - comprehensive detection
     */
    private function extract_all_elementor_images($data, &$image_ids, &$missing_images, &$image_sources, &$debug_info = array()) {
        if (!is_array($data)) return;
        
        foreach ($data as $value) {
            if (!is_array($value)) continue;
            
            $widget_type = $value['widgetType'] ?? '';
            $settings = $value['settings'] ?? array();
            
            // Track widget types
            if ($widget_type && !isset($debug_info['widgets_found'])) {
                $debug_info['widgets_found'] = array();
            }
            if ($widget_type) {
                if (!isset($debug_info['widgets_found'][$widget_type])) {
                    $debug_info['widgets_found'][$widget_type] = 0;
                }
                $debug_info['widgets_found'][$widget_type]++;
            }
            
            // ✅ AGGRESSIVE: Detect ANY 'image' field in settings FIRST
            if (isset($settings['image']) && is_array($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'image');
                    $debug_info['generic_image_field_count'] = ($debug_info['generic_image_field_count'] ?? 0) + 1;
                }
            }
            
            // 1. IMAGE WIDGET (keep for tracking)
            if ($widget_type === 'image') {
                $debug_info['image_widget_count'] = ($debug_info['image_widget_count'] ?? 0) + 1;
            }
            
            // 2. IMAGE BOX WIDGET
            if ($widget_type === 'image-box') {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'image');
                }
            }
            
            // 3. CAROUSEL WIDGETS
            if (in_array($widget_type, ['image-carousel', 'gallery', 'media-carousel', 'tripimgo-carousel'])) {
                $carousel_fields = ['carousel', 'gallery', 'wp_gallery'];
                foreach ($carousel_fields as $field) {
                    if (isset($settings[$field]) && is_array($settings[$field])) {
                        $carousel_count = count($settings[$field]);
                        $debug_info['carousel_items_found'] = ($debug_info['carousel_items_found'] ?? 0) + $carousel_count;
                        
                        foreach ($settings[$field] as $item) {
                            if (isset($item['id']) || isset($item['url'])) {
                                $this->add_image_from_field($item, $image_ids, $missing_images, $image_sources, 'carousel');
                            }
                        }
                    }
                }
            }
            
            // 4. BACKGROUND IMAGES
            $bg_fields = ['background_image', 'background_image_mobile', 'background_overlay_image', 'background_overlay_image_mobile', 'background_slideshow_gallery'];
            
            foreach ($bg_fields as $field) {
                if (isset($settings[$field])) {
                    if (isset($settings[$field]['id']) || isset($settings[$field]['url'])) {
                        $source = $this->detect_background_source($value, $settings);
                        $this->add_image_from_field($settings[$field], $image_ids, $missing_images, $image_sources, $source);
                        
                        // ✅ Track backgrounds separately
                        if ($source === 'hero') {
                            $debug_info['hero_count'] = ($debug_info['hero_count'] ?? 0) + 1;
                        } else {
                            $debug_info['background_count'] = ($debug_info['background_count'] ?? 0) + 1;
                        }
                    } elseif (is_array($settings[$field])) {
                        foreach ($settings[$field] as $item) {
                            if (isset($item['id']) || isset($item['url'])) {
                                $this->add_image_from_field($item, $image_ids, $missing_images, $image_sources, 'background');
                                $debug_info['background_count'] = ($debug_info['background_count'] ?? 0) + 1;
                            }
                        }
                    }
                }
            }
            
            // 5-11. OTHER WIDGETS
            if ($widget_type === 'video' && isset($settings['image_overlay'])) {
                if (isset($settings['image_overlay']['id']) || isset($settings['image_overlay']['url'])) {
                    $this->add_image_from_field($settings['image_overlay'], $image_ids, $missing_images, $image_sources, 'video-poster');
                }
            }
            
            if ($widget_type === 'testimonial' && isset($settings['testimonial_image'])) {
                if (isset($settings['testimonial_image']['id']) || isset($settings['testimonial_image']['url'])) {
                    $this->add_image_from_field($settings['testimonial_image'], $image_ids, $missing_images, $image_sources, 'avatar');
                }
            }
            
            if (in_array($widget_type, ['team-member', 'person']) && isset($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'avatar');
                }
            }
            
            if (in_array($widget_type, ['icon-box', 'icon-list'])) {
                if (isset($settings['image'])) {
                    if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                        $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'icon');
                    }
                }
                if (isset($settings['icon_list']) && is_array($settings['icon_list'])) {
                    foreach ($settings['icon_list'] as $item) {
                        if (isset($item['image'])) {
                            if (isset($item['image']['id']) || isset($item['image']['url'])) {
                                $this->add_image_from_field($item['image'], $image_ids, $missing_images, $image_sources, 'icon');
                            }
                        }
                    }
                }
            }
            
            if ($widget_type === 'call-to-action' && isset($settings['bg_image'])) {
                if (isset($settings['bg_image']['id']) || isset($settings['bg_image']['url'])) {
                    $this->add_image_from_field($settings['bg_image'], $image_ids, $missing_images, $image_sources, 'background');
                }
            }
            
            if ($widget_type === 'price-table' && isset($settings['ribbon_image'])) {
                if (isset($settings['ribbon_image']['id']) || isset($settings['ribbon_image']['url'])) {
                    $this->add_image_from_field($settings['ribbon_image'], $image_ids, $missing_images, $image_sources, 'icon');
                }
            }
            
            if (in_array($widget_type, ['logo', 'site-logo']) && isset($settings['image'])) {
                if (isset($settings['image']['id']) || isset($settings['image']['url'])) {
                    $this->add_image_from_field($settings['image'], $image_ids, $missing_images, $image_sources, 'logo');
                }
            }
            
            // 12. GENERIC
            $this->search_settings_for_images($settings, $image_ids, $missing_images, $image_sources);
            
            // Recurse
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
    
    private function search_settings_for_images($settings, &$image_ids, &$missing_images, &$image_sources) {
        if (!is_array($settings)) return;
        
        foreach ($settings as $key => $value) {
            if (in_array($key, ['background_image', 'image', 'carousel', 'gallery'])) {
                continue;
            }
            
            if (is_array($value) && isset($value['id']) && isset($value['url'])) {
                $url = $value['url'];
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url)) {
                    $this->add_image_from_field($value, $image_ids, $missing_images, $image_sources, 'other');
                }
            }
            
            if (is_array($value)) {
                $this->search_settings_for_images($value, $image_ids, $missing_images, $image_sources);
            }
        }
    }
    
    private function add_image_from_field($field, &$image_ids, &$missing_images, &$image_sources, $source) {
        $id = intval($field['id'] ?? 0);
        $url = $field['url'] ?? '';
        
        // If no ID but has URL, try to find attachment by URL
        if ($id <= 0 && $url) {
            $id = $this->find_attachment_by_url($url);
        }
        
        if ($id <= 0) return;
        
        $post = get_post($id);
        if ($post && $post->post_type === 'attachment') {
            $image_ids[] = $id;
            if (!isset($image_sources[$id])) {
                $image_sources[$id] = array();
            }
            $image_sources[$id][] = $source;
        } elseif ($url) {
            $missing_images[] = array(
                'id' => $id,
                'url' => $url,
                'source' => $source
            );
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
    
    /**
     * Extract from HTML - SIMPLIFIED (Elementor is authoritative)
     * Only adds 'html' source if NO other source exists
     */
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
                
                // ✅ AGGRESSIVE FIX: NEVER add 'html' source
                // Let it remain without source if Elementor didn't detect it
                // Better to have NO source than WRONG source
                
                // Only initialize array if completely missing
                if (!isset($image_sources[$attachment_id])) {
                    // Leave empty - will be filled by Elementor or remain unknown
                }
            }
        }
    }

    /**
     * ✅ Find orphan files (exist on disk, not in DB, not in Elementor)
     */
    private function find_orphan_files($page_id, $valid_images, $missing_image_ids) {
        PIM_Debug_Logger::enter('find_orphan_files', array('page_id' => $page_id));
        
        $orphans = array();
        
        // Get upload directory for this page
        $page_date = get_post_field('post_date', $page_id);
        $year = date('Y', strtotime($page_date));
        $month = date('m', strtotime($page_date));
        
        $upload_dir = wp_upload_dir();
        $page_upload_path = $upload_dir['basedir'] . '/' . $year . '/' . $month;
        
        if (!is_dir($page_upload_path)) {
            PIM_Debug_Logger::warning('Upload directory does not exist', array('path' => $page_upload_path));
            return $orphans;
        }
        
        // Get all image files
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
        
        PIM_Debug_Logger::log('Found files on disk', array('count' => count($all_files)));
        
        // Build list of files that ARE used
        $used_files = array();
        
        // From valid images
        foreach ($valid_images as $image_id) {
            $main_file = get_attached_file($image_id);
            if ($main_file) {
                $used_files[] = $main_file;
            }
            
            // Thumbnails
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
        
        // From missing in database (URLs)
        foreach ($missing_image_ids as $missing_data) {
            $url = $missing_data['url'];
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);
            if (file_exists($file_path)) {
                $used_files[] = $file_path;
            }
        }
        
        PIM_Debug_Logger::log('Files in use', array('count' => count($used_files)));
        
        // Find orphans
        foreach ($all_files as $file) {
            if (!in_array($file, $used_files)) {
                $orphans[] = array(
                    'file' => basename($file),
                    'path' => $file,
                    'size' => filesize($file),
                    'url' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file)
                );
            }
        }
        
        PIM_Debug_Logger::success('Found orphan files', array('count' => count($orphans)));
        PIM_Debug_Logger::exit_function('find_orphan_files');
        
        return $orphans;
    }
}