<?php
/**
 * Admin Page Template
 * 
 * FIXES:
 * ✅ ISSUE 22: DEBUG checkbox defaultne VYPNUTÝ
 * ✅ TODO 26: Checkbox presunutý do prvého riadku (text → checkbox)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('🖼️ Page Images Manager', 'page-images-manager'); ?></h1>
    <p><?php echo esc_html__('Select a page to manage thumbnails for all images used on that page.', 'page-images-manager'); ?></p>

    <div class="pim-container">
        
        <!-- Step 1: Page Selection -->
        <div class="pim-section">
            <h2><?php echo esc_html__('Step 1: Select Page', 'page-images-manager'); ?></h2>
            
            <!-- ✅ TODO 26: Checkbox na konci riadku, text → checkbox -->
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
                
                <span id="load-status"></span>
                
                <!-- ✅ TODO 26: Debug Log checkbox na konci riadku -->
                <label style="display: flex; align-items: center; gap: 8px; margin-left: auto; cursor: pointer; user-select: none; white-space: nowrap;">
                    <span style="font-weight: 500; font-size: 13px; color: #2271b1;">
                        📋 Show Debug Log View
                    </span>
                    <input type="checkbox" id="pim-show-debug-toggle" style="width: 18px; height: 18px; cursor: pointer; margin: 0;">
                </label>
            </div>
        </div>

        <!-- Step 2: Image Management -->
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
    
    
    <!-- ============================================ -->
    <!-- DEBUG LOG VIEWER - LAZY LOADED -->
    <!-- ✅ ISSUE 22: Default hidden (checkbox unchecked) -->
    <!-- ============================================ -->
    
    <!-- Placeholder - obsah sa načíta cez AJAX -->
    <div id="pim-debug-log-section" style="margin-top: 40px; display: none;">
        <!-- Načíta sa dynamicky cez AJAX -->
    </div>
    
</div>

<!-- ✅ Hidden data pre AJAX endpoint -->
<script type="text/javascript">
    var pimDebugLogNonce = '<?php echo wp_create_nonce('pim_load_debug_log'); ?>';
</script>