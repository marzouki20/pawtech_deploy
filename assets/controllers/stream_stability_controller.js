/**
 * Stream Stability Fixes
 * Comprehensive solutions for stream disconnection and instability issues
 * 
 * Add this JavaScript to your camera_view.html.twig template
 * to improve stream reliability and automatic recovery
 */

// ==================== CONFIGURATION ====================

const STREAM_CONFIG = {
    // Polling configuration
    pollInterval: 200,              // 200ms between frame polls (was 50ms - too aggressive)
    maxPollInterval: 500,           // Max 500ms when experiencing issues
    minPollInterval: 100,           // Min 100ms for smooth playback
    
    // Reconnection configuration  
    maxReconnectAttempts: 10,       // More retry attempts
    initialReconnectDelay: 1000,    // Start with 1 second
    maxReconnectDelay: 10000,       // Max 10 seconds between retries
    reconnectBackoffMultiplier: 1.5, // Exponential backoff
    
    // Health check configuration
    healthCheckInterval: 3000,      // Check health every 3 seconds
    frameTimeout: 5000,             // Consider stream dead after 5s without frame
    
    // Auto-heal configuration
    enableAutoHeal: true,           // Enable automatic stream recovery
    autoHealOnFrameStale: true,     // Restart when frames become stale
    
    // Stability thresholds
    consecutiveErrorsThreshold: 3,  // Trigger reconnection after this many errors
    slowFrameThreshold: 1000,        // Consider frame slow if > 1s
};

// Global state
let streamHealthData = {
    consecutiveErrors: 0,
    lastFrameTime: 0,
    lastFrameSuccess: true,
    currentPollInterval: STREAM_CONFIG.pollInterval,
    reconnectDelay: STREAM_CONFIG.initialReconnectDelay,
    isAutoHealing: false,
    streamHealthy: true
};

// ==================== IMPROVED STREAM FUNCTIONS ====================

/**
 * Enhanced MJPEG streaming with stability improvements
 */
function startMjpegStream() {
    console.log('[STABILITY] Starting enhanced MJPEG stream');
    const mjpegImg = document.getElementById('mjpeg-stream');
    if (!mjpegImg) {
        console.error('[STABILITY] MJPEG element not found');
        handleStreamError('MJPEG element not found');
        return;
    }
    
    // Reset health state
    streamHealthData = {
        consecutiveErrors: 0,
        lastFrameTime: Date.now(),
        lastFrameSuccess: true,
        currentPollInterval: STREAM_CONFIG.pollInterval,
        reconnectDelay: STREAM_CONFIG.initialReconnectDelay,
        isAutoHealing: false,
        streamHealthy: true
    };
    
    const frameUrl = '/admin/cameras/' + cameraId + '/frame.jpg';
    
    // Stop existing intervals
    if (mjpegInterval) {
        clearInterval(mjpegInterval);
    }
    
    // Show MJPEG container
    mjpegImg.style.display = 'block';
    
    const vlcActiveX = document.getElementById('vlc-activex');
    const vlcPlugin = document.getElementById('vlc-plugin');
    if (vlcActiveX) vlcActiveX.style.display = 'none';
    if (vlcPlugin) vlcPlugin.style.display = 'none';
    
    // Enhanced load handler with timing
    mjpegImg.onload = function() {
        const now = Date.now();
        const timeSinceLastFrame = now - streamHealthData.lastFrameTime;
        
        console.log('[STABILITY] Frame loaded successfully', {
            timeSinceLastFrame,
            consecutiveErrors: streamHealthData.consecutiveErrors
        });
        
        // Reset error counter on success
        streamHealthData.consecutiveErrors = 0;
        streamHealthData.lastFrameSuccess = true;
        streamHealthData.lastFrameTime = now;
        streamHealthData.streamHealthy = true;
        
        // Adjust polling interval based on performance
        adjustPollingInterval(timeSinceLastFrame);
        
        showLoading(false);
        updateConnectionStatus('online', 'Connected (MJPEG)');
    };
    
    // Enhanced error handler with auto-retry
    mjpegImg.onerror = function() {
        console.warn('[STABILITY] Frame load error', {
            consecutiveErrors: streamHealthData.consecutiveErrors
        });
        
        streamHealthData.consecutiveErrors++;
        streamHealthData.lastFrameSuccess = false;
        
        // Check if we need to reconnect
        if (streamHealthData.consecutiveErrors >= STREAM_CONFIG.consecutiveErrorsThreshold) {
            console.error('[STABILITY] Too many consecutive errors, triggering reconnection');
            triggerReconnection();
            return;
        }
        
        // Retry with current interval
        setTimeout(() => {
            mjpegImg.src = frameUrl + '?t=' + Date.now() + '&r=' + Math.random();
        }, streamHealthData.currentPollInterval);
    };
    
    // Set initial source
    mjpegImg.src = frameUrl + '?t=' + Date.now();
    
    // Start adaptive polling interval
    mjpegInterval = setInterval(() => {
        const now = Date.now();
        const timeSinceLastFrame = now - streamHealthData.lastFrameTime;
        
        // Check if stream has gone stale
        if (timeSinceLastFrame > STREAM_CONFIG.frameTimeout) {
            console.warn('[STABILITY] Stream timeout detected', { 
                timeSinceLastFrame,
                threshold: STREAM_CONFIG.frameTimeout 
            });
            
            if (STREAM_CONFIG.enableAutoHeal && STREAM_CONFIG.autoHealOnFrameStale) {
                handleStreamStale();
            }
        }
        
        // Poll for new frame
        if (timeSinceLastFrame > streamHealthData.currentPollInterval) {
            mjpegImg.src = frameUrl + '?t=' + now + '&r=' + Math.random();
        }
    }, streamHealthData.currentPollInterval);
}

/**
 * Adjust polling interval based on frame delivery performance
 */
function adjustPollingInterval(timeSinceLastFrame) {
    if (timeSinceLastFrame < STREAM_CONFIG.slowFrameThreshold) {
        // Good performance - reduce poll interval slightly
        streamHealthData.currentPollInterval = Math.max(
            STREAM_CONFIG.minPollInterval,
            streamHealthData.currentPollInterval - 10
        );
    } else {
        // Slow frames - increase poll interval to reduce server load
        streamHealthData.currentPollInterval = Math.min(
            STREAM_CONFIG.maxPollInterval,
            streamHealthData.currentPollInterval + 20
        );
    }
}

/**
 * Handle stale stream - trigger auto-heal
 */
function handleStreamStale() {
    if (streamHealthData.isAutoHealing) return;
    
    streamHealthData.isAutoHealing = true;
    streamHealthData.streamHealthy = false;
    
    console.log('[STABILITY] Stream became stale, attempting auto-heal...');
    updateConnectionStatus('reconnecting', 'Attempting auto-recovery...');
    
    // First try: just refresh the frame
    const mjpegImg = document.getElementById('mjpeg-stream');
    if (mjpegImg) {
        mjpegImg.src = '/admin/cameras/' + cameraId + '/frame.jpg?t=' + Date.now();
    }
    
    // If still stale after 2 seconds, trigger full reconnection
    setTimeout(() => {
        if (!streamHealthData.streamHealthy) {
            triggerReconnection();
        }
    }, 2000);
}

/**
 * Trigger reconnection with exponential backoff
 */
function triggerReconnection() {
    if (reconnectAttempts >= STREAM_CONFIG.maxReconnectAttempts) {
        console.error('[STABILITY] Max reconnection attempts reached');
        handleStreamError('Maximum reconnection attempts reached. Please refresh the page.');
        return;
    }
    
    reconnectAttempts++;
    const delay = Math.min(
        streamHealthData.reconnectDelay * Math.pow(STREAM_CONFIG.reconnectBackoffMultiplier, reconnectAttempts - 1),
        STREAM_CONFIG.maxReconnectDelay
    );
    
    console.log('[STABILITY] Reconnecting in', delay, 'ms (attempt', reconnectAttempts, ')');
    updateConnectionStatus('reconnecting', `Reconnecting (${reconnectAttempts}/${STREAM_CONFIG.maxReconnectAttempts})...`);
    
    // Stop current stream
    stopStream();
    
    // Wait and reconnect
    setTimeout(() => {
        // Try to restart the backend stream first
        restartBackendStream();
    }, delay);
}

/**
 * Restart the backend FFmpeg stream
 */
function restartBackendStream() {
    fetch(`/admin/cameras/${cameraId}/control`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'rtsp_restart' })
    })
    .then(response => response.json())
    .then(data => {
        console.log('[STABILITY] Backend restart response:', data);
        if (data.success) {
            // Give FFmpeg time to start
            setTimeout(() => {
                connectStream();
            }, 2000);
        } else {
            // Fall back to regular connect
            connectStream();
        }
    })
    .catch(error => {
        console.error('[STABILITY] Backend restart failed:', error);
        connectStream();
    });
}

/**
 * Enhanced reconnect function with stability improvements
 */
function reconnectStream() {
    // Reset reconnection state
    streamHealthData.consecutiveErrors = 0;
    streamHealthData.reconnectDelay = STREAM_CONFIG.initialReconnectDelay;
    reconnectAttempts = 0;
    
    // Stop current stream
    stopStream();
    
    // Wait and reconnect
    setTimeout(() => {
        connectStream();
    }, 500);
}

/**
 * Health check function - polls backend for stream status
 */
function pollStreamHealth() {
    fetch(`/admin/cameras/${cameraId}/status`)
    .then(response => response.json())
    .then(data => {
        if (data.status) {
            const health = data.status.health;
            
            if (health && !health.healthy && STREAM_CONFIG.enableAutoHeal) {
                console.warn('[STABILITY] Backend health check failed:', health.errors);
                
                // Auto-heal by restarting the stream
                if (!streamHealthData.isAutoHealing) {
                    streamHealthData.isAutoHealing = true;
                    
                    fetch(`/admin/cameras/${cameraId}/control`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'rtsp_restart' })
                    })
                    .then(() => {
                        setTimeout(() => {
                            streamHealthData.isAutoHealing = false;
                        }, 5000);
                    })
                    .catch(() => {
                        streamHealthData.isAutoHealing = false;
                    });
                }
            }
        }
    })
    .catch(error => {
        console.error('[STABILITY] Health check error:', error);
    });
}

// Start health check interval
setInterval(pollStreamHealth, STREAM_CONFIG.healthCheckInterval);

// ==================== IMPROVED FFmpeg COMMAND ====================

/*
 * For better stream stability, update the FFmpeg command in DirectRtspService.php
 * to include these additional parameters:
 * 
 * Replace buildMjpegCommand() with:
 * 
private function buildMjpegCommand(string $rtspUrl, int $cameraId, string $outputDir): string
{
    return sprintf(
        'nohup ffmpeg -y ' .
        // Connection options
        '-rtsp_transport tcp ' .
        '-timeout 10000000 ' .        // 10 second connection timeout
        '-reconnect 1 ' .             // Enable auto-reconnect
        '-reconnect_streamed 1 ' .     // Reconnect on stream errors
        '-reconnect_delay_max 5 ' .   // Max 5 seconds between reconnects
        // Buffer and delay options
        '-fflags nobuffer ' .
        '-flags low_delay ' .
        '-max_delay 50000 ' .         // Reduced max delay
        '-reorder_queue_size 5 ' .    // Smaller queue
        '-overrun_nonfatal 1 ' .      // Don't fail on buffer overrun
        // Analysis options
        '-analyzeduration 500000 ' .  // 500ms analysis
        '-probesize 500000 ' .        // 500KB probe
        // Stream options
        '-r 25 ' .                    // Force 25fps
        '-i "%s" ' .
        // Output options
        '-c:v mjpeg ' .
        '-q:v 3 ' .                   // Higher quality (lower = better)
        '-f mjpeg ' .
        '-an ' .                      // No audio
        '-thread_queue_size 8 ' .     // Thread input queue
        '-flush_packets 1 ' .         // Flush packets immediately
        '-output_ts_offset 0 ' .      // No timestamp offset
        '- > /tmp/rtsp_stream_%d.mjpg 2>&1 & echo $! > %s/rtsp.pid',
        $rtspUrl,
        $cameraId,
        $outputDir
    );
}
*/

// ==================== DIAGNOSTIC FUNCTIONS ====================

/**
 * Get stream diagnostics for debugging
 */
function getStreamDiagnostics() {
    return {
        config: STREAM_CONFIG,
        state: streamHealthData,
        reconnectAttempts: reconnectAttempts,
        mjpegInterval: mjpegInterval,
        timestamp: new Date().toISOString()
    };
}

/**
 * Export diagnostics to console
 */
function logStreamDiagnostics() {
    console.group('📊 Stream Diagnostics');
    console.log('Configuration:', STREAM_CONFIG);
    console.log('Health State:', streamHealthData);
    console.log('Reconnect Attempts:', reconnectAttempts);
    console.log('Poll Interval:', mjpegInterval ? 'Active' : 'Inactive');
    console.groupEnd();
}

// Make diagnostics available globally
window.getStreamDiagnostics = getStreamDiagnostics;
window.logStreamDiagnostics = logStreamDiagnostics;
