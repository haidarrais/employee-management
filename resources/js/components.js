/**
 * UI Components - Laws of UX compliant
 * https://lawsofux.com/
 */

// Initialize all interactive components
document.addEventListener('DOMContentLoaded', () => {
    Components.init();
});

const Components = {
    init() {
        this.initDropdowns();
        this.initModals();
        this.initTabs();
        this.initAccordions();
        this.initTooltips();
        this.initCharacterCounters();
        this.initPasswordToggles();
    },
    
    /**
     * Laws of UX: Visibility of System Status
     * Dropdowns show/hide with clear state
     */
    initDropdowns() {
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.stopPropagation();
                const dropdown = toggle.closest('.dropdown');
                
                // Close other dropdowns
                document.querySelectorAll('.dropdown.open').forEach(open => {
                    if (open !== dropdown) open.classList.remove('open');
                });
                
                dropdown.classList.toggle('open');
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', () => {
            document.querySelectorAll('.dropdown.open').forEach(dropdown => {
                dropdown.classList.remove('open');
            });
        });
    },
    
    /**
     * Laws of UX: Feedback
     * Modals provide clear close actions
     */
    initModals() {
        document.querySelectorAll('[data-modal-target]').forEach(trigger => {
            trigger.addEventListener('click', () => {
                const target = document.querySelector(trigger.dataset.modalTarget);
                target?.classList.remove('hidden');
            });
        });
        
        document.querySelectorAll('[data-modal-close]').forEach(closeBtn => {
            closeBtn.addEventListener('click', () => {
                const modal = closeBtn.closest('.modal, .modal-backdrop');
                modal?.classList.add('hidden');
            });
        });
        
        // Close on backdrop click
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', () => {
                const modal = backdrop.nextElementSibling;
                modal?.classList.add('hidden');
                backdrop.classList.add('hidden');
            });
        });
        
        // Close on Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal:not(.hidden)').forEach(modal => {
                    modal.classList.add('hidden');
                });
                document.querySelectorAll('.modal-backdrop:not(.hidden)').forEach(backdrop => {
                    backdrop.classList.add('hidden');
                });
            }
        });
    },
    
    /**
     * Laws of UX: Recognition Rather Than Recall
     * Tabs show available options clearly
     */
    initTabs() {
        document.querySelectorAll('.tab-list').forEach(tabList => {
            const tabs = tabList.querySelectorAll('[data-tab]');
            const panels = document.querySelectorAll('[data-tab-panel]');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetId = tab.dataset.tab;
                    
                    // Update tabs
                    tabs.forEach(t => t.classList.remove('active', 'border-primary-600', 'text-primary-600'));
                    tabs.forEach(t => t.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700'));
                    tab.classList.add('active', 'border-primary-600', 'text-primary-600');
                    tab.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700');
                    
                    // Update panels
                    panels.forEach(panel => {
                        if (panel.dataset.tabPanel === targetId) {
                            panel.classList.remove('hidden');
                        } else {
                            panel.classList.add('hidden');
                        }
                    });
                });
            });
        });
    },
    
    /**
     * Laws of UX: Progressive Disclosure
     * Accordions reveal information incrementally
     */
    initAccordions() {
        document.querySelectorAll('.accordion-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const accordion = toggle.closest('.accordion-item');
                const content = accordion.querySelector('.accordion-content');
                const icon = toggle.querySelector('.accordion-icon');
                
                if (accordion.classList.contains('open')) {
                    accordion.classList.remove('open');
                    content.style.maxHeight = '0';
                    if (icon) icon.style.transform = 'rotate(0deg)';
                } else {
                    accordion.classList.add('open');
                    content.style.maxHeight = content.scrollHeight + 'px';
                    if (icon) icon.style.transform = 'rotate(180deg)';
                }
            });
        });
    },
    
    /**
     * Laws of UX: Visibility
     * Tooltips provide additional context
     */
    initTooltips() {
        document.querySelectorAll('[data-tooltip]').forEach(element => {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute z-50 px-2 py-1 text-xs text-white bg-gray-900 rounded shadow-lg';
            tooltip.textContent = element.dataset.tooltip;
            tooltip.style.visibility = 'hidden';
            tooltip.style.opacity = '0';
            tooltip.style.transition = 'opacity 0.2s, visibility 0.2s';
            
            element.style.position = 'relative';
            element.appendChild(tooltip);
            
            element.addEventListener('mouseenter', () => {
                tooltip.style.visibility = 'visible';
                tooltip.style.opacity = '1';
            });
            
            element.addEventListener('mouseleave', () => {
                tooltip.style.visibility = 'hidden';
                tooltip.style.opacity = '0';
            });
        });
    },
    
    /**
     * Laws of UX: Feedback
     * Character counters show remaining limit
     */
    initCharacterCounters() {
        document.querySelectorAll('[data-max-length]').forEach(input => {
            const maxLength = parseInt(input.dataset.maxLength);
            const counter = document.createElement('div');
            counter.className = 'text-sm text-gray-500 text-right mt-1';
            
            const updateCounter = () => {
                const remaining = maxLength - input.value.length;
                counter.textContent = `${remaining} characters remaining`;
                counter.classList.toggle('text-red-500', remaining < 0);
            };
            
            input.parentNode.appendChild(counter);
            input.addEventListener('input', updateCounter);
            updateCounter();
        });
    },
    
    /**
     * Laws of UX: Visibility
     * Password visibility toggle
     */
    initPasswordToggles() {
        document.querySelectorAll('.password-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const input = toggle.parentElement.querySelector('input');
                const isPassword = input.type === 'password';
                
                input.type = isPassword ? 'text' : 'password';
                toggle.querySelector('svg').classList.toggle('hidden', isPassword);
            });
        });
    },
};

/**
 * Form Validation Component
 * Laws of UX: Error Prevention
 */
const FormValidation = {
    patterns: {
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        phone: /^\+?[\d\s-()]+$/,
    },
    
    validate(input) {
        const value = input.value.trim();
        const rules = input.dataset.validate?.split(',') || [];
        
        for (const rule of rules) {
            const [ruleName, ruleValue] = rule.split(':');
            
            switch (ruleName) {
                case 'required':
                    if (!value) return 'This field is required';
                    break;
                case 'email':
                    if (value && !this.patterns.email.test(value)) return 'Please enter a valid email';
                    break;
                case 'min':
                    if (value.length < parseInt(ruleValue)) return `Minimum ${ruleValue} characters required`;
                    break;
                case 'max':
                    if (value.length > parseInt(ruleValue)) return `Maximum ${ruleValue} characters allowed`;
                    break;
                case 'match':
                    const matchInput = document.querySelector(ruleValue);
                    if (matchInput && value !== matchInput.value) return 'Fields do not match';
                    break;
            }
        }
        
        return null;
    },
    
    init() {
        document.querySelectorAll('[data-validate]').forEach(input => {
            const errorEl = document.createElement('div');
            errorEl.className = 'form-error hidden';
            input.parentNode.appendChild(errorEl);
            
            input.addEventListener('blur', () => this.validateField(input, errorEl));
            input.addEventListener('input', () => {
                if (!errorEl.classList.contains('hidden')) {
                    this.validateField(input, errorEl);
                }
            });
        });
    },
    
    validateField(input, errorEl) {
        const error = this.validate(input);
        
        if (error) {
            input.classList.add('form-input-error');
            errorEl.textContent = error;
            errorEl.classList.remove('hidden');
            return false;
        } else {
            input.classList.remove('form-input-error');
            errorEl.classList.add('hidden');
            return true;
        }
    },
};

// Auto-init validation
document.addEventListener('DOMContentLoaded', () => FormValidation.init());

/**
 * Loading State Component
 * Laws of UX: Visibility of System Status
 */
const LoadingState = {
    show(button, originalText) {
        if (!button) return;
        
        button.dataset.originalText = originalText || button.textContent;
        button.disabled = true;
        button.classList.add('opacity-50', 'cursor-not-allowed');
        
        // Add spinner
        const spinner = document.createElement('span');
        spinner.className = 'spinner inline-block w-4 h-4 ml-2';
        button.appendChild(spinner);
    },
    
    hide(button) {
        if (!button) return;
        
        button.disabled = false;
        button.classList.remove('opacity-50', 'cursor-not-allowed');
        
        // Remove spinner
        const spinner = button.querySelector('.spinner');
        spinner?.remove();
        
        // Restore original text
        button.textContent = button.dataset.originalText || button.textContent;
    },
};

/**
 * QR Scanner Component
 * Laws of UX: Focus
 */
const QRScanner = {
    video: null,
    canvas: null,
    scanning: false,
    
    async init(videoElementId) {
        this.video = document.getElementById(videoElementId);
        if (!this.video) return;
        
        this.canvas = document.createElement('canvas');
    },
    
    async start() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            this.video.srcObject = stream;
            this.video.play();
            this.scanning = true;
            
            this.scanFrame();
        } catch (error) {
            console.error('Camera access denied:', error);
            throw error;
        }
    },
    
    stop() {
        this.scanning = false;
        
        if (this.video?.srcObject) {
            this.video.srcObject.getTracks().forEach(track => track.stop());
            this.video.srcObject = null;
        }
    },
    
    scanFrame() {
        if (!this.scanning) return;
        
        if (this.video.readyState === this.video.HAVE_ENOUGH_DATA) {
            // Process frame - in production, use a QR library here
        }
        
        requestAnimationFrame(() => this.scanFrame());
    },
};

/**
 * Geolocation Component
 * Laws of UX: Error Prevention
 */
const Geolocation = {
    async getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported by your browser'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                resolve,
                (error) => {
                    let message = 'Unable to get location';
                    
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location permission denied. Please enable location access.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Location information unavailable.';
                            break;
                        case error.TIMEOUT:
                            message = 'Location request timed out.';
                            break;
                    }
                    
                    reject(new Error(message));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                }
            );
        });
    },
    
    calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371000; // Earth's radius in meters
        const dLat = this.toRad(lat2 - lat1);
        const dLon = this.toRad(lon2 - lon1);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    },
    
    toRad(deg) {
        return deg * (Math.PI / 180);
    },
};

export { Components, FormValidation, LoadingState, QRScanner, Geolocation };