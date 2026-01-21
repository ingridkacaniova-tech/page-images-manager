/**
 * Debug Log Module
 * Handles lazy loading of debug log viewer
 * 
 * FIXES:
 * ✅ TODO 15: Show/hide "View in Log" buttons based on checkbox state
 * ✅ ISSUE 14: Added Reload button
 * ✅ TODO 29: DEBUG INFO section default COLLAPSED
 */

window.PIM_DebugLog = (function($) {
    'use strict';
    
    let debugLogLoaded = false;
    
    function init() {
        // Toggle debug log
        $('#pim-show-debug-toggle').on('change', function() {
            const isChecked = $(this).is(':checked');
            const debugSection = $('#pim-debug-log-section');
            
            if (isChecked) {
                // ✅ TODO 15: Show "View in Log" buttons
                $('.pim-debug-only').fadeIn(200);
                
                if (!debugLogLoaded) {
                    loadDebugLog();
                } else {
                    debugSection.slideDown();
                }
            } else {
                // ✅ TODO 15: Hide "View in Log" buttons
                $('.pim-debug-only').fadeOut(200);
                
                debugSection.slideUp();
            }
        });
        
        // ✅ TODO 40: "Show latest debug log" button - jump AND filter to Last Session
        $(document).on('click', '.pim-section-jump-to-log', function() {
            // Ensure debug log is visible
            if (!$('#pim-show-debug-toggle').is(':checked')) {
                $('#pim-show-debug-toggle').prop('checked', true).trigger('change');
            }
            
            // Wait for log to load, then jump and filter
            setTimeout(function() {
                const debugSection = $('#pim-debug-log-section');
                
                // ✅ Set filter to Last Session
                const $sessionSelect = $('#pim-session-filter');
                if ($sessionSelect.length) {
                    $sessionSelect.val('last').trigger('change');
                }
                
                // Scroll to log
                $('html, body').animate({
                    scrollTop: debugSection.offset().top - 20
                }, 500);
            }, debugLogLoaded ? 100 : 1000);
        });

    }
    
    function loadDebugLog() {
        const debugSection = $('#pim-debug-log-section');
        
        debugSection.html('<div style="padding: 40px; text-align: center;"><span class="spinner is-active" style="float: none; margin: 0 auto;"></span><p>Loading debug log...</p></div>');
        debugSection.slideDown();
        
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_debug_log',
                nonce: pimDebugLogNonce
            },
            success: function(response) {
                if (response.success) {
                    // ✅ TODO 29: Inject DEBUG INFO box COLLAPSED by default
                    let debugInfoHTML = renderDebugInfo();
                    debugSection.html(debugInfoHTML + response.data.html);
                    debugLogLoaded = true;
                    
                    // Attach reload handler
                    attachReloadHandler();
                    
                    // ✅ TODO 29: Attach toggle handler for DEBUG INFO
                    attachDebugInfoToggle();
                } else {
                    debugSection.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                }
            },
            error: function() {
                debugSection.html('<div class="notice notice-error"><p>Failed to load debug log.</p></div>');
            }
        });
    }
    
    /**
     * ✅ TODO 29: Render DEBUG INFO box with COLLAPSIBLE toggle
     */
    function renderDebugInfo() {
        const debugData = PIM_PageSelector.getDebugData();
        
        if (!debugData) {
            return '';
        }
        
        const validCount = debugData.valid_images ? debugData.valid_images.length : 0;
        const missingFilesCount = debugData.missing_files ? debugData.missing_files.length : 0;
        const missingDBCount = debugData.missing_image_ids ? Object.keys(debugData.missing_image_ids).length : 0;
        const totalCount = validCount + missingFilesCount + missingDBCount;
        
        let html = '<div id="pim-debug-info-box" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">';
        
        // ✅ TODO 31: Vertically centered header
        html += '<h3 style="margin: 0; color: #856404; cursor: pointer; user-select: none; display: flex; align-items: center; gap: 8px; line-height: 1.2;" id="pim-debug-info-toggle">';
        html += '<span class="dashicons dashicons-arrow-right" style="font-size: 20px; width: 20px; height: 20px; transition: transform 0.3s; flex-shrink: 0;"></span>';
        html += '<span style="flex: 1;">🛠️ DEBUG INFO (click to expand)</span>';
        html += '</h3>';
        
        // ✅ TODO 29: Content HIDDEN by default
        html += '<div id="pim-debug-info-content" style="display: none;">';
        html += '<pre style="background: white; padding: 10px; max-height: 400px; overflow: auto; margin: 0;">';
        html += 'SUMMARY:\n';
        html += '--------\n';
        html += 'Valid Images (exist in DB + disk): ' + validCount + '\n';
        html += 'Missing Files (in DB but no file): ' + missingFilesCount + '\n';
        html += 'Missing in DB (file exists, no DB record): ' + missingDBCount + '\n';
        html += 'Total: ' + totalCount + '\n\n';
        
        html += 'ELEMENTOR:\n';
        html += '----------\n';
        if (debugData.debug_info) {
            html += JSON.stringify(debugData.debug_info, null, 2);
        }
        
        html += '</pre></div>';
        html += '</div>';
        
        return html;
    }
    
    /**
     * ✅ TODO 29: Toggle DEBUG INFO collapse/expand
     */
    function attachDebugInfoToggle() {
        $(document).on('click', '#pim-debug-info-toggle', function() {
            const $content = $('#pim-debug-info-content');
            const $icon = $(this).find('.dashicons');
            
            if ($content.is(':visible')) {
                // Collapse
                $content.slideUp(300);
                $icon.css('transform', 'rotate(0deg)');
                $(this).html('<span class="dashicons dashicons-arrow-right" style="font-size: 20px; width: 20px; height: 20px; transition: transform 0.3s; flex-shrink: 0;"></span><span style="flex: 1;">🛠️ DEBUG INFO (click to expand)</span>');
            } else {
                // Expand
                $content.slideDown(300);
                $icon.css('transform', 'rotate(90deg)');
                $(this).html('<span class="dashicons dashicons-arrow-right" style="font-size: 20px; width: 20px; height: 20px; transition: transform 0.3s; transform: rotate(90deg); flex-shrink: 0;"></span><span style="flex: 1;">🛠️ DEBUG INFO (click to collapse)</span>');
            }
        });
    }
    
    /**
     * Attach Reload button handler
     */
    function attachReloadHandler() {
        $(document).on('click', '[onclick*="reloadDebugLog"]', function(e) {
            e.preventDefault();
            reloadDebugLog();
        });
    }
    
    /**
     * Reload debug log content
     */
    function reloadDebugLog() {
        const debugSection = $('#pim-debug-log-section');
        const $logContent = $('#log-container');
        
        if (!$logContent.length) {
            console.warn('Debug log container not found');
            return;
        }
        
        // Show loading state
        const originalContent = $logContent.html();
        $logContent.html('<div style="padding: 40px; text-align: center; color: #d4d4d4;"><span class="spinner is-active" style="float: none;"></span><p>Reloading log...</p></div>');
        
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'load_debug_log',
                nonce: pimDebugLogNonce
            },
            success: function(response) {
                if (response.success) {
                    // Replace entire section with fresh content
                    debugSection.html(response.data.html);
                    
                    // Re-attach reload handler
                    attachReloadHandler();
                    
                    // Show success toast
                    if (typeof PIM_Toast !== 'undefined') {
                        PIM_Toast.success('Debug log reloaded');
                    }
                } else {
                    $logContent.html(originalContent);
                    if (typeof PIM_Toast !== 'undefined') {
                        PIM_Toast.error('Failed to reload: ' + response.data);
                    }
                }
            },
            error: function() {
                $logContent.html(originalContent);
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.error('AJAX error while reloading log');
                }
            }
        });
    }
    
    /**
     * Log message from JavaScript to PHP debug.log
     */
    function log(message, data) {
        // Fire and forget - don't wait for response
        $.post(pimData.ajaxurl, {
            action: 'log_js_stack',
            nonce: pimData.nonce,
            message: message,
            stack: data ? JSON.stringify(data, null, 2) : ''
        });
    }

    return {
        init: init,
        reload: reloadDebugLog,
        log: log  // ← PRIDAJ
    };
    
})(jQuery);