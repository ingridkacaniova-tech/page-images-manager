/**
 * Lock Handling Module
 * ✅ Lock/unlock per source group (background, carousel, etc.)
 * Click on 🔒/🔓 icon in bottom-right corner of EACH group
 */

window.PIM_LockHandling = (function($) {
    'use strict';
    
    function init() {
        // Click on icon in bottom-right corner of source group
        $(document).on('click', '.pim-source-group', function(e) {
            const $group = $(this);
            const offset = $group.offset();
            const x = e.pageX - offset.left;
            const y = e.pageY - offset.top;
            const width = $group.outerWidth();
            const height = $group.outerHeight();
            
            // Check if click is in BOTTOM-RIGHT corner (25x25px area where icon is)
            if (x > width - 25 && y > height - 25) {
                e.stopPropagation(); // Prevent radio button clicks
                
                const isLocked = $group.hasClass('locked');
                const $row = $group.closest('.pim-image-row');
                const imageId = $row.data('image-id');
                const pageId = $('#page-selector').val();
                const source = $group.find('.pim-source-name').text().replace(':', '').trim();
                
                if (isLocked) {
                    // UNLOCK this group
                    unlockGroup($group, imageId, pageId, source);
                } else {
                    // LOCK this group
                    lockGroup($group, imageId, pageId, source);
                }
            }
        });
        
        // Visual feedback: cursor pointer over icon area
        $(document).on('mousemove', '.pim-source-group', function(e) {
            const $this = $(this);
            const offset = $this.offset();
            const x = e.pageX - offset.left;
            const y = e.pageY - offset.top;
            const width = $this.outerWidth();
            const height = $this.outerHeight();
            
            // Check if hovering over icon area
            if (x > width - 25 && y > height - 25) {
                $this.css('cursor', 'pointer');
            } else {
                $this.css('cursor', 'default');
            }
        });
        
        // Load lock states on page load
        $(document).on('pim:imagesLoaded pim:imagesRefreshed', loadLockStates);
    }
    
    /**
     * LOCK one source group
     */
    function lockGroup($group, imageId, pageId, source) {
        const selectedSize = $group.find('input[type="radio"]:checked').val();
        
        if (!selectedSize || selectedSize === 'non-standard') {
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.warning('Please select a thumbnail size first for ' + source);
            } else {
                alert('⚠️ Please select a thumbnail size first');
            }
            return;
        }
        
        // Add animation class
        $group.addClass('icon-changing');
        
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'lock_thumbnail_size',
                image_id: imageId,
                page_id: pageId,
                size_name: selectedSize,
                nonce: pimData.nonce
            },
            success: function(response) {
                $group.addClass('locked just-locked');
                
                setTimeout(function() {
                    $group.removeClass('just-locked icon-changing');
                }, 600);
                
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.success('🔒 Locked ' + source + ': ' + selectedSize);
                }
            },
            error: function() {
                $group.removeClass('icon-changing');
                
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.error('Failed to lock ' + source);
                } else {
                    alert('❌ Failed to lock');
                }
            }
        });
    }
    
    /**
     * UNLOCK one source group
     */
    function unlockGroup($group, imageId, pageId, source) {
        // Add animation class
        $group.addClass('icon-changing');
        
        // First, get what's locked for this source
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_lock_status',
                image_id: imageId,
                page_id: pageId,
                nonce: pimData.nonce
            },
            success: function(response) {
                if (response.success && response.data.locked_sizes.length > 0) {
                    const lockedSizes = response.data.locked_sizes;
                    
                    // Find which size corresponds to this source
                    // (simplified - unlock all sizes, could be more specific)
                    let unlockCount = 0;
                    
                    lockedSizes.forEach(function(sizeName) {
                        $.ajax({
                            url: pimData.ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'unlock_thumbnail_size',
                                image_id: imageId,
                                page_id: pageId,
                                size_name: sizeName,
                                nonce: pimData.nonce
                            },
                            success: function() {
                                unlockCount++;
                                
                                if (unlockCount === lockedSizes.length) {
                                    $group.removeClass('locked');
                                    
                                    setTimeout(function() {
                                        $group.removeClass('icon-changing');
                                    }, 400);
                                    
                                    if (typeof PIM_Toast !== 'undefined') {
                                        PIM_Toast.info('🔓 Unlocked ' + source);
                                    }
                                }
                            }
                        });
                    });
                } else {
                    // No locked sizes found
                    $group.removeClass('locked icon-changing');
                }
            },
            error: function() {
                $group.removeClass('icon-changing');
                
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.error('Failed to check lock status');
                }
            }
        });
    }
    
    /**
     * Load lock states for all groups on page
     */
    function loadLockStates() {
        const pageId = $('#page-selector').val();
        
        if (!pageId) return;
        
        $('.pim-source-group').each(function() {
            const $group = $(this);
            const $row = $group.closest('.pim-image-row');
            const imageId = $row.data('image-id');
            
            if (!imageId) return;
            
            $.ajax({
                url: pimData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_lock_status',
                    image_id: imageId,
                    page_id: pageId,
                    nonce: pimData.nonce
                },
                success: function(response) {
                    if (response.success && response.data.locked_sizes.length > 0) {
                        // This image has locked sizes
                        // For now, mark all groups as locked
                        // (could be more granular per source)
                        $group.addClass('locked');
                    } else {
                        $group.removeClass('locked');
                    }
                }
            });
        });
    }
    
    return {
        init: init,
        loadLockStates: loadLockStates
    };
    
})(jQuery);