/**
 * Core Module - FINAL VERSION
 * ✅ NEW: updateSingleImageRow() helper for efficient updates
 */

const PIM_Core = (function($) {
    'use strict';
    
    // STATE MANAGEMENT
    const state = {
        currentPageId: null,
        isLoading: false
    };
    
    // AJAX HELPERS
    function ajax(action, data, successCallback, errorCallback) {
        const ajaxData = {
            action: action,
            nonce: pimData.nonce,
            ...data
        };
        
        return $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: ajaxData,
            success: function(response) {
                if (response.success) {
                    if (successCallback) successCallback(response.data);
                } else {
                    const errorMsg = response.data || 'Unknown error';
                    if (errorCallback) {
                        errorCallback(errorMsg);
                    } else {
                        PIM_Toast.error(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                const errorMsg = 'AJAX request failed: ' + error;
                if (errorCallback) {
                    errorCallback(errorMsg);
                } else {
                    PIM_Toast.error(errorMsg);
                }
            }
        });
    }
    
    /**
     * ✅ ISSUE 35 & 36 FIX: 
     * Create NEW FormData, add action FIRST, then copy original FormData
     */
    function ajaxUpload(action, formData, successCallback, errorCallback) {
        console.log('\n🔹 === AJAX UPLOAD DEBUG START ===');
        console.log('📤 Action:', action);
        console.log('📤 AJAX URL:', pimData.ajaxurl);
        
        const newFormData = new FormData();
        
        newFormData.append('action', action);
        newFormData.append('nonce', pimData.nonce);
        
        console.log('✅ Added action:', action);
        console.log('✅ Added nonce:', pimData.nonce);
        
        console.log('📋 Original FormData entries:');
        for (const [key, value] of formData.entries()) {
            if (value instanceof File) {
                console.log(`  - ${key}: [File] ${value.name} (${value.size} bytes)`);
            } else {
                console.log(`  - ${key}:`, value);
            }
            newFormData.append(key, value);
        }
        
        console.log('\n📦 Final FormData contents:');
        for (const [key, value] of newFormData.entries()) {
            if (value instanceof File) {
                console.log(`  ✔ ${key}: [File] ${value.name} (${value.size} bytes)`);
            } else {
                console.log(`  ✔ ${key}:`, value);
            }
        }
        console.log('🔹 === AJAX UPLOAD DEBUG END ===\n');
        
        return $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: newFormData,
            processData: false,
            contentType: false,
            beforeSend: function(xhr, settings) {
                console.log('🚀 Sending AJAX request...');
                console.log('   URL:', settings.url);
                console.log('   Type:', settings.type);
            },
            success: function(response) {
                console.log('✅ === AJAX SUCCESS ===');
                console.log('Full response:', response);
                
                if (response.success) {
                    console.log('✅ Success data:', response.data);
                    
                    if (response.data.generated_thumbnails) {
                        console.log('\n🖼️ Generated Thumbnails:');
                        response.data.generated_thumbnails.forEach(function(thumb) {
                            console.log(`  ✔ ${thumb.name}: ${thumb.file} (${thumb.width}×${thumb.height}, ${thumb.size_kb} KB)`);
                        });
                        console.log(`📊 Total: ${response.data.total_thumbnails} thumbnail(s)`);
                        console.log(`📁 Main file: ${response.data.main_file}`);
                        console.log(`📐 Was scaled: ${response.data.was_scaled ? 'YES' : 'NO'}`);
                    }
                    
                    if (successCallback) successCallback(response.data);
                } else {
                    const errorMsg = response.data || 'Upload failed';
                    console.error('❌ Server returned error:', errorMsg);
                    if (errorCallback) {
                        errorCallback(errorMsg);
                    } else {
                        PIM_Toast.error(errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('❌ === AJAX ERROR ===');
                console.error('   Status:', status);
                console.error('   Error:', error);
                console.error('   Response Code:', xhr.status);
                console.error('   Response Text:', xhr.responseText);
                console.error('   Headers:', xhr.getAllResponseHeaders());
                console.error('======================');
                
                const errorMsg = 'Upload failed: ' + error;
                if (errorCallback) {
                    errorCallback(errorMsg);
                } else {
                    PIM_Toast.error(errorMsg);
                }
            }
        });
    }
    
    // ✅ NEW: Update single image row efficiently
    function updateSingleImageRow(imageId, callback) {
        const pageId = getCurrentPageId();
        
        if (!pageId || !imageId) {
            console.error('updateSingleImageRow: Missing page_id or image_id');
            if (callback) callback(false);
            return;
        }


        // ✅ LOG JS STACK TO PHP (ASYNC, NON-BLOCKING)
        // Ak zlyhá, nevadí - nedržíme execution
        const stack = new Error().stack;
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'log_js_stack',
                message: '🔄 updateSingleImageRow called for image #' + imageId,
                stack: stack,
                nonce: pimData.nonce
            },
            async: true,  // ✅ ASYNC - nedržíme execution
            timeout: 2000, // ✅ Max 2s
            error: function() {
                // ✅ TICHÝ FAIL - len console warning
                console.warn('JS stack logging failed (non-critical)');
            }
        });
        
        console.log('🔄 Updating single row for image #' + imageId);
        
        // ✅ Hlavná logika pokračuje BEZ ČAKANIA na logging
        ajax('get_single_image_row',
            {
                image_id: imageId,
                page_id: pageId
            },
            function(data) {
                const $oldRow = $('.pim-image-row[data-image-id="' + imageId + '"]');
                if ($oldRow.length === 0) {
                    console.warn('Old row not found for image #' + imageId);
                    if (callback) callback(false);
                    return;
                }
                
                // Replace old row with new HTML
                const $newRow = $(data.html);
                $oldRow.replaceWith($newRow);
                
                console.log('✅ Row updated for image #' + imageId);
                if (callback) callback(true);
            },
            function(error) {
                console.error('Failed to update row:', error);
                if (callback) callback(false);
            }
        );
    }

    
    // UTILITIES
    function getCurrentPageId() {
        return $('#page-selector').val() || state.currentPageId;
    }
    
    function setCurrentPageId(pageId) {
        state.currentPageId = pageId;
    }
    
    function showLoading(message) {
        state.isLoading = true;
        $('#images-section').fadeTo(300, 0.3);
        if (message) {
            $('#load-status').html('<span style="color: #666;">' + message + '</span>');
        }
    }
    
    function hideLoading() {
        state.isLoading = false;
        $('#images-section').fadeTo(300, 1);
    }
    
    function refreshImages(callback) {
        const pageId = getCurrentPageId();
        
        if (!pageId) {
            PIM_Toast.warning('No page selected');
            return;
        }
        
        showLoading('Refreshing images...');
        
        ajax('load_page_images_from_saved_data', { page_id: pageId }, 
            function(data) {
                $('#images-list').html(data.html);
                hideLoading();
                
                $(document).trigger('pim:imagesRefreshed');
                
                if (callback) callback(data);
            },
            function(error) {
                PIM_Toast.error('Error refreshing images: ' + error);
                hideLoading();
            }
        );
    }

    function collectSourceMappings($row) {
        const sourceMappings = {};
        const missingSources = [];
        
        $row.find('.pim-source-group').each(function() {
            const source = $(this).find('.pim-source-name').text().replace(':', '').trim();
            const selectedSize = $(this).find('input[type="radio"]:checked').val();
            
            if (selectedSize && selectedSize !== 'non-standard') {
                sourceMappings[source] = selectedSize;
            } else if (selectedSize === 'non-standard') {
                sourceMappings[source] = 'non-standard';
            } else {
                missingSources.push(source);
            }
        });
        
        return { sourceMappings, missingSources };
    }
    
    function createFileInput(accept, callback) {
        const $input = $('<input type="file" style="display:none">');
        if (accept) $input.attr('accept', accept);
        
        $('body').append($input);
        $input.click();
        
        $input.on('change', function() {
            const file = this.files[0];
            $input.remove();
            if (file && callback) callback(file);
        });
        
        return $input;
    }
    
    // PUBLIC API
    return {
        ajax,
        ajaxUpload,
        updateSingleImageRow,
        getCurrentPageId,
        setCurrentPageId,
        showLoading,
        hideLoading,
        refreshImages,
        collectSourceMappings,
        createFileInput,
        state
    };
    
})(jQuery);

window.PIM_Core = PIM_Core;