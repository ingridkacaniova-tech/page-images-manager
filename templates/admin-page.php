<?php
/**
 * Admin Page Template
 * 
 * ✅ ORIGINAL structure preserved
 * ✅ ONLY ADDITION: Scan controls at the top
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('🖼️ Page Images Manager', 'page-images-manager'); ?></h1>
    
    <!-- ✅ NEW: Scan All Pages Section -->
    <div class="pim-section">
        <h2><?php _e('🔄 Scan All Pages', 'page-images-manager'); ?></h2>
        <p class="description">
            <?php _e('Collect image usage data from all pages to enable cross-page protection. Run this before using the plugin.', 'page-images-manager'); ?>
        </p>
        
        <!-- ✅ Buttons in one row -->
        <div class="pim-scan-buttons">
            <button id="collect-images-btn" class="button button-primary">
                🔄 Collect Images from All Pages & Save to Database
            </button>
            <button id="repair-elementor-btn" class="button button-secondary" style="background: #f0ad4e; border-color: #f0ad4e; color: white;">
                🔧 Repair Elementor URLs and Wrong Data
            </button>
            <button id="show-scan-info-btn" class="button button-secondary">
                📊 Show Last Scan Info  
            </button>
            <button id="save-list-to-file-btn" class="button button-secondary">
                💾 Save Collected data to File
            </button>
        </div>
        
        <!-- ✅ NEW: Scan info always visible (updated by JS) -->
        <div id="scan-info-display" class="pim-scan-info-panel">
            <span><strong>Last scan:</strong> <span id="scan-timestamp">Never</span></span>
            <span class="pim-scan-separator">|</span>
            <span><strong>Duration:</strong> <span id="scan-duration">—</span></span>
            <span class="pim-scan-separator">|</span>
            <span><strong>Scanned:</strong> <span id="scan-stats">—</span></span>
        </div>
    </div>
        
    <!-- ✅ ORIGINAL: Description text -->
    <p><?php echo esc_html__('Select a page to manage thumbnails for all images used on that page.', 'page-images-manager'); ?></p>

    <!-- ✅ ORIGINAL: pim-container wrapper -->
    <div class="pim-container">
        
        <!-- ✅ ORIGINAL: Step 1 section -->
        <div class="pim-section">
            <h2><?php echo esc_html__('Step 1: Select Page', 'page-images-manager'); ?></h2>
            
            <!-- ✅ ORIGINAL: Inline layout with checkbox at the end -->
            <div style="display: flex; align-items: center; gap: 15px;">
                <select id="page-selector" class="pim-select">
                    <option value=""><?php echo esc_html__('-- Select a Page --', 'page-images-manager'); ?></option>
                    <?php
                    $pages = get_pages(array(
                        'post_status' => 'publish,draft',
                        'sort_column' => 'post_title',
                        'sort_order' => 'ASC'
                    ));
                    
                    foreach ($pages as $page) {
                        printf(
                            '<option value="%d">#%d - %s</option>',
                            esc_attr($page->ID),
                            esc_attr($page->ID),
                            esc_html($page->post_title)
                        );
                    }
                    ?>
                </select>
                
                <button type="button" id="load-images-btn" class="button button-primary" disabled>
                    <?php echo esc_html__('Load Images', 'page-images-manager'); ?>
                </button>
                
                <button type="button" id="export-elementor-json-btn" class="button button-secondary" disabled style="background: #8e44ad; border-color: #8e44ad; color: white;">
                    📄 Export Elementor JSON
                </button>
                
                <span id="load-status"></span>
                
                <!-- ✅ ORIGINAL: Debug Log checkbox INLINE na konci riadku -->
                <label style="display: flex; align-items: center; gap: 8px; margin-left: auto; cursor: pointer; user-select: none; white-space: nowrap;">
                    <span style="font-weight: 500; font-size: 13px; color: #2271b1;">
                        📋 Show Debug Log View
                    </span>
                    <input type="checkbox" id="pim-show-debug-toggle" style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                </label>
            </div>
        </div>

        <!-- ✅ ORIGINAL: Step 2 section -->
        <div class="pim-section" id="images-section" style="display: none;">
            <h2><?php echo esc_html__('Step 2: Select Thumbnails for Each Image', 'page-images-manager'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Check the thumbnail sizes you want to generate for each image.', 'page-images-manager'); ?>
            </p>

            <div id="images-list">
                <!-- Images will be loaded here via AJAX -->
            </div>
        </div>

    </div>
    
    <!-- ✅ ORIGINAL: Debug log section -->
    <div id="pim-debug-log-section" style="margin-top: 40px; display: none;">
        <!-- Načíta sa dynamicky cez AJAX -->
    </div>
    
</div>

<!-- ✅ ORIGINAL: Hidden data pre AJAX -->
<script type="text/javascript">
    var pimDebugLogNonce = '<?php echo wp_create_nonce('pim_load_debug_log'); ?>';
</script>