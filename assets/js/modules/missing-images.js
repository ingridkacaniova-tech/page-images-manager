/**
 * Missing Images Module
 * ✅ UPDATED: Uses updateSingleImageRow instead of refreshImages
 */

const PIM_MissingImages = (function($) {
    'use strict';
    
    function init() {
        $(document).on('click', '.generate-missing-btn', uploadAndGenerate);
        $(document).on('click', '.create-attachment-btn', createAttachment);
        $(document).on('click', '.upload-and-create-btn', uploadAndCreate);
    }
    
    // UPLOAD & GENERATE (for missing files)
    function uploadAndGenerate() {
        const $button = $(this);
        const imageId = $button.data('image-id');
        const $row = $button.closest('.pim-image-row');
        const pageId = PIM_Core.getCurrentPageId();
        
        const sourceMappings = PIM_ThumbnailGeneration.validateSourceMappings($row);
        if (!sourceMappings) return;
        
        PIM_Core.createFileInput('image/*', function(file) {
            const toastId = PIM_Toast.loading('Uploading & generating...');
            
            $button.prop('disabled', true).text('⏳ Uploading & Generating...');
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('image_id', imageId);
            formData.append('source_mappings', JSON.stringify(sourceMappings));
            formData.append('page_id', pageId);
            
            PIM_Core.ajaxUpload('generate_missing_file', formData,
                function(data) {
                    console.log('Upload & Generate response:', data);
                    PIM_Toast.update(toastId, data.message, 'success', 4000);
                    
                    // ✅ Update this row (it should move to Existing Images section)
                    setTimeout(function() {
                        PIM_Core.updateSingleImageRow(imageId, function(success) {
                            if (success) {
                                console.log('✅ Row updated - should now be in Existing Images');
                                
                                // Remove from Missing Files section
                                $row.fadeOut(300, function() {
                                    $(this).remove();
                                });
                            } else {
                                console.error('❌ Row update failed, falling back to full refresh');
                                PIM_Core.refreshImages();
                            }
                        });
                    }, 500);
                },
                function(error) {
                    PIM_Toast.update(toastId, error, 'error');
                    $button.prop('disabled', false).text('📤 Upload & Generate');
                }
            );
        });
    }
    
    // CREATE ATTACHMENT (from URL)
    function createAttachment() {
        const $button = $(this);
        const imageUrl = $button.data('url');
        const pageId = PIM_Core.getCurrentPageId();
        
        console.log('🔨 Create button clicked');
        console.log('URL:', imageUrl);
        console.log('Page ID:', pageId);
        
        if (!imageUrl) {
            PIM_Toast.error('Missing image URL');
            return;
        }
        
        if (!pageId) {
            PIM_Toast.error('No page selected');
            return;
        }
        
        const toastId = PIM_Toast.loading('Creating attachment...');
        
        $button.prop('disabled', true).text('⏳ Creating...');
        
        PIM_Core.ajax('create_attachment_from_url',
            {
                image_url: imageUrl,
                page_id: pageId
            },
            function(data) {
                console.log('Response:', data);
                PIM_Toast.update(toastId, 'Attachment created! ID: ' + data.attachment_id, 'success');
                
                // ✅ Full refresh needed here (new attachment created)
                PIM_Core.refreshImages();
            },
            function(error) {
                console.error('Create attachment error:', error);
                PIM_Toast.update(toastId, error, 'error');
                $button.prop('disabled', false).text('🔨 Create');
            }
        );
    }
    
    // UPLOAD & CREATE
    function uploadAndCreate() {
        const $button = $(this);
        const imageUrl = $button.data('url');
        const missingId = $button.data('image-id');
        const pageId = PIM_Core.getCurrentPageId();
        
        PIM_Core.createFileInput('image/*', function(file) {
            const toastId = PIM_Toast.loading('Uploading file...');
            
            $button.prop('disabled', true).text('⏳ Uploading...');
            
            const formData = new FormData();
            formData.append('file', file);
            formData.append('image_url', imageUrl);
            formData.append('missing_id', missingId);
            formData.append('page_id', pageId);
            
            PIM_Core.ajaxUpload('upload_and_create_attachment', formData,
                function(data) {
                    PIM_Toast.update(toastId, 'File uploaded! ID: ' + data.attachment_id, 'success');
                    
                    // ✅ Full refresh needed here (new attachment created)
                    PIM_Core.refreshImages();
                },
                function(error) {
                    PIM_Toast.update(toastId, error, 'error');
                    $button.prop('disabled', false).text('📤 Upload & Create');
                }
            );
        });
    }
    
    return {
        init
    };
    
})(jQuery);

window.PIM_MissingImages = PIM_MissingImages;