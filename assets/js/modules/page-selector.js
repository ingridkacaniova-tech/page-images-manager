/**
 * Page Selector Module
 * Handles page selection and image loading
 * 
 * ✅ TODO 16: Stores debug data for Debug Log View
 */

window.PIM_PageSelector = (function($) {
    'use strict';
    
    // ✅ Store debug data globally for Debug Log View
    let currentDebugData = null;
    
    function init() {
        // Enable/disable Load Images button
        $('#page-selector').on('change', function() {
            const pageId = $(this).val();
            $('#load-images-btn').prop('disabled', !pageId);
            $('#export-elementor-json-btn').prop('disabled', !pageId);
        });
        
        // Export Elementor JSON button
        $('#export-elementor-json-btn').on('click', function() {
            const pageId = $('#page-selector').val();
            
            if (!pageId) {
                PIM_Toast.warning('Please select a page');
                return;
            }
            
            const btn = $(this);
            btn.prop('disabled', true);
            
            PIM_Core.ajax('export_elementor_json', { page_id: pageId },
                function(data) {
                    // Create download link
                    const blob = new Blob([data.json], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = data.filename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    PIM_Toast.success('✅ Elementor JSON exported!');
                    btn.prop('disabled', false);
                },
                function(error) {
                    PIM_Toast.error('❌ Export failed: ' + error);
                    btn.prop('disabled', false);
                }
            );
        });
        
        // Load images button
        $('#load-images-btn').on('click', function() {
            const pageId = $('#page-selector').val();
            const button = $(this);
            const status = $('#load-status');
            
            if (!pageId) {
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.warning('Please select a page');
                } else {
                    alert('⚠️ Please select a page');
                }
                return;
            }
            
            button.prop('disabled', true).text('Loading...');
            // ✅ ISSUE 39: Show processing toast
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.info('Processing... refreshing images...');
            }

            status.html('<span style="color: #666;">Loading images...</span>');
            
            $.ajax({
                url: pimData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'load_page_images_from_saved_data',
                    page_id: pageId,
                    nonce: pimData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#images-list').html(response.data.html);
                        $('#images-section').slideDown();
                        status.html('<span style="color: green;">✔ Found ' + response.data.count + ' images</span>');
                        
                        // ✅ TODO 16: Store debug data
                        if (response.data.debug_data) {
                            currentDebugData = response.data.debug_data;
                        }
                        
                        if (typeof PIM_Toast !== 'undefined') {
                            PIM_Toast.success('Loaded ' + response.data.count + ' images');
                        }
                        
                        // Trigger event for other modules
                        $(document).trigger('pim:imagesLoaded');
                        // ✅ ISSUE 37: Restore debug buttons visibility after load
                        if ($('#pim-show-debug-toggle').is(':checked')) {
                            $('.pim-debug-only').show();
                        }
                    } else {
                        // ✅ ZMENA: Handle error with dialog
                        status.html('<span style="color: red;">✗ Error: ' + response.data + '</span>');
                        
                        // Check if it's "no scan data" error
                        if (response.data && response.data.error_code === 'no_scan_data') {
                            if (typeof PIM_Dialog !== 'undefined') {
                                PIM_Dialog.confirm({
                                    title: '⚠️ Scan Required',
                                    message: response.data.message || 'Please run "Collect Images from All Pages & Save" first.',
                                    confirmText: 'OK',
                                    onConfirm: function() {}
                                });
                            } else {
                                alert('⚠️ ' + response.data.message);
                            }
                        } else {
                            // Other errors - show toast
                            if (typeof PIM_Toast !== 'undefined') {
                                PIM_Toast.error('Error: ' + (response.data.message || response.data));
                            } else {
                                alert('❌ Error: ' + response.data);
                            }
                        }
                    }
                    button.prop('disabled', false).text('Load Images');
                },
                error: function(xhr, textStatus, error) {
                    $('#load-status').html('<span style="color: red;">✗ AJAX error</span>');
                    
                    if (typeof PIM_Toast !== 'undefined') {
                        PIM_Toast.error('AJAX error: ' + error);
                    } else {
                        alert('❌ AJAX error: ' + error);
                    }
                    
                    button.prop('disabled', false).text('Load Images');
                }
            });
        });
    }
    
    /**
     * ✅ Get stored debug data
     */
    function getDebugData() {
        return currentDebugData;
    }
    
    return {
        init: init,
        getDebugData: getDebugData
    };
    
})(jQuery);