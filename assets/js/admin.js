/**
 * Page Images Manager - Main Entry Point
 * Orchestrates all modules
 * 
 * File Structure:
 * - core.js - AJAX helpers and utilities
 * - page-selector.js - Page dropdown and Load Images
 * - collapsible-sections.js - Expandable/collapsible UI
 * - thumbnail-generation.js - Radio buttons and format selection
 * - image-actions.js - Reload, Fix ID, Delete actions
 * - duplicate-handling.js - Link & Generate core functionality
 * - duplicate-dialog.js - Enhanced popup dialog (TODO 50B/51)
 * - missing-images.js - Missing files/DB actions
 * - debug-log.js - Debug log viewer
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🚀 Page Images Manager - Initializing...');
    
    // ============================================
    // INITIALIZE ALL MODULES
    // ============================================
    
    // Check if modules are loaded
    const modules = [
        'PIM_Toast',              // ✅ ISSUE 25: Added first
        'PIM_Core',
        'PIM_Dialog',             // ✅ TODO 54: NEW - dialog helpers
        'PIM_PageSelector',
        'PIM_CollapsibleSections',
        'PIM_ThumbnailGeneration',
        'PIM_ImageActions',
        'PIM_DuplicateHandling',
        'PIM_DuplicateDialog',    // ✅ TODO 50B/51: NEW module
        'PIM_MissingImages',
        'PIM_DebugLog',
        'PIM_LockHandling'        // ✅ NEW
    ];
    
    const missingModules = modules.filter(name => !window[name]);
    
    if (missingModules.length > 0) {
        console.error('❌ Missing modules:', missingModules);
        console.error('Please ensure all module files are loaded in the correct order');
        return;
    }
    
    // Initialize modules in order
    try {
        PIM_PageSelector.init();
        console.log('✓ Page Selector initialized');
        
        PIM_CollapsibleSections.init();
        console.log('✓ Collapsible Sections initialized');
        
        PIM_ThumbnailGeneration.init();
        console.log('✓ Thumbnail Generation initialized');
        
        PIM_ImageActions.init();
        console.log('✓ Image Actions initialized');
        
        PIM_DuplicateHandling.init();
        console.log('✓ Duplicate Handling initialized');
        
        PIM_DuplicateDialog.init();
        console.log('✓ Duplicate Dialog initialized');
        
        PIM_MissingImages.init();
        console.log('✓ Missing Images initialized');
        
        PIM_DebugLog.init();
        console.log('✓ Debug Log initialized');

        PIM_LockHandling.init();
        console.log('✓ Lock Handling initialized');
        
        console.log('✅ All modules initialized successfully');
        
    } catch (error) {
        console.error('❌ Initialization error:', error);
    }
    
    // ============================================
    // GLOBAL FUNCTIONS (for backwards compatibility)
    // ============================================
    
    // Make refreshImages available globally
    window.pimRefreshImages = function() {
        PIM_Core.refreshImages();
    };
    
    // ============================================
    // DEVELOPMENT HELPERS
    // ============================================
    
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('local')) {
        console.log('🔧 Development mode active');
        
        // Expose modules for debugging
        window.PIM_Modules = {
            Toast: PIM_Toast,
            Core: PIM_Core,
            PageSelector: PIM_PageSelector,
            CollapsibleSections: PIM_CollapsibleSections,
            ThumbnailGeneration: PIM_ThumbnailGeneration,
            ImageActions: PIM_ImageActions,
            DuplicateHandling: PIM_DuplicateHandling,
            DuplicateDialog: PIM_DuplicateDialog,  // ✅ TODO 50B/51
            MissingImages: PIM_MissingImages,
            DebugLog: PIM_DebugLog,
            LockHandling: PIM_LockHandling
        };
        
        console.log('💡 Modules available via window.PIM_Modules');
    }
    
    // ============================================
    // ERROR HANDLING
    // ============================================
    
    // Global error handler for unhandled errors
    window.addEventListener('error', function(event) {
        console.error('🔴 Unhandled error:', event.error);
    });
    
    // Promise rejection handler
    window.addEventListener('unhandledrejection', function(event) {
        console.error('🔴 Unhandled promise rejection:', event.reason);
    });
    
    console.log('✅ Page Images Manager ready');
});