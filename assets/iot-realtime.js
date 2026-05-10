// Real-time IoT data subscription using Server-Sent Events (SSE)
// This doesn't require a separate Mercure hub - works with Symfony directly!

class IoTRealtimeSubscriber {
    constructor(stationId, onDataCallback, onStatusCallback) {
        this.stationId = stationId;
        this.onDataCallback = onDataCallback;
        this.onStatusCallback = onStatusCallback;
        this.eventSource = null;
        this.lastSeen = null;
        this.staleThreshold = 30000; // 30 seconds
        this.checkInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
    }

    /**
     * Initialize the SSE connection
     */
    connect(baseUrl = '') {
        const url = `${baseUrl}/iot/stream/${this.stationId}`;
        
        console.log(`Connecting to SSE: ${url}`);
        
        try {
            this.eventSource = new EventSource(url);
        } catch (e) {
            console.error('Failed to create EventSource:', e);
            this.updateStatus(false);
            return;
        }
        
        this.eventSource.onopen = () => {
            console.log('Connected to IoT SSE stream');
            this.reconnectAttempts = 0;
            this.updateStatus(true);
            this.lastSeen = new Date();
        };
        
        this.eventSource.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                console.log('Received IoT data:', data);
                
                this.lastSeen = new Date();
                
                if (this.onDataCallback) {
                    this.onDataCallback(data);
                }
            } catch (e) {
                console.error('Error parsing SSE message:', e);
            }
        };
        
        this.eventSource.onerror = (error) => {
            console.error('SSE connection error:', error);
            this.updateStatus(false);
            this.handleReconnect(baseUrl);
        };
        
        // Start stale checking
        this.startStaleCheck();
    }

    /**
     * Handle reconnection on error
     */
    handleReconnect(baseUrl) {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Reconnecting... attempt ${this.reconnectAttempts}`);
            
            if (this.eventSource) {
                this.eventSource.close();
            }
            
            setTimeout(() => {
                this.connect(baseUrl);
            }, 3000 * this.reconnectAttempts);
        }
    }

    /**
     * Check for stale connections
     */
    startStaleCheck() {
        this.checkInterval = setInterval(() => {
            if (this.lastSeen) {
                const now = new Date();
                const diff = now - this.lastSeen;
                
                if (diff > this.staleThreshold) {
                    console.warn('IoT data is stale!');
                    if (this.onStatusCallback) {
                        this.onStatusCallback(false, diff);
                    }
                } else {
                    if (this.onStatusCallback) {
                        this.onStatusCallback(true, 0);
                    }
                }
            }
        }, 5000);
    }

    /**
     * Update connection status
     */
    updateStatus(connected, staleAge = 0) {
        if (this.onStatusCallback) {
            this.onStatusCallback(connected, staleAge);
        }
    }

    /**
     * Disconnect from SSE
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
        
        console.log('Disconnected from IoT SSE');
    }
}

/**
 * Alternative: Long polling approach
 * Works even if SSE is blocked
 */
class IoTPollingSubscriber {
    constructor(stationId, onDataCallback, onStatusCallback) {
        this.stationId = stationId;
        this.onDataCallback = onDataCallback;
        this.onStatusCallback = onStatusCallback;
        this.latestId = 0;
        this.pollInterval = null;
        this.isPolling = false;
    }

    start(baseUrl = '', intervalMs = 5000) {
        this.fetchLatest(baseUrl);
        
        this.pollInterval = setInterval(() => {
            this.fetchLatest(baseUrl);
        }, intervalMs);
    }

    async fetchLatest(baseUrl) {
        if (this.isPolling) return;
        
        this.isPolling = true;
        
        try {
            const url = `${baseUrl}/iot/latest/${this.stationId}`;
            const response = await fetch(url);
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.id && data.id > this.latestId) {
                    this.latestId = data.id;
                    
                    if (this.onDataCallback) {
                        this.onDataCallback(data);
                    }
                    
                    if (this.onStatusCallback) {
                        this.onStatusCallback(true, 0);
                    }
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
            
            if (this.onStatusCallback) {
                this.onStatusCallback(false, 0);
            }
        }
        
        this.isPolling = false;
    }

    stop() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }
}

/**
 * Helper function to initialize IoT real-time updates on a page
 * Uses SSE by default, falls back to polling if needed
 */
function initIoTRealtime(stationId, updateCallback, statusCallback, options = {}) {
    const method = options.method || 'sse'; // 'sse' or 'poll'
    const baseUrl = options.baseUrl || '';
    const pollInterval = options.pollInterval || 5000;
    
    if (method === 'poll') {
        console.log('Using polling method for IoT updates');
        const subscriber = new IoTPollingSubscriber(stationId, updateCallback, statusCallback);
        subscriber.start(baseUrl, pollInterval);
        return subscriber;
    }
    
    console.log('Using SSE method for IoT updates');
    const subscriber = new IoTRealtimeSubscriber(stationId, updateCallback, statusCallback);
    subscriber.connect(baseUrl);
    return subscriber;
}

// Export for use in other scripts
window.IoTRealtimeSubscriber = IoTRealtimeSubscriber;
window.IoTPollingSubscriber = IoTPollingSubscriber;
window.initIoTRealtime = initIoTRealtime;
