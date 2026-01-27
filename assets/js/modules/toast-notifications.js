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
 *   
 * ✅ ISSUE 60: Progress toasts
 *   const id = PIM_Toast.progress('Scanning...', 0);
 *   PIM_Toast.updateProgress(id, 'Scanning... 45/200', 22);
 *   PIM_Toast.closeProgress(id);
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
        return show(message, 'loading', 0, id);
    }
    
    function update(id, message, type = 'success', duration = 3000) {
        const toast = toasts[id];
        if (!toast) return;
        
        const $toast = toast.element;
        const $icon = $toast.querySelector('.pim-toast-icon');
        const $message = $toast.querySelector('.pim-toast-message');
        
        $icon.textContent = getIcon(type);
        $message.textContent = message;
        
        $toast.className = `pim-toast pim-toast-${type} show`;
        
        if (duration > 0) {
            clearTimeout(toast.timeout);
            toast.timeout = setTimeout(() => dismiss(id), duration);
        }
    }
    
    function dismiss(id) {
        const toast = toasts[id];
        if (!toast) return;
        
        const $toast = toast.element;
        $toast.classList.remove('show');
        
        setTimeout(() => {
            if ($toast.parentNode) {
                $toast.parentNode.removeChild($toast);
            }
            delete toasts[id];
        }, 300);
    }
    
    function clearAll() {
        Object.keys(toasts).forEach(id => dismiss(id));
    }
    
    // ============================================
    // ✅ ISSUE 60: NEW - PROGRESS TOAST METHODS
    // ============================================
    
    function progress(message, progressPercent = 0, styles = '', customId) {
        const id = customId || `toast-${++toastCounter}`;
        
        // 🐛 DEBUG: Log what we received
        console.log('🎯 PIM_Toast.progress() called with:');
        console.log('  message:', message);
        console.log('  progressPercent:', progressPercent);
        console.log('  styles:', styles);
        console.log('  customId:', customId);
        
        // ✅ Parse styles string into CSS classes
        const styleClasses = parseProgressStyles(styles);
        
        // 🐛 DEBUG: Log parsed classes
        console.log('  ➡️ Parsed styleClasses:', styleClasses);
        
        const $toast = createProgressToastElement(message, progressPercent, id, false, styleClasses);
        
        // 🐛 DEBUG: Log toast HTML
        console.log('  ➡️ Toast HTML:', $toast.outerHTML);
        
        const $container = getContainer();
        $container.appendChild($toast);
        
        setTimeout(() => $toast.classList.add('show'), 10);
        
        toasts[id] = {
            element: $toast,
            timeout: null
        };
        
        return id;
    }
    
    /**
     * Create indeterminate progress toast (when percentage unknown)
     */
    function progressIndeterminate(message, styles = '', customId) {
        const id = customId || `toast-${++toastCounter}`;
        
        const styleClasses = parseProgressStyles(styles);
        
        const $toast = createProgressToastElement(message, 0, id, true, styleClasses);
        
        const $container = getContainer();
        $container.appendChild($toast);
        
        setTimeout(() => $toast.classList.add('show'), 10);
        
        toasts[id] = {
            element: $toast,
            timeout: null
        };
        
        return id;
    }
    
    /**
     * Update progress percentage and message
     */
    function updateProgress(id, message, progressPercent) {
        const toast = toasts[id];
        if (!toast) return;
        
        const $toast = toast.element;
        const $message = $toast.querySelector('.pim-toast-message');
        const $progressFill = $toast.querySelector('.pim-toast-progress-fill');
        const $percentText = $toast.querySelector('.pim-toast-progress-percent');  // ✅ NEW
        
        if ($message) {
            $message.textContent = message;
        }
        
        // ✅ NEW: Update percent text
        if ($percentText) {
            $percentText.textContent = Math.round(progressPercent) + '%';
        }
        
        if ($progressFill) {
            const clampedPercent = Math.min(100, Math.max(0, progressPercent));
            $progressFill.style.width = clampedPercent + '%';
            
            // ✅ Update data attributes for style variants
            $progressFill.setAttribute('data-percent', Math.round(clampedPercent));
            
            // ✅ Set percent range for color variant
            let range = '0-30';
            if (clampedPercent >= 30 && clampedPercent < 70) {
                range = '30-70';
            } else if (clampedPercent >= 70) {
                range = '70-100';
            }
            $progressFill.setAttribute('data-percent-range', range);
            $toast.setAttribute('data-percent-range', range);
        }
    }

    /**
     * Close progress toast
     */
    function closeProgress(id) {
        dismiss(id);
    }
    
    // ============================================
    // INTERNAL FUNCTIONS
    // ============================================
    
    function show(message, type, duration, customId) {
        const id = customId || `toast-${++toastCounter}`;
        
        const $toast = createToastElement(message, type, id);
        
        const $container = getContainer();
        $container.appendChild($toast);
        
        setTimeout(() => $toast.classList.add('show'), 10);
        
        toasts[id] = {
            element: $toast,
            timeout: null
        };
        
        if (duration > 0) {
            toasts[id].timeout = setTimeout(() => dismiss(id), duration);
        }
        
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
        
        const $close = $toast.querySelector('.pim-toast-close');
        $close.addEventListener('click', (e) => {
            e.stopPropagation();
            dismiss(id);
        });
        
        return $toast;
    }
    
    /**
     * ✅ Create progress toast element with style classes
     */
    function createProgressToastElement(message, progressPercent, id, isIndeterminate = false, styleClasses = '') {
        // 🐛 DEBUG
        console.log('🎨 createProgressToastElement() called with:');
        console.log('  styleClasses:', styleClasses);
        
        const $toast = document.createElement('div');
        $toast.className = `pim-toast pim-toast-progress ${styleClasses}`;
        
        // 🐛 DEBUG: Verify final className
        console.log('  ➡️ Toast className:', $toast.className);
        
        $toast.setAttribute('data-toast-id', id);
        
        // ✅ Set initial percent range
        let range = '0-30';
        if (progressPercent >= 30 && progressPercent < 70) {
            range = '30-70';
        } else if (progressPercent >= 70) {
            range = '70-100';
        }
        $toast.setAttribute('data-percent-range', range);
        
        const progressClass = isIndeterminate ? 'pim-toast-progress-fill indeterminate' : 'pim-toast-progress-fill';
        
        $toast.innerHTML = `
            <div class="pim-toast-content">
                <span class="pim-toast-icon">⏳</span>
                <span class="pim-toast-message">${escapeHtml(message)}</span>
            </div>
            <div class="pim-toast-progress-bar">
                <div class="${progressClass}" 
                    style="width: ${progressPercent}%" 
                    data-percent="${Math.round(progressPercent)}"
                    data-percent-range="${range}"></div>
                <span class="pim-toast-progress-percent">${Math.round(progressPercent)}%</span>
            </div>
        `;
        
        return $toast;
    }
    
    /**
     * ✅ Parse style string into CSS classes
     * Example: "gradient+sparkle+hourglass" → "pim-progress-style-gradient pim-progress-style-sparkle pim-progress-style-hourglass"
     */
    function parseProgressStyles(styles) {
        // 🐛 DEBUG
        console.log('🔧 parseProgressStyles() called with:', styles);
        
        if (!styles) {
            console.log('  ❌ Styles empty, returning empty string');
            return '';
        }
        
        const styleMap = {
            'gradient': 'pim-progress-style-gradient',
            'sparkle': 'pim-progress-style-sparkle',
            'wave': 'pim-progress-style-wave',
            'percent': 'pim-progress-style-percent',
            'hourglass': 'pim-progress-style-hourglass',
            'pulse': 'pim-progress-style-pulse',
            'color': 'pim-progress-style-color'
        };
        
        const result = styles.split('+')
            .map(style => {
                const trimmed = style.trim().toLowerCase();
                const mapped = styleMap[trimmed];
                console.log(`    "${trimmed}" ➡️ "${mapped || 'NOT FOUND'}"`);
                return mapped;
            })
            .filter(Boolean)
            .join(' ');
        
        console.log('  ➡️ Final result:', result);
        return result;
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
        clearAll,
        progress,
        updateProgress,
        closeProgress,
        progressIndeterminate
    };
    
})();

window.PIM_Toast = PIM_Toast;