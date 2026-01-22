<?php
/**
 * AJAX Handler - Images
 * Handles image listing, regeneration, and deletion
 * 
 * ✅ NEW: get_single_image_row endpoint for efficient updates
 */

if (!defined('ABSPATH')) exit;

class PIM_Ajax_Handler_Images {
    private $extractor;
    private $generator;
    private $renderer;

    public function __construct() {
        $this->extractor = new PIM_Image_Extractor();
        $this->generator = new PIM_Thumbnail_Generator();
        $this->renderer = new PIM_Image_Renderer();
    }

    /**
     * Register AJAX hooks
     */
    public function register_hooks() {
        add_action('wp_ajax_get_page_images', array($this, 'get_page_images'));
        add_action('wp_ajax_get_single_image_row', array($this, 'get_single_image_row'));
        add_action('wp_ajax_regenerate_page_images', array($this, 'regenerate_page_images'));
        add_action('wp_ajax_delete_all_thumbnails', array($this, 'delete_all_thumbnails'));

        // ✅ NEW: Targeted button refresh
        add_action('wp_ajax_get_image_actions', array($this, 'get_image_actions'));
    }

    /**
     * Get page images
     */
    public function get_page_images() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('get_page_images');

        // ✅ BACKTRACE - kto nás volá?
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        error_log("\n📋 === GET_PAGE_IMAGES CALLED ===");
        error_log("📞 CALL STACK:");
        foreach ($bt as $i => $trace) {
            $func = isset($trace['function']) ? $trace['function'] : 'unknown';
            $class = isset($trace['class']) ? $trace['class'] . '::' : '';
            $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
            $line = isset($trace['line']) ? $trace['line'] : '?';
            error_log("   #{$i} {$class}{$func}() in {$file}:{$line}");
        }
        
        check_ajax_referer('page_images_manager', 'nonce');
        
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$page_id) {
            wp_send_json_error(__('Invalid page ID', 'page-images-manager'));
        }

        $data = $this->extractor->extract_all_images($page_id);
        
        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        $html = $this->renderer->generate_html($data);
        
        wp_send_json_success(array(
            'html' => $html,
            'count' => $data['count'],
            'debug_data' => array(
                'valid_images' => $data['valid_images'],
                'missing_files' => $data['missing_files'],
                'missing_image_ids' => $data['missing_image_ids'],
                'debug_info' => $data['debug_info']
            )
        ));
    }

    /**
     * ✅ NEW: Get single image row (for efficient updates after actions)
     * ✅ OPTIMIZED: No full extraction, just render row with cached data
     */
    public function get_single_image_row() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('get_single_image_row');

        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        
        if (!$image_id || !$page_id) {
            wp_send_json_error('Missing required data');
        }

        // ✅ BACKTRACE
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        error_log("\n🔄 === GET_SINGLE_IMAGE_ROW (OPTIMIZED) ===");
        error_log("Image ID: {$image_id}");
        error_log("Page ID: {$page_id}");
        error_log("📞 CALL STACK:");
        foreach ($bt as $i => $trace) {
            $func = isset($trace['function']) ? $trace['function'] : 'unknown';
            $class = isset($trace['class']) ? $trace['class'] . '::' : '';
            $file = isset($trace['file']) ? basename($trace['file']) : 'unknown';
            $line = isset($trace['line']) ? $trace['line'] : '?';
            error_log("   #{$i} {$class}{$func}() in {$file}:{$line}");
        }

        // ✅ Check if image exists
        $post = get_post($image_id);
        if (!$post || $post->post_type !== 'attachment') {
            error_log("❌ Image #{$image_id} not found in database");
            wp_send_json_error('Image not found');
        }

        $file_path = get_attached_file($image_id);
        if (!$file_path || !file_exists($file_path)) {
            error_log("❌ Image file not found on disk");
            wp_send_json_error('Image file not found');
        }

        error_log("✅ Image exists in DB and on disk");

        // ✅ Get custom sizes
        $size_helper = new PIM_Size_Helper();
        $custom_sizes = $size_helper->get_custom_sizes();

        // ✅ Get image metadata
        wp_cache_delete($image_id, 'post_meta');
        $image_meta = wp_get_attachment_metadata($image_id);

        // ✅ Simple source detection (no full extraction)
        $image_sources = array($image_id => array('unknown'));

        // ✅ No duplicates needed for single row update
        $duplicates = array();

        // ✅ Render single row HTML
        ob_start();
        $this->renderer->render_image_row_public(
            $image_id,
            $custom_sizes,
            $image_sources,
            $duplicates,
            $image_meta
        );
        $html = ob_get_clean();

        error_log("✅ Row HTML generated (no full extraction)");

        wp_send_json_success(array(
            'html' => $html,
            'image_id' => $image_id
        ));
    }

    /**
     * Regenerate page images
     */
    public function regenerate_page_images() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('regenerate_page_images');

        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $source_mappings = isset($_POST['source_mappings']) ? json_decode(stripslashes($_POST['source_mappings']), true) : array();
        
        if (!$image_id || !$page_id || empty($source_mappings)) {
            wp_send_json_error('Missing required data');
        }

        $result = $this->generator->regenerate_thumbnails($image_id, $source_mappings, $page_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => 'Thumbnails regenerated successfully!',
            'image_id' => $image_id
        ));
    }

    /**
     * Delete all thumbnails
     */
    public function delete_all_thumbnails() {
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start('delete_all_thumbnails');

        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        
        if (!$image_id) {
            wp_send_json_error('Invalid image ID');
        }

        $file_manager = new PIM_Thumbnail_File_Manager();
        $result = $file_manager->delete_all_thumbnails($image_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array('message' => $result));
    }

    /**
     * ✅ Get only action buttons HTML for single image
     * NO extraction, duplicates come from frontend
     */
    public function get_image_actions() {
        PIM_Debug_Logger::log_session_start('get_image_actions');
        check_ajax_referer('page_images_manager', 'nonce');
        
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $duplicate_ids = isset($_POST['duplicate_ids']) ? json_decode(stripslashes($_POST['duplicate_ids']), true) : array();
        
        if (!$image_id || !$page_id) {
            wp_send_json_error('Missing required data');
        }
        
        error_log("✅ get_image_actions: Image #{$image_id}, Duplicates: " . count($duplicate_ids));
        
        // ✅ Convert duplicate IDs to format expected by renderer
        $duplicates = array();
        foreach ($duplicate_ids as $dup_id) {
            $duplicates[] = array(
                'missing_id' => $dup_id,
                'missing_url' => wp_get_attachment_url($dup_id),
                'source' => 'duplicate'
            );
        }
        
        // ✅ Render only action buttons (NO extraction, NO detection)
        $buttons_html = $this->renderer->render_image_actions_only($image_id, $page_id, $duplicates);
        
        wp_send_json_success(array(
            'html' => $buttons_html,
            'image_id' => $image_id
        ));
    }


}
