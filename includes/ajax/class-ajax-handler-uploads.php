<?php
/**
 * AJAX Handler - Uploads
 * Handles file uploads and image regeneration
 * 
 * ✅ UNIFIED: reload_image_file and generate_missing_file merged into ONE method
 * They do the EXACT same thing - only the success message differs
 */

if (!defined('ABSPATH')) exit;

class PIM_Ajax_Handler_Uploads {
    private $generator;
    private $file_manager;

    public function __construct() {
        $this->generator = new PIM_Thumbnail_Generator();
        $this->file_manager = new PIM_Thumbnail_File_Manager();
    }

    /**
     * Register AJAX hooks
     */
    public function register_hooks() {
        // ✅ Both actions call the SAME method
        add_action('wp_ajax_reload_image_file', array($this, 'upload_and_regenerate'));
        add_action('wp_ajax_generate_missing_file', array($this, 'upload_and_regenerate'));
    }

    /**
     * ✅ UNIFIED METHOD - handles both reload_image_file and generate_missing_file
     * The only difference is the success message at the end
     */
    public function upload_and_regenerate() {
        $action = sanitize_text_field($_POST['action']);
        
        // ✅ SESSION START
        PIM_Debug_Logger::log_session_start($action);

        PIM_Debug_Logger::enter('upload_and_regenerate', array(
            'action' => $action,
            'has_file' => isset($_FILES['file']),
            'image_id' => $_POST['image_id'] ?? 'missing',
            'page_id' => $_POST['page_id'] ?? 'missing'
        ));

        // ============================================
        // STEP 0: Validate inputs
        // ============================================
        check_ajax_referer('page_images_manager', 'nonce');
        PIM_Debug_Logger::success('Nonce verified');

        if (!isset($_FILES['file'])) {
            PIM_Debug_Logger::error('No file uploaded');
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error('No file uploaded');
        }

        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        $page_id = isset($_POST['page_id']) ? intval($_POST['page_id']) : 0;
        $source_mappings = isset($_POST['source_mappings']) ? json_decode(stripslashes($_POST['source_mappings']), true) : array();

        PIM_Debug_Logger::log('Parsed inputs', array(
            'image_id' => $image_id,
            'page_id' => $page_id,
            'source_mappings' => $source_mappings,
            'file_name' => $_FILES['file']['name'],
            'file_size' => $_FILES['file']['size']
        ));

        if (!$image_id || !$page_id) {
            PIM_Debug_Logger::error('Missing required data');
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error('Missing required data');
        }

        PIM_Debug_Logger::success('All inputs validated');

        // ============================================
        // ✅ STEP 0.5: Delete based on UPLOADED filename
        // ============================================
        $this->delete_old_files_before_upload($_FILES['file']['name']);

        // ============================================
        // STEP 1: Upload new file
        // ============================================
        PIM_Debug_Logger::log('STEP 1: Uploading new file...');
        $upload_result = $this->file_manager->upload_and_create_attachment($_FILES['file'], $page_id);

        if (is_wp_error($upload_result)) {
            PIM_Debug_Logger::error('Upload failed', $upload_result->get_error_message());
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error($upload_result->get_error_message());
        }

        $new_id = $upload_result['attachment_id'];
        PIM_Debug_Logger::success('Upload complete', array('new_attachment_id' => $new_id));

        // ============================================
        // STEP 2: Replace old attachment file
        // ============================================
        PIM_Debug_Logger::log('STEP 2: Replacing old attachment file...');
        $replace_result = $this->file_manager->update_attachment_file($image_id, $new_id);

        if (is_wp_error($replace_result)) {
            PIM_Debug_Logger::error('Replacement failed', $replace_result->get_error_message());
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error($replace_result->get_error_message());
        }

        PIM_Debug_Logger::success('Replacement complete');

        // ============================================
        // STEP 3: Clear cache
        // ============================================
        PIM_Debug_Logger::log('STEP 3: Clearing WordPress caches...');
        wp_cache_delete($image_id, 'post_meta');
        wp_cache_delete($image_id, 'posts');
        clean_post_cache($image_id);
        PIM_Debug_Logger::success('All caches cleared');

        // ============================================
        // STEP 4: Verify file exists
        // ============================================
        PIM_Debug_Logger::log('STEP 4: Verifying file after replacement...');
        $final_file = get_attached_file($image_id);

        if (!file_exists($final_file)) {
            PIM_Debug_Logger::error('CRITICAL: File missing after update');
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error('File verification failed after update');
        }

        PIM_Debug_Logger::success('File verified on disk', array('file' => basename($final_file)));

        // ============================================
        // STEP 5: Check if scaled
        // ============================================
        $is_scaled = strpos(basename($final_file), '-scaled.') !== false ||
                    strpos(basename($final_file), '-scaled-') !== false;

        PIM_Debug_Logger::log('Scaled detection', array(
            'filename' => basename($final_file),
            'is_scaled' => $is_scaled
        ));

        // ============================================
        // STEP 6: Regenerate thumbnails
        // ============================================
        PIM_Debug_Logger::log('STEP 6: Regenerating thumbnails...');
        $result = $this->generator->regenerate_thumbnails($image_id, $source_mappings, $page_id, $is_scaled);

        if (is_wp_error($result)) {
            PIM_Debug_Logger::error('Thumbnail generation failed', $result->get_error_message());
            PIM_Debug_Logger::exit_function('upload_and_regenerate');
            wp_send_json_error($result->get_error_message());
        }

        PIM_Debug_Logger::success('Thumbnails regenerated');

        // ============================================
        // STEP 7: Collect results
        // ============================================
        $metadata = wp_get_attachment_metadata($image_id);
        $generated_sizes = $this->collect_generated_sizes($metadata, $source_mappings, $final_file);

        // ============================================
        // FINAL: Send response
        // ✅ Only difference: message based on action
        // ============================================
        $is_reload = ($action === 'reload_image_file');
        
        $message = $is_reload
            ? sprintf(
                'Image reloaded! Generated %d thumbnail size(s): %s',
                count($generated_sizes),
                implode(', ', array_column($generated_sizes, 'name'))
            )
            : sprintf(
                'Missing file generated! Created %d thumbnail size(s): %s',
                count($generated_sizes),
                implode(', ', array_column($generated_sizes, 'name'))
            );

        $response_data = array(
            'message' => $message,
            'image_id' => $image_id,
            'main_file' => basename($final_file),
            'was_scaled' => $is_scaled,
            'generated_thumbnails' => $generated_sizes,
            'total_thumbnails' => count($generated_sizes),
            'source_mappings' => $source_mappings
        );

        PIM_Debug_Logger::exit_function('upload_and_regenerate', array(
            'success' => true,
            'action' => $action,
            'thumbnails_generated' => count($generated_sizes)
        ));

        wp_send_json_success($response_data);
    }

    /**
     * ✅ Delete old files BEFORE upload (prevents -1 suffix)
     */
    private function delete_old_files_before_upload($uploaded_filename) {
        PIM_Debug_Logger::log('STEP 0.5: Deleting old files BEFORE upload...');

        $uploaded_info = pathinfo($uploaded_filename);
        $uploaded_base = $uploaded_info['filename'];
        $uploaded_ext = $uploaded_info['extension'];

        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['path'];
        $pattern = $target_dir . '/' . $uploaded_base . '*.' . $uploaded_ext;
        $files_to_delete = glob($pattern);

        PIM_Debug_Logger::log('Files to delete in target directory', array(
            'pattern' => $pattern,
            'count' => count($files_to_delete),
            'files' => array_map('basename', $files_to_delete)
        ));

        $deleted_count = 0;
        if (!empty($files_to_delete)) {
            foreach ($files_to_delete as $file) {
                if (file_exists($file) && @unlink($file)) {
                    PIM_Debug_Logger::file_op('Deleted BEFORE upload', basename($file));
                    $deleted_count++;
                }
            }
        }

        PIM_Debug_Logger::success("Deleted {$deleted_count} files matching uploaded filename");
    }

    /**
     * Collect generated sizes info
     */
    private function collect_generated_sizes($metadata, $source_mappings, $final_file) {
        $generated_sizes = array();
        $unique_sizes = array_unique(array_values($source_mappings));

        foreach ($unique_sizes as $size_name) {
            if ($size_name === 'non-standard' || !isset($metadata['sizes'][$size_name])) {
                continue;
            }

            $size_data = $metadata['sizes'][$size_name];
            $thumb_path = dirname($final_file) . '/' . $size_data['file'];

            $generated_sizes[] = array(
                'name' => $size_name,
                'file' => $size_data['file'],
                'width' => $size_data['width'],
                'height' => $size_data['height'],
                'size_kb' => file_exists($thumb_path) ? round(filesize($thumb_path) / 1024, 2) : 0
            );
        }

        return $generated_sizes;
    }
}
