/**
 * Debug Log Viewer JavaScript
 * Handles filtering, copying, and interaction with debug log
 */

window.PIM_DebugLogViewer = (function($) {
    'use strict';
    
    let originalLogContent = '';
    
    /**
     * Initialize on DOM ready
     */
    function init() {
        $(document).ready(function() {
            // Store original content when log is loaded
            originalLogContent = $('#log-container').html();
        });
    }
    
    /**
     * Apply filter to log lines
     */
    function applyFilter() {
        const filter = document.getElementById('log-filter').value.trim();
        
        if (!filter) {
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.warning('Please enter a keyword to filter');
            } else {
                alert('⚠️ Please enter a keyword to filter');
            }
            return;
        }
        
        const container = document.getElementById('log-container');
        const lines = container.querySelectorAll('.pim-log-line');
        let visibleCount = 0;
        
        lines.forEach(line => {
            if (line.textContent.toLowerCase().includes(filter.toLowerCase())) {
                line.style.display = 'block';
                visibleCount++;
            } else {
                line.style.display = 'none';
            }
        });
        
        // Show filter results banner
        document.getElementById('filter-results').style.display = 'block';
        document.getElementById('filter-count').textContent = visibleCount;
        document.getElementById('filter-keyword').textContent = filter;
        
        // Scroll to top of log
        container.scrollTop = 0;
    }
    
    /**
     * Clear filter and restore original content
     */
    function clearFilter() {
        document.getElementById('log-filter').value = '';
        const container = document.getElementById('log-container');
        container.innerHTML = originalLogContent;
        document.getElementById('filter-results').style.display = 'none';
        container.scrollTop = 0;
    }
    
    /**
     * Quick filter shortcut
     */
    function quickFilter(keyword) {
        document.getElementById('log-filter').value = keyword;
        applyFilter();
    }

    /**
     * Show last session from last AJAX request start
     */
    function showLastSession() {
        const container = document.getElementById('log-container');
        const lines = container.querySelectorAll('.pim-log-line');
        
        // ✅ Find LAST occurrence of AJAX HANDLER marker
        let lastSessionStartIndex = -1;
        lines.forEach((line, index) => {
            // Each AJAX call starts with this marker
            if (line.textContent.includes('🔗🔗🔗 AJAX HANDLER CALLED')) {
                lastSessionStartIndex = index;  // ← Vždy prepíše na najnovší
            }
        });
        
        if (lastSessionStartIndex === -1) {
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.warning('No AJAX request found in log');
            } else {
                alert('No AJAX request found');
            }
            return;
        }
        
        // Hide all lines before last AJAX request
        let visibleCount = 0;
        lines.forEach((line, index) => {
            if (index >= lastSessionStartIndex) {
                line.style.display = 'block';
                visibleCount++;
            } else {
                line.style.display = 'none';
            }
        });
        
        // Update filter input
        document.getElementById('log-filter').value = 'Last AJAX Request';
        
        // Show filter results banner
        document.getElementById('filter-results').style.display = 'block';
        document.getElementById('filter-count').textContent = visibleCount;
        document.getElementById('filter-keyword').textContent = 'Last AJAX Request (from line ' + (lastSessionStartIndex + 1) + ')';
        
        // Scroll to top of log
        container.scrollTop = 0;
        
        if (typeof PIM_Toast !== 'undefined') {
            PIM_Toast.success('Showing last AJAX request (' + visibleCount + ' lines)');
        }
    }
    
    /**
     * Copy visible log content to clipboard
     */
    function copyToClipboard() {
        const container = document.getElementById('log-container');
        const visibleLines = Array.from(container.querySelectorAll('.pim-log-line'))
            .filter(line => line.style.display !== 'none')
            .map(line => line.textContent)
            .join('\n');
        
        const textToCopy = visibleLines || container.textContent;
        
        navigator.clipboard.writeText(textToCopy).then(() => {
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.success('Copied ' + textToCopy.split('\n').length + ' lines to clipboard!');
            } else {
                alert('✅ Copied ' + textToCopy.split('\n').length + ' lines to clipboard!');
            }
        }).catch(err => {
            // Fallback for older browsers
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            if (typeof PIM_Toast !== 'undefined') {
                PIM_Toast.success('Copied to clipboard!');
            } else {
                alert('✅ Copied to clipboard!');
            }
        });
    }
    
    /**
     * Clear entire debug log file
     */
    function clearLogFile() {
        if (!confirm('Clear entire debug log? This cannot be undone!')) {
            return;
        }
        
        $.ajax({
            url: pimData.ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_debug_log',
                nonce: pimDebugLogNonce
            },
            success: function(response) {
                if (response.success) {
                    if (typeof PIM_Toast !== 'undefined') {
                        PIM_Toast.success('Debug log cleared!');
                    } else {
                        alert('✅ Debug log cleared!');
                    }
                    
                    // Reload debug log
                    if (typeof PIM_DebugLog !== 'undefined') {
                        $('#pim-debug-log-section').html('');
                        $('#pim-show-debug-toggle').prop('checked', false).trigger('change');
                    }
                } else {
                    if (typeof PIM_Toast !== 'undefined') {
                        PIM_Toast.error('Error: ' + response.data);
                    } else {
                        alert('❌ Error: ' + response.data);
                    }
                }
            },
            error: function() {
                if (typeof PIM_Toast !== 'undefined') {
                    PIM_Toast.error('Failed to clear debug log');
                } else {
                    alert('❌ Failed to clear debug log');
                }
            }
        });
    }
    
    // Public API
    return {
        init,
        applyFilter,
        clearFilter,
        quickFilter,
        showLastSession,
        copyToClipboard,
        clearLogFile
    };
    
})(jQuery);

// Initialize when loaded
PIM_DebugLogViewer.init();