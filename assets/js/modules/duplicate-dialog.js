/**
 * Duplicate Dialog Module
 * ✅ TODO 50B: Enhanced confirmation popup with details table
 * ✅ TODO 51: Custom source management
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
                duplicate_ids: JSON.stringify(duplicateIds)
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
                        renderEnhancedPopup(
                            $button, 
                            primaryId, 
                            duplicateIds, 
                            pageId, 
                            sourceMappings, 
                            data.details,
                            ghostData.ghost_files,
                            executeCallback
                        );
                    },
                    function() {
                        // Ghost files optional, continue without them
                        renderEnhancedPopup(
                            $button, 
                            primaryId, 
                            duplicateIds, 
                            pageId, 
                            sourceMappings, 
                            data.details,
                            [],
                            executeCallback
                        );
                    }
                );
            },
            function(error) {
                PIM_Toast.update(toastId, 'Failed to load details: ' + error, 'error');
            }
        );
    }
    
    /**
     * ✅ TODO 50B: Render enhanced popup with table
     */
    function renderEnhancedPopup($button, primaryId, duplicateIds, pageId, sourceMappings, details, ghostFiles, executeCallback) {
        // Get primary image info
        const primaryFile = $button.closest('.pim-image-row').find('.pim-image-info h4').text() || 'Unknown';
        const currentPage = $('#page-selector option:selected').text() || 'Current Page';
        
        // Build details table
        let tableHtml = '<table class="pim-duplicate-table">';
        tableHtml += '<thead><tr>';
        tableHtml += '<th>Image ID</th>';
        tableHtml += '<th>Reason</th>';
        tableHtml += '<th>Page Name</th>';
        tableHtml += '<th>Page ID</th>';
        tableHtml += '<th>Choose or Insert Source</th>';
        tableHtml += '</tr></thead>';
        tableHtml += '<tbody>';
        
        details.forEach(function(detail) {
            tableHtml += '<tr>';
            tableHtml += '<td>' + detail.id + '</td>';
            tableHtml += '<td>' + (detail.reason || 'duplicate') + '</td>';
            tableHtml += '<td>' + (detail.page_name || 'Unknown') + '</td>';
            tableHtml += '<td>' + (detail.page_id || '-') + '</td>';
            tableHtml += '<td>' + renderSourceDropdown(detail) + '</td>';
            tableHtml += '</tr>';
        });
        
        tableHtml += '</tbody></table>';
        
        // Build ghost files warning
        let ghostHtml = '';
        if (ghostFiles && ghostFiles.length > 0) {
            ghostHtml = '<div class="pim-ghost-warning">';
            ghostHtml += '<p><strong>⚠️ Vymazu sa vsetky obrazky s menom*, ktore nepatria k ziadnej stranke</strong></p>';
            ghostHtml += '<ul class="pim-ghost-list">';
            ghostFiles.forEach(function(file) {
                ghostHtml += '<li>' + file + '</li>';
            });
            ghostHtml += '</ul>';
            ghostHtml += '</div>';
        }
        
        // Build source mappings display
        let formatList = '';
        for (const source in sourceMappings) {
            formatList += '<li><strong>' + source + '</strong> → ' + sourceMappings[source] + '</li>';
        }
        
        const popupHtml = 
            '<div id="pim-link-popup-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content pim-link-popup-enhanced">' +
            '<h2>🔗 Link & Update</h2>' +
            '<p class="pim-popup-subtitle">Duplicate IDs to attachment #' + primaryId + ': <strong>' + primaryFile + '</strong>, ' + currentPage + '</p>' +
            
            '<h3>Duplicate Details:</h3>' +
            tableHtml +
            
            ghostHtml +
            
            '<div class="pim-result-info">' +
            '<p><strong>💡 Result:</strong> All widgets will reference one attachment (#' + primaryId + ') with optimized thumbnail sizes.</p>' +
            '<p><strong>Thumbnails to generate:</strong></p>' +
            '<ul>' + formatList + '</ul>' +
            '</div>' +
            
            '<div class="pim-popup-actions">' +
            '<button id="pim-link-popup-cancel" class="button">Cancel</button>' +
            '<button id="pim-link-popup-confirm" class="button button-primary">✅ Link & Generate</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        // Event handlers
        $('#pim-link-popup-cancel').on('click', function() {
            $('#pim-link-popup-overlay').remove();
        });
        
        $('#pim-link-popup-confirm').on('click', function() {
            // Collect source selections from dropdowns
            const selectedSources = collectSourceSelections();
            
            // Merge with original source mappings
            const finalMappings = Object.assign({}, sourceMappings, selectedSources);
            
            $('#pim-link-popup-overlay').remove();
            
            // Call execution callback
            executeCallback($button, primaryId, duplicateIds, pageId, finalMappings);
        });
        
        $('#pim-link-popup-overlay').on('click', function(e) {
            if (e.target.id === 'pim-link-popup-overlay') {
                $(this).remove();
            }
        });
        
        $(document).on('keydown.pim-link-popup', function(e) {
            if (e.key === 'Escape') {
                $('#pim-link-popup-overlay').remove();
                $(document).off('keydown.pim-link-popup');
            }
        });
        
        // Handle custom source input
        $('.pim-source-select').on('change', handleSourceDropdownChange);
    }
    
    /**
     * ✅ TODO 50B/51: Render source dropdown with custom option
     */
    function renderSourceDropdown(detail) {
        const builtInSources = ['hero', 'carousel', 'background', 'image', 'gallery', 'content', 'avatar', 'icon', 'logo'];
        const allSources = [...builtInSources, ...customSources];
        
        let html = '<select class="pim-source-select" data-duplicate-id="' + detail.id + '">';
        html += '<option value="">-- Select or Insert --</option>';
        
        allSources.forEach(function(src) {
            const selected = (detail.existing_source === src) ? ' selected' : '';
            html += '<option value="' + src + '"' + selected + '>' + src + '</option>';
        });
        
        html += '<option value="__custom__">➕ Add new source...</option>';
        html += '</select>';
        
        return html;
    }
    
    /**
     * ✅ TODO 51: Handle custom source input (with NICE dialog!)
     */
    function handleSourceDropdownChange() {
        const $select = $(this);
        const value = $select.val();
        
        if (value === '__custom__') {
            showAddCustomSourceDialog($select);
        }
    }
    
    /**
     * ✅ Show nice dialog for adding custom source
     */
    function showAddCustomSourceDialog($select) {
        const popupHtml = 
            '<div id="pim-custom-source-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content pim-custom-source-popup">' +
            '<h2>➕ Add new source</h2>' +
            '<p>Enter a name for the new source (e.g., product-gallery, team-photo):</p>' +
            '<input type="text" id="pim-custom-source-input" class="pim-custom-source-input" placeholder="my-custom-source" maxlength="50">' +
            '<p class="pim-input-hint">Use lowercase letters, numbers, and hyphens only. No spaces.</p>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-custom-source-cancel" class="button">Cancel</button>' +
            '<button id="pim-custom-source-save" class="button button-primary">✅ Save</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        // Focus input
        $('#pim-custom-source-input').focus();
        
        // Cancel button
        $('#pim-custom-source-cancel').on('click', function() {
            $('#pim-custom-source-overlay').remove();
            $select.val(''); // Reset dropdown
        });
        
        // Save button
        $('#pim-custom-source-save').on('click', function() {
            const sourceName = $('#pim-custom-source-input').val().trim();
            
            if (!sourceName) {
                PIM_Toast.warning('Please enter a source name');
                return;
            }
            
            // Validate format (lowercase, hyphens, numbers only)
            if (!/^[a-z0-9-]+$/.test(sourceName)) {
                PIM_Toast.error('Use only lowercase letters, numbers, and hyphens');
                return;
            }
            
            $('#pim-custom-source-overlay').remove();
            saveCustomSource($select, sourceName);
        });
        
        // Enter key to save
        $('#pim-custom-source-input').on('keypress', function(e) {
            if (e.key === 'Enter') {
                $('#pim-custom-source-save').click();
            }
        });
        
        // Close on overlay click
        $('#pim-custom-source-overlay').on('click', function(e) {
            if (e.target.id === 'pim-custom-source-overlay') {
                $(this).remove();
                $select.val('');
            }
        });
        
        // Close on ESC
        $(document).on('keydown.pim-custom-source', function(e) {
            if (e.key === 'Escape') {
                $('#pim-custom-source-overlay').remove();
                $select.val('');
                $(document).off('keydown.pim-custom-source');
            }
        });
    }
    
    /**
     * Save custom source to backend
     */
    function saveCustomSource($select, sourceName) {
        PIM_Core.ajax('save_custom_source',
            {
                source_name: sourceName
            },
            function(data) {
                // Add to cache
                if (!customSources.includes(sourceName)) {
                    customSources.push(sourceName);
                }
                
                // Add to this dropdown
                const $option = $('<option value="' + sourceName + '" selected>' + sourceName + '</option>');
                $select.find('option[value="__custom__"]').before($option);
                $select.val(sourceName);
                
                PIM_Toast.success('Custom source "' + sourceName + '" saved!');
            },
            function(error) {
                PIM_Toast.error('Failed to save custom source: ' + error);
                $select.val(''); // Reset to empty
            }
        );
    }
    
    /**
     * ✅ TODO 50B: Collect source selections from table
     */
    function collectSourceSelections() {
        const selections = {};
        
        $('.pim-source-select').each(function() {
            const $select = $(this);
            const duplicateId = $select.data('duplicate-id');
            const selectedSource = $select.val();
            
            if (selectedSource && selectedSource !== '__custom__') {
                selections['duplicate_' + duplicateId] = selectedSource;
            }
        });
        
        return selections;
    }
    
    return {
        init,
        showEnhancedDialog
    };
    
})(jQuery);

window.PIM_DuplicateDialog = PIM_DuplicateDialog;