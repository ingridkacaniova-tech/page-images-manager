<?php
/**
 * Debug Log Viewer Template
 * Clean HTML template - no inline CSS or JS
 * 
 * Variables available:
 * - $data['lines'] - array of log lines
 * - $data['stats'] - statistics array
 * - $data['total_lines'] - total lines in file
 */

if (!defined('ABSPATH')) exit;

$lines = $data['lines'];
$stats = $data['stats'];
$total_lines = $data['total_lines'];
?>

<div class="pim-debug-log-viewer">
    <h2>🔍 Debug Log Viewer</h2>
    
    <!-- Filter Controls -->
    <div class="pim-debug-controls">
        <input type="text" 
               id="log-filter" 
               class="pim-filter-input"
               placeholder="Type keyword to filter (e.g., PRESELECTING)">
        
        <button type="button" class="button button-primary" onclick="PIM_DebugLogViewer.applyFilter()">
            🔎 Apply Filter
        </button>
        <button type="button" class="button" onclick="PIM_DebugLogViewer.clearFilter()">
            ✖ Clear
        </button>
        <button type="button" class="button button-primary pim-last-session-btn" onclick="PIM_DebugLogViewer.showLastSession()">
            📅 Show Last Session
        </button>       
        <button type="button" class="button" onclick="PIM_DebugLog.reload()">
            🔄 Reload
        </button>
        <button type="button" class="button" onclick="PIM_DebugLogViewer.copyToClipboard()">
            📋 Copy
        </button>
        <button type="button" class="button pim-clear-log-btn" onclick="PIM_DebugLogViewer.clearLogFile()">
            🗑️ Clear Log
        </button>
    </div>
    
    <!-- Statistics Dashboard -->
    <div class="pim-debug-stats">
        <div class="pim-stat-box pim-stat-total">
            <div class="pim-stat-label">Total Lines</div>
            <div class="pim-stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="pim-stat-sublabel">Last 1000 of <?php echo number_format($total_lines); ?></div>
        </div>
        
        <div class="pim-stat-box pim-stat-preselecting">
            <div class="pim-stat-label">Preselections</div>
            <div class="pim-stat-value"><?php echo $stats['preselecting']; ?></div>
        </div>
        
        <div class="pim-stat-box pim-stat-semantic">
            <div class="pim-stat-label">Semantic Matches</div>
            <div class="pim-stat-value"><?php echo $stats['semantic']; ?></div>
        </div>
        
        <div class="pim-stat-box pim-stat-success">
            <div class="pim-stat-label">Success ✅</div>
            <div class="pim-stat-value"><?php echo $stats['success']; ?></div>
        </div>
        
        <div class="pim-stat-box pim-stat-errors">
            <div class="pim-stat-label">Errors ❌</div>
            <div class="pim-stat-value"><?php echo $stats['errors']; ?></div>
        </div>
        
        <div class="pim-stat-box pim-stat-images">
            <div class="pim-stat-label">Images</div>
            <div class="pim-stat-value"><?php echo $stats['images']; ?></div>
        </div>
    </div>
    
    <!-- Quick Filters -->
    <div class="pim-debug-quick-filters">
        <strong>🎯 Quick Filters:</strong>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('PRESELECTING')">
            Show Preselections
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('Semantic match')">
            Semantic Matches
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('Image ID: 10152')">
            IMG_2510
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('Image ID: 10156')">
            DSC7077
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('Image ID: 10158')">
            IMG_8223
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('❌')">
            Errors Only
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('DETECTING THUMBNAILS')">
            Detection Scans
        </button>
        <button type="button" class="button button-small" onclick="PIM_DebugLogViewer.quickFilter('FILESYSTEM')">
            Filesystem
        </button>
    </div>
    
    <!-- Filter Results Info -->
    <div id="filter-results" class="pim-debug-filter-results" style="display: none;">
        <strong>🔍 Filtered Results:</strong> 
        <span id="filter-count">0</span> lines match "<span id="filter-keyword"></span>"
    </div>
    
    <!-- Log Content -->
    <div id="log-container" class="pim-debug-log-content">
        <?php foreach ($lines as $line): ?>
            <?php
            $line_html = esc_html($line);
            
            // Syntax highlighting - PRESELECTING keyword
            if (stripos($line, 'PRESELECTING') !== false) {
                $line_html = preg_replace(
                    '/(PRESELECTING)/i', 
                    '<span class="pim-highlight-preselecting">$1</span>', 
                    $line_html
                );
            }
            
            // Syntax highlighting - Semantic match
            if (stripos($line, 'Semantic match') !== false) {
                $line_html = str_replace(
                    'Semantic match', 
                    '<span class="pim-highlight-semantic">Semantic match</span>', 
                    $line_html
                );
            }
            
            // Color entire line based on emoji
            $line_class = 'pim-log-line';
            if (strpos($line, '✅') !== false) {
                $line_class .= ' pim-log-success';
            } elseif (strpos($line, '❌') !== false) {
                $line_class .= ' pim-log-error';
            } elseif (strpos($line, '⚠️') !== false) {
                $line_class .= ' pim-log-warning';
            } elseif (strpos($line, '🔍') !== false) {
                $line_class .= ' pim-log-info';
            }
            ?>
            <div class="<?php echo $line_class; ?>"><?php echo $line_html; ?></div>
        <?php endforeach; ?>
    </div>
</div>