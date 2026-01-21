/**
 * Collapsible Sections Module
 * Handles expandable/collapsible sections with localStorage persistence
 * ✅ TODO 38: Added section navigation with up/down arrows
 */

const PIM_CollapsibleSections = (function($) {
    'use strict';
    
    function init() {
        // Set up toggle handlers
        $(document).on('click', '.pim-section-toggle', toggleSection);
        
        // ✅ TODO 38: Section navigation
        $(document).on('click', '.pim-section-nav', navigateSection);
        
        // Restore saved states on page load
        $(document).on('pim:imagesLoaded', restoreStates);
    }
    
    function toggleSection() {
        const $toggle = $(this);
        const sectionId = $toggle.data('section');
        const $content = $('#' + sectionId + '-content');
        
        if (!$content.length) {
            console.warn('Section content not found:', sectionId);
            return;
        }
        
        // Toggle collapsed class
        $toggle.toggleClass('collapsed');
        $content.slideToggle(300);
        
        // Save state to localStorage
        const isCollapsed = $toggle.hasClass('collapsed');
        saveState(sectionId, isCollapsed);
    }
    
    function restoreStates() {
        $('.pim-section-toggle').each(function() {
            const $toggle = $(this);
            const sectionId = $toggle.data('section');
            const savedState = localStorage.getItem('pim_section_' + sectionId);
            
            if (savedState === 'collapsed') {
                $toggle.addClass('collapsed');
                $('#' + sectionId + '-content').hide();
            }
        });
    }
    
    function saveState(sectionId, isCollapsed) {
        localStorage.setItem(
            'pim_section_' + sectionId,
            isCollapsed ? 'collapsed' : 'expanded'
        );
    }
    
    // ✅ TODO 38: Navigate between sections with smooth scroll (DYNAMIC)
    function navigateSection(e) {
        e.stopPropagation(); // Don't trigger section toggle
        
        const direction = $(this).data('direction');
        const $currentSection = $(this).closest('.pim-collapsible-section');
        
        // ✅ Dynamically find all existing sections on page
        const $allSections = $('.pim-collapsible-section');
        const currentIndex = $allSections.index($currentSection);
        
        let targetIndex;
        if (direction === 'down') {
            targetIndex = currentIndex + 1;
        } else if (direction === 'up') {
            targetIndex = currentIndex - 1;
        }
        
        // Check if target section exists
        if (targetIndex >= 0 && targetIndex < $allSections.length) {
            const $targetSection = $allSections.eq(targetIndex);
            if ($targetSection.length) {
                // Smooth scroll to target section
                $('html, body').animate({
                    scrollTop: $targetSection.offset().top - 20
                }, 400);
            }
        }
    }

    
    function expandAll() {
        $('.pim-section-toggle').removeClass('collapsed');
        $('.pim-section-content').slideDown(300);
        
        // Clear all saved states
        $('.pim-section-toggle').each(function() {
            const sectionId = $(this).data('section');
            localStorage.removeItem('pim_section_' + sectionId);
        });
    }
    
    function collapseAll() {
        $('.pim-section-toggle').addClass('collapsed');
        $('.pim-section-content').slideUp(300);
        
        // Save all as collapsed
        $('.pim-section-toggle').each(function() {
            const sectionId = $(this).data('section');
            saveState(sectionId, true);
        });
    }

    // ✅ ISSUE 44: "Show latest debug log" button handler
    $(document).on('click', '.pim-show-debug-log', function() {
        // Ensure debug log is visible
        if (!$('#pim-show-debug-toggle').is(':checked')) {
            $('#pim-show-debug-toggle').prop('checked', true).trigger('change');
        }
        
        // Wait for log to load, then show last session
        setTimeout(function() {
            if (typeof PIM_DebugLogViewer !== 'undefined') {
                PIM_DebugLogViewer.showLastSession();
            }
            
            // Scroll to log
            const debugSection = $('#pim-debug-log-section');
            if (debugSection.length) {
                $('html, body').animate({
                    scrollTop: debugSection.offset().top - 20
                }, 500);
            }
        }, PIM_DebugLog && PIM_DebugLog.isLoaded ? 100 : 1000);
    });
    
    // Public API
    return {
        init,
        expandAll,
        collapseAll,
        restoreStates
    };
})(jQuery);

window.PIM_CollapsibleSections = PIM_CollapsibleSections;
