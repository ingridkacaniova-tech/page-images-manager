/**
 * Dialog Helpers Module
 * ✅ TODO 54: Centralized custom dialogs (no browser alerts/confirms)
 * 
 * All custom popup dialogs in one place:
 * - Generic confirm dialog
 * - Delete ghost duplicates
 * - Link & Generate
 * - Add custom source
 * - Reload image confirm
 * - Fix Elementor ID
 * - Delete all thumbnails
 */

const PIM_Dialog = (function($) {
    'use strict';
    
    /**
     * ============================================
     * GENERIC CONFIRM DIALOG
     * ============================================
     */
    function confirm(options) {
        const defaults = {
            title: 'Confirm',
            message: 'Are you sure?',
            confirmText: 'OK',
            cancelText: 'Cancel',
            confirmClass: 'button-primary',
            isDangerous: false, // If true, makes confirm button red
            onConfirm: function() {},
            onCancel: function() {}
        };
        
        const settings = Object.assign({}, defaults, options);
        
        const buttonStyle = settings.isDangerous 
            ? 'background: #d63638; border-color: #d63638;' 
            : '';
        
        const popupHtml = 
            '<div id="pim-confirm-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content" style="max-width: 500px;">' +
            '<h2>' + settings.title + '</h2>' +
            '<p style="margin: 20px 0;">' + settings.message + '</p>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-confirm-cancel" class="button">' + settings.cancelText + '</button>' +
            '<button id="pim-confirm-ok" class="button ' + settings.confirmClass + '" style="' + buttonStyle + '">' + settings.confirmText + '</button>' +
            '</div>' +
            '</div>' +
            '</div>';
        
        $('body').append(popupHtml);
        
        // Cancel
        $('#pim-confirm-cancel').on('click', function() {
            $('#pim-confirm-overlay').remove();
            settings.onCancel();
        });
        
        // Confirm
        $('#pim-confirm-ok').on('click', function() {
            $('#pim-confirm-overlay').remove();
            settings.onConfirm();
        });
        
        // ESC key
        $(document).on('keydown.pim-confirm', function(e) {
            if (e.key === 'Escape') {
                $('#pim-confirm-overlay').remove();
                settings.onCancel();
                $(document).off('keydown.pim-confirm');
            }
        });
        
        // Overlay click
        $('#pim-confirm-overlay').on('click', function(e) {
            if (e.target.id === 'pim-confirm-overlay') {
                $(this).remove();
                settings.onCancel();
            }
        });
    }
    
    /**
     * ============================================
     * DELETE GHOST DUPLICATES DIALOG
     * ============================================
     */
    function showDeleteGhost(ghostIds, onConfirm) {
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
        
        $('#pim-delete-ghost-cancel').on('click', function() {
            $('#pim-delete-ghost-overlay').remove();
        });
        
        $('#pim-delete-ghost-confirm').on('click', function() {
            $('#pim-delete-ghost-overlay').remove();
            onConfirm();
        });
        
        $('#pim-delete-ghost-overlay').on('click', function(e) {
            if (e.target.id === 'pim-delete-ghost-overlay') {
                $(this).remove();
            }
        });
        
        $(document).on('keydown.pim-delete-ghost', function(e) {
            if (e.key === 'Escape') {
                $('#pim-delete-ghost-overlay').remove();
                $(document).off('keydown.pim-delete-ghost');
            }
        });
    }
    
    /**
     * ============================================
     * RELOAD IMAGE CONFIRM
     * ============================================
     */
    function showReloadConfirm(file, hasNonStandard, onConfirm) {
        const message = hasNonStandard
            ? 'Upload new file and relink Elementor references?<br><br><strong>⚠️ This will NOT generate thumbnails.</strong><br>Old file will be DELETED.'
            : 'Replace this image?<br><br><strong>⚠️ Old file and all its thumbnails will be DELETED!</strong><br>Selected thumbnail sizes will be generated.';
        
        const popupHtml = 
            '<div id="pim-reload-popup-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content" style="max-width: 500px;">' +
            '<h2 style="margin: 0 0 20px 0; color: #2271b1;">🔄 Replace this image?</h2>' +
            '<p style="margin-bottom: 15px;">' + message + '</p>' +
            '<p style="background: #f0f0f1; padding: 12px; border-radius: 4px; margin: 20px 0;"><strong>File:</strong> ' + file.name + '</p>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-reload-popup-cancel" class="button">Cancel</button>' +
            '<button id="pim-reload-popup-confirm" class="button button-primary">✅ Replace Image</button>' +
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
    
    /**
     * ============================================
     * FIX ELEMENTOR ID CONFIRM
     * ============================================
     */
    function showFixIdConfirm(imageId, onConfirm) {
        const popupHtml = 
            '<div id="pim-fixid-popup-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content" style="max-width: 500px;">' +
            '<h2 style="margin: 0 0 20px 0; color: #2271b1;">🔧 Fix Elementor ID?</h2>' +
            '<p style="margin-bottom: 15px;">This will search Elementor data for image <strong>#' + imageId + '</strong> URL and update it with the correct ID.</p>' +
            '<p style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 12px; margin: 20px 0; font-size: 13px;">💡 <strong>Use this when:</strong> Elementor widgets show wrong image due to incorrect attachment ID.</p>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-fixid-popup-cancel" class="button">Cancel</button>' +
            '<button id="pim-fixid-popup-confirm" class="button button-primary">✅ Fix ID</button>' +
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
    
    /**
     * ============================================
     * DELETE ALL THUMBNAILS CONFIRM
     * ============================================
     */
    function showDeleteAllConfirm(imageId, onConfirm) {
        const popupHtml = 
            '<div id="pim-delete-popup-overlay" class="pim-popup-overlay">' +
            '<div class="pim-popup-content" style="max-width: 500px;">' +
            '<h2 style="margin: 0 0 20px 0; color: #d63638;">🗑️ Delete ALL thumbnails?</h2>' +
            '<p style="margin-bottom: 15px;">This will permanently delete <strong>ALL thumbnails</strong> for image <strong>#' + imageId + '</strong>.</p>' +
            '<p style="background: #fef0f0; border-left: 4px solid #d63638; padding: 12px; margin: 20px 0; font-size: 13px; color: #721c24;"><strong>⚠️ Warning:</strong> This action cannot be undone! The original image will remain, but all generated thumbnails will be deleted.</p>' +
            '<div class="pim-popup-actions">' +
            '<button id="pim-delete-popup-cancel" class="button">Cancel</button>' +
            '<button id="pim-delete-popup-confirm" class="button button-primary" style="background: #d63638; border-color: #d63638;">🗑️ Delete All</button>' +
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
    
    /**
     * ============================================
     * ADD CUSTOM SOURCE DIALOG
     * ============================================
     */
    function showAddCustomSource(onConfirm) {
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
        
        // Cancel
        $('#pim-custom-source-cancel').on('click', function() {
            $('#pim-custom-source-overlay').remove();
        });
        
        // Save
        $('#pim-custom-source-save').on('click', function() {
            const sourceName = $('#pim-custom-source-input').val().trim();
            
            if (!sourceName) {
                PIM_Toast.warning('Please enter a source name');
                return;
            }
            
            // Validate format
            if (!/^[a-z0-9-]+$/.test(sourceName)) {
                PIM_Toast.error('Use only lowercase letters, numbers, and hyphens');
                return;
            }
            
            $('#pim-custom-source-overlay').remove();
            
            // Save to backend
            PIM_Core.ajax('save_custom_source', {source_name: sourceName},
                function(data) {
                    // Add to global cache
                    if (!window.PIM_CustomSources) {
                        window.PIM_CustomSources = [];
                    }
                    if (!window.PIM_CustomSources.includes(sourceName)) {
                        window.PIM_CustomSources.push(sourceName);
                    }
                    
                    PIM_Toast.success('Custom source "' + sourceName + '" saved!');
                    onConfirm(sourceName);
                },
                function(error) {
                    PIM_Toast.error('Failed to save custom source: ' + error);
                }
            );
        });
        
        // Enter key to save
        $('#pim-custom-source-input').on('keypress', function(e) {
            if (e.key === 'Enter') {
                $('#pim-custom-source-save').click();
            }
        });
        
        // Overlay click
        $('#pim-custom-source-overlay').on('click', function(e) {
            if (e.target.id === 'pim-custom-source-overlay') {
                $(this).remove();
            }
        });
        
        // ESC key
        $(document).on('keydown.pim-custom-source', function(e) {
            if (e.key === 'Escape') {
                $('#pim-custom-source-overlay').remove();
                $(document).off('keydown.pim-custom-source');
            }
        });
    }
    
    /**
     * ============================================
     * LINK & GENERATE DIALOG (with table)
     * ============================================
     */
    function showLinkAndGenerate(data, onConfirm) {
        const {primaryId, primaryFile, currentPage, details, ghostFiles, sourceMappings} = data;
        
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
        
        // Cancel
        $('#pim-link-popup-cancel').on('click', function() {
            $('#pim-link-popup-overlay').remove();
        });
        
        // Confirm
        $('#pim-link-popup-confirm').on('click', function() {
            // Collect source selections
            const selectedSources = collectSourceSelections();
            $('#pim-link-popup-overlay').remove();
            onConfirm(selectedSources);
        });
        
        // Overlay click
        $('#pim-link-popup-overlay').on('click', function(e) {
            if (e.target.id === 'pim-link-popup-overlay') {
                $(this).remove();
            }
        });
        
        // ESC key
        $(document).on('keydown.pim-link-popup', function(e) {
            if (e.key === 'Escape') {
                $('#pim-link-popup-overlay').remove();
                $(document).off('keydown.pim-link-popup');
            }
        });
        
        // Handle custom source dropdown
        $('.pim-source-select').on('change', handleSourceDropdownChange);
    }
    
    /**
     * Helper: Render source dropdown
     */
    function renderSourceDropdown(detail) {
        const builtInSources = ['hero', 'carousel', 'background', 'image', 'gallery', 'content', 'avatar', 'icon', 'logo'];
        const customSources = window.PIM_CustomSources || [];
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
     * Helper: Collect source selections
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
    
    /**
     * Helper: Handle custom source dropdown
     */
    function handleSourceDropdownChange() {
        const $select = $(this);
        const value = $select.val();
        
        if (value === '__custom__') {
            showAddCustomSource(function(sourceName) {
                // Add to dropdown
                const $option = $('<option value="' + sourceName + '" selected>' + sourceName + '</option>');
                $select.find('option[value="__custom__"]').before($option);
                $select.val(sourceName);
            });
        }
    }
    
    // ============================================
    // Return all functions as public API
    // ============================================
    return {
        confirm,
        showDeleteGhost,
        showReloadConfirm,
        showFixIdConfirm,
        showDeleteAllConfirm,
        showAddCustomSource,
        showLinkAndGenerate
    };
    
})(jQuery);

window.PIM_Dialog = PIM_Dialog;