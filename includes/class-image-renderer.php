<?php
/**
 * Image Renderer Class
 * Handles HTML generation for the plugin UI
 * 
 * ✅ NEW: Public render_image_row_public() for single row updates
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIM_Image_Renderer {
    
    private $size_helper;
    
    public function __construct() {
        $this->size_helper = new PIM_Size_Helper();
    }

    /**
     * Generate complete HTML output
     */
    public function generate_html($data) {
        $page_usage_data = $data['page_usage_data'] ?? array();
        $orphaned_files = $data['orphaned_files'] ?? array();
        $duplicates = $data['duplicates'] ?? array();
        $debug_info = $data['debug_info'] ?? array();
        
        $page_id = !empty($page_usage_data) ? key($page_usage_data) : 0;
        $page_data = $page_usage_data[$page_id] ?? array();
        
        $existing_images_data = $page_data['existing_images'] ?? array();
        $missing_files_data = $page_data['missing_in_files'] ?? array();
        $missing_db_data = $page_data['missing_in_database'] ?? array();
        
        $valid_images = array();
        foreach ($existing_images_data as $img) {
            $id = intval($img['id']);
            if ($id > 0 && !in_array($id, $valid_images)) {
                $valid_images[] = $id;
            }
        }
        
        $missing_files = array();
        foreach ($missing_files_data as $img) {
            $id = intval($img['id']);
            if ($id > 0 && !in_array($id, $missing_files)) {
                $missing_files[] = $id;
            }
        }
        
        $missing_image_ids = array();
        foreach ($missing_db_data as $img) {
            $missing_image_ids[$img['id']] = array(
                'id' => $img['id'],
                'url' => $img['file_url']
            );
        }
        
        $image_sources = array();
        foreach ($existing_images_data as $img) {
            $id = intval($img['id']);
            if ($id > 0) {
                if (!isset($image_sources[$id])) {
                    $image_sources[$id] = array();
                }
                $source = $img['source'];
                if (!empty($source) && !in_array($source, $image_sources[$id])) {
                    $image_sources[$id][] = $source;
                }
            }
        }
        
        foreach ($missing_files_data as $img) {
            $id = intval($img['id']);
            if ($id > 0) {
                if (!isset($image_sources[$id])) {
                    $image_sources[$id] = array();
                }
                $source = $img['source'];
                if (!empty($source) && !in_array($source, $image_sources[$id])) {
                    $image_sources[$id][] = $source;
                }
            }
        }
        
        error_log("🎨 Renderer: valid_images=" . count($valid_images) . ", missing_files=" . count($missing_files) . ", missing_db=" . count($missing_image_ids));
            
        ob_start();
        
        $custom_sizes = $this->size_helper->get_custom_sizes();
        
        $section_count = 0;
        if (!empty($valid_images)) $section_count++;
        if (!empty($missing_files)) $section_count++;
        if (!empty($missing_image_ids)) $section_count++;
        
        $current_section = 0;
        
        // Existing Images section
        if (!empty($valid_images)) {
            $current_section++;
            $is_first = ($current_section === 1);
            $is_last = ($current_section === $section_count);
            
            echo '<div class="pim-collapsible-section" id="section-existing-images">';
            echo '<div class="pim-section-header">';
            
                echo '<h3 class="pim-section-toggle" data-section="existing-images">';
                echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                echo 'Existing Images (' . count($valid_images) . ')';
                echo '</h3>';
                
                echo '<div class="pim-section-actions">';
                echo '<button type="button" class="button button-secondary pim-show-debug-log pim-debug-only" data-section="existing-images" style="display: none;">📋 Show latest debug log</button>';
                
                if (!$is_first) {
                    echo '<button class="button pim-section-nav" data-direction="up" title="Previous section"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
                }
                if (!$is_last) {
                    echo '<button class="button pim-section-nav" data-direction="down" title="Next section"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
                }
                
                echo '</div>';
                
            echo '</div>';
            
            echo '<div class="pim-section-content" id="existing-images-content">';
            foreach ($valid_images as $image_id) {
                $this->render_image_row($image_id, $custom_sizes, $image_sources, $duplicates);
            }
            echo '</div>';
            echo '</div>';
        }
        
        // Missing Files section
        if (!empty($missing_files)) {
            $current_section++;
            $is_first = ($current_section === 1);
            $is_last = ($current_section === $section_count);
            
            echo '<div class="pim-collapsible-section" id="section-missing-files">';
            echo '<div class="pim-section-header">';
            
                echo '<h3 class="pim-section-toggle" data-section="missing-files" style="color: #d63638;">';
                echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                echo '⚠️ Missing Files (' . count($missing_files) . ')';
                echo '</h3>';
                
                echo '<div class="pim-section-actions">';
                echo '<button type="button" class="button button-secondary pim-show-debug-log pim-debug-only" data-section="missing-files" style="display: none;">📋 Show latest debug log</button>';
                
                if (!$is_first) {
                    echo '<button class="button pim-section-nav" data-direction="up" title="Previous section"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
                }
                if (!$is_last) {
                    echo '<button class="button pim-section-nav" data-direction="down" title="Next section"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
                }
                
                echo '</div>';
                
            echo '</div>';
            
            echo '<div class="pim-section-content" id="missing-files-content">';
            foreach ($missing_files as $image_id) {
                $this->render_missing_file_row($image_id, $custom_sizes, $image_sources);
            }
            echo '</div>';
            echo '</div>';
        }
        
        // Missing in Database section
        if (!empty($missing_image_ids)) {
            $current_section++;
            $is_first = ($current_section === 1);
            $is_last = ($current_section === $section_count);
            
            echo '<div class="pim-collapsible-section" id="section-missing-db">';
            echo '<div class="pim-section-header">';
            
                echo '<h3 class="pim-section-toggle" data-section="missing-db" style="color: #dc3232;">';
                echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                echo '❌ Missing in Database (' . count($missing_image_ids) . ')';
                echo '</h3>';
                
                echo '<div class="pim-section-actions">';
                echo '<button type="button" class="button button-secondary pim-show-debug-log pim-debug-only" data-section="missing-db" style="display: none;">📋 Show latest debug log</button>';
                
                if (!$is_first) {
                    echo '<button class="button pim-section-nav" data-direction="up" title="Previous section"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
                }
                if (!$is_last) {
                    echo '<button class="button pim-section-nav" data-direction="down" title="Next section"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
                }
                
                echo '</div>';
                
            echo '</div>';
            
            echo '<div class="pim-section-content" id="missing-db-content">';
            foreach ($missing_image_ids as $missing_id => $missing_data) {
                $this->render_missing_db_row($missing_id, $missing_data);
            }
            echo '</div>';
            echo '</div>';
        }
        
        // Orphan Files section
        if (!empty($orphaned_files)) {
            $current_section++;
            $is_first = ($current_section === 1);
            $is_last = ($current_section === $section_count);
            
            echo '<div class="pim-collapsible-section" id="section-orphan-files">';
            echo '<div class="pim-section-header">';
            
                echo '<h3 class="pim-section-toggle" data-section="orphan-files" style="color: #999;">';
                echo '<span class="dashicons dashicons-arrow-down-alt2"></span>';
                echo '🗑️ Orphan Files (' . count($orphaned_files) . ')';
                echo '</h3>';
                
                echo '<div class="pim-section-actions">';
                
                if (!$is_first) {
                    echo '<button class="button pim-section-nav" data-direction="up" title="Previous section"><span class="dashicons dashicons-arrow-up-alt2"></span></button>';
                }
                if (!$is_last) {
                    echo '<button class="button pim-section-nav" data-direction="down" title="Next section"><span class="dashicons dashicons-arrow-down-alt2"></span></button>';
                }
                
                echo '</div>';
                
            echo '</div>';
            
            echo '<div class="pim-section-content" id="orphan-files-content">';
            foreach ($orphaned_files as $orphan) {
                $this->render_orphan_file_row($orphan);
            }
            echo '</div>';
            echo '</div>';
        }

        return ob_get_clean();
    }

    public function render_image_row_public($image_id, $custom_sizes, $image_sources, $duplicates, $image_meta = null) {
        error_log("🎨 === RENDER_IMAGE_ROW_PUBLIC START ===");
        error_log("   Image ID: {$image_id}");
        
        if ($image_meta === null) {
            error_log("   Loading metadata...");
            $image_meta = wp_get_attachment_metadata($image_id);
        } else {
            error_log("   Using provided metadata");
        }
        
        error_log("🎨 Calling render_image_row()...");
        
        $this->render_image_row($image_id, $custom_sizes, $image_sources, $duplicates, $image_meta);
        
        error_log("🎨 === RENDER_IMAGE_ROW_PUBLIC END ===");
    }

    /**
     * Render existing image row
     */
    private function render_image_row($image_id, $custom_sizes, $image_sources, $duplicates, $image_meta = null) {
        if ($image_meta === null) {
            $image_meta = wp_get_attachment_metadata($image_id);
        }
        
        $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
        $image_title = get_the_title($image_id);
        $file_path = get_attached_file($image_id);
        
        echo '<div class="pim-image-row" data-image-id="' . $image_id . '">';
        
        // Thumbnail
        echo '<div class="pim-image-thumb">';
        if ($image_url) {
            echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_title) . '">';
        } else {
            echo '<div class="pim-image-thumb-placeholder">❌ Missing</div>';
        }
        echo '</div>';
        
        // Info
        echo '<div class="pim-image-info">';
        echo '<h4>' . esc_html($image_title ?: 'Untitled') . '</h4>';
        echo '<p class="description"><strong>ID:</strong> ' . $image_id . '</p>';
        
        $sources = $image_sources[$image_id] ?? array('unknown');
        $sources_unique = array_unique($sources);
        echo '<p class="description"><strong>Source:</strong> ' . implode(', ', $sources_unique) . '</p>';
        
        if ($file_path) {
            echo '<p class="description"><strong>File:</strong> ' . esc_html(basename($file_path)) . '</p>';
            echo '<p class="description" style="font-size: 11px; color: #666; word-break: break-all;">' . esc_html($file_path) . '</p>';
        }
        
        $this->render_resolution_info($file_path, $image_meta);
        
        echo '</div>';
        
        // Size selector with lock icon
        echo '<div class="pim-size-selector" data-image-id="' . $image_id . '">';
        $this->render_size_selector($image_id, $sources_unique, $custom_sizes, $image_meta);
        echo '</div>';
        
        // Actions
        echo '<div class="pim-image-actions">';
        
        // ✅ DEBUG: Log what duplicates we received
        error_log("🎨 RENDERER: Image #{$image_id} - Checking for duplicates");
        error_log("   Duplicates array keys: " . print_r(array_keys($duplicates), true));
        if (isset($duplicates[$image_id])) {
            error_log("   ✅ Image #{$image_id} HAS duplicates: " . print_r($duplicates[$image_id], true));
        } else {
            error_log("   ❌ Image #{$image_id} has NO duplicates in array");
        }
        
        // Link & Generate for duplicates
        if (isset($duplicates[$image_id])) {
            $duplicate_ids = array_column($duplicates[$image_id], 'missing_id');
            echo '<button type="button" class="button button-primary link-generate-btn" ';
            echo 'data-primary-id="' . $image_id . '" ';
            echo 'data-duplicate-ids=\'' . json_encode($duplicate_ids) . '\'>';
            echo '🔗 Link & Generate (' . count($duplicate_ids) . ')';
            echo '</button>';
        }
        
        // ✅ Delete unused occurrences button
        // Shows if image has more source entries than unique sources
        $total_sources = count($sources);
        $unique_count = count($sources_unique);
        $unused_count = $total_sources - $unique_count;
        
        if ($unused_count > 0) {
            echo '<button type="button" class="button button-link-delete delete-unused-btn" ';
            echo 'data-image-id="' . $image_id . '" ';
            echo 'style="border-color: #d63638; color: #d63638;">';
            echo '🗑️ Delete unused occurrences (' . $unused_count . ')';
            echo '</button>';
        }
        
        // Reload Image button
        echo '<button type="button" class="button button-primary reload-image-btn" data-image-id="' . $image_id . '">🔄 Reload Image</button>';
        
        // Delete All button
        echo '<button type="button" class="button button-link-delete delete-all-btn">🗑️ Delete All</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Render resolution info
     */
    private function render_resolution_info($file_path, $image_meta) {
        $current_width = isset($image_meta['width']) ? $image_meta['width'] : 0;
        $current_height = isset($image_meta['height']) ? $image_meta['height'] : 0;
        
        $original_width = isset($image_meta['original_width']) ? $image_meta['original_width'] : $current_width;
        $original_height = isset($image_meta['original_height']) ? $image_meta['original_height'] : $current_height;
        
        $is_scaled = isset($image_meta['original_image']) || ($file_path && strpos(basename($file_path), '-scaled') !== false);
        
        if ($is_scaled && ($current_width !== $original_width || $current_height !== $original_height)) {
            echo '<p class="description"><strong>Scaled Resolution:</strong> ' . $current_width . '×' . $current_height . '</p>';
        }
        
        if ($original_width > 0 && $original_height > 0) {
            echo '<p class="description"><strong>Original Resolution:</strong> ' . $original_width . '×' . $original_height . '</p>';
        } else {
            echo '<p class="description" style="color: #d63638;"><strong>Original Resolution:</strong> Unknown (metadata missing)</p>';
        }
        
        if ($is_scaled && isset($image_meta['original_image'])) {
            echo '<p class="description" style="color: #999; font-size: 11px;"><strong>Original File:</strong> ' . esc_html($image_meta['original_image']) . '</p>';
        }
    }
    
    /**
     * Render missing file row
     */
    private function render_missing_file_row($image_id, $custom_sizes, $image_sources) {
        $image_title = get_the_title($image_id);
        $image_meta = wp_get_attachment_metadata($image_id);
        $file_path = get_attached_file($image_id);
        
        $sources = $image_sources[$image_id] ?? array('unknown');
        $sources_unique = array_unique($sources);
        
        echo '<div class="pim-image-row pim-missing-file-row" data-image-id="' . $image_id . '">';
        
        echo '<div class="pim-image-thumb">';
        echo '<div class="pim-image-thumb-placeholder" style="background: #fef0f0; border: 2px dashed #d63638; width: 150px; height: 150px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #999;">❌<br>File<br>Missing</div>';
        echo '</div>';
        
        echo '<div class="pim-image-info">';
        echo '<h4 style="color: #d63638; margin: 0 0 8px 0;">' . esc_html($image_title ? $image_title : 'Untitled') . '</h4>';
        echo '<p class="description"><strong>ID:</strong> ' . $image_id . '</p>';
        echo '<p class="description"><strong>Source:</strong> ' . implode(', ', $sources_unique) . '</p>';
        
        if (isset($image_meta['width']) && isset($image_meta['height'])) {
            echo '<p class="description"><strong>Expected:</strong> ' . $image_meta['width'] . '×' . $image_meta['height'] . '</p>';
        }
        
        echo '<p class="description" style="color: #d63638; font-weight: 500; word-break: break-all; margin-top: 8px;"><strong>Missing:</strong> ' . esc_html($file_path) . '</p>';
        echo '</div>';
        
        echo '<div class="pim-size-selector" data-image-id="' . $image_id . '">';
        $this->render_size_selector($image_id, $sources_unique, $custom_sizes, null);
        echo '</div>';
        
        echo '<div class="pim-image-actions">';
        echo '<button type="button" class="button button-primary generate-missing-btn" data-image-id="' . $image_id . '">📤 Upload & Generate</button>';
        echo '<button type="button" class="button button-link-delete delete-attachment-btn" data-image-id="' . $image_id . '">🗑️ Delete</button>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * ✅ ISSUE 57 - SCENARIO 4: Delete Missing in Database Item
     * UPDATE class-image-renderer.php → render_missing_db_row()
     */
    private function render_missing_db_row($missing_id, $missing_data) {
        $url = $missing_data['url'];
        $filename = basename(parse_url($url, PHP_URL_PATH));
        
        echo '<div style="border: 1px solid #ddd; border-left: 4px solid #ffc107; margin: 10px 0; background: #fff8e1;">';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr>';
        
        // Thumbnail placeholder
        echo '<td rowspan="4" style="width: 150px; padding: 10px; text-align: center; vertical-align: top;">';
        echo '<div class="pim-image-thumb-placeholder" style="background: #fff3cd; border: 2px dashed #ffc107; width: 130px; height: 130px; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #999;">👻<br>DB Record<br>Missing</div>';
        echo '</td>';
        
        // Header
        echo '<td style="padding: 20px 8px 0px 8px;">';
        echo '<h4 style="color: #dc3232; margin: 0;">Missing Attachment #' . $missing_id . '</h4>';
        echo '</td>';
        
        // ✅ TWO BUTTONS: Create & Delete
        echo '<td rowspan="4" style="width: 200px; padding: 10px; text-align: center; vertical-align: top;">';
        
        // Create button
        echo '<button type="button" class="button button-primary create-attachment-btn" data-url="' . esc_attr($url) . '" style="width: 100%; margin-bottom: 8px;">🔨 Create in Database</button>';
        
        // ✅ NEW: Delete button
        echo '<button type="button" class="button button-link-delete delete-missing-item-btn" data-url="' . esc_attr($url) . '" data-missing-id="' . esc_attr($missing_id) . '" style="width: 100%; color: #d63638;">🗑️ Delete Item</button>';
        
        echo '<p style="font-size: 10px; color: #666; margin-top: 8px;">Delete: Removes from Elementor + disk cleanup</p>';
        echo '</td>';
        echo '</tr>';
        
        // Info rows
        echo '<tr><td style="padding: 0px 8px 0px 8px;"><div style="display: flex; justify-content: space-between;"><span style="font-size: 12px;"><strong>Filename:</strong> ' . esc_html($filename) . '</span></div></td></tr>';
        
        echo '<tr><td style="padding: 0px 8px 0px 8px;"><span style="font-size: 12px;"><strong>Source:</strong> ' . esc_html($missing_data['source']) . '</span></td></tr>';
        
        echo '<tr><td style="padding: 0px 8px 20px 8px; font-size: 13px; color: #d63638; font-weight: 500; word-break: break-all;"><strong>URL:</strong> ' . esc_html($url) . '</td></tr>';
        
        echo '</table></div>';
    }

    /**
     * Size selector with "Non-Standard" radio option
     */
    private function render_size_selector($image_id, $sources, $custom_sizes, $image_meta) {
        foreach ($sources as $source) {
            $preselected_size = $this->size_helper->get_preselected_size(
                $image_id, 
                $source, 
                $image_meta
            );
            
            echo '<div class="pim-source-group">';
            echo '<h5 class="pim-source-name">' . esc_html($source) . ':</h5>';
            echo '<div class="pim-size-radios">';
            
            foreach ($custom_sizes as $size_name => $size_data) {
                $width = $size_data['width'] ?? 0;
                $height = $size_data['height'] ?? 0;
                $checked = ($size_name === $preselected_size) ? ' checked' : '';
                $radio_name = 'size-' . $image_id . '-' . $source;
                
                echo '<label>';
                echo '<input type="radio" class="size-radio" name="' . esc_attr($radio_name) . '" value="' . esc_attr($size_name) . '"' . $checked . ' data-source="' . esc_attr($source) . '">';
                echo '<span class="size-name">' . esc_html($size_name) . '</span> ';
                echo '<span class="size-dimensions">(' . $width . '×' . $height . ')</span>';
                echo '</label>';
            }
            
            echo '<label>';
            echo '<input type="radio" class="size-radio" name="' . esc_attr($radio_name) . '" value="non-standard" data-source="' . esc_attr($source) . '">';
            echo '<span class="size-name">⚠️ Non-Standard</span> ';
            echo '<span class="size-dimensions">(keep as-is)</span>';
            echo '</label>';
            
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * ✅ Render only action buttons (no size selectors, no thumbnail detection)
     */
    public function render_image_actions_only($image_id, $page_id, $duplicates) {
        ob_start();
        
        $has_duplicates = !empty($duplicates);
        $duplicate_ids = array();
        
        if ($has_duplicates) {
            foreach ($duplicates as $dup) {
                $duplicate_ids[] = $dup['missing_id'];
            }
        }
        
        ?>
        <div class="pim-image-actions">
            
            <?php if ($has_duplicates): ?>
                <button type="button" 
                        class="button button-primary link-generate-btn" 
                        data-primary-id="<?php echo esc_attr($image_id); ?>"
                        data-duplicate-ids='<?php echo esc_attr(json_encode($duplicate_ids)); ?>'>
                    🔗 Link &amp; Generate (<?php echo count($duplicate_ids); ?>)
                </button>
            <?php endif; ?>
            
            <button type="button" 
                    class="button button-primary reload-image-btn" 
                    data-image-id="<?php echo esc_attr($image_id); ?>"
                    title="Replace image file and regenerate selected thumbnails">
                🔄 Reload Image
            </button>
            
            <button type="button" class="button button-link-delete delete-all-btn">
                🗑️ Delete All
            </button>
            
        </div>
        <?php
        
        return ob_get_clean();
    }

    /**
     * ✅ Render orphan file row
     */
    private function render_orphan_file_row($orphan) {
        $filename = basename($orphan['file_url']);
        $file_path = $orphan['file_url'];
        $file_size = size_format($orphan['file_size'] ?? 0, 2);
        
        echo '<div style="border: 1px solid #ddd; border-left: 4px solid #999; margin: 10px 0; background: #f9f9f9;">';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<tr>';
        
        // Icon placeholder
        echo '<td rowspan="3" style="width: 80px; padding: 10px; text-align: center; vertical-align: top;">';
        echo '<div style="width: 60px; height: 60px; background: #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📄</div>';
        echo '</td>';
        
        // Header
        echo '<td style="padding: 15px 8px 0px 8px;">';
        echo '<h4 style="color: #666; margin: 0;">Orphan File</h4>';
        echo '</td>';
        
        // Delete button
        echo '<td rowspan="3" style="width: 150px; padding: 10px; text-align: center; vertical-align: top;">';
        echo '<button type="button" class="button button-link-delete delete-orphan-btn" data-file-path="' . esc_attr($file_path) . '" style="width: 100%; color: #d63638;">🗑️ Delete</button>';
        echo '<p style="font-size: 10px; color: #999; margin-top: 5px;">Not used anywhere</p>';
        echo '</td>';
        echo '</tr>';
        
        // Filename
        echo '<tr><td style="padding: 0px 8px;"><span style="font-size: 12px;"><strong>File:</strong> ' . esc_html($filename) . '</span></td></tr>';
        
        // File size
        echo '<tr><td style="padding: 0px 8px 15px 8px;"><span style="font-size: 11px; color: #666;"><strong>Size:</strong> ' . esc_html($file_size) . '</span></td></tr>';
        
        echo '</table></div>';
    }
}