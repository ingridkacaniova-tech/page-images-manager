<?php
error_log("🟢 class-ajax-handler-misc-v2.php LOADED (TODO 50/51)");

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
        PIM_Debug_Logger::log_session_start('delete_ghost_duplicates');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $ghost_ids = isset($_POST['ghost_ids']) ? json_decode(stripslashes($_POST['ghost_ids']), true) : array();
        
        if (empty($ghost_ids)) {
            wp_send_json_error('Missing ghost IDs');
        }
        
        $result = $this->duplicate_handler->delete_ghost_duplicates($ghost_ids);
        
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
     * EXISTING METHODS (unchanged)
     */
    public function create_attachment_from_url() {
        PIM_Debug_Logger::log_session_start('create_attachment_from_url');
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_url = isset($_POST['image_url']) ? sanitize_text_field($_POST['image_url']) : '';
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_url || !$page_id) {
            wp_send_json_error('Missing required data');
        }

        $result = $this->generator->create_attachment_from_url($image_url, $page_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success($result);
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
}