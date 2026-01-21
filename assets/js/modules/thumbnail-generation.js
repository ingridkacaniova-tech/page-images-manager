/**
 * Thumbnail Generation Module
 * Handles radio button selection, Non-Standard detection, and button text updates
 */

const PIM_ThumbnailGeneration = (function($) {
    'use strict';
    
    function init() {
        // Monitor radio changes
        $(document).on('change', '.size-radio', onRadioChange);
        
        // Apply button text updates after images load
        $(document).on('pim:imagesLoaded pim:imagesRefreshed', updateAllButtonTexts);
    }
    
    function onRadioChange() {
        const $row = $(this).closest('.pim-image-row');
        updateButtonTextForRow($row);
    }
    
    function updateButtonTextForRow($row) {
        const hasNonStandard = $row.find('input[value="non-standard"]:checked').length > 0;
        const $reloadBtn = $row.find('.reload-image-btn');
        
        if (hasNonStandard) {
            $reloadBtn.text('🔄 Load & Relink');
            $reloadBtn.attr('title', 'Upload new file and update Elementor references (no thumbnail generation)');
        } else {
            $reloadBtn.text('🔄 Reload Image');
            $reloadBtn.attr('title', 'Replace image file and regenerate selected thumbnails');
        }
    }
    
    function updateAllButtonTexts() {
        setTimeout(function() {
            $('.pim-image-row').each(function() {
                updateButtonTextForRow($(this));
            });
        }, 300);
    }
    
    function validateSourceMappings($row) {
        const result = PIM_Core.collectSourceMappings($row);
        
        if (result.missingSources.length > 0) {
            // ✅ Toast instead of alert
            PIM_Toast.warning('Please select format for: ' + result.missingSources.join(', '));
            return null;
        }
        
        if (Object.keys(result.sourceMappings).length === 0) {
            // ✅ Toast instead of alert
            PIM_Toast.warning('No thumbnail sizes selected');
            return null;
        }
        
        return result.sourceMappings;
    }
    
    function hasNonStandardSelection($row) {
        return $row.find('input[value="non-standard"]:checked').length > 0;
    }
    
    // Public API
    return {
        init,
        validateSourceMappings,
        hasNonStandardSelection,
        updateButtonTextForRow
    };
    
})(jQuery);

window.PIM_ThumbnailGeneration = PIM_ThumbnailGeneration;