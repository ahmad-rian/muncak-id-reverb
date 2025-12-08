/**
 * LiveKit Viewer - MuncakID Livestreaming
 * 
 * Features:
 * - LiveKit SFU for video playback (ultra-low latency)
 * - Reverb for chat & metadata (self-hosted WebSocket)
 * - Mirror state sync
 * - Mid-stream join support (automatic)
 */

import { Room, RoomEvent, Track } from 'livekit-client';

const streamId = window.streamId;
const streamSlug = window.streamSlug || streamId; // Fallback to ID if slug is empty

let livekitRoom = null;
let hasJoined = false; // Track if viewer has joined (prevent double counting)
let reverbReady = false; // Track Reverb connection status
let channelSubscription = null; // Store channel reference
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 10; // Increased from 5
let subscriptionQueue = []; // Queue for failed subscriptions
let isSubscribing = false; // Prevent concurrent subscription attempts

console.log('👁️ LiveKit Viewer starting...');
console.log('Stream ID:', streamId);
console.log('Stream Slug/ID:', streamSlug);

// ✅ FIX: Wait for Echo to be available with retry logic
let echo = null;
let echoInitAttempts = 0;
const MAX_ECHO_INIT_ATTEMPTS = 20; // 20 attempts * 250ms = 5 seconds

function initializeEcho() {
    echo = window.Echo;
    
    if (!echo) {
        if (echoInitAttempts < MAX_ECHO_INIT_ATTEMPTS) {
            echoInitAttempts++;
            console.log(`⏳ Waiting for Laravel Echo... (${echoInitAttempts}/${MAX_ECHO_INIT_ATTEMPTS})`);
            setTimeout(initializeEcho, 250);
            return false;
        } else {
            console.error('❌ Laravel Echo not initialized after 5 seconds!');
            throw new Error('Laravel Echo is required');
        }
    }
    
    console.log('✅ Laravel Echo initialized');
    setupReverbConnection();
    return true;
}

// Setup Reverb connection (called after Echo is ready)
function setupReverbConnection() {

// ✅ FIX: Wait for Reverb connection before subscribing to channel
function subscribeToChannel() {
    if (channelSubscription) {
        console.log('📡 Already subscribed to channel');
        return true;
    }

    if (isSubscribing) {
        console.log('⏳ Subscription already in progress, queueing...');
        if (!subscriptionQueue.includes('subscribe')) {
            subscriptionQueue.push('subscribe');
        }
        return false;
    }

    if (!reverbReady) {
        console.log('⏳ Waiting for Reverb connection, queueing...');
        if (!subscriptionQueue.includes('subscribe')) {
            subscriptionQueue.push('subscribe');
        }
        return false;
    }

    isSubscribing = true;
    
    try {
        console.log('📡 Subscribing to Reverb channel:', `stream.${streamId}`);
        channelSubscription = echo.channel(`stream.${streamId}`);
        
        // Setup channel event listeners
        setupChannelListeners();
        
        console.log('✅ Successfully subscribed to channel');
        subscriptionQueue = []; // Clear queue on success
        isSubscribing = false;
        return true;
    } catch (err) {
        console.error('❌ Failed to subscribe:', err);
        channelSubscription = null;
        isSubscribing = false;
        
        // Retry after delay if not too many attempts
        if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
            console.log('🔄 Will retry subscription in 1 second...');
            setTimeout(() => {
                subscribeToChannel();
            }, 1000);
        }
        
        return false;
    }
}

// Reverb connection status with retry logic
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
    reverbReady = true;
    reconnectAttempts = 0;
    
    // Process queued subscriptions
    if (subscriptionQueue.length > 0) {
        console.log('🔄 Processing queued subscription...');
        subscriptionQueue = [];
        subscribeToChannel();
    } else {
        // Subscribe to channel after connection is ready
        subscribeToChannel();
    }
});

echo.connector.pusher.connection.bind('unavailable', () => {
    console.warn('⚠️ Reverb connection unavailable');
    reverbReady = false;
});

echo.connector.pusher.connection.bind('failed', () => {
    console.error('❌ Reverb connection failed');
    reverbReady = false;
    
    // Attempt reconnection with exponential backoff
    if (reconnectAttempts < MAX_RECONNECT_ATTEMPTS) {
        reconnectAttempts++;
        const backoffDelay = Math.min(500 * Math.pow(1.5, reconnectAttempts), 10000); // Start from 500ms, max 10s
        console.log(`🔄 Reconnecting to Reverb in ${backoffDelay}ms (attempt ${reconnectAttempts}/${MAX_RECONNECT_ATTEMPTS})`);
        
        setTimeout(() => {
            console.log('🔄 Attempting Reverb reconnection...');
            echo.connector.pusher.connect();
        }, backoffDelay);
    } else {
        console.error('❌ Max reconnection attempts reached. Please refresh the page.');
        // Show user-friendly error message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-error fixed top-4 right-4 w-96 z-50';
        errorDiv.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h3 class="font-bold">Connection Lost</h3>
                <div class="text-xs">Chat and viewer count may be unavailable. Please refresh.</div>
            </div>
        `;
        document.body.appendChild(errorDiv);
        
        setTimeout(() => errorDiv.remove(), 10000);
    }
});

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Reverb connection error:', err);
    reverbReady = false;
});

// Setup channel event listeners (called after subscription)
function setupChannelListeners() {
    if (!channelSubscription) {
        console.warn('⚠️ Cannot setup listeners: channel not subscribed');
        return;
    }

const channel = channelSubscription;

// DOM elements
const video = document.getElementById('video-player');
const chatMessages = document.getElementById('chat-messages');
const chatInput = document.getElementById('chat-input');
const chatForm = document.getElementById('chat-form');
const charCounter = document.getElementById('char-counter');
const viewerCountEl = document.getElementById('viewer-count');

// Listen for mirror state changes (Reverb)
channel.listen('MirrorStateChanged', (data) => {
    console.log('🪞 Mirror state changed:', data.is_mirrored);
    if (video) {
        video.style.transform = data.is_mirrored ? 'scaleX(-1)' : 'scaleX(1)';
    }
});

// Listen for chat messages (Reverb)
channel.listen('ChatMessageSent', (data) => {
    console.log('💬 Chat message:', data);
    if (chatMessages) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'chat-message';
        messageDiv.innerHTML = `<strong>${data.username}:</strong> ${data.message}`;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

// Listen for viewer count updates (Reverb)
channel.listen('ViewerCountUpdated', (data) => {
    console.log('👥 Viewer count updated:', data.count);
    if (viewerCountEl) {
        viewerCountEl.textContent = data.count;
    }
    // Also update chat viewer count badge
    const chatViewerCount = document.getElementById('chat-viewer-count');
    if (chatViewerCount) {
        chatViewerCount.textContent = data.count;
    }
});

// Listen for stream status changes (Reverb)
channel.listen('StreamStarted', () => {
    console.log('🟢 Stream started');
    initializeViewer();
});

channel.listen('StreamEnded', () => {
    console.log('🔴 Stream ended');

    // Disconnect from LiveKit
    if (livekitRoom) {
        livekitRoom.disconnect();
        livekitRoom = null;
    }

    // Clear video
    if (video) {
        video.srcObject = null;
    }

    // Hide loading indicator
    const loadingIndicator = document.querySelector('.absolute.inset-0.flex.items-center.justify-center');
    if (loadingIndicator) {
        loadingIndicator.remove();
    }

    // Show stream ended message - find video container
    let videoContainer = null;
    if (video && video.parentElement) {
        videoContainer = video.parentElement;
    } else {
        // Fallback: find by class or ID
        videoContainer = document.querySelector('.relative.aspect-video') ||
            document.querySelector('#video-container') ||
            document.querySelector('video')?.parentElement;
    }

    if (videoContainer) {
        videoContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center h-full bg-base-200 rounded-lg">
                <div class="text-center p-8">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 text-error" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                    </svg>
                    <h2 class="text-2xl font-bold mb-2">Stream Ended</h2>
                    <p class="text-base-content/70 mb-4">The broadcaster has ended the stream</p>
                    <p class="text-sm text-base-content/50">Redirecting to streams list in <span id="countdown">3</span>s...</p>
                    <div class="loading loading-spinner loading-md mt-4"></div>
                </div>
            </div>
        `;

        // Countdown timer
        let countdown = 3;
        const countdownEl = document.getElementById('countdown');
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownEl) {
                countdownEl.textContent = countdown;
            }
            if (countdown <= 0) {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }

    // Redirect to index after 3 seconds
    setTimeout(() => {
        window.location.href = '/live-cam';
    }, 3000);
});

// Initialize viewer
async function initializeViewer() {
    try {
        console.log('🎬 Initializing LiveKit viewer...');

        // Get LiveKit token
        const tokenResponse = await fetch(`/live-cam/${streamSlug}/livekit/token`);
        const tokenData = await tokenResponse.json();

        if (!tokenData.success) {
            throw new Error('Failed to get LiveKit token');
        }

        console.log('✅ Got LiveKit token');
        console.log('🔗 Connecting to:', tokenData.url);
        console.log('🏠 Room:', tokenData.room);

        // Create LiveKit room
        livekitRoom = new Room({
            adaptiveStream: true,
            dynacast: true,
        });

        // Setup event listeners
        livekitRoom.on(RoomEvent.Connected, () => {
            console.log('✅ Connected to LiveKit room');
        });

        livekitRoom.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
            console.log('📺 Track subscribed:', track.kind, 'from', participant.identity);

            if (track.kind === Track.Kind.Video && video) {
                track.attach(video);
                video.play();
                console.log('✅ Video playing');

                // Hide loading indicator
                const loadingIndicator = document.getElementById('loading-indicator');
                if (loadingIndicator) {
                    loadingIndicator.classList.add('hidden');
                }
            }
        });

        livekitRoom.on(RoomEvent.TrackUnsubscribed, (track) => {
            console.log('📴 Track unsubscribed:', track.kind);
            track.detach();
        });

        livekitRoom.on(RoomEvent.Disconnected, () => {
            console.log('🔌 Disconnected from LiveKit room');
        });

        // Connect to room
        await livekitRoom.connect(tokenData.url, tokenData.token);

        // Update viewer count
        await updateViewerCount(1);
        hasJoined = true; // Mark as joined

    } catch (err) {
        console.error('❌ Failed to initialize viewer:', err);
    }
}

// Update viewer count
async function updateViewerCount(delta) {
    try {
        const action = delta > 0 ? 'join' : 'leave';
        const url = `/live-cam/${streamSlug}/viewer-count`;
        const data = JSON.stringify({ action });

        // Use sendBeacon for leave (more reliable on page unload)
        if (action === 'leave') {
            const blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
            // Use fetch for join
            await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: data
            });
        }
    } catch (err) {
        console.error('Failed to update viewer count:', err);
    }
}

// Chat functionality
// Update character counter
if (chatInput && charCounter) {
    chatInput.addEventListener('input', () => {
        charCounter.textContent = `${chatInput.value.length}/200`;
    });
}

// Send chat message
if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = chatInput.value.trim();
        if (!message) return;

        // Get username from window or generate guest name
        const username = window.chatUsername || window.username || `Guest-${Math.random().toString(36).substr(2, 6).toUpperCase()}`;

        try {
            const response = await fetch(`/live-cam/${streamSlug}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    username: username,
                    message: message
                })
            });

            if (response.ok) {
                chatInput.value = '';
                charCounter.textContent = '0/200';
            }
        } catch (err) {
            console.error('Failed to send message:', err);
        }
    });
}

// Load chat history
async function loadChatHistory() {
    try {
        const response = await fetch(`/live-cam/${streamSlug}/chat-history`);
        const data = await response.json();

        if (data.messages && data.messages.length > 0) {
            data.messages.forEach(msg => {
                if (chatMessages) {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'chat-message';
                    messageDiv.innerHTML = `<strong>${msg.username}:</strong> ${msg.message}`;
                    chatMessages.appendChild(messageDiv);
                }
            });
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            console.log(`💬 Loaded ${data.messages.length} messages`);
        }
    } catch (err) {
        console.error('Failed to load chat history:', err);
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (livekitRoom && hasJoined) {
        updateViewerCount(-1);
        livekitRoom.disconnect();
    }
});

// Initialize
console.log('✅ LiveKit Viewer initialized');

// Auto-start if stream is live
(async () => {
    try {
        const response = await fetch(`/live-cam/${streamSlug}/status`);
        const data = await response.json();

        if (data.is_live) {
            console.log('🟢 Stream is LIVE');
            await initializeViewer();
        } else {
            console.log('⚪ Stream is OFFLINE');
        }
    } catch (err) {
        console.error('Failed to check stream status:', err);
    }
})();

// Load chat history
loadChatHistory();
} // End of setupReverbConnection

// ✅ Start Echo initialization
initializeEcho();
