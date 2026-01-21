<?php
/**
 * Main Plugin Class (FINAL)
 * 
 * REFACTORED VERSION:
 * - ✅ All AJAX handling delegated to ajax/ handlers
 * - ✅ Clean separation of concerns
 */
class Page_Images_Manager {
    
    private $ajax_handler_images;
    private $ajax_handler_uploads;
    private $ajax_handler_misc;
    private $ajax_handler_debug;
    
    public function __construct() {
        // ✅ Initialize ALL AJAX handlers (including debug)
        $this->ajax_handler_images = new PIM_Ajax_Handler_Images();
        $this->ajax_handler_uploads = new PIM_Ajax_Handler_Uploads();
        $this->ajax_handler_misc = new PIM_Ajax_Handler_Misc();
        $this->ajax_handler_debug = new PIM_Ajax_Handler_Debug();
        
        // Register hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // ✅ Delegate AJAX hooks to ALL handlers
        $this->ajax_handler_images->register_hooks();
        $this->ajax_handler_uploads->register_hooks();
        $this->ajax_handler_misc->register_hooks();
        $this->ajax_handler_debug->register_hooks();
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_media_page(
            __('Image Manager', 'page-images-manager'),
            __('Image Manager', 'page-images-manager'),
            'manage_options',
            'page-images-manager',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue modular JavaScript and CSS files
     * ✅ TODO 50/51: Added duplicate-handling.css
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'media_page_page-images-manager') {
            return;
        }
        
        wp_enqueue_media();
        
        // ============================================
        // ENQUEUE CSS MODULES
        // ============================================
        
        wp_enqueue_style('pim-toast-css', PIM_PLUGIN_URL . 'assets/css/modules/toast-notifications.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/toast-notifications.css'));
        wp_enqueue_style('pim-collapsible-css', PIM_PLUGIN_URL . 'assets/css/modules/collapsible-sections.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/collapsible-sections.css'));
        wp_enqueue_style('pim-grid-css', PIM_PLUGIN_URL . 'assets/css/modules/image-grid.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/image-grid.css'));
        wp_enqueue_style('pim-selectors-css', PIM_PLUGIN_URL . 'assets/css/modules/size-selectors.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/size-selectors.css'));
        wp_enqueue_style('pim-buttons-css', PIM_PLUGIN_URL . 'assets/css/modules/buttons-animations.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/buttons-animations.css'));
        wp_enqueue_style('pim-locked-css', PIM_PLUGIN_URL . 'assets/css/modules/locked-state.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/locked-state.css'));
        wp_enqueue_style('pim-debug-log-viewer-css', PIM_PLUGIN_URL . 'assets/css/modules/debug-log-viewer.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/debug-log-viewer.css'));
        
        // ✅ TODO 50/51: NEW - Duplicate handling styles
        wp_enqueue_style('pim-duplicate-handling-css', PIM_PLUGIN_URL . 'assets/css/modules/duplicate-handling.css', array(), filemtime(PIM_PLUGIN_DIR . 'assets/css/modules/duplicate-handling.css'));
        
        // Inline CSS for lock icons
        $lock_unlocked_url = PIM_PLUGIN_URL . 'assets/images/lock-unlocked.svg';
        $lock_locked_url = PIM_PLUGIN_URL . 'assets/images/lock-locked.svg';
        
        $inline_css = "
        .pim-source-group::after {
            background-image: url('{$lock_unlocked_url}') !important;
        }
        .pim-source-group.locked::after {
            background-image: url('{$lock_locked_url}') !important;
        }
        ";
        
        wp_add_inline_style('pim-locked-css', $inline_css);
        
        // Main admin CSS (depends on all modules)
        wp_enqueue_style('pim-admin-css', PIM_PLUGIN_URL . 'assets/css/admin.css', array('pim-toast-css', 'pim-collapsible-css', 'pim-grid-css', 'pim-selectors-css', 'pim-buttons-css', 'pim-locked-css', 'pim-debug-log-viewer-css', 'pim-duplicate-handling-css'), filemtime(PIM_PLUGIN_DIR . 'assets/css/admin.css'));
        
        // ============================================
        // ENQUEUE JAVASCRIPT MODULES (no changes)
        // ============================================
        
        wp_enqueue_script('pim-core', PIM_PLUGIN_URL . 'assets/js/modules/core.js', array('jquery'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/core.js'), true);
        wp_enqueue_script('pim-toast', PIM_PLUGIN_URL . 'assets/js/modules/toast-notifications.js', array(), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/toast-notifications.js'), true);
        wp_enqueue_script('pim-page-selector', PIM_PLUGIN_URL . 'assets/js/modules/page-selector.js', array('jquery', 'pim-core', 'pim-toast'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/page-selector.js'), true);
        wp_enqueue_script('pim-collapsible', PIM_PLUGIN_URL . 'assets/js/modules/collapsible-sections.js', array('jquery', 'pim-core'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/collapsible-sections.js'), true);
        wp_enqueue_script('pim-thumbnail-gen', PIM_PLUGIN_URL . 'assets/js/modules/thumbnail-generation.js', array('jquery', 'pim-core'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/thumbnail-generation.js'), true);
        wp_enqueue_script('pim-image-actions', PIM_PLUGIN_URL . 'assets/js/modules/image-actions.js', array('jquery', 'pim-core', 'pim-thumbnail-gen'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/image-actions.js'), true);
        wp_enqueue_script('pim-duplicate-handling', PIM_PLUGIN_URL . 'assets/js/modules/duplicate-handling.js', array('jquery', 'pim-core', 'pim-thumbnail-gen'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/duplicate-handling.js'), true);
        
        // ✅ TODO 50B/51: NEW - Enhanced dialog module
        wp_enqueue_script('pim-duplicate-dialog', PIM_PLUGIN_URL . 'assets/js/modules/duplicate-dialog.js', array('jquery', 'pim-core', 'pim-duplicate-handling', 'pim-toast'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/duplicate-dialog.js'), true);
        
        wp_enqueue_script('pim-missing-images', PIM_PLUGIN_URL . 'assets/js/modules/missing-images.js', array('jquery', 'pim-core', 'pim-thumbnail-gen'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/missing-images.js'), true);
        wp_enqueue_script('pim-debug-log', PIM_PLUGIN_URL . 'assets/js/modules/debug-log.js', array('jquery', 'pim-core'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/debug-log.js'), true);
        wp_enqueue_script('pim-debug-log-viewer', PIM_PLUGIN_URL . 'assets/js/modules/debug-log-viewer.js', array('jquery'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/debug-log-viewer.js'), true);
        wp_enqueue_script('pim-lock-handling', PIM_PLUGIN_URL . 'assets/js/modules/lock-handling.js', array('jquery', 'pim-core', 'pim-toast'), filemtime(PIM_PLUGIN_DIR . 'assets/js/modules/lock-handling.js'), true);
        
        // Main orchestrator (depends on all modules)
        wp_enqueue_script('pim-admin-js', PIM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'pim-core', 'pim-toast', 'pim-page-selector', 'pim-collapsible', 'pim-thumbnail-gen', 'pim-image-actions', 'pim-duplicate-handling', 'pim-duplicate-dialog', 'pim-missing-images', 'pim-debug-log', 'pim-debug-log-viewer', 'pim-lock-handling'), filemtime(PIM_PLUGIN_DIR . 'assets/js/admin.js'), true);
        
        // Localize script with data
        $inline_script = sprintf(
            'const pimData = %s;',
            wp_json_encode(array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('page_images_manager')
            ))
        );
        wp_add_inline_script('pim-core', $inline_script, 'before');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        require_once PIM_PLUGIN_DIR . 'templates/admin-page.php';
    }
}