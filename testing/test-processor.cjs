const Pusher = require('pusher-js');

// Global tracking for metrics
const connectionMetrics = {
    activeConnections: 0,
    totalConnections: 0,
    totalErrors: 0
};

// Track request start time for latency measurement
function recordRequestStart(requestParams, context, ee, next) {
    context.vars.requestStartTime = Date.now();
    return next();
}

// Record response metrics (latency, throughput)
function recordResponseMetrics(requestParams, response, context, ee, next) {
    if (context.vars.requestStartTime) {
        const latency = Date.now() - context.vars.requestStartTime;

        // Emit latency metric
        ee.emit('histogram', 'http_request_latency', latency);

        // Track errors
        if (response.statusCode >= 400) {
            ee.emit('counter', 'http_errors', 1);
            ee.emit('counter', 'total_errors', 1);
            connectionMetrics.totalErrors++;
        }

        // Track throughput (requests per second is automatic in Artillery)
        ee.emit('counter', 'requests_completed', 1);
    }

    return next();
}

// Subscribe to Reverb channel and measure connection time
function subscribeToReverbChannel(context, events, done) {
    const startTime = Date.now();

    try {
        // Get configuration from environment variables
        const reverbKey = context.vars.reverb_key || 't04ejpaztc3hvzutpy42';
        const reverbHost = context.vars.reverb_host || 'reverb.muncak.id';
        const reverbPort = parseInt(context.vars.reverb_port || '9000');
        const reverbScheme = context.vars.reverb_scheme || 'http';
        const useTLS = reverbScheme === 'https';

        console.log(`[Reverb] Connecting to ${reverbScheme}://${reverbHost}:${reverbPort}`);
        console.log(`[Reverb] App Key: ${reverbKey}`);
        console.log(`[Reverb] Use TLS: ${useTLS}`);

        // Initialize Pusher client for Reverb (Reverb uses Pusher protocol)
        // Note: cluster is required by pusher-js 8.x but gets overridden by wsHost
        const pusherConfig = {
            cluster: 'mt1',  // Dummy cluster, overridden by wsHost
            wsHost: reverbHost,
            forceTLS: useTLS,
            enabledTransports: ['ws', 'wss'],
            disableStats: true
        };

        // Set port based on TLS setting
        if (useTLS) {
            pusherConfig.wssPort = reverbPort;
        } else {
            pusherConfig.wsPort = reverbPort;
        }

        console.log('[Reverb] Pusher config:', JSON.stringify(pusherConfig, null, 2));

        const pusher = new Pusher(reverbKey, pusherConfig);

        const streamId = context.vars.streamId || '2';
        const channelName = `stream.${streamId}`;

        // Track connection attempt
        connectionMetrics.totalConnections++;

        // Subscribe to the channel
        const channel = pusher.subscribe(channelName);

        // Wait for subscription success
        channel.bind('pusher:subscription_succeeded', () => {
            const connectionTime = Date.now() - startTime;

            console.log(`[Reverb] ✓ Successfully subscribed to ${channelName} in ${connectionTime}ms`);

            // Track connection establishment time
            events.emit('histogram', 'connection_establishment_time', connectionTime);
            events.emit('histogram', 'websocket_connection_latency', connectionTime);
            events.emit('counter', 'reverb_connections_success', 1);

            // Track active connections
            connectionMetrics.activeConnections++;
            events.emit('gauge', 'concurrent_connections', connectionMetrics.activeConnections);
            events.emit('gauge', 'active_websocket_connections', connectionMetrics.activeConnections);

            console.log(`[Reverb] Active connections: ${connectionMetrics.activeConnections}`);

            // Store connection info
            context.vars.reverbChannel = channel;
            context.vars.reverb = pusher;
            context.vars.reverbConnectedAt = Date.now();

            // Listen for chat messages
            channel.bind('ChatMessageSent', (data) => {
                const messageLatency = Date.now() - (data.timestamp || Date.now());
                events.emit('histogram', 'chat_message_latency', messageLatency);
                events.emit('counter', 'chat_messages_received', 1);
                events.emit('counter', 'messages_per_second', 1);
                console.log(`[Reverb] Chat message received, latency: ${messageLatency}ms`);
            });

            // Listen for viewer count updates
            channel.bind('ViewerCountUpdated', (data) => {
                events.emit('counter', 'viewer_count_updates', 1);
                events.emit('gauge', 'current_viewer_count', data.count || 0);
                console.log(`[Reverb] Viewer count updated: ${data.count}`);
            });

            // Listen for quality changes
            channel.bind('QualityChanged', (data) => {
                events.emit('counter', 'video_quality_changes', 1);

                const qualityValue = data.quality === '1080p' ? 1080 : 720;
                events.emit('gauge', 'current_video_quality', qualityValue);

                // Track quality stability
                if (data.quality === '720p') {
                    events.emit('counter', 'video_quality_720p_stable', 1);
                } else if (data.quality === '1080p') {
                    events.emit('counter', 'video_quality_1080p_stable', 1);
                }
                console.log(`[Reverb] Quality changed to: ${data.quality}`);
            });

            // Listen for chunk events (video streaming)
            channel.bind('NewChunkAvailable', (data) => {
                events.emit('counter', 'video_chunks_received', 1);

                // Track quality from chunk data
                if (data.quality) {
                    const qualityValue = data.quality === '1080p' ? 1080 : 720;
                    events.emit('gauge', 'current_video_quality', qualityValue);
                }
            });

            done();
        });

        // Handle subscription errors
        channel.bind('pusher:subscription_error', (err) => {
            console.error('[Reverb] Subscription error:', err);
            events.emit('counter', 'reverb_connections_failed', 1);
            events.emit('counter', 'websocket_errors', 1);
            events.emit('counter', 'total_errors', 1);
            connectionMetrics.totalErrors++;
            done(err);
        });

        // Track connection state
        let connectionErrorHandled = false;

        // Handle connection errors
        pusher.connection.bind('error', (err) => {
            console.error('[Reverb] Connection error:', err);
            if (!connectionErrorHandled) {
                connectionErrorHandled = true;
                events.emit('counter', 'reverb_connections_failed', 1);
                events.emit('counter', 'websocket_errors', 1);
                events.emit('counter', 'total_errors', 1);
                connectionMetrics.totalErrors++;
                done(err);
            }
        });

        // Log connection state changes
        pusher.connection.bind('state_change', (states) => {
            console.log(`[Reverb] Connection state: ${states.previous} -> ${states.current}`);
        });

        // Timeout after 30 seconds
        setTimeout(() => {
            if (!context.vars.reverb) {
                console.error('[Reverb] Connection timeout after 30s');
                events.emit('counter', 'reverb_connections_failed', 1);
                events.emit('counter', 'websocket_errors', 1);
                done(new Error('Reverb connection timeout'));
            }
        }, 30000);

    } catch (err) {
        events.emit('counter', 'reverb_connections_failed', 1);
        events.emit('counter', 'websocket_errors', 1);
        events.emit('counter', 'total_errors', 1);
        connectionMetrics.totalErrors++;
        done(err);
    }
}

// Simulate video quality at 720p
function simulateVideoQuality720p(context, events, done) {
    try {
        // Simulate quality check
        const qualityCheckTime = Date.now() - (context.vars.reverbConnectedAt || Date.now());

        // Record 720p quality stability
        events.emit('counter', 'video_quality_720p_stable', 1);
        events.emit('gauge', 'current_video_quality', 720);

        // Simulate quality check latency (5-15ms)
        const simulatedLatency = Math.floor(Math.random() * 10) + 5;
        events.emit('histogram', 'video_quality_check_latency', simulatedLatency);

        context.vars.currentQuality = '720p';

        done();
    } catch (err) {
        done(err);
    }
}

// Simulate video quality at 1080p
function simulateVideoQuality1080p(context, events, done) {
    try {
        // Simulate quality change
        const previousQuality = context.vars.currentQuality || '720p';

        if (previousQuality !== '1080p') {
            events.emit('counter', 'video_quality_changes', 1);
        }

        // Record 1080p quality stability
        events.emit('counter', 'video_quality_1080p_stable', 1);
        events.emit('gauge', 'current_video_quality', 1080);

        // Simulate quality check latency (5-15ms)
        const simulatedLatency = Math.floor(Math.random() * 10) + 5;
        events.emit('histogram', 'video_quality_check_latency', simulatedLatency);

        context.vars.currentQuality = '1080p';

        done();
    } catch (err) {
        done(err);
    }
}

// Disconnect from Reverb cleanly
function disconnectReverb(context, events, done) {
    try {
        // Calculate connection duration
        if (context.vars.reverbConnectedAt) {
            const connectionDuration = Date.now() - context.vars.reverbConnectedAt;
            events.emit('histogram', 'connection_duration', connectionDuration);
        }

        // Unsubscribe from channel
        if (context.vars.reverbChannel) {
            context.vars.reverbChannel.unbind_all();
            context.vars.reverb.unsubscribe(context.vars.reverbChannel.name);
        }

        // Disconnect
        if (context.vars.reverb) {
            context.vars.reverb.disconnect();
            events.emit('counter', 'reverb_disconnections', 1);

            // Update active connections count
            connectionMetrics.activeConnections--;
            events.emit('gauge', 'concurrent_connections', Math.max(0, connectionMetrics.activeConnections));
            events.emit('gauge', 'active_websocket_connections', Math.max(0, connectionMetrics.activeConnections));
        }

        // Clean up context
        delete context.vars.reverb;
        delete context.vars.reverbChannel;
        delete context.vars.reverbConnectedAt;

    } catch (err) {
        // Ignore disconnect errors but still update metrics
        if (connectionMetrics.activeConnections > 0) {
            connectionMetrics.activeConnections--;
        }
        events.emit('gauge', 'concurrent_connections', Math.max(0, connectionMetrics.activeConnections));
    }

    // Always call done
    done();
}

// Calculate error rate
function calculateErrorRate(context, events, done) {
    if (connectionMetrics.totalConnections > 0) {
        const errorRate = (connectionMetrics.totalErrors / connectionMetrics.totalConnections) * 100;
        events.emit('gauge', 'error_rate_percentage', errorRate);
    }
    done();
}

module.exports = {
    recordRequestStart,
    recordResponseMetrics,
    subscribeToReverbChannel,
    simulateVideoQuality720p,
    simulateVideoQuality1080p,
    disconnectReverb,
    calculateErrorRate
};
