/**
 * Image Actions Module
 * ✅ UPDATED: Uses updateSingleImageRow instead of refreshImages
 */

const PIM_ImageActions = (function($) {
    'use strict';
    
    function init() {
        $(document).on('click', '.pim-image-thumb img', openMediaLibrary);
        $(document).on('click', '.reload-image-btn', reloadImage);
        $(document).on('click', '.pim-fix-id-btn', fixElementorId);
        $(document).on('click', '.delete-all-btn', deleteAllThumbnails);
    }
    
    // THUMBNAIL CLICK - Open Media Library
    function openMediaLibrary() {
        const imageId = $(this).closest('.pim-image-row').data('image-id');
        
        if (!wp.media) {
            console.error('WordPress Media Library not available');
            return;
        }
        
        const frame = wp.media({
            title: 'Image Details',
            button: { text: 'Close' },
            multiple: false,
            library: { type: 'image' }
        });
        
        frame.on('open', function() {
            const selection = frame.state().get('selection');
            const attachment = wp.media.attachment(imageId);
            attachment.fetch();
            selection.add(attachment ? [attachment] : []);
        });
        
        frame.open();
    }
    
    // RELOAD IMAGE
    function reloadImage() {
        const $button = $(this);
        const $row = $button.closest('.pim-image-row');
        const imageId = $row.data('image-id');
        const pageId = PIM_Core.getCurrentPageId();
        
        console.log('🔄 Reload Image clicked:');
        console.log('  Image ID:', imageId);
        console.log('  Page ID:', pageId);
        
        if (!$row.length || !imageId) {
            PIM_Toast.error('Could not find image row or ID');
            return;
        }
        
        const sourceMappings = PIM_ThumbnailGeneration.validateSourceMappings($row);
        if (!sourceMappings) return;
        
        const hasNonStandard = PIM_ThumbnailGeneration.hasNonStandardSelection($row);
        
        PIM_Core.createFileInput('image/*', function(file) {
            showReloadConfirmation(file, hasNonStandard, function() {
                uploadAndReload($button, $row, file, imageId, pageId, sourceMappings, hasNonStandard);
            });
        });
    }
    
    function showReloadConfirmation(file, hasNonStandard, onConfirm) {
        const message = hasNonStandard
            ? 'Upload new file and relink Elementor references?<br><br><strong>⚠️ This will NOT generate thumbnails.</strong><br>Old file will be DELETED.'
            : 'Replace this image?<br><br><strong>⚠️ Old file and all its thumbnails will be DELETED!</strong><br>Selected thumbnail sizes will be generated.';
        
        const popupHtml = 
            '<div id="pim-reload-popup-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
            '<h2 style="margin: 0 0 20px 0; color: #2271b1;">🔄 Replace this image?</h2>' +
            '<p style="margin-bottom: 15px;">' + message + '</p>' +
            '<p style="background: #f0f0f1; padding: 12px; border-radius: 4px; margin: 20px 0;"><strong>File:</strong> ' + file.name + '</p>' +
            '<div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">' +
            '<button id="pim-reload-popup-cancel" class="button" style="padding: 8px 20px;">Cancel</button>' +
            '<button id="pim-reload-popup-confirm" class="button button-primary" style="padding: 8px 20px;">✅ Replace Image</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        $('#pim-reload-popup-cancel').on('click', function() {
            $('#pim-reload-popup-overlay').remove();
        });
        
        $('#pim-reload-popup-confirm').on('click', function() {
            $('#pim-reload-popup-overlay').remove();
            onConfirm();
        });
        
        $('#pim-reload-popup-overlay').on('click', function(e) {
            if (e.target.id === 'pim-reload-popup-overlay') {
                $(this).remove();
            }
        });
        
        $(document).on('keydown.pim-reload-popup', function(e) {
            if (e.key === 'Escape') {
                $('#pim-reload-popup-overlay').remove();
                $(document).off('keydown.pim-reload-popup');
            }
        });
    }
    
    function uploadAndReload($button, $row, file, imageId, pageId, sourceMappings, hasNonStandard) {
        console.log('📤 uploadAndReload called:');
        console.log('  Image ID:', imageId);
        console.log('  Page ID:', pageId);
        console.log('  File:', file.name);
        console.log('  Source mappings:', sourceMappings);
        
        const toastId = PIM_Toast.loading(
            hasNonStandard ? 'Uploading & relinking...' : 'Uploading & replacing...'
        );
        
        $button.prop('disabled', true);
        
        const formData = new FormData();
        formData.append('file', file);
        formData.append('image_id', imageId);
        formData.append('source_mappings', JSON.stringify(sourceMappings));
        formData.append('page_id', pageId);
        
        console.log('📤 Sending AJAX with image_id:', imageId);
        
        PIM_Core.ajaxUpload('reload_image_file', formData,
            function(data) {
                console.log('✅ Upload successful:', data);
                PIM_Toast.update(toastId, data.message, 'success', 4000);
                
                // ✅ Update ONLY this row
                setTimeout(function() {
                    PIM_Core.updateSingleImageRow(imageId, function(success) {
                        if (success) {
                            console.log('✅ Row updated successfully');
                            
                            // Restore debug buttons visibility if needed
                            if ($('#pim-show-debug-toggle').is(':checked')) {
                                $('.pim-debug-only').show();
                            }
                        } else {
                            console.error('❌ Row update failed, falling back to full refresh');
                            PIM_Core.refreshImages();
                        }
                    });
                }, 500);
            },
            function(error) {
                console.error('❌ Upload failed:', error);
                PIM_Toast.update(toastId, 'Upload failed: ' + error, 'error');
                $button.prop('disabled', false);
            }
        );
    }
    
    // FIX ELEMENTOR ID
    function fixElementorId() {
        const $button = $(this);
        const imageId = $button.data('image-id');
        const pageId = PIM_Core.getCurrentPageId();
        
        showFixIdConfirmation(imageId, function() {
            executeFixElementorId($button, imageId, pageId);
        });
    }
    
    function showFixIdConfirmation(imageId, onConfirm) {
        const popupHtml = 
            '<div id="pim-fixid-popup-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
            '<h2 style="margin: 0 0 20px 0; color: #2271b1;">🔧 Fix Elementor ID?</h2>' +
            '<p style="margin-bottom: 15px;">This will search Elementor data for image <strong>#' + imageId + '</strong> URL and update it with the correct ID.</p>' +
            '<p style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin: 20px 0; font-size: 13px;">💡 <strong>Use this when:</strong> Elementor widgets show wrong image due to incorrect attachment ID.</p>' +
            '<div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">' +
            '<button id="pim-fixid-popup-cancel" class="button" style="padding: 8px 20px;">Cancel</button>' +
            '<button id="pim-fixid-popup-confirm" class="button button-primary" style="padding: 8px 20px;">✅ Fix ID</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        $('#pim-fixid-popup-cancel').on('click', function() {
            $('#pim-fixid-popup-overlay').remove();
        });
        
        $('#pim-fixid-popup-confirm').on('click', function() {
            $('#pim-fixid-popup-overlay').remove();
            onConfirm();
        });
        
        $('#pim-fixid-popup-overlay').on('click', function(e) {
            if (e.target.id === 'pim-fixid-popup-overlay') {
                $(this).remove();
            }
        });
        
        $(document).on('keydown.pim-fixid-popup', function(e) {
            if (e.key === 'Escape') {
                $('#pim-fixid-popup-overlay').remove();
                $(document).off('keydown.pim-fixid-popup');
            }
        });
    }
    
    function executeFixElementorId($button, imageId, pageId) {
        $button.prop('disabled', true).text('⏳ Fixing...');
        
        PIM_Core.ajax('fix_elementor_image_id', 
            { image_id: imageId, page_id: pageId },
            function(data) {
                PIM_Toast.success(data.message);
                
                // ✅ Update ONLY this row
                PIM_Core.updateSingleImageRow(imageId, function(success) {
                    if (!success) {
                        PIM_Core.refreshImages();
                    }
                });
            },
            function(error) {
                PIM_Toast.error(error);
                $button.prop('disabled', false).text('🔧 Fix ID');
            }
        );
    }
    
    // DELETE ALL THUMBNAILS
    function deleteAllThumbnails() {
        const $button = $(this);
        const $row = $button.closest('.pim-image-row');
        const imageId = $row.data('image-id');
        
        showDeleteAllConfirmation(imageId, function() {
            executeDeleteAll($button, $row, imageId);
        });
    }
    
    function showDeleteAllConfirmation(imageId, onConfirm) {
        const popupHtml = 
            '<div id="pim-delete-popup-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center;">' +
            '<div style="background: white; padding: 30px; border-radius: 8px; max-width: 500px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
            '<h2 style="margin: 0 0 20px 0; color: #d63638;">🗑️ Delete ALL thumbnails?</h2>' +
            '<p style="margin-bottom: 15px;">This will permanently delete <strong>ALL thumbnails</strong> for image <strong>#' + imageId + '</strong>.</p>' +
            '<p style="background: #fef0f0; border-left: 4px solid #d63638; padding: 12px; margin: 20px 0; font-size: 13px; color: #721c24;"><strong>⚠️ Warning:</strong> This action cannot be undone! The original image will remain, but all generated thumbnails will be deleted.</p>' +
            '<div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">' +
            '<button id="pim-delete-popup-cancel" class="button" style="padding: 8px 20px;">Cancel</button>' +
            '<button id="pim-delete-popup-confirm" class="button button-primary" style="padding: 8px 20px; background: #d63638; border-color: #d63638;">🗑️ Delete All</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        $('#pim-delete-popup-cancel').on('click', function() {
            $('#pim-delete-popup-overlay').remove();
        });
        
        $('#pim-delete-popup-confirm').on('click', function() {
            $('#pim-delete-popup-overlay').remove();
            onConfirm();
        });
        
        $('#pim-delete-popup-overlay').on('click', function(e) {
            if (e.target.id === 'pim-delete-popup-overlay') {
                $(this).remove();
            }
        });
        
        $(document).on('keydown.pim-delete-popup', function(e) {
            if (e.key === 'Escape') {
                $('#pim-delete-popup-overlay').remove();
                $(document).off('keydown.pim-delete-popup');
            }
        });
    }
    
    function executeDeleteAll($button, $row, imageId) {
        $button.prop('disabled', true).text('Deleting...');
        
        PIM_Core.ajax('delete_all_thumbnails', 
            { image_id: imageId },
            function() {
                PIM_Toast.success('All thumbnails deleted!');
                
                $row.fadeOut(300, function() {
                    $(this).remove();
                });
            },
            function(error) {
                PIM_Toast.error(error);
                $button.prop('disabled', false).text('🗑️ Delete All');
            }
        );
    }
    
    return {
        init
    };
    
})(jQuery);

window.PIM_ImageActions = PIM_ImageActions;