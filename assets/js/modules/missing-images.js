/**
 * Missing Images Module
 * ✅ UPDATED: Uses updateSingleImageRow instead of refreshImages
 * ✅ ISSUE 57 - SCENARIO 4: Delete Missing in Database Item
 * ADD to missing-images.js
 */

const PIM_MissingImages = (function($) {
    'use strict';
    
    function init() {
        $(document).on('click', '.generate-missing-btn', uploadAndGenerate);
        $(document).on('click', '.create-attachment-btn', createAttachment);
        $(document).on('click', '.delete-orphan-btn', deleteOrphanFile);
        $(document).on('click', '.delete-missing-item-btn', deleteMissingItem); 
        $(document).on('click', '.delete-orphan-btn', deleteOrphanFile); 
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
        const $row = $button.closest('div[style*="border"]'); // Get the row
        
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
                console.log('✅ Attachment created:', data);
                
                if (data.mode === 'empty_attachment') {
                    // ✅ SCENARIO 1: Empty attachment created
                    // Item should move to Missing Files section
                    PIM_Toast.update(toastId, 'Empty attachment created! Item will appear in Missing Files.', 'success', 4000);
                    
                    // Remove from Missing in Database section
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Check if section is now empty
                        const $section = $('#missing-db-content');
                        if ($section.find('div[style*="border"]').length === 0) {
                            $('#section-missing-db').fadeOut(300, function() {
                                $(this).remove();
                            });
                        }
                    });
                    
                    // ✅ NO FULL REFRESH! Item will appear in Missing Files on next manual "Load Images"
                    // OR we can add it dynamically to Missing Files section (more complex)
                    
                } else if (data.mode === 'consolidated' || data.mode === 'new') {
                    // ✅ SCENARIO 2A/2B: Real attachment created
                    // Update ONLY this image row
                    
                    PIM_Toast.update(toastId, data.message || 'Attachment created!', 'success', 4000);
                    
                    // Update the row to show in Existing Images
                    PIM_Core.updateSingleImageRow(data.attachment_id, function(success) {
                        if (success) {
                            console.log('✅ Row updated - now in Existing Images');
                            
                            // Remove from Missing in Database
                            $row.fadeOut(300, function() {
                                $(this).remove();
                                
                                // Check if section is empty
                                const $section = $('#missing-db-content');
                                if ($section.find('div[style*="border"]').length === 0) {
                                    $('#section-missing-db').fadeOut(300);
                                }
                            });
                        } else {
                            console.error('❌ Row update failed');
                            PIM_Toast.warning('Created but failed to update display. Refresh page to see changes.');
                        }
                    });
                }
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
    
    /**
     * ✅ Delete Missing in Database item (Elementor + disk cleanup)
     */
    function deleteMissingItem() {
        const $button = $(this);
        const imageUrl = $button.data('url');
        const missingId = $button.data('missing-id');
        const pageId = PIM_Core.getCurrentPageId();
        const $row = $button.closest('div[style*="border"]');
        
        if (!imageUrl || !pageId) {
            PIM_Toast.error('Missing required data');
            return;
        }
        
        const filename = imageUrl.split('/').pop();
        
        // ✅ Confirmation dialog
        PIM_Dialog.confirm({
            title: '🗑️ Delete Missing Item?',
            message: `This will:<br>
                    • Remove reference from Elementor<br>
                    • Delete file from disk (if exists)<br>
                    <br>
                    <strong>File:</strong> ${filename}<br>
                    <br>
                    Continue?`,
            confirmText: '🗑️ Delete',
            cancelText: 'Cancel',
            isDangerous: true,
            onConfirm: function() {
                executeDeleteMissingItem($button, imageUrl, missingId, pageId, $row);
            }
        });
    }

    /**
     * ✅ Execute missing item deletion
     */
    function executeDeleteMissingItem($button, imageUrl, missingId, pageId, $row) {
        const toastId = PIM_Toast.loading('Deleting missing item...');
        
        $button.prop('disabled', true).text('⏳ Deleting...');
        
        PIM_Core.ajax('delete_missing_item',
            {
                image_url: imageUrl,
                missing_id: missingId,
                page_id: pageId
            },
            function(data) {
                console.log('✅ Missing item deleted:', data);
                
                let message = 'Missing item deleted!';
                if (data.file_deleted) {
                    message += ' File removed from disk.';
                }
                if (data.elementor_updated) {
                    message += ' Elementor updated.';
                }
                
                PIM_Toast.update(toastId, message, 'success', 4000);
                
                // Remove row with animation
                $row.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Check if section is now empty
                    const $section = $('#missing-db-content');
                    if ($section.find('div[style*="border"]').length === 0) {
                        $('#section-missing-db').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                });
            },
            function(error) {
                console.error('❌ Delete failed:', error);
                PIM_Toast.update(toastId, error, 'error');
                $button.prop('disabled', false).text('🗑️ Delete Item');
            }
        );
    }

    /**
     * ✅ Delete orphan file from disk
     */
    function deleteOrphanFile() {
        const $button = $(this);
        const filePath = $button.data('file-path');
        const $row = $button.closest('div[style*="border"]');
        
        if (!filePath) {
            PIM_Toast.error('Missing file path');
            return;
        }
        
        // ✅ Confirmation dialog
        PIM_Dialog.confirm({
            title: '🗑️ Delete Orphan File?',
            message: 'This file is not used anywhere. Delete it permanently?',
            confirmText: '🗑️ Delete',
            cancelText: 'Cancel',
            isDangerous: true,
            onConfirm: function() {
                executeDeleteOrphan($button, filePath, $row);
            }
        });
    }

    /**
     * ✅ Execute orphan deletion
     */
    function executeDeleteOrphan($button, filePath, $row) {
        const toastId = PIM_Toast.loading('Deleting orphan file...');
        
        $button.prop('disabled', true).text('⏳ Deleting...');
        
        PIM_Core.ajax('delete_orphan_file',
            {
                file_path: filePath
            },
            function(data) {
                console.log('✅ Orphan deleted:', data);
                PIM_Toast.update(toastId, 'Orphan file deleted!', 'success');
                
                // Remove row
                $row.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Check if section is now empty
                    const $section = $('#orphan-files-content');
                    if ($section.find('div[style*="border"]').length === 0) {
                        $('#section-orphan-files').fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                });
            },
            function(error) {
                console.error('❌ Delete failed:', error);
                PIM_Toast.update(toastId, error, 'error');
                $button.prop('disabled', false).text('🗑️ Delete');
            }
        );
    }

    return {
        init
    };
    
})(jQuery);

window.PIM_MissingImages = PIM_MissingImages;