/**
 * Duplicate Dialog Module
 * ✅ TODO 50B: Enhanced confirmation popup with details table
 * ✅ TODO 51: Custom source management
 * ✅ TODO 54: Moved dialogs to dialog-helpers.js
 */

const PIM_DuplicateDialog = (function($) {
    'use strict';
    
    let customSources = []; // Cache for custom sources
    
    function init() {
        // ✅ TODO 51: Load custom sources on init
        loadCustomSources();
    }
    
    /**
     * ✅ TODO 51: Load custom sources from backend
     */
    function loadCustomSources() {
        PIM_Core.ajax('get_custom_sources', {}, 
            function(data) {
                customSources = data.custom_sources || [];
                // Store globally for PIM_Dialog access
                window.PIM_CustomSources = customSources;
                console.log('✅ Loaded custom sources:', customSources);
            }
        );
    }
    
    /**
     * ✅ TODO 50B: Show enhanced confirmation popup
     */
    function showEnhancedDialog($button, primaryId, duplicateIds, pageId, sourceMappings, executeCallback) {
        const toastId = PIM_Toast.loading('Loading duplicate details...');
        
        // Get duplicate details
        PIM_Core.ajax('get_duplicate_details',
            {
                duplicate_ids: JSON.stringify(duplicateIds),
                primary_id: primaryId,
                page_id: pageId
            },
            function(data) {
                PIM_Toast.dismiss(toastId);
                
                // Get ghost files
                PIM_Core.ajax('get_ghost_files',
                    {
                        primary_id: primaryId,
                        duplicate_ids: JSON.stringify(duplicateIds),
                        page_id: pageId
                    },
                    function(ghostData) {
                        // Prepare data for dialog
                        const primaryFile = $button.closest('.pim-image-row').find('.pim-image-info h4').text() || 'Unknown';
                        const currentPage = $('#page-selector option:selected').text() || 'Current Page';
                        
                        const dialogData = {
                            primaryId: primaryId,
                            primaryFile: primaryFile,
                            currentPage: currentPage,
                            details: data.details,
                            ghostFiles: ghostData.ghost_files,
                            sourceMappings: sourceMappings
                        };
                        
                        // ✅ TODO 54: Use centralized dialog
                        PIM_Dialog.showLinkAndGenerate(dialogData, function(selectedSources) {
                            // Merge with original source mappings
                            const finalMappings = Object.assign({}, sourceMappings, selectedSources);
                            executeCallback($button, primaryId, duplicateIds, pageId, finalMappings);
                        });
                    },
                    function() {
                        // Ghost files optional, continue without them
                        const primaryFile = $button.closest('.pim-image-row').find('.pim-image-info h4').text() || 'Unknown';
                        const currentPage = $('#page-selector option:selected').text() || 'Current Page';
                        
                        const dialogData = {
                            primaryId: primaryId,
                            primaryFile: primaryFile,
                            currentPage: currentPage,
                            details: data.details,
                            ghostFiles: [],
                            sourceMappings: sourceMappings
                        };
                        
                        // ✅ TODO 54: Use centralized dialog
                        PIM_Dialog.showLinkAndGenerate(dialogData, function(selectedSources) {
                            const finalMappings = Object.assign({}, sourceMappings, selectedSources);
                            executeCallback($button, primaryId, duplicateIds, pageId, finalMappings);
                        });
                    }
                );
            },
            function(error) {
                PIM_Toast.update(toastId, 'Failed to load details: ' + error, 'error');
            }
        );
    }
    
    return {
        init,
        showEnhancedDialog
    };
    
})(jQuery);

window.PIM_DuplicateDialog = PIM_DuplicateDialog;