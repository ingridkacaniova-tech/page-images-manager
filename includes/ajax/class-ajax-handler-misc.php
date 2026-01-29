<?php
error_log("🟢 class-ajax-handler-misc.php LOADED (TODO 50/51)");

if (!defined('ABSPATH')) exit;

class PIM_Ajax_Handler_Misc {
    private $generator;
    private $file_manager;
    private $duplicate_handler;
    private $lock_manager;

    public function __construct() {
        $this->generator = new PIM_Thumbnail_Generator();
        $this->file_manager = new PIM_Thumbnail_File_Manager();
        $this->duplicate_handler = new PIM_Duplicate_Handler();
        $this->lock_manager = new PIM_Lock_Manager();
    }

    /**
     * Register AJAX hooks
     */
    public function register_hooks() {
        add_action('wp_ajax_create_attachment_from_url', array($this, 'create_attachment_from_url'));
        add_action('wp_ajax_upload_and_create_attachment', array($this, 'upload_and_create_attachment'));
        add_action('wp_ajax_link_and_generate', array($this, 'link_and_generate'));
        add_action('wp_ajax_fix_elementor_image_id', array($this, 'fix_elementor_image_id'));
        add_action('wp_ajax_lock_thumbnail_size', array($this, 'lock_thumbnail_size'));
        add_action('wp_ajax_unlock_thumbnail_size', array($this, 'unlock_thumbnail_size'));
        add_action('wp_ajax_get_lock_status', array($this, 'get_lock_status'));
        
        // ✅ TODO 50/51: New endpoints
        add_action('wp_ajax_find_ghost_duplicates', array($this, 'find_ghost_duplicates'));
        add_action('wp_ajax_get_duplicate_details', array($this, 'get_duplicate_details'));
        add_action('wp_ajax_delete_ghost_duplicates', array($this, 'delete_ghost_duplicates'));
        add_action('wp_ajax_save_custom_source', array($this, 'save_custom_source'));
        add_action('wp_ajax_get_custom_sources', array($this, 'get_custom_sources'));
        add_action('wp_ajax_get_ghost_files', array($this, 'get_ghost_files'));

        add_action('wp_ajax_delete_missing_item', array($this, 'delete_missing_item'));
        add_action('wp_ajax_delete_orphan_file', array($this, 'delete_orphan_file'));

        // ✅ NEW: Scan all pages functionality
        add_action('wp_ajax_get_total_pages', array($this, 'get_total_pages'));
        add_action('wp_ajax_collect_base_images_data_from_all_pages', array($this, 'collect_base_images_data_from_all_pages'));
        add_action('wp_ajax_export_image_list', array($this, 'export_image_list'));
        add_action('wp_ajax_get_latest_scan', array($this, 'get_latest_scan'));
        
        // ✅ ISSUE -1: Repair Elementor URLs
        add_action('wp_ajax_repair_elementor_urls', array($this, 'repair_elementor_urls'));
        
        // ✅ DEBUG: Export Elementor JSON
        add_action('wp_ajax_export_elementor_json', array($this, 'export_elementor_json'));
    }

    /**
     * ✅ TODO 50A: Find ghost duplicates
     */
    public function find_ghost_duplicates() {
        PIM_Debug_Logger::log_session_start('find_ghost_duplicates');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $primary_id = isset($_POST['primary_id']) ? intval($_POST['primary_id']) : 0;
        $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode(stripslashes($_POST['duplicate_ids']), true) : array();
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$primary_id || empty($duplicate_ids) || !$page_id) {
            wp_send_json_error('Missing required data');
        }
        
        $ghosts = $this->duplicate_handler->find_ghost_duplicates($primary_id, $duplicate_ids, $page_id);
        
        wp_send_json_success(array(
            'ghosts' => $ghosts,
            'count' => count($ghosts)
        ));
    }
    
    /**
     * ✅ TODO 50B: Get duplicate details
     */
    public function get_duplicate_details() {
        PIM_Debug_Logger::log_session_start('get_duplicate_details');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode(stripslashes($_POST['duplicate_ids']), true) : array();
        $primary_id = isset($_POST['primary_id']) ? intval($_POST['primary_id']) : 0;  // ✅ PRIDANÉ
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;            // ✅ PRIDANÉ
        
        if (empty($duplicate_ids)) {
            wp_send_json_error('Missing duplicate IDs');
        }
        
        $details = array();
        foreach ($duplicate_ids as $dup_id) {
            $details[] = $this->duplicate_handler->get_duplicate_details($dup_id, $primary_id, $page_id);  // ✅ PRIDANÉ PARAMETRE
        }
        
        wp_send_json_success(array(
            'details' => $details
        ));
    }
    
    /**
     * ✅ TODO 50A: Delete ghost duplicates
     */
    public function delete_ghost_duplicates() {
        $session_id = PIM_Debug_Logger::log_session_start('delete_ghost_duplicates');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $ghost_ids = isset($_POST['ghost_ids']) ? json_decode(stripslashes($_POST['ghost_ids']), true) : array();
        
        if (empty($ghost_ids)) {
            wp_send_json_error('Missing ghost IDs');
        }
        
        $result = $this->duplicate_handler->delete_ghost_duplicates($ghost_ids);
        
        // ✅ Pridaj session_id do response pre JS
        $result['session_id'] = $session_id;
        
        $result['session_id'] = $session_id;
        wp_send_json_success($result);
    }
 
    /**
     * ✅ TODO 51: Save custom source
     */
    public function save_custom_source() {
        PIM_Debug_Logger::log_session_start('save_custom_source');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $source_name = isset($_POST['source_name']) ? sanitize_text_field($_POST['source_name']) : '';
        
        if (empty($source_name)) {
            wp_send_json_error('Missing source name');
        }
        
        $result = $this->duplicate_handler->save_custom_source($source_name);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Custom source saved',
                'source_name' => $source_name
            ));
        } else {
            wp_send_json_error('Failed to save custom source');
        }
    }
    
    /**
     * ✅ TODO 51: Get custom sources
     */
    public function get_custom_sources() {
        PIM_Debug_Logger::log_session_start('get_custom_sources');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $custom_sources = $this->duplicate_handler->get_custom_sources();
        
        wp_send_json_success(array(
            'custom_sources' => $custom_sources
        ));
    }
    
    /**
     * ✅ TODO 50B: Get ghost files
     */
    public function get_ghost_files() {
        PIM_Debug_Logger::log_session_start('get_ghost_files');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $primary_id = isset($_POST['primary_id']) ? intval($_POST['primary_id']) : 0;
        $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode(stripslashes($_POST['duplicate_ids']), true) : array();
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$primary_id || !$page_id) {
            wp_send_json_error('Missing required data');
        }
        
        $ghost_files = $this->duplicate_handler->get_ghost_files($primary_id, $duplicate_ids, $page_id);
        
        wp_send_json_success(array(
            'ghost_files' => $ghost_files,
            'count' => count($ghost_files)
        ));
    }

    /**
     * ✅ ISSUE 57 - SCENARIO 2A: File does not exist
     * ✅ ISSUE 57 - SCENARIO 2B: Filename mismatch + orphan cleanup
     */
    public function create_attachment_from_url() {
        $session_id = PIM_Debug_Logger::log_session_start('create_attachment_from_url');
        
        PIM_Debug_Logger::enter('create_attachment_from_url', array(
            'has_image_url' => isset($_POST['image_url']),
            'has_page_id' => isset($_POST['page_id'])
        ));
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_url = isset($_POST['image_url']) ? sanitize_text_field($_POST['image_url']) : '';
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_url || !$page_id) {
            PIM_Debug_Logger::error('Missing required data');
            PIM_Debug_Logger::exit_function('create_attachment_from_url');
            wp_send_json_error('Missing required data');
        }

        PIM_Debug_Logger::log('Processing Missing in Database item', array(
            'url' => $image_url,
            'page_id' => $page_id
        ));

        // ✅ Convert URL to file path
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        
        PIM_Debug_Logger::file_op('Checking if file exists', $file_path);
        
        // ✅✅✅ SCENARIO 2B: FILE MISMATCH - Check for orphans ✅✅✅
        if (!file_exists($file_path)) {
            PIM_Debug_Logger::warning('Exact file does not exist - checking for orphans');
            
            $filename = basename($file_path);
            $base_name = $this->get_base_filename($filename);
            $dir = dirname($file_path);
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            
            // Find all files with same base name
            $pattern = $dir . '/' . $base_name . '*.' . $extension;
            $found_files = glob($pattern);
            
            PIM_Debug_Logger::log('Found files with base name', array(
                'pattern' => $pattern,
                'count' => count($found_files),
                'files' => array_map('basename', $found_files)
            ));
            
            if (!empty($found_files)) {
                // ✅ Check each file - is it an orphan?
                $orphans_deleted = $this->cleanup_orphan_files($found_files, $base_name);
                
                PIM_Debug_Logger::log('Orphan cleanup complete', array(
                    'deleted' => $orphans_deleted
                ));
            }
            
            // ✅ After cleanup, exact file STILL doesn't exist → SCENARIO 1
            if (!file_exists($file_path)) {
                PIM_Debug_Logger::warning('File still missing after orphan cleanup - SCENARIO 1');
                
                $attachment_id = $this->create_empty_attachment($image_url, $page_id);
                
                // ... (Scenario 1 logic)
                
                wp_send_json_success(array(
                    'attachment_id' => $attachment_id,
                    'mode' => 'empty_attachment',
                    'orphans_deleted' => $orphans_deleted,
                    'message' => sprintf(
                        'Created empty attachment #%d. Deleted %d orphan file(s).',
                        $attachment_id,
                        $orphans_deleted
                    )
                ));
            }
        }
        // ✅✅✅ SCENARIO 2A: File does not exist ✅✅✅
        if (!file_exists($file_path)) {
            PIM_Debug_Logger::warning('File does not exist - SCENARIO 2A: Creating empty attachment');
            
            $attachment_id = $this->create_empty_attachment($image_url, $page_id);
            
            if (is_wp_error($attachment_id)) {
                PIM_Debug_Logger::error('Failed to create empty attachment', $attachment_id->get_error_message());
                PIM_Debug_Logger::exit_function('create_attachment_from_url');
                wp_send_json_error($attachment_id->get_error_message());
            }
            
            // ✅ Update Elementor JSON
            $this->update_elementor_url_to_id($page_id, $image_url, $attachment_id);
            
            PIM_Debug_Logger::exit_function('create_attachment_from_url', array(
                'mode' => 'empty_attachment',
                'attachment_id' => $attachment_id
            ));
            
            wp_send_json_success(array(
                'attachment_id' => $attachment_id,
                'url' => '',  // No URL (file missing)
                'mode' => 'empty_attachment',
                'message' => sprintf(
                    'Created empty attachment #%d. File missing - will appear in Missing Files section.',
                    $attachment_id
                )
            ));
        }
        
        // ✅✅✅ SCENARIO 2A: FILE EXISTS ✅✅✅
        PIM_Debug_Logger::success('File exists on server - SCENARIO 2A');
        
        $filename = basename($file_path);
        
        // Check for consolidation...
        $base_name = $this->get_base_filename($filename);
        $existing_attachment = $this->find_attachment_by_base_name($base_name);
        
        if ($existing_attachment) {
            // ✅ CONSOLIDATION MODE
            PIM_Debug_Logger::success('Found existing attachment for consolidation', array(
                'existing_id' => $existing_attachment->ID,
                'existing_file' => basename($existing_attachment->file_path)
            ));
            
            $attachment_id = intval($existing_attachment->ID);
            $result = $this->add_thumbnail_to_existing_attachment(
                $attachment_id, 
                $file_path,
                $filename,
                $image_url,
                $page_id
            );
            
            PIM_Debug_Logger::exit_function('create_attachment_from_url', array(
                'mode' => 'consolidated',
                'attachment_id' => $attachment_id
            ));
            
            wp_send_json_success($result);
            
        } else {
            // ✅ NORMAL MODE - Create new attachment
            PIM_Debug_Logger::log('No existing attachment found, creating new one');
            
            $attachment_id = $this->create_new_attachment_from_file($file_path, $page_id);
            
            if (is_wp_error($attachment_id)) {
                PIM_Debug_Logger::error('Failed to create attachment', $attachment_id->get_error_message());
                PIM_Debug_Logger::exit_function('create_attachment_from_url');
                wp_send_json_error($attachment_id->get_error_message());
            }
            
            // ✅ Update Elementor JSON
            $this->update_elementor_url_to_id($page_id, $image_url, $attachment_id);
            
            PIM_Debug_Logger::exit_function('create_attachment_from_url', array(
                'mode' => 'new',
                'attachment_id' => $attachment_id
            ));
            
            wp_send_json_success(array(
                'attachment_id' => $attachment_id,
                'url' => wp_get_attachment_url($attachment_id),
                'mode' => 'new'
            ));
        }
    }

    /**
     * ✅ Extract base filename (remove dimensions, -scaled, etc.)
     */
    private function get_base_filename($filename) {
        // Remove extension
        $name = preg_replace('/\.(jpg|jpeg|png|gif|webp)$/i', '', $filename);
        
        // Remove -scaled
        $name = preg_replace('/-scaled$/', '', $name);
        
        // Remove dimensions (-250x0, -1920x1080, etc.)
        $name = preg_replace('/-\d+x\d+$/', '', $name);
        
        // Remove trailing numbers (-1, -2, etc.)
        $name = preg_replace('/-\d+$/', '', $name);
        
        return $name;
    }

    /**
     * ✅ Find existing attachment by base filename
     */
    private function find_attachment_by_base_name($base_name) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT p.ID, pm.meta_value AS file_path
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_wp_attached_file'
            AND pm.meta_value LIKE %s
            ORDER BY p.ID DESC
            LIMIT 1
        ", '%' . $wpdb->esc_like($base_name) . '%');
        
        return $wpdb->get_row($query);
    }

    /**
     * ✅ Add thumbnail to existing attachment (CONSOLIDATION)
     */
    private function add_thumbnail_to_existing_attachment($attachment_id, $file_path, $filename, $image_url, $page_id) {
        PIM_Debug_Logger::enter('add_thumbnail_to_existing_attachment', array(
            'attachment_id' => $attachment_id,
            'file' => $filename
        ));
        
        // Get current metadata
        wp_cache_delete($attachment_id, 'post_meta');
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!$metadata) {
            $metadata = array('sizes' => array());
        }
        if (!isset($metadata['sizes'])) {
            $metadata['sizes'] = array();
        }
        
        // Get image dimensions
        $image_size = @getimagesize($file_path);
        
        if (!$image_size) {
            PIM_Debug_Logger::error('Could not get image dimensions');
            wp_send_json_error('Could not get image dimensions');
        }
        
        $width = $image_size[0];
        $height = $image_size[1];
        
        PIM_Debug_Logger::log('Image dimensions', array('width' => $width, 'height' => $height));
        
        // ✅ Try to match dimensions to existing size
        $matched_size = $this->match_dimensions_to_size($width, $height);
        
        if ($matched_size) {
            $size_name = $matched_size;
            PIM_Debug_Logger::success('Matched to standard size', array('size' => $size_name));
        } else {
            $size_name = 'non-standard-' . $width . 'x' . $height;
            PIM_Debug_Logger::log('No match, using non-standard', array('size' => $size_name));
        }
        
        // Add to metadata
        $metadata['sizes'][$size_name] = array(
            'file' => $filename,
            'width' => $width,
            'height' => $height,
            'mime-type' => $image_size['mime']
        );
        
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        PIM_Debug_Logger::success('Added thumbnail to metadata', array('size_name' => $size_name));
        
        // ✅ Add "RECOVERED - FILL IN" source
        $this->add_supplemental_source($attachment_id, 'RECOVERED - FILL IN');
        
        // ✅ Update Elementor JSON
        $this->update_elementor_url_to_id($page_id, $image_url, $attachment_id);
        
        PIM_Debug_Logger::exit_function('add_thumbnail_to_existing_attachment', array(
            'success' => true,
            'size_added' => $size_name
        ));
        
        return array(
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
            'mode' => 'consolidated',
            'size_added' => $size_name,
            'matched_size' => $matched_size,
            'message' => sprintf(
                'Consolidated into existing attachment #%d, added size: %s',
                $attachment_id,
                $size_name
            )
        );
    }

    /**
     * ✅ Match dimensions to existing registered size
     */
    private function match_dimensions_to_size($width, $height) {
        $all_sizes = wp_get_registered_image_subsizes();
        
        foreach ($all_sizes as $size_name => $size_data) {
            $size_width = $size_data['width'] ?? 0;
            $size_height = $size_data['height'] ?? 0;
            
            // Exact match
            if ($width == $size_width && $height == $size_height) {
                return $size_name;
            }
            
            // Match with height = 0 (proportional width)
            if ($size_height == 0 && $width == $size_width) {
                return $size_name;
            }
        }
        
        return null;
    }

    /**
     * ✅ Add supplemental source (RECOVERED - FILL IN)
     */
    private function add_supplemental_source($attachment_id, $source_name) {
        $supplemental_sources = get_post_meta($attachment_id, '_pim_supplemental_sources', true);
        
        if (!is_array($supplemental_sources)) {
            $supplemental_sources = array();
        }
        
        if (!in_array($source_name, $supplemental_sources)) {
            $supplemental_sources[] = $source_name;
            update_post_meta($attachment_id, '_pim_supplemental_sources', $supplemental_sources);
            
            error_log("✅ Added supplemental source '{$source_name}' to attachment #{$attachment_id}");
        }
    }

    /**
     * ✅ Create new attachment from existing file (NORMAL MODE)
     */
    private function create_new_attachment_from_file($file_path, $page_id) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        
        $filename = basename($file_path);
        $filetype = wp_check_filetype($filename, null);
        
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $page_id
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_path, $page_id);
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // ✅ Generate basic metadata (NO thumbnails)
        $image_size = @getimagesize($file_path);
        
        if ($image_size) {
            $upload_dir = wp_upload_dir();
            $metadata = array(
                'width' => $image_size[0],
                'height' => $image_size[1],
                'file' => str_replace($upload_dir['basedir'] . '/', '', $file_path),
                'sizes' => array(),  // ✅ Empty - no thumbnails
                'image_meta' => array(
                    'aperture' => '0',
                    'credit' => '',
                    'camera' => '',
                    'caption' => '',
                    'created_timestamp' => '0',
                    'copyright' => '',
                    'focal_length' => '0',
                    'iso' => '0',
                    'shutter_speed' => '0',
                    'title' => '',
                    'orientation' => '0',
                    'keywords' => array()
                )
            );
            
            wp_update_attachment_metadata($attachment_id, $metadata);
            
            error_log("✅ Created attachment #{$attachment_id} with basic metadata (no thumbnails)");
        }
        
        return $attachment_id;
    }

    /**
     * ✅ Update Elementor JSON - replace URL/ID with new attachment ID
     */
    private function update_elementor_url_to_id($page_id, $old_url, $new_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return false;
        }
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return false;
        }
        
        // Recursively find and replace
        $replaced = $this->replace_url_with_id_recursive($data, $old_url, $new_id);
        
        if ($replaced) {
            update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data)));
            
            if (class_exists('\\Elementor\\Plugin')) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }
            
            error_log("✅ Elementor updated: {$old_url} → ID {$new_id}");
            return true;
        }
        
        return false;
    }

    /**
     * ✅ Recursive URL → ID replacement in Elementor JSON
     */
    private function replace_url_with_id_recursive(&$data, $url, $new_id) {
        $replaced = false;
        
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                // Check if this element has the URL
                if (isset($value['url']) && $value['url'] === $url) {
                    $old_id = isset($value['id']) ? $value['id'] : 'none';
                    $value['id'] = $new_id;
                    $replaced = true;
                    error_log("   ✅ Replaced ID {$old_id} → {$new_id} for URL");
                }
                
                // Recurse
                if ($this->replace_url_with_id_recursive($value, $url, $new_id)) {
                    $replaced = true;
                }
            }
        }
        
        return $replaced;
    }

    public function upload_and_create_attachment() {
        PIM_Debug_Logger::log_session_start('upload_and_create_attachment');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        if (!isset($_FILES['file'])) {
            wp_send_json_error('No file uploaded');
        }

        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$page_id) {
            wp_send_json_error('Invalid page ID');
        }

        $result = $this->file_manager->upload_and_create_attachment($_FILES['file'], $page_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
    }

    public function link_and_generate() {
        PIM_Debug_Logger::log_session_start('link_and_generate');

        error_log("\n🔗🔗🔗 AJAX HANDLER CALLED - link_and_generate() 🔗🔗🔗");
        
        PIM_Debug_Logger::enter('link_and_generate', array(
            'POST_action' => $_POST['action'] ?? 'missing',
            'has_primary_id' => isset($_POST['primary_id']),
            'has_duplicate_ids' => isset($_POST['duplicate_ids']),
            'has_source_mappings' => isset($_POST['source_mappings']),
            'has_page_id' => isset($_POST['page_id'])
        ));

        check_ajax_referer('page_images_manager', 'nonce');
        PIM_Debug_Logger::success('Nonce verified');
        
        $primary_id = isset($_POST['primary_id']) ? intval($_POST['primary_id']) : 0;
        $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode(stripslashes($_POST['duplicate_ids']), true) : array();
        $source_mappings = isset($_POST['source_mappings']) ? json_decode(stripslashes($_POST['source_mappings']), true) : array();
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        PIM_Debug_Logger::log('Parsed inputs', array(
            'primary_id' => $primary_id,
            'duplicate_ids' => $duplicate_ids,
            'source_mappings' => $source_mappings,
            'page_id' => $page_id
        ));
        
        if (!$primary_id || empty($duplicate_ids) || !$page_id) {
            PIM_Debug_Logger::error('Validation failed - missing required data');
            PIM_Debug_Logger::exit_function('link_and_generate');
            wp_send_json_error('Missing required data');
        }
        
        if (empty($source_mappings)) {
            PIM_Debug_Logger::error('Validation failed - empty source_mappings');
            PIM_Debug_Logger::exit_function('link_and_generate');
            wp_send_json_error('No source mappings provided');
        }

        PIM_Debug_Logger::success('All inputs validated');
        PIM_Debug_Logger::log('Calling duplicate_handler->link_and_generate()');
        
        $result = $this->duplicate_handler->link_and_generate(
            $primary_id,
            $duplicate_ids,
            $source_mappings,
            $page_id
        );
        
        if (is_wp_error($result)) {
            PIM_Debug_Logger::error('link_and_generate failed', $result->get_error_message());
            PIM_Debug_Logger::exit_function('link_and_generate');
            wp_send_json_error($result->get_error_message());
        }

        PIM_Debug_Logger::exit_function('link_and_generate', array(
            'success' => true,
            'merged_count' => $result['merged_count'] ?? 0,
            'generated_sizes' => $result['generated_sizes'] ?? []
        ));
        
        wp_send_json_success($result);
    }

    public function fix_elementor_image_id() {
        PIM_Debug_Logger::log_session_start('fix_elementor_image_id');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_id || !$page_id) {
            wp_send_json_error('Missing required data');
        }

        $image_url = wp_get_attachment_url($image_id);
        if (!$image_url) {
            wp_send_json_error('Could not get image URL');
        }

        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            wp_send_json_error('No Elementor data found');
        }

        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            wp_send_json_error('Invalid Elementor data');
        }

        $fixed_count = $this->fix_image_ids_recursive($data, $image_url, $image_id);

        if ($fixed_count > 0) {
            update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data)));
            
            if (class_exists('\\Elementor\\Plugin')) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            }

            wp_send_json_success(array(
                'message' => sprintf('Fixed %d occurrence(s) of image #%d', $fixed_count, $image_id)
            ));
        } else {
            wp_send_json_error('Image URL not found in Elementor data');
        }
    }

    private function fix_image_ids_recursive(&$data, $url, $correct_id) {
        $count = 0;
        
        if (!is_array($data)) {
            return $count;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                if (isset($value['url']) && $value['url'] === $url) {
                    if (!isset($value['id']) || $value['id'] != $correct_id) {
                        $value['id'] = $correct_id;
                        $count++;
                    }
                }
                $count += $this->fix_image_ids_recursive($value, $url, $correct_id);
            }
        }

        return $count;
    }

    public function lock_thumbnail_size() {
        PIM_Debug_Logger::log_session_start('lock_thumbnail_size');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $size_name = isset($_POST['size_name']) ? sanitize_text_field($_POST['size_name']) : '';
        
        if (!$image_id || !$page_id || !$size_name) {
            wp_send_json_error('Missing required data');
        }

        $result = $this->lock_manager->lock_size($image_id, $page_id, $size_name);

        if ($result) {
            $page = get_post($page_id);
            $page_title = $page ? $page->post_title : 'Page #' . $page_id;
            wp_send_json_success(array(
                'message' => sprintf('🔒 Locked %s for %s', $size_name, $page_title)
            ));
        } else {
            wp_send_json_error('Failed to lock size');
        }
    }

    public function unlock_thumbnail_size() {
        PIM_Debug_Logger::log_session_start('unlock_thumbnail_size');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $size_name = isset($_POST['size_name']) ? sanitize_text_field($_POST['size_name']) : '';
        
        if (!$image_id || !$page_id || !$size_name) {
            wp_send_json_error('Missing required data');
        }

        $result = $this->lock_manager->unlock_size($image_id, $page_id, $size_name);

        if ($result) {
            $page = get_post($page_id);
            $page_title = $page ? $page->post_title : 'Page #' . $page_id;
            wp_send_json_success(array(
                'message' => sprintf('🔓 Unlocked %s for %s', $size_name, $page_title)
            ));
        } else {
            wp_send_json_error('Failed to unlock size');
        }
    }

    public function get_lock_status() {
        PIM_Debug_Logger::log_session_start('get_lock_status');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_id || !$page_id) {
            wp_send_json_error('Missing required data');
        }

        $locked_sizes = $this->lock_manager->get_page_lock_status($image_id, $page_id);

        wp_send_json_success(array(
            'locked_sizes' => $locked_sizes,
            'is_locked' => !empty($locked_sizes)
        ));
    }

    /**
     * ✅ Delete Missing in Database item (Elementor cleanup + disk cleanup)
     */
    public function delete_missing_item() {
        $session_id = PIM_Debug_Logger::log_session_start('delete_missing_item');
        
        PIM_Debug_Logger::enter('delete_missing_item', array(
            'has_image_url' => isset($_POST['image_url']),
            'has_page_id' => isset($_POST['page_id'])
        ));
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_url = isset($_POST['image_url']) ? sanitize_text_field($_POST['image_url']) : '';
        $missing_id = isset($_POST['missing_id']) ? intval($_POST['missing_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_url || !$page_id) {
            PIM_Debug_Logger::error('Missing required data');
            PIM_Debug_Logger::exit_function('delete_missing_item');
            wp_send_json_error('Missing required data');
        }
        
        PIM_Debug_Logger::log('Deleting missing item', array(
            'url' => $image_url,
            'missing_id' => $missing_id,
            'page_id' => $page_id
        ));
        
        $file_deleted = false;
        $elementor_updated = false;
        
        // ✅ STEP 1: Delete file from disk (if exists)
        $upload_dir = wp_upload_dir();
        $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        
        if (file_exists($file_path)) {
            PIM_Debug_Logger::log('File exists, attempting deletion', array('path' => $file_path));
            
            if (@unlink($file_path)) {
                $file_deleted = true;
                PIM_Debug_Logger::success('File deleted from disk', array('file' => basename($file_path)));
            } else {
                PIM_Debug_Logger::warning('Failed to delete file', array('file' => basename($file_path)));
            }
        } else {
            PIM_Debug_Logger::log('File does not exist on disk (already missing)');
        }
        
        // ✅ STEP 2: Remove from Elementor JSON
        PIM_Debug_Logger::log('Removing from Elementor data...');
        $elementor_updated = $this->remove_image_from_elementor($page_id, $image_url, $missing_id);
        
        if ($elementor_updated) {
            PIM_Debug_Logger::success('Removed from Elementor data');
        } else {
            PIM_Debug_Logger::warning('Not found in Elementor data (or already removed)');
        }
        
        // ✅ STEP 3: Clear Elementor cache
        if (class_exists('\\Elementor\\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
            PIM_Debug_Logger::log('Elementor cache cleared');
        }
        
        PIM_Debug_Logger::exit_function('delete_missing_item', array(
            'file_deleted' => $file_deleted,
            'elementor_updated' => $elementor_updated
        ));
        
        wp_send_json_success(array(
            'file_deleted' => $file_deleted,
            'elementor_updated' => $elementor_updated,
            'message' => sprintf(
                'Missing item deleted. %s %s',
                $file_deleted ? 'File removed.' : 'File was already missing.',
                $elementor_updated ? 'Elementor updated.' : 'Not found in Elementor.'
            )
        ));
    }

    /**
     * ✅ Remove image from Elementor JSON by URL or ID
     */
    private function remove_image_from_elementor($page_id, $image_url, $missing_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            return false;
        }
        
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            return false;
        }
        
        // ✅ Recursively find and remove
        $removed = $this->remove_image_recursive($data, $image_url, $missing_id);
        
        if ($removed) {
            update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data)));
            error_log("✅ Removed image from Elementor: URL={$image_url}, ID={$missing_id}");
            return true;
        }
        
        return false;
    }

    /**
     * ✅ Recursive removal from Elementor JSON
     */
    private function remove_image_recursive(&$data, $url, $missing_id) {
        $removed = false;
        
        if (!is_array($data)) {
            return false;
        }
        
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                // ✅ Check if this is an image element matching our criteria
                $is_match = false;
                
                // Match by URL
                if (isset($value['url']) && $value['url'] === $url) {
                    $is_match = true;
                }
                
                // Match by ID (if provided)
                if ($missing_id > 0 && isset($value['id']) && intval($value['id']) === $missing_id) {
                    $is_match = true;
                }
                
                if ($is_match) {
                    // ✅ Found it! Remove this element
                    // Instead of removing, set to empty array (preserves structure)
                    $value = array();
                    $removed = true;
                    error_log("   ✅ Removed image element from Elementor");
                    continue;
                }
                
                // ✅ Special handling for galleries/carousels (array of images)
                if (in_array($key, array('carousel', 'gallery', 'wp_gallery'))) {
                    foreach ($value as $idx => $item) {
                        if (is_array($item)) {
                            $item_match = false;
                            
                            if (isset($item['url']) && $item['url'] === $url) {
                                $item_match = true;
                            }
                            
                            if ($missing_id > 0 && isset($item['id']) && intval($item['id']) === $missing_id) {
                                $item_match = true;
                            }
                            
                            if ($item_match) {
                                unset($value[$idx]);
                                $removed = true;
                                error_log("   ✅ Removed from {$key} array");
                            }
                        }
                    }
                    
                    // Re-index array after removal
                    if ($removed) {
                        $value = array_values($value);
                    }
                }
                
                // ✅ Recurse deeper
                if ($this->remove_image_recursive($value, $url, $missing_id)) {
                    $removed = true;
                }
            }
        }
        
        return $removed;
    }

    /**
     * ✅ Delete orphan file from disk
     */
    public function delete_orphan_file() {
        $session_id = PIM_Debug_Logger::log_session_start('delete_orphan_file');
        
        PIM_Debug_Logger::enter('delete_orphan_file', array(
            'has_file_path' => isset($_POST['file_path'])
        ));
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        
        if (!$file_path) {
            PIM_Debug_Logger::error('Missing file path');
            PIM_Debug_Logger::exit_function('delete_orphan_file');
            wp_send_json_error('Missing file path');
        }
        
        PIM_Debug_Logger::log('Attempting to delete orphan', array('path' => $file_path));
        
        // ✅ Security: Verify file is in uploads directory
        $upload_dir = wp_upload_dir();
        if (strpos($file_path, $upload_dir['basedir']) !== 0) {
            PIM_Debug_Logger::error('Security: File not in uploads directory');
            PIM_Debug_Logger::exit_function('delete_orphan_file');
            wp_send_json_error('Security error: File not in uploads directory');
        }
        
        // ✅ Verify file exists
        if (!file_exists($file_path)) {
            PIM_Debug_Logger::warning('File does not exist');
            PIM_Debug_Logger::exit_function('delete_orphan_file');
            wp_send_json_error('File does not exist');
        }
        
        // ✅ Double-check it's really an orphan (not in any attachment metadata)
        if ($this->is_file_in_use($file_path)) {
            PIM_Debug_Logger::error('File is in use - cannot delete');
            PIM_Debug_Logger::exit_function('delete_orphan_file');
            wp_send_json_error('File is in use and cannot be deleted');
        }
        
        // ✅ Delete file
        if (@unlink($file_path)) {
            PIM_Debug_Logger::success('Orphan file deleted', array('file' => basename($file_path)));
            
            PIM_Debug_Logger::exit_function('delete_orphan_file', array('success' => true));
            
            wp_send_json_success(array(
                'message' => 'Orphan file deleted: ' . basename($file_path)
            ));
        } else {
            PIM_Debug_Logger::error('Failed to delete file');
            PIM_Debug_Logger::exit_function('delete_orphan_file');
            wp_send_json_error('Failed to delete file');
        }
    }

    /**
     * ✅ Check if file is used in any attachment metadata
     */
    private function is_file_in_use($file_path) {
        global $wpdb;
        
        $filename = basename($file_path);
        
        // Check main files
        $query = $wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_wp_attached_file'
            AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like($filename) . '%');
        
        $main_file_count = $wpdb->get_var($query);
        
        if ($main_file_count > 0) {
            return true;
        }
        
        // Check thumbnail files in metadata
        $query = $wpdb->prepare("
            SELECT meta_value FROM {$wpdb->postmeta}
            WHERE meta_key = '_wp_attachment_metadata'
            AND meta_value LIKE %s
        ", '%' . $wpdb->esc_like($filename) . '%');
        
        $metadata_entries = $wpdb->get_results($query);
        
        foreach ($metadata_entries as $entry) {
            $metadata = maybe_unserialize($entry->meta_value);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_data) {
                    if (isset($size_data['file']) && $size_data['file'] === $filename) {
                        return true;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * ✅ Create empty attachment (file missing)
     */
    private function create_empty_attachment($image_url, $page_id) {
        PIM_Debug_Logger::enter('create_empty_attachment', array(
            'url' => $image_url,
            'page_id' => $page_id
        ));
        
        // Extract filename from URL
        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        $filetype = wp_check_filetype($filename, null);
        
        if (!$filetype['type']) {
            $filetype['type'] = 'image/jpeg';  // Default
        }
        
        PIM_Debug_Logger::log('Creating empty attachment', array(
            'filename' => $filename,
            'mime_type' => $filetype['type']
        ));
        
        // ✅ Create attachment WITHOUT file
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $page_id,
            'guid' => $image_url  // ✅ Use URL as GUID (no file exists)
        );
        
        // ✅ Insert WITHOUT file path (2nd parameter = empty)
        $attachment_id = wp_insert_attachment($attachment, '', $page_id);
        
        if (is_wp_error($attachment_id)) {
            PIM_Debug_Logger::error('wp_insert_attachment failed', $attachment_id->get_error_message());
            return $attachment_id;
        }
        
        PIM_Debug_Logger::success('Empty attachment created', array('id' => $attachment_id));
        
        // ✅ Set EMPTY metadata (no file, no dimensions)
        $metadata = array(
            'file' => '',  // Empty - no file
            'width' => 0,
            'height' => 0,
            'sizes' => array(),
            'image_meta' => array()
        );
        
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        PIM_Debug_Logger::log('Set empty metadata');
        
        // ✅ Store original URL for reference
        update_post_meta($attachment_id, '_pim_original_url', $image_url);
        update_post_meta($attachment_id, '_pim_file_missing', true);
        
        PIM_Debug_Logger::success('Marked as file missing');
        
        PIM_Debug_Logger::exit_function('create_empty_attachment', array(
            'attachment_id' => $attachment_id
        ));
        
        return $attachment_id;
    }

    /**
     * ✅ Cleanup orphan files (not used in any attachment metadata)
     */
    private function cleanup_orphan_files($files, $base_name) {
        global $wpdb;
        
        PIM_Debug_Logger::enter('cleanup_orphan_files', array(
            'files_count' => count($files),
            'base_name' => $base_name
        ));
        
        $deleted_count = 0;
        
        // ✅ Get ALL attachments with this base name
        $query = $wpdb->prepare("
            SELECT p.ID, pm.meta_value AS metadata
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_metadata'
            WHERE p.post_type = 'attachment'
            AND p.guid LIKE %s
        ", '%' . $wpdb->esc_like($base_name) . '%');
        
        $attachments = $wpdb->get_results($query);
        
        PIM_Debug_Logger::log('Found attachments with base name', array(
            'count' => count($attachments)
        ));
        
        // ✅ Build list of files that ARE used
        $used_files = array();
        
        foreach ($attachments as $att) {
            // Main file
            $main_file = get_attached_file($att->ID);
            if ($main_file) {
                $used_files[] = $main_file;
            }
            
            // Thumbnail files
            if ($att->metadata) {
                $metadata = maybe_unserialize($att->metadata);
                if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                    $dir = dirname($main_file);
                    foreach ($metadata['sizes'] as $size_data) {
                        if (isset($size_data['file'])) {
                            $used_files[] = $dir . '/' . $size_data['file'];
                        }
                    }
                }
            }
        }
        
        PIM_Debug_Logger::log('Files in use', array(
            'count' => count($used_files)
        ));
        
        // ✅ Delete files that are NOT used
        foreach ($files as $file) {
            if (!in_array($file, $used_files)) {
                // This is an orphan!
                if (file_exists($file) && @unlink($file)) {
                    $deleted_count++;
                    PIM_Debug_Logger::success('Deleted orphan', array('file' => basename($file)));
                } else {
                    PIM_Debug_Logger::warning('Failed to delete', array('file' => basename($file)));
                }
            } else {
                PIM_Debug_Logger::log('File in use, keeping', array('file' => basename($file)));
            }
        }
        
        PIM_Debug_Logger::exit_function('cleanup_orphan_files', array(
            'deleted' => $deleted_count
        ));
        
        return $deleted_count;
    }

    /**
     * ✅ NEW: Get total pages count
     */
    public function get_total_pages() {
        check_ajax_referer('page_images_manager', 'nonce');
        
        $pages = get_pages(array('number' => 9999));
        
        wp_send_json_success(array(
            'total' => count($pages)
        ));
    }

    /**
     * ✅ Scan all pages and save to _pim_page_usage (FLAT STRUCTURE)
     */
    public function collect_base_images_data_from_all_pages() {
        PIM_Debug_Logger::log_session_start('collect_base_images_data_from_all_pages');
        
        error_log("\n🔄🔄🔄 === COLLECT BASE IMAGES DATA FROM ALL PAGES START === 🔄🔄🔄");
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $start_time = microtime(true);
        
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC'
        ]);
        
        error_log("📊 Found " . count($pages) . " published pages to scan");
        
        $total_images_processed = 0;
        $total_uses_found = 0;
        $all_scan_data = array();
        $global_orphaned_files = array();
        $global_duplicates = array();
        $global_debug_info = array();
        $total_count = 0;
        
        foreach ($pages as $page) {
            error_log("\n📄 === Scanning Page: {$page->post_title} (ID: {$page->ID}) ===");
            
            $extractor = new PIM_Image_Extractor();
            $data = $extractor->collect_base_data_from_page($page->ID);
            
            $page_usage_data = $data['page_usage_data'][$page->ID] ?? array();
            
            $all_scan_data[$page->ID] = $data;
            
            if (!empty($data['orphaned_files'])) {
                $global_orphaned_files = array_merge($global_orphaned_files, $data['orphaned_files']);
            }
            
            if (!empty($data['duplicates'])) {
                foreach ($data['duplicates'] as $primary_id => $dups) {
                    if (!isset($global_duplicates[$primary_id])) {
                        $global_duplicates[$primary_id] = array();
                    }
                    $global_duplicates[$primary_id] = array_merge($global_duplicates[$primary_id], $dups);
                }
            }
            
            if (!empty($data['scan_summary'])) {
                $total_count += $data['scan_summary']['count'] ?? 0;
                
                foreach ($data['scan_summary'] as $key => $value) {
                    if ($key === 'count') {
                        continue;
                    }
                    
                    if ($key === 'widgets_found' && is_array($value)) {
                        if (!isset($global_debug_info['widgets_found'])) {
                            $global_debug_info['widgets_found'] = array();
                        }
                        foreach ($value as $widget => $count) {
                            $global_debug_info['widgets_found'][$widget] = ($global_debug_info['widgets_found'][$widget] ?? 0) + $count;
                        }
                    } elseif (is_numeric($value)) {
                        $global_debug_info[$key] = ($global_debug_info[$key] ?? 0) + $value;
                    }
                }
            }
            
            if (empty($page_usage_data)) {
                error_log("  ℹ️ No images found on this page");
                continue;
            }
            
            $this->process_and_save_images(
                $page->ID,
                $page_usage_data['existing_images'] ?? array(),
                'existing_images',
                $total_images_processed,
                $total_uses_found
            );
            
            $this->process_and_save_images(
                $page->ID,
                $page_usage_data['missing_in_files'] ?? array(),
                'missing_in_files',
                $total_images_processed,
                $total_uses_found
            );
            
            $this->process_and_save_images(
                $page->ID,
                $page_usage_data['missing_in_database'] ?? array(),
                'missing_in_database',
                $total_images_processed,
                $total_uses_found
            );
        }
        
        update_post_meta(0, '_pim_scan_data', array(
            'orphaned_files' => $global_orphaned_files,
            'duplicates' => $global_duplicates
        ));
        error_log("💾 Saved global scan data (orphaned_files + duplicates) to wp_postmeta(0)");
        
        $duration = round((microtime(true) - $start_time), 2);
        
        error_log("\n📊 === SCAN SUMMARY ===");
        error_log("  Pages scanned: " . count($pages));
        error_log("  Images processed: {$total_images_processed}");
        error_log("  Total uses found: {$total_uses_found}");
        error_log("  Duration: {$duration} seconds");
        
        $scan_summary = array_merge(
            array(
                'timestamp' => current_time('mysql'),
                'duration' => $duration,
                'total_pages' => count($pages),
                'total_images' => $total_images_processed,
                'total_uses' => $total_uses_found,
                'user' => wp_get_current_user()->display_name
            ),
            $global_debug_info,
            array('count' => $total_count)
        );
        
        update_option('pim_last_scan', $scan_summary);
        
        $this->save_scan_to_file($all_scan_data, $global_orphaned_files, $global_duplicates, $scan_summary);
        
        error_log("✅ Saved scan info to wp_options");
        error_log("🔄🔄🔄 === COLLECT BASE IMAGES DATA FROM ALL PAGES END === 🔄🔄🔄\n");
        
        wp_send_json_success(array(
            'message' => sprintf(
                "Scanned %d pages, processed %d images (%d uses)",
                count($pages),
                $total_images_processed,
                $total_uses_found
            ),
            'duration' => $duration,
            'total_pages' => count($pages),
            'total_images' => $total_images_processed,
            'total_uses' => $total_uses_found
        ));
    }
    
    private function save_scan_to_file($all_scan_data, $global_orphaned_files, $global_duplicates, $scan_summary) {
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/pim-scan-data-' . date('Y-m-d-H-i-s') . '.json';
        
        $combined_page_usage = array();
        
        foreach ($all_scan_data as $page_id => $data) {
            if (isset($data['page_usage_data'][$page_id])) {
                $combined_page_usage[$page_id] = $data['page_usage_data'][$page_id];
            }
        }
        
        $final_structure = array(
            'page_usage_data' => $combined_page_usage,
            'orphaned_files' => $global_orphaned_files,
            'duplicates' => $global_duplicates,
            'scan_summary' => $scan_summary
        );
        
        $json_data = json_encode($final_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($file_path, $json_data)) {
            error_log("💾 Saved scan data to: {$file_path}");
        } else {
            error_log("❌ Failed to save scan data to file");
        }
    }
    
    private function process_and_save_images($page_id, $images_data, $category, &$total_images_processed, &$total_uses_found) {
        $by_image = array();
        
        foreach ($images_data as $img) {
            $image_id = intval($img['id']);
            if ($image_id <= 0) continue;
            
            if (!isset($by_image[$image_id])) {
                $by_image[$image_id] = array();
            }
            
            $by_image[$image_id][] = $img;
        }
        
        foreach ($by_image as $image_id => $uses) {
            $existing_usage = get_post_meta($image_id, '_pim_page_usage', true);
            if (!is_array($existing_usage)) {
                $existing_usage = array();
            }
            
            if (!isset($existing_usage[$page_id])) {
                $existing_usage[$page_id] = array(
                    'existing_images' => array(),
                    'missing_in_files' => array(),
                    'missing_in_database' => array()
                );
            }
            
            $existing_usage[$page_id][$category] = $uses;
            
            update_post_meta($image_id, '_pim_page_usage', $existing_usage);
            
            $total_images_processed++;
            $total_uses_found += count($uses);
            
            error_log("  💾 Image #{$image_id}: Saved " . count($uses) . " uses in category '{$category}'");
        }
    }

    /**
     * ✅ NEW: Export image list to CSV
     */
    public function export_image_list() {
        check_ajax_referer('page_images_manager', 'nonce');
        
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT post_id, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_pim_page_usage'
        ");
        
        $csv_data = array();
        $csv_data[] = array('Image ID', 'Filename', 'Page ID', 'Page Title', 'Size', 'Source', 'Elementor ID', 'File URL');
        
        foreach ($results as $row) {
            $image_id = $row->post_id;
            $page_usage = maybe_unserialize($row->meta_value);
            $filename = basename(get_attached_file($image_id));
            
            foreach ($page_usage as $page_id => $sizes) {
                $page = get_post($page_id);
                $page_title = $page ? $page->post_title : 'Unknown';
                
                foreach ($sizes as $size_name => $data) {
                    $csv_data[] = array(
                        $image_id,
                        $filename,
                        $page_id,
                        $page_title,
                        $size_name,
                        $data['source'] ?? 'unknown',
                        $data['elementor_id'] ?? 'unknown',
                        $data['file_url'] ?? ''
                    );
                }
            }
        }
        
        // Generate CSV
        $filename = 'pim-image-usage-' . date('Y-m-d-His') . '.csv';
        $upload_dir = wp_upload_dir();
        $filepath = $upload_dir['basedir'] . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        foreach ($csv_data as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
        
        wp_send_json_success(array(
            'download_url' => $upload_dir['baseurl'] . '/' . $filename,
            'filename' => $filename,
            'total_rows' => count($csv_data) - 1
        ));
    }

    /**
     * ✅ Helper: Detect size from source name
     */
    private function detect_size_from_source($source) {
        $size_map = array(
            'hero' => 'hero',
            'background' => 'hero',
            'carousel' => 'carousel-photo',
            'gallery' => 'carousel-photo',
            'image' => 'standard-page-photo',
            'avatar' => 'thumbnail',
            'logo' => 'thumbnail'
        );
        
        return $size_map[$source] ?? 'full';
    }

    public function get_latest_scan() {
        check_ajax_referer('page_images_manager', 'nonce');
        
        $cache = get_option('pim_last_scan', []);
        wp_send_json_success($cache);
    }

    /**
     * ✅ Extract all unique size names from _pim_page_usage
     */
    private function extract_all_sizes_from_usage($page_usage) {
        $all_sizes = array();
        
        foreach ($page_usage as $page_id => $uses) {
            foreach ($uses as $use) {
                if (isset($use['size_name'])) {
                    $all_sizes[] = $use['size_name'];
                }
            }
        }
        
        return array_unique($all_sizes);
    }

    /**
     * ✅ ISSUE -1: Repair Elementor URLs
     * Fixes URLs that lost "-scaled" suffix or have incorrect dimensions
     */
    public function repair_elementor_urls() {
        check_ajax_referer('page_images_manager', 'nonce');
        
        global $wpdb;
        
        $repaired_count = 0;
        $error_count = 0;
        
        error_log("\n🔧 === REPAIR ELEMENTOR URLS START ===");
        
        $pages = get_pages(array(
            'post_status' => 'publish,draft',
            'number' => 99999
        ));
        
        error_log("Found " . count($pages) . " pages to check");
        
        foreach ($pages as $page) {
            $page_id = $page->ID;
            $elementor_data = get_post_meta($page_id, '_elementor_data', true);
            
            if (empty($elementor_data)) {
                continue;
            }
            
            $data = json_decode($elementor_data, true);
            if (!is_array($data)) {
                continue;
            }
            
            $modified = false;
            $page_repairs = 0;
            
            $data = $this->repair_urls_recursive($data, $modified, $page_repairs);
            
            if ($modified) {
                update_post_meta($page_id, '_elementor_data', wp_slash(json_encode($data)));
                
                if (class_exists('\\Elementor\\Plugin')) {
                    \Elementor\Plugin::$instance->files_manager->clear_cache();
                }
                
                $repaired_count += $page_repairs;
                error_log("✅ Page #{$page_id}: Repaired {$page_repairs} URL(s)");
            }
        }
        
        error_log("🔧 === REPAIR COMPLETE: {$repaired_count} URLs repaired ===\n");
        
        wp_send_json_success(array(
            'message' => "Repaired {$repaired_count} URL(s) across " . count($pages) . " pages"
        ));
    }

    /**
     * Recursively repair URLs in Elementor data
     */
    private function repair_urls_recursive(&$data, &$modified, &$repair_count, $parent_key = '', $parent_settings = array(), $depth = 0) {
        if (!is_array($data)) {
            return $data;
        }
        
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                // ✅ Capture settings and element type for context
                $current_settings = isset($value['settings']) ? $value['settings'] : array();
                $el_type = isset($value['elType']) ? $value['elType'] : '';
                
                // ✅ Determine next depth level
                $next_depth = ($el_type === 'container' || $el_type === 'section' || $el_type === 'column') ? $depth + 1 : $depth;
                
                // ✅ Handle image objects with id and url
                if (isset($value['id']) && isset($value['url'])) {
                    $image_id = intval($value['id']);
                    $current_url = $value['url'];
                    
                    if ($image_id > 0) {
                        // ✅ Detect context and determine expected size
                        $expected_size = $this->detect_size_from_context($key, $parent_key, $value, $parent_settings, $depth);
                        
                        // ✅ Get correct URL based on context
                        $correct_url = $this->get_correct_thumbnail_url_with_context($image_id, $current_url, $expected_size);
                        
                        if ($correct_url && $correct_url !== $current_url) {
                            $value['url'] = $correct_url;
                            $modified = true;
                            $repair_count++;
                            error_log("  🔧 Fixed URL [{$key}] depth={$depth} size={$expected_size}: {$current_url} → {$correct_url}");
                        }
                    }
                }
                
                // ✅ Recurse with parent key context, settings, and depth
                $value = $this->repair_urls_recursive($value, $modified, $repair_count, $key, $current_settings, $next_depth);
            }
        }
        
        return $data;
    }

    /**
     * Detect expected size from Elementor context
     */
    private function detect_size_from_context($key, $parent_key, $value, $parent_settings = array(), $depth = 0) {
        // ✅ If size is explicitly defined and is one of OUR custom sizes, use it
        if (isset($value['size']) && !empty($value['size'])) {
            $custom_sizes = array('hero', 'carousel-photo', 'standard-page-photo', 
                                  'teaser-photo', 'page-background', 'small-carousel-cards');
            
            if (in_array($value['size'], $custom_sizes)) {
                return $value['size'];  // ✅ Our custom size
            }
            // Otherwise: WordPress default size (thumbnail, medium, large, full) → ignore, use fallback
        }
        
        // ✅ Hero detection: top-level container (depth <= 1) with min_height >= 300px
        if ($key === 'background_image' && $depth <= 1 && isset($parent_settings['min_height']['size'])) {
            $min_height = intval($parent_settings['min_height']['size']);
            if ($min_height >= 300) {
                return 'hero'; // Hero section (top-level only)
            }
        }
        
        // ✅ Detect from parent key or current key
        $context_key = strtolower($parent_key ?: $key);
        
        if (strpos($context_key, 'carousel') !== false) {
            return 'carousel-photo';
        }
        if (strpos($context_key, 'gallery') !== false) {
            return 'carousel-photo';
        }
        if (strpos($context_key, 'background') !== false && strpos($context_key, 'hero') !== false) {
            return 'hero';
        }
        if (strpos($context_key, 'image') !== false) {
            return 'standard-page-photo';
        }
        
        // ✅ Default for background_image without clear context
        if ($key === 'background_image') {
            return 'standard-page-photo'; // Safe default for nested containers/columns
        }
        
        return null;
    }

    /**
     * Get correct thumbnail URL with context
     */
    private function get_correct_thumbnail_url_with_context($image_id, $current_url, $expected_size) {
        $file_path = get_attached_file($image_id);
        if (!$file_path || !file_exists($file_path)) {
            return null;
        }
        
        $metadata = wp_get_attachment_metadata($image_id);
        if (!$metadata) {
            return null;
        }
        
        $upload_dir = wp_upload_dir();
        $base_dir = dirname($file_path);
        $base_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $base_dir);
        $base_filename = basename($file_path, '.' . pathinfo($file_path, PATHINFO_EXTENSION));
        $ext = pathinfo($file_path, PATHINFO_EXTENSION);
        
        // ✅ If expected size is known, try to find that thumbnail
        if ($expected_size && isset($metadata['sizes'][$expected_size])) {
            $correct_filename = $metadata['sizes'][$expected_size]['file'];
            $correct_path = $base_dir . '/' . $correct_filename;
            
            if (file_exists($correct_path)) {
                return $base_url . '/' . $correct_filename;
            }
        }
        
        // ✅ Try to match dimensions in URL (e.g., IMG_2510-1920x1080.jpeg)
        preg_match('/-(\d+)x(\d+)\.(jpg|jpeg|png|gif|webp)$/i', $current_url, $matches);
        
        if ($matches) {
            $width = $matches[1];
            $height = $matches[2];
            
            // ✅ Check metadata for matching dimensions
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_name => $size_data) {
                    if ($size_data['width'] == $width && $size_data['height'] == $height) {
                        return $base_url . '/' . $size_data['file'];
                    }
                }
            }
            
            // ✅ Check filesystem
            $expected_filename = $base_filename . '-' . $width . 'x' . $height . '.' . $ext;
            $expected_path = $base_dir . '/' . $expected_filename;
            
            if (file_exists($expected_path)) {
                return $base_url . '/' . $expected_filename;
            }
        }
        
        // ✅ Handle URLs without dimensions (e.g., IMG_2510-scaled.jpeg)
        // This happens when carousel/gallery URLs were corrupted
        $current_filename = basename(parse_url($current_url, PHP_URL_PATH));
        
        // If URL is just the main file (no dimensions), try to find correct thumbnail on disk
        if ($current_filename === basename($file_path)) {
            // If we know expected size, use it
            if ($expected_size && isset($metadata['sizes'][$expected_size])) {
                return $base_url . '/' . $metadata['sizes'][$expected_size]['file'];
            }
            
            // Otherwise search for first available thumbnail
            $pattern = $base_dir . '/' . $base_filename . '-*x*.' . $ext;
            $thumbnails = glob($pattern);
            
            if (!empty($thumbnails)) {
                error_log("  ⚠️ URL has no dimensions, using first available thumbnail");
                return $base_url . '/' . basename($thumbnails[0]);
            }
        }
        
        // ✅ Check if filename differs from actual file (wrong path/name)
        $correct_filename = basename($file_path);
        if ($current_filename !== $correct_filename) {
            return $base_url . '/' . $correct_filename;
        }
        
        // ✅ URL is already correct
        return null;
    }
    
    /**
     * ✅ DEBUG: Export Elementor JSON for debugging
     */
    public function export_elementor_json() {
        check_ajax_referer('page_images_manager', 'nonce');
        
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$page_id) {
            wp_send_json_error('Invalid page ID');
        }
        
        $page = get_post($page_id);
        if (!$page) {
            wp_send_json_error('Page not found');
        }
        
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        
        if (empty($elementor_data)) {
            wp_send_json_error('No Elementor data found for this page');
        }
        
        $data = json_decode($elementor_data, true);
        
        $filename = 'elementor-page-' . $page_id . '-' . sanitize_title($page->post_title) . '.json';
        
        wp_send_json_success(array(
            'json' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'filename' => $filename,
            'page_title' => $page->post_title,
            'page_id' => $page_id
        ));
    }
}