<?php
/**
 * Thumbnail File Manager Class
 * Handles file operations: deletion, upload, replacement, verification
 * 
 * RESPONSIBILITIES:
 * ✔ Mazanie súborov
 * ✔ Upload súborov
 * ✔ Náhrada súborov
 * ✔ Verifikácia súborov
 * 
 * ✅ ISSUE 35/36 FIX: Changed wp_delete_attachment() to prevent file deletion
 */

if (!defined('ABSPATH')) exit;

class PIM_Thumbnail_File_Manager {

    /**
     * ✅ ISSUE 21: Delete only UNLOCKED thumbnails
     * Protected (locked) sizes are preserved
     */
    public function delete_unlocked_thumbnails($image_id, $file, $locked_sizes) {
        $dir = dirname($file);
        $path_info = pathinfo($file);
        
        // Get base filename (remove -scaled if present)
        $base_name = preg_replace('/-scaled$/', '', $path_info['filename']);
        $extension = $path_info['extension'];

        error_log("🗑️ === DELETE UNLOCKED THUMBNAILS START ===");
        error_log("📁 Directory: " . $dir);
        error_log("📄 Base name: " . $base_name);
        error_log("🔒 Protected sizes: " . implode(', ', $locked_sizes));

        // Pattern: base_name-*.extension
        $pattern = $dir . '/' . $base_name . '-*.' . $extension;
        $files = glob($pattern);

        if ($files === false) {
            error_log("⚠️ glob() failed");
            return;
        }

        error_log("📊 Found " . count($files) . " files matching pattern");

        $deleted_count = 0;
        $protected_count = 0;

        // Get metadata to match files to size names
        $metadata = wp_get_attachment_metadata($image_id);
        $size_files = array();
        if (isset($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                $size_files[$size_data['file']] = $size_name;
            }
        }

        foreach ($files as $thumb_file) {
            $filename = basename($thumb_file);

            // Skip main file and -scaled file
            if ($filename === basename($file)) {
                error_log("⭐️ Skipping main file: " . $filename);
                continue;
            }

            if ($filename === $base_name . '-scaled.' . $extension) {
                error_log("⭐️ Skipping -scaled file: " . $filename);
                continue;
            }

            // Check if this file corresponds to a locked size
            $is_locked = false;
            if (isset($size_files[$filename])) {
                $size_name = $size_files[$filename];
                if (in_array($size_name, $locked_sizes)) {
                    $is_locked = true;
                    error_log("🔒 PROTECTED (locked): " . $filename . " (" . $size_name . ")");
                    $protected_count++;
                }
            }

            if (!$is_locked) {
                // Delete unlocked thumbnail
                if (file_exists($thumb_file)) {
                    $deleted = @unlink($thumb_file);
                    if ($deleted) {
                        error_log("✅ Deleted (unlocked): " . $filename);
                        $deleted_count++;
                    } else {
                        error_log("❌ Failed to delete: " . $filename);
                    }
                }
            }
        }

        error_log("📊 Summary: Deleted {$deleted_count}, Protected {$protected_count}");
        error_log("🗑️ === DELETE UNLOCKED THUMBNAILS END ===");

        // Clean metadata - remove unlocked sizes only
        if (isset($metadata['sizes'])) {
            $cleaned_sizes = array();
            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (in_array($size_name, $locked_sizes)) {
                    $cleaned_sizes[$size_name] = $size_data; // Keep locked
                }
            }
            $metadata['sizes'] = $cleaned_sizes;
            wp_update_attachment_metadata($image_id, $metadata);
            error_log("🧹 Cleaned metadata (kept " . count($cleaned_sizes) . " locked sizes)");
        }
    }

    /**
     * Delete ALL thumbnails (no lock protection)
     */
    public function delete_all_thumbnails($image_id) {
        $file = get_attached_file($image_id);
        
        if (!$file || !file_exists($file)) {
            return new WP_Error('file_not_found', 'Original file not found');
        }

        $dir = dirname($file);
        $path_info = pathinfo($file);
        $base_name = preg_replace('/-scaled$/', '', $path_info['filename']);
        $extension = $path_info['extension'];

        // Delete all thumbnail files
        $pattern = $dir . '/' . $base_name . '-*.' . $extension;
        $files = glob($pattern);
        $deleted_count = 0;

        if ($files !== false) {
            foreach ($files as $thumb_file) {
                $filename = basename($thumb_file);
                
                // Skip main file and -scaled
                if ($filename === basename($file) || 
                    $filename === $base_name . '-scaled.' . $extension) {
                    continue;
                }

                if (file_exists($thumb_file) && @unlink($thumb_file)) {
                    $deleted_count++;
                }
            }
        }

        // Clear metadata
        $metadata = wp_get_attachment_metadata($image_id);
        if ($metadata && isset($metadata['sizes'])) {
            $metadata['sizes'] = array();
            wp_update_attachment_metadata($image_id, $metadata);
        }

        return sprintf('Deleted %d thumbnail(s) for image #%d', $deleted_count, $image_id);
    }

    /**
     * Verify thumbnails exist on disk
     */
    public function verify_thumbnails_on_disk($image_id, $metadata, $sizes_to_generate) {
        $file = get_attached_file($image_id);
        $dir = dirname($file);
        
        $verified_count = 0;
        foreach ($sizes_to_generate as $size_name) {
            if (isset($metadata['sizes'][$size_name]['file'])) {
                $thumb_path = $dir . '/' . $metadata['sizes'][$size_name]['file'];
                if (file_exists($thumb_path)) {
                    $verified_count++;
                    error_log("✅ Verified on disk: {$size_name} → " . basename($thumb_path));
                } else {
                    error_log("❌ Missing on disk: {$size_name} → " . basename($thumb_path));
                }
            }
        }
        
        error_log("📊 Generated {$verified_count}/" . count($sizes_to_generate) . " thumbnails successfully");
    }

    /**
     * Upload and create new attachment
     */
    public function upload_and_create_attachment($file, $page_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $upload_overrides = array('test_form' => false);
        $uploaded = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded['error'])) {
            return new WP_Error('upload_failed', $uploaded['error']);
        }

        $file_path = $uploaded['file'];
        $file_type = wp_check_filetype(basename($file_path), null);

        $attachment = array(
            'post_mime_type' => $file_type['type'],
            'post_title' => sanitize_file_name(pathinfo($file_path, PATHINFO_FILENAME)),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $page_id
        );

        $attachment_id = wp_insert_attachment($attachment, $file_path, $page_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // Generate metadata (handles scaling automatically)
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        return array(
            'attachment_id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        );
    }

    /**
     * ✅ ISSUE 35/36 FIX: Simplified update_attachment_file
     * Old files are already deleted in AJAX handler BEFORE upload
     * 
     * CRITICAL FIX: Use wp_delete_post() instead of wp_delete_attachment()
     * to prevent deleting the actual file from disk
     */
    public function update_attachment_file($old_id, $new_id) {
        if (!$old_id || !$new_id) {
            return new WP_Error('invalid_ids', 'Invalid IDs');
        }

        $new_file = get_attached_file($new_id);
        if (!$new_file || !file_exists($new_file)) {
            return new WP_Error('file_not_found', 'New file not found');
        }

        error_log("📄 === UPDATE_ATTACHMENT_FILE START ===");
        error_log("Old ID: $old_id");
        error_log("New ID: $new_id");
        error_log("New file: " . basename($new_file));

        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Update the attachment file path
        update_attached_file($old_id, $new_file);
        error_log("📌 Updated _wp_attached_file to: " . basename($new_file));

        // Scale if needed (don't generate thumbnails - done in regenerate_thumbnails)
        error_log("📐 Scaling if needed...");
        $generator = new PIM_Thumbnail_Generator();
        $scaled_file = $generator->apply_scaling_and_cleanup($new_file, $old_id);
        error_log("📐 File after scaling: " . basename($scaled_file));

        // Update to scaled version if different
        if ($scaled_file !== $new_file) {
            update_attached_file($old_id, $scaled_file);
            error_log("✅ Updated _wp_attached_file to scaled version: " . basename($scaled_file));
        }

        $final_file = get_attached_file($old_id);
        error_log("📌 Final _wp_attached_file: " . basename($final_file));

        // ✅ CRITICAL FIX: Detach file from temporary attachment BEFORE deletion
        // This prevents wp_delete_attachment() from deleting the actual file
        delete_post_meta($new_id, '_wp_attached_file');
        delete_post_meta($new_id, '_wp_attachment_metadata');
        error_log("🔓 Detached file metadata from temporary attachment #$new_id");
        
        // Save mime type before deletion
        $new_post = get_post($new_id);
        $new_mime_type = $new_post ? $new_post->post_mime_type : null;

        // Now safe to delete temporary attachment (file already belongs to $old_id)
        wp_delete_attachment($new_id, true);
        error_log("🗑️ Deleted temporary attachment #$new_id (file was detached first)");

        // Update mime type on old attachment
        if ($new_mime_type) {
            wp_update_post(array(
                'ID' => $old_id,
                'post_mime_type' => $new_mime_type
            ));
            error_log("✅ Updated mime type on attachment #$old_id");
        }

        // Verify final file exists
        if (!file_exists($final_file)) {
            error_log("❌ CRITICAL: Final file doesn't exist: " . $final_file);
            return new WP_Error('file_missing', 'Final file not found after update');
        }

        error_log("✅ === UPDATE_ATTACHMENT_FILE END ===");

        return array(
            'message' => 'File updated successfully',
            'scaled' => strpos(basename($final_file), '-scaled') !== false,
            'final_file' => $final_file
        );
    }
}