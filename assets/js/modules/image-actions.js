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
            PIM_Dialog.showReloadConfirmation(file, hasNonStandard, function() {
                uploadAndReload($button, $row, file, imageId, pageId, sourceMappings, hasNonStandard);
            });
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
        
        PIM_Dialog.showFixIdConfirmation(imageId, function() {
            executeFixElementorId($button, imageId, pageId);
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
        
        PIM_Dialog.showDeleteAllConfirmation(imageId, function() {
            executeDeleteAll($button, $row, imageId);
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