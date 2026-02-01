/**
 * Page Images Manager - Main Entry Point
 * Orchestrates all modules
 */
    jQuery(document).ready(function($) {
    'use strict';
    
    console.log('🚀 Page Images Manager - Initializing...');
    
    // ============================================
    // GLOBAL DATA STORAGE
    // ============================================
        window.PIM = window.PIM || {};
        window.PIM.globalData = {
            lastScan: null,
            imageDetails: {}
    };
    
    // ============================================
    // INITIALIZE ALL MODULES
    // ============================================
    
    // Check if modules are loaded
    const modules = [
        'PIM_Toast',              // ✅ FIXED: Correct name
        'PIM_Core',               // ✅ FIXED: Correct name
        'PIM_Dialog',
        'PIM_PageSelector',
        'PIM_CollapsibleSections',
        'PIM_ThumbnailGeneration',
        'PIM_ImageActions',
        'PIM_DuplicateHandling',
        'PIM_DuplicateDialog',
        'PIM_MissingImages',
        'PIM_DebugLog',
        'PIM_LockHandling'
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
    // GLOBAL CONTROLS - SCAN BUTTONS
    // ============================================

    // ============================================
    // ✅ ISSUE 60: SCAN BUTTON - WITH STYLED PROGRESS
    // ============================================

    $('#collect-images-btn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true);
        
        // ✅ Show progress toast WITH STYLES
        // Available styles: gradient, sparkle, percent, hourglass, wave, pulse, color
        const toastId = PIM_Toast.progress(
            'Starting scan...', 
            0, 
            'gradient+sparkle+hourglass+percent'  // ✅ Apply multiple styles
        );
        
        // ✅ Start batch processing
        scanAllPagesWithProgress(toastId, function(success) {
            if (success) {
                PIM_Toast.closeProgress(toastId);
                PIM_Toast.success('Scan complete! Check "Last Scan Info" for details.');
                loadScanInfo();
            } else {
                PIM_Toast.closeProgress(toastId);
                PIM_Toast.error('❌ Scan failed!');
            }
            btn.prop('disabled', false);
        });
    });

    // ============================================
    // ✅ ISSUE -1: REPAIR ELEMENTOR URLS BUTTON
    // ============================================
    
    $('#repair-elementor-btn').click(function() {
        const btn = $(this);
        
        if (!confirm('⚠️ This will repair Elementor URLs across all pages.\n\nIt will fix URLs that lost the "-scaled" suffix or have incorrect dimensions.\n\nContinue?')) {
            return;
        }
        
        btn.prop('disabled', true);
        const toastId = PIM_Toast.progress('Repairing Elementor URLs...', 0, 'wave+pulse');
        
        PIM_Core.ajax('repair_elementor_urls', {}, 
            function(data) {
                PIM_Toast.closeProgress(toastId);
                PIM_Toast.success(`${data.message || 'Repair complete!'}`);
                btn.prop('disabled', false);
            },
            function(error) {
                PIM_Toast.closeProgress(toastId);
                PIM_Toast.error(`❌ Repair failed: ${error}`);
                btn.prop('disabled', false);
            }
        );
    });

    /**
     * ✅ Scan all pages with progress updates
     */
    function scanAllPagesWithProgress(toastId, callback) {
        // Step 1: Get total pages count
        PIM_Core.ajax('get_total_pages', {}, 
            function(data) {
                const totalPages = data.total;
                PIM_Toast.updateProgress(toastId, `Scanning... 0/${totalPages} pages`, 0);
                
                // Step 2: Call single batch scan (WordPress can handle it)
                scanSingleBatch(toastId, totalPages, callback);
            },
            function(error) {
                console.error('Failed to get total pages:', error);
                callback(false);
            }
        );
    }

    /**
     * ✅ Execute single batch scan with progress simulation
     * 
     * WHY SIMULATION?
     * - Backend processes all pages in ONE request
     * - We can't track real progress (PHP doesn't report back during execution)
     * - So we SIMULATE progress to show user something is happening
     * - When AJAX completes, we jump to 100%
     */
    function scanSingleBatch(toastId, totalPages, callback) {
        const startTime = Date.now();
        let progressInterval; // ✅ Declare here so we can clear it later
        
        // ✅ Start actual scan (SINGLE AJAX REQUEST)
        PIM_Core.ajax('collect_base_images_data_from_all_pages', {}, 
            function(data) {
                // ✅ CRITICAL: Clear interval when AJAX completes
                clearInterval(progressInterval);
                
                // Success - jump to 100%
                PIM_Toast.updateProgress(toastId, 
                    `Scan complete! ${data.total_pages} pages, ${data.total_images} images (${data.duration}s)`, 
                    100
                );
                
                // Small delay before closing toast
                setTimeout(function() {
                    callback(true);
                }, 500);
            },
            function(error) {
                // ✅ CRITICAL: Clear interval on error too!
                clearInterval(progressInterval);
                
                console.error('Scan failed:', error);
                callback(false);
            }
        );
        
        // ✅ SIMULATE progress during scan (since we can't track real progress)
        // This runs WHILE the AJAX request is processing
        let simulatedProgress = 0;
        progressInterval = setInterval(function() {
            simulatedProgress += 5; // Increase by 5% every 500ms
            
            // Stop at 95% (we jump to 100% when AJAX completes)
            if (simulatedProgress >= 95) {
                clearInterval(progressInterval);
                return;
            }
            
            // ✅ Calculate current/remaining pages based on progress
            const current = Math.round((simulatedProgress / 100) * totalPages);
            const remaining = totalPages - current;
            
            PIM_Toast.updateProgress(toastId, 
                `Scanning... ${current}/${totalPages} pages (${remaining} remaining)`, 
                simulatedProgress
            );
        }, 500); // Update every 500ms
    }

    // 📊 Show Last Scan Info
    $('#show-scan-info-btn').click(function() {
        if (window.PIM.globalData.lastScan) {
            console.log('📊 Last Scan Info:', window.PIM.globalData.lastScan);
            console.table(window.PIM.globalData.lastScan);
            PIM_Toast.info('Check Console (F12) for detailed scan info');
        } else {
            PIM_Toast.warning('No scan data available. Click "Collect Images from All Pages" first.');
        }
    });

    // 💾 TODO 67: Save Cache & Database to Files
    $('#save-list-to-file-btn').click(function() {
        const btn = $(this);
        btn.prop('disabled', true);
        
        PIM_DebugLog.log('TODO 67: Export button clicked');
        
        let cacheExported = false;
        let databaseExported = false;
        
        // Export 1: Cache data
        PIM_DebugLog.log('TODO 67: Calling export_cache_to_json...');
        PIM_Core.ajax('export_cache_to_json', {},
            function(data) {
                PIM_DebugLog.log('TODO 67: Cache export success', data);
                cacheExported = true;
                
                // Download cache file
                downloadFile(data.download_url, data.filename);
                
                // Show success toast
                PIM_Toast.success(`Cache data saved: ${data.filename}`);
                
                // Check if both exports are done
                if (cacheExported && databaseExported) {
                    btn.prop('disabled', false);
                    PIM_Toast.success('Both files exported successfully!');
                }
            },
            function(error) {
                PIM_DebugLog.log('TODO 67: Cache export failed', error);
                PIM_Toast.error(`❌ Cache export failed: ${error}`);
                btn.prop('disabled', false);
            }
        );
        
        // Export 2: Database data
        PIM_DebugLog.log('TODO 67: Calling export_database_to_json...');
        PIM_Core.ajax('export_database_to_json', {},
            function(data) {
                PIM_DebugLog.log('TODO 67: Database export success', data);
                databaseExported = true;
                
                // Download database file
                downloadFile(data.download_url, data.filename);
                
                // Show success toast
                PIM_Toast.success(`Database data saved: ${data.filename} (${data.total_images} images)`);
                
                // Check if both exports are done
                if (cacheExported && databaseExported) {
                    btn.prop('disabled', false);
                    PIM_Toast.success('Both files exported successfully!');
                }
            },
            function(error) {
                PIM_DebugLog.log('TODO 67: Database export failed', error);
                PIM_Toast.error(`❌ Database export failed: ${error}`);
                btn.prop('disabled', false);
            }
        );
    });
    
    /**
     * ✅ TODO 67: Helper function to download file
     */
    function downloadFile(url, filename) {
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.target = '_blank';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        PIM_DebugLog.log('TODO 67: Download triggered', {url: url, filename: filename});
    }

    // ============================================
    // LOAD SCAN INFO FROM DB (on page load)
    // ============================================
    function loadScanInfo() {
        PIM_Core.ajax('get_latest_scan', {}, function(data) {
            window.PIM.globalData.lastScan = data;
            
            $('#scan-timestamp').text(data.timestamp || 'Never');
            $('#scan-user').text(data.user || '—');
            $('#scan-duration').text(data.duration ? data.duration + ' sec' : '—');
            $('#scan-stats').text(
                (data.pages || 0) + ' pages, ' + 
                (data.images || 0) + ' images'
            );
        }, function(error) {
            console.warn('No scan data found:', error);
        });
    }

    // Load scan info on page load
    loadScanInfo();
    
    // ============================================
    // GLOBAL FUNCTIONS (for backwards compatibility)
    // ============================================
    
    window.pimRefreshImages = function() {
        PIM_Core.refreshImages();
    };
    
    // ============================================
    // DEVELOPMENT HELPERS
    // ============================================
    
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('local')) {
        console.log('🔧 Development mode active');
        
        window.PIMModules = {
            Toast: PIM_Toast,
            Core: PIM_Core,
            PageSelector: PIM_PageSelector,
            CollapsibleSections: PIM_CollapsibleSections,
            ThumbnailGeneration: PIM_ThumbnailGeneration,
            ImageActions: PIM_ImageActions,
            DuplicateHandling: PIM_DuplicateHandling,
            DuplicateDialog: PIM_DuplicateDialog,
            MissingImages: PIM_MissingImages,
            DebugLog: PIM_DebugLog,
            LockHandling: PIM_LockHandling
        };
        
        console.log('💡 Modules available via window.PIMModules');
        console.log('💡 Global data via window.PIM.globalData');
    }
    
    // ============================================
    // ERROR HANDLING 
    // ============================================
    
    window.addEventListener('error', function(event) {
        console.error('🔴 Unhandled error:', event.error);
    });
    
    window.addEventListener('unhandledrejection', function(event) {
        console.error('🔴 Unhandled promise rejection:', event.reason);
    });
    
    console.log('✅ Page Images Manager ready');
});