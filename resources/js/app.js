import './bootstrap';
import './components';

// Initialize application
document.addEventListener('DOMContentLoaded', () => {
    // Initialize all components
    window.App.init();
});

// Global App namespace
window.App = {
    // API Configuration
    api: {
        baseUrl: '/api/v1',
        token: document.querySelector('meta[name="api-token"]')?.content || null,
        
        getHeaders() {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            };
            
            if (this.token) {
                headers['Authorization'] = `Bearer ${this.token}`;
            }
            
            return headers;
        },
        
        async request(endpoint, options = {}) {
            const url = `${this.baseUrl}${endpoint}`;
            const config = {
                ...options,
                headers: {
                    ...this.getHeaders(),
                    ...options.headers,
                },
            };
            
            try {
                const response = await fetch(url, config);
                const data = await response.json();
                
                if (!response.ok) {
                    throw { response, data };
                }
                
                return data;
            } catch (error) {
                if (error.response) {
                    throw error;
                }
                throw { response: { status: 0 }, data: { message: 'Network error' } };
            }
        },
        
        get(endpoint) {
            return this.request(endpoint, { method: 'GET' });
        },
        
        post(endpoint, data) {
            return this.request(endpoint, {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },
    },
    
    // Initialize
    init() {
        this.initSessionTimeout();
        this.initOfflineDetection();
        this.initNavigation();
    },
    
    // Session timeout handling
    initSessionTimeout() {
        const warningHeader = document.headers?.get('X-Session-Warning');
        
        if (warningHeader === 'true') {
            this.showSessionWarning();
        }
    },
    
    showSessionWarning() {
        const modal = document.getElementById('session-warning-modal');
        if (modal) {
            modal.classList.remove('hidden');
            
            const extendBtn = document.getElementById('extend-session-btn');
            const logoutBtn = document.getElementById('logout-btn');
            
            extendBtn?.addEventListener('click', async () => {
                try {
                    await this.api.post('/auth/session/extend');
                    modal.classList.add('hidden');
                    this.showToast('Session extended', 'success');
                } catch (error) {
                    this.showToast('Failed to extend session', 'error');
                }
            });
            
            logoutBtn?.addEventListener('click', () => {
                window.location.href = '/logout';
            });
        }
    },
    
    // Offline detection and queue
    initOfflineDetection() {
        const updateOnlineStatus = () => {
            const indicator = document.getElementById('connection-indicator');
            if (indicator) {
                if (navigator.onLine) {
                    indicator.classList.remove('bg-red-500', 'bg-yellow-500');
                    indicator.classList.add('bg-green-500');
                    indicator.title = 'Connected';
                } else {
                    indicator.classList.remove('bg-green-500', 'bg-yellow-500');
                    indicator.classList.add('bg-red-500');
                    indicator.title = 'Offline - Changes will be queued';
                }
            }
        };
        
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        updateOnlineStatus();
        
        // Queue processing on network restoration
        window.addEventListener('online', () => {
            this.processOfflineQueue();
        });
    },
    
    async processOfflineQueue() {
        const queue = await this.getOfflineQueue();
        if (queue.length > 0) {
            for (const item of queue) {
                try {
                    await this.api.post(item.endpoint, item.data);
                    await this.removeFromQueue(item.id);
                    this.showToast('Offline submission processed', 'success');
                } catch (error) {
                    console.error('Failed to process queue item:', error);
                }
            }
        }
    },
    
    async getOfflineQueue() {
        const stored = localStorage.getItem('attendance_queue');
        return stored ? JSON.parse(stored) : [];
    },
    
    async addToQueue(endpoint, data) {
        const queue = await this.getOfflineQueue();
        queue.push({
            id: Date.now(),
            endpoint,
            data,
            timestamp: new Date().toISOString(),
        });
        localStorage.setItem('attendance_queue', JSON.stringify(queue));
        this.showToast('Submission queued for when you\'re back online', 'warning');
    },
    
    async removeFromQueue(id) {
        const queue = await this.getOfflineQueue();
        const filtered = queue.filter(item => item.id !== id);
        localStorage.setItem('attendance_queue', JSON.stringify(filtered));
    },
    
    // Navigation
    initNavigation() {
        const menuToggle = document.querySelector('.menu-toggle');
        const mobileNav = document.querySelector('.mobile-nav');
        
        menuToggle?.addEventListener('click', () => {
            mobileNav?.classList.toggle('hidden');
        });
    },
    
    // Toast notifications
    showToast(message, type = 'info') {
        const container = document.getElementById('toast-container') || this.createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} mb-2 animate-slide-up`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.remove();
        }, 5000);
    },
    
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 max-w-sm';
        document.body.appendChild(container);
        return container;
    },
    
    // Geolocation helpers
    async getLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation is not supported'));
                return;
            }
            
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude,
                        accuracy: position.coords.accuracy,
                    });
                },
                (error) => {
                    reject(error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0,
                }
            );
        });
    },
    
    // Calculate distance using Haversine formula
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
    
    // Check if within geofence
    async checkGeofence(workplaceLat, workplaceLon, radiusMeters = 10) {
        try {
            const location = await this.getLocation();
            const distance = this.calculateDistance(
                location.latitude,
                location.longitude,
                workplaceLat,
                workplaceLon
            );
            
            return {
                withinGeofence: distance <= radiusMeters,
                distance: Math.round(distance),
                location,
            };
        } catch (error) {
            throw error;
        }
    },
    
    // QR Scanner
    async startScanner(videoElement, onScan) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: 'environment' }
            });
            
            videoElement.srcObject = stream;
            await videoElement.play();
            
            // Use a library like html5-qrcode in production
            // This is a placeholder for the scanning logic
            this.scanning = true;
            
            return () => {
                this.stopScanner(videoElement);
            };
        } catch (error) {
            throw error;
        }
    },
    
    stopScanner(videoElement) {
        this.scanning = false;
        if (videoElement.srcObject) {
            videoElement.srcObject.getTracks().forEach(track => track.stop());
            videoElement.srcObject = null;
        }
    },
};

// Authentication helpers
window.Auth = {
    async login(email, password) {
        try {
            const response = await window.App.api.post('/auth/login', { email, password });
            
            if (response.requires_mfa) {
                window.location.href = '/mfa-verify';
            } else {
                // Save token
                document.querySelector('meta[name="api-token"]').content = response.token;
                window.App.api.token = response.token;
                window.location.href = '/dashboard';
            }
        } catch (error) {
            throw error.data;
        }
    },
    
    async verifyMfa(code) {
        try {
            const response = await window.App.api.post('/auth/mfa/verify', { code });
            document.querySelector('meta[name="api-token"]').content = response.token;
            window.App.api.token = response.token;
            window.location.href = response.redirect_url;
        } catch (error) {
            throw error.data;
        }
    },
    
    logout() {
        window.App.api.post('/auth/logout').finally(() => {
            document.querySelector('meta[name="api-token"]').content = '';
            window.location.href = '/login';
        });
    },
};

// Export for use in other scripts
export default window.App;