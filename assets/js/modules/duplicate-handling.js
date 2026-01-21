/**
 * Duplicate Handling Module - CORE
 * ✅ TODO 50A: Ghost detection and deletion
 * ✅ Main Link & Generate flow
 * 
 * SPLIT: UI dialog je v duplicate-dialog.js
 */

const PIM_DuplicateHandling = (function($) {
    'use strict';
    
    function init() {
        $(document).on('click', '.link-generate-btn', onLinkGenerateClick);
        $(document).on('click', '.delete-ghost-btn', onDeleteGhostClick);
        
        // ✅ TODO 50A: Check for ghosts after images load
        $(document).on('pim:imagesLoaded pim:imagesRefreshed', checkForGhostDuplicates);
    }
    
    /**
     * ✅ TODO 50A: Check for ghost duplicates
     */
    function checkForGhostDuplicates() {
        $('.link-generate-btn').each(function() {
            const $btn = $(this);
            const primaryId = $btn.data('primary-id');
            const duplicateIds = $btn.data('duplicate-ids');
            const pageId = PIM_Core.getCurrentPageId();
            
            if (!primaryId || !duplicateIds || !pageId) return;
            
            // Remove any existing ghost button
            $btn.siblings('.delete-ghost-btn').remove();
            
            PIM_Core.ajax('find_ghost_duplicates',
                {
                    primary_id: primaryId,
                    duplicate_ids: JSON.stringify(duplicateIds),
                    page_id: pageId
                },
                function(data) {
                    if (data.ghosts && data.ghosts.length > 0) {
                        // Add ghost delete button
                        const $ghostBtn = $('<button type="button" class="button delete-ghost-btn" ' +
                            'data-ghost-ids=\'' + JSON.stringify(data.ghosts) + '\'>' +
                            '🗑️ Delete unused occurrences (' + data.ghosts.length + ')' +
                            '</button>');
                        
                        $btn.after($ghostBtn);
                        console.log('👻 Found ' + data.ghosts.length + ' ghost duplicates for #' + primaryId);
                    }
                }
            );
        });
    }
    
    /**
     * ✅ TODO 50A: Delete ghost duplicates (with NICE dialog!)
     */
    function onDeleteGhostClick() {
        const $button = $(this);
        const ghostIds = $button.data('ghost-ids');
        
        if (!ghostIds || ghostIds.length === 0) {
            PIM_Toast.warning('No ghost duplicates to delete');
            return;
        }
        
        showDeleteGhostDialog($button, ghostIds);
    }
    
    /**
     * ✅ Show nice dialog for ghost deletion
     */
    function showDeleteGhostDialog($button, ghostIds) {
        let idList = '';
        ghostIds.forEach(function(id) {
            idList += '<li>#' + id + '</li>';
        });
        
        const popupHtml = 
            '<div id="pim-delete-ghost-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content pim-delete-ghost-popup">' +
            '<h2>🗑️ Delete unused occurrences?</h2>' +
            '<p><strong>These IDs are not used in Elementor:</strong></p>' +
            '<ul class="pim-ghost-id-list">' + idList + '</ul>' +
            '<div class="pim-warning-box">' +
            '<p><strong>⚠️ Warning:</strong> This will permanently delete them from database and disk.</p>' +
            '</div>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-delete-ghost-cancel" class="button">Cancel</button>' +
            '<button id="pim-delete-ghost-confirm" class="button button-primary" style="background: #d63638; border-color: #d63638;">🗑️ Delete</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        // Cancel button
        $('#pim-delete-ghost-cancel').on('click', function() {
            $('#pim-delete-ghost-overlay').remove();
        });
        
        // Confirm button
        $('#pim-delete-ghost-confirm').on('click', function() {
            $('#pim-delete-ghost-overlay').remove();
            executeDeleteGhost($button, ghostIds);
        });
        
        // Close on overlay click
        $('#pim-delete-ghost-overlay').on('click', function(e) {
            if (e.target.id === 'pim-delete-ghost-overlay') {
                $(this).remove();
            }
        });
        
        // Close on ESC
        $(document).on('keydown.pim-delete-ghost', function(e) {
            if (e.key === 'Escape') {
                $('#pim-delete-ghost-overlay').remove();
                $(document).off('keydown.pim-delete-ghost');
            }
        });
    }
    
    /**
     * Execute ghost deletion
     */
    function executeDeleteGhost($button, ghostIds) {
        const toastId = PIM_Toast.loading('Deleting ghost duplicates...');
        $button.prop('disabled', true);
        
        PIM_Core.ajax('delete_ghost_duplicates',
            {
                ghost_ids: JSON.stringify(ghostIds)
            },
            function(data) {
                PIM_Toast.update(toastId, data.message, 'success', 4000);
                
                // Remove the button
                $button.remove();
                
                // Refresh images to update counts
                setTimeout(function() {
                    PIM_Core.refreshImages();
                }, 500);
            },
            function(error) {
                PIM_Toast.update(toastId, error, 'error');
                $button.prop('disabled', false);
            }
        );
    }
    
    /**
     * Main Link & Generate click handler
     */
    function onLinkGenerateClick() {
        const $button = $(this);
        const primaryId = $button.data('primary-id');
        const duplicateIds = $button.data('duplicate-ids');
        const pageId = PIM_Core.getCurrentPageId();
        const $row = $button.closest('.pim-image-row');
        
        const sourceMappings = PIM_ThumbnailGeneration.validateSourceMappings($row);
        if (!sourceMappings) return;
        
        console.log('🔗 Link & Generate:', {
            primaryId,
            duplicateIds,
            sourceMappings
        });
        
        // ✅ TODO 50B: Delegate to dialog module
        if (typeof PIM_DuplicateDialog !== 'undefined') {
            PIM_DuplicateDialog.showEnhancedDialog(
                $button, 
                primaryId, 
                duplicateIds, 
                pageId, 
                sourceMappings,
                executeLinkAndGenerate  // Callback
            );
        } else {
            // Fallback: direct execution without enhanced dialog
            executeLinkAndGenerate($button, primaryId, duplicateIds, pageId, sourceMappings);
        }
    }
    
    /**
     * Execute Link & Generate (called by dialog or directly)
     */
    function executeLinkAndGenerate($button, primaryId, duplicateIds, pageId, sourceMappings) {
        const toastId = PIM_Toast.loading('Linking & generating...');
        
        $button.prop('disabled', true).text('⏳ Linking & Generating...');
        
        const logData = {
            primary_id: primaryId,
            duplicate_ids: duplicateIds,
            source_mappings: sourceMappings,
            page_id: pageId
        };
        
        console.log('🚀 Executing Link & Generate:', logData);
        if (typeof PIM_DebugLog !== 'undefined') {
            PIM_DebugLog.log('🚀 Executing Link & Generate', logData);
        }
        
        PIM_Core.ajax('link_and_generate',
            {
                primary_id: primaryId,
                duplicate_ids: JSON.stringify(duplicateIds),
                source_mappings: JSON.stringify(sourceMappings),
                page_id: pageId
            },
            function(data) {
                console.log('✅ Link & Generate response:', data);
                if (typeof PIM_DebugLog !== 'undefined') {
                    PIM_DebugLog.log('✅ Link & Generate response', data);
                }
                
                // ✅ Show ghost files cleanup in message
                let message = data.message;
                if (data.deleted_ghost_files > 0) {
                    message += '\n🗑️ Cleaned up ' + data.deleted_ghost_files + ' ghost file(s).';
                }
                
                PIM_Toast.update(toastId, message, 'success', 5000);
                
                setTimeout(function() {
                    PIM_Core.updateSingleImageRow(primaryId, function(success) {
                        if (success) {
                            console.log('✅ Primary row updated');
                            
                            duplicateIds.forEach(function(dupId) {
                                const $dupRow = $('.pim-image-row[data-image-id="' + dupId + '"]');
                                if ($dupRow.length) {
                                    console.log('🗑️ Removing duplicate row #' + dupId);
                                    $dupRow.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                }
                            });
                        } else {
                            console.error('❌ Row update failed, falling back to full refresh');
                            PIM_Core.refreshImages();
                        }
                    });
                }, 500);
            },
            function(error) {
                console.error('❌ Link & Generate error:', error);
                if (typeof PIM_DebugLog !== 'undefined') {
                    PIM_DebugLog.log('❌ Link & Generate error', error);
                }
                
                PIM_Toast.update(toastId, error, 'error');
                $button.prop('disabled', false).text('🔗 Link & Generate');
            }
        );
    }

    
    return {
        init,
        executeLinkAndGenerate  // ✅ Expose for dialog module
    };
    
})(jQuery);

window.PIM_DuplicateHandling = PIM_DuplicateHandling;