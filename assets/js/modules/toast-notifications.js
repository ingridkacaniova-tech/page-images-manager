/**
 * Toast Notifications Module
 * Lightweight custom toast system (no dependencies)
 * 
 * Usage:
 *   PIM_Toast.success('Image uploaded!');
 *   PIM_Toast.error('Upload failed');
 *   PIM_Toast.warning('No sizes selected');
 *   PIM_Toast.info('Processing...');
 *   PIM_Toast.loading('Uploading...', id);
 */

const PIM_Toast = (function() {
    'use strict';
    
    let toastCounter = 0;
    let toasts = {};
    
    // ============================================
    // PUBLIC API
    // ============================================
    
    function success(message, duration = 3000) {
        return show(message, 'success', duration);
    }
    
    function error(message, duration = 4000) {
        return show(message, 'error', duration);
    }
    
    function warning(message, duration = 3500) {
        return show(message, 'warning', duration);
    }
    
    function info(message, duration = 3000) {
        return show(message, 'info', duration);
    }
    
    function loading(message, id) {
        return show(message, 'loading', 0, id); // 0 = no auto-dismiss
    }
    
    /**
     * Update existing toast (useful for loading → success transitions)
     */
    function update(id, message, type = 'success', duration = 3000) {
        const toast = toasts[id];
        if (!toast) return;
        
        const $toast = toast.element;
        const $icon = $toast.querySelector('.pim-toast-icon');
        const $message = $toast.querySelector('.pim-toast-message');
        
        // Update content
        $icon.textContent = getIcon(type);
        $message.textContent = message;
        
        // Update styling
        $toast.className = `pim-toast pim-toast-${type} show`;
        
        // Auto-dismiss if duration set
        if (duration > 0) {
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(() => dismiss(id), duration);
        }
    }
    
    /**
     * Manually dismiss a toast
     */
    function dismiss(id) {
        const toast = toasts[id];
        if (!toast) return;
        
        const $toast = toast.element;
        
        // Fade out
        $toast.classList.remove('show');
        
        // Remove from DOM after animation
        setTimeout(() => {
            if ($toast.parentNode) {
                $toast.parentNode.removeChild($toast);
            }
            delete toasts[id];
        }, 300);
    }
    
    /**
     * Clear all toasts
     */
    function clearAll() {
        Object.keys(toasts).forEach(id => dismiss(id));
    }
    
    // ============================================
    // INTERNAL FUNCTIONS
    // ============================================
    
    function show(message, type, duration, customId) {
        const id = customId || `toast-${++toastCounter}`;
        
        // Create toast element
        const $toast = createToastElement(message, type, id);
        
        // Get or create container
        const $container = getContainer();
        $container.appendChild($toast);
        
        // Trigger slide-in animation
        setTimeout(() => $toast.classList.add('show'), 10);
        
        // Store reference
        toasts[id] = {
            element: $toast,
            timeout: null
        };
        
        // Auto-dismiss if duration is set
        if (duration > 0) {
            toasts[id].timeout = setTimeout(() => dismiss(id), duration);
        }
        
        // Add click to dismiss
        $toast.addEventListener('click', () => dismiss(id));
        
        return id;
    }
    
    function createToastElement(message, type, id) {
        const $toast = document.createElement('div');
        $toast.className = `pim-toast pim-toast-${type}`;
        $toast.setAttribute('data-toast-id', id);
        
        $toast.innerHTML = `
            <span class="pim-toast-icon">${getIcon(type)}</span>
            <span class="pim-toast-message">${escapeHtml(message)}</span>
            <span class="pim-toast-close" title="Dismiss">×</span>
        `;
        
        // Close button handler
        const $close = $toast.querySelector('.pim-toast-close');
        $close.addEventListener('click', (e) => {
            e.stopPropagation();
            dismiss(id);
        });
        
        return $toast;
    }
    
    function getContainer() {
        let $container = document.getElementById('pim-toast-container');
        
        if (!$container) {
            $container = document.createElement('div');
            $container.id = 'pim-toast-container';
            document.body.appendChild($container);
        }
        
        return $container;
    }
    
    function getIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            loading: '⏳'
        };
        return icons[type] || icons.info;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // ============================================
    // PUBLIC API
    // ============================================
    
    return {
        success,
        error,
        warning,
        info,
        loading,
        update,
        dismiss,
        clearAll
    };
    
})();

// Make globally available
window.PIM_Toast = PIM_Toast;