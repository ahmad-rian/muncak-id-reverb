/**
 * LiveKit Broadcaster - MuncakID Livestreaming
 * 
 * Features:
 * - LiveKit SFU for video streaming (ultra-low latency)
 * - Reverb for chat & metadata (self-hosted WebSocket)
 * - Mirror toggle
 * - Thumbnail capture
 * - Stream duration timer
 * - Trail classification integration
 * - Viewer count tracking
 */

import { Room, RoomEvent, Track } from 'livekit-client';

const streamId = window.streamId;
const streamSlug = window.streamSlug || streamId; // Fallback to ID if slug is empty

let livekitRoom = null;
let localTracks = [];
let isMirrored = false;
let startTime = null;
let durationInterval = null;
let reverbReady = false; // Track Reverb connection status
let channelSubscription = null; // Store channel reference
let reconnectAttempts = 0;
const MAX_RECONNECT_ATTEMPTS = 5;

console.log('🎥 LiveKit Broadcaster starting...');
console.log('Stream ID:', streamId);
console.log('Stream Slug/ID:', streamSlug);

// Reverb/Echo setup (for chat & metadata)
const echo = window.Echo;

if (!echo) {
    console.error('❌ Laravel Echo not initialized!');
    throw new Error('Laravel Echo is required');
}

// ✅ FIX: Wait for Reverb connection before subscribing to channel
function subscribeToChannel() {
    if (channelSubscription) {
        console.log('📡 Already subscribed to channel');
        return;
    }

    console.log('📡 Subscribing to Reverb channel:', `stream.${streamId}`);
    channelSubscription = echo.channel(`stream.${streamId}`);
    
    // Setup channel event listeners
    setupChannelListeners();
}

// Reverb connection status with retry logic
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
    reverbReady = true;
    reconnectAttempts = 0;
    
    // Subscribe to channel after connection is ready
    subscribeToChannel();
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
        const backoffDelay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
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
const video = document.getElementById('camera-preview');
const startBtn = document.getElementById('start-button');
const stopBtn = document.getElementById('stop-button');
const mirrorBtn = document.getElementById('mirror-camera');
const statusBadge = document.getElementById('stream-status');
const streamDuration = document.getElementById('stream-duration');
const chatMessages = document.getElementById('chat-monitor');
const chatInput = document.getElementById('chat-input');
const chatForm = document.getElementById('chat-form');

// Listen for viewer count updates (Reverb)
channel.listen('ViewerCountUpdated', (data) => {
    console.log('👥 Viewer count:', data.count);
    const viewerCountEl = document.getElementById('viewer-count');
    if (viewerCountEl) {
        viewerCountEl.textContent = data.count;
    }
});

// Listen for chat messages (Reverb)
channel.listen('ChatMessageSent', (data) => {
    console.log('💬 Chat message:', data);
    if (chatMessages) {
        const placeholder = chatMessages.querySelector('.text-center');
        if (placeholder) placeholder.remove();

        const messageDiv = document.createElement('div');
        messageDiv.className = 'text-sm';
        messageDiv.innerHTML = `<strong>${data.username}:</strong> ${data.message}`;
        chatMessages.appendChild(messageDiv);
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

// Start broadcast
if (startBtn) {
    startBtn.addEventListener('click', async () => {
        try {
            console.log('🎬 Starting LiveKit broadcast...');

            // Get LiveKit token from server
            const basePath = window.location.pathname.includes('/admin/live-stream')
                ? `/admin/live-stream/${streamSlug}`
                : `/live-cam/${streamSlug}`;

            const tokenResponse = await fetch(`${basePath}/livekit/token`);
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

            livekitRoom.on(RoomEvent.Disconnected, () => {
                console.log('🔌 Disconnected from LiveKit room');
            });

            livekitRoom.on(RoomEvent.ParticipantConnected, (participant) => {
                console.log('👤 Participant joined:', participant.identity);
            });

            // Connect to room
            await livekitRoom.connect(tokenData.url, tokenData.token);

            // Get camera and microphone
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    frameRate: { ideal: 30 }
                },
                audio: true
            });

            console.log('✅ Camera access granted');

            // Show preview
            if (video) {
                video.srcObject = stream;
                video.muted = true;
                await video.play();
            }

            // Publish tracks to LiveKit
            const videoTrack = stream.getVideoTracks()[0];
            const audioTrack = stream.getAudioTracks()[0];

            await livekitRoom.localParticipant.publishTrack(videoTrack, {
                name: 'camera',
                simulcast: true, // Enable simulcast for better quality adaptation
            });

            await livekitRoom.localParticipant.publishTrack(audioTrack, {
                name: 'microphone',
            });

            localTracks = [videoTrack, audioTrack];

            console.log('✅ Published tracks to LiveKit');

            // Notify server to start stream
            const startResponse = await fetch(`${basePath}/start`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!startResponse.ok) {
                throw new Error('Failed to start stream on server');
            }

            console.log('✅ Stream started on server');

            // Capture thumbnail
            setTimeout(() => captureThumbnail(), 1000);

            // Start trail classification (immediate + every 30 minutes)
            setTimeout(() => captureAndClassify(), 2000); // First classification after 2 seconds
            const classificationInterval = setInterval(() => captureAndClassify(), 1800000); // Then every 30 minutes (30 * 60 * 1000)
            window.classificationInterval = classificationInterval; // Store for cleanup

            // Start duration timer
            startTime = Date.now();
            durationInterval = setInterval(updateDuration, 1000);

            // Update UI
            if (startBtn) {
                startBtn.style.display = 'none';
                console.log('✅ Start button hidden');
            }
            if (stopBtn) {
                stopBtn.style.display = 'block';
                console.log('✅ Stop button shown');
            }
            if (statusBadge) {
                statusBadge.innerHTML = '<span class="badge badge-success gap-2"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span></span>LIVE</span>';
                console.log('✅ Status badge updated to LIVE');
            }

        } catch (err) {
            console.error('❌ Failed to start broadcast:', err);
            alert('Failed to start broadcast: ' + err.message);
            stopBroadcast();
        }
    });
}

// Stop broadcast
if (stopBtn) {
    stopBtn.addEventListener('click', () => {
        stopBroadcast();
    });
}

async function stopBroadcast() {
    console.log('🛑 Stopping broadcast...');

    // Stop classification timer FIRST (before server stop)
    if (window.classificationInterval) {
        clearInterval(window.classificationInterval);
        window.classificationInterval = null;
        console.log('🛑 Classification stopped');
    }

    // Unpublish tracks from LiveKit (but keep them running for preview)
    if (livekitRoom && livekitRoom.localParticipant) {
        try {
            // Unpublish video tracks
            if (livekitRoom.localParticipant.videoTracks) {
                livekitRoom.localParticipant.videoTracks.forEach((publication) => {
                    livekitRoom.localParticipant.unpublishTrack(publication.track);
                });
            }

            // Unpublish audio tracks
            if (livekitRoom.localParticipant.audioTracks) {
                livekitRoom.localParticipant.audioTracks.forEach((publication) => {
                    livekitRoom.localParticipant.unpublishTrack(publication.track);
                });
            }
        } catch (err) {
            console.warn('Failed to unpublish tracks:', err);
        }

        // Disconnect from room
        await livekitRoom.disconnect();
        livekitRoom = null;
    }

    // DON'T stop local tracks - keep camera preview running
    // localTracks.forEach(track => track.stop()); // ← Commented out
    // localTracks = []; // ← Keep tracks for preview

    // DON'T clear video preview - keep showing camera
    // if (video) {
    //     video.srcObject = null; // ← Commented out
    // }

    // Notify server
    const basePath = window.location.pathname.includes('/admin/live-stream')
        ? `/admin/live-stream/${streamSlug}`
        : `/live-cam/${streamSlug}`;

    try {
        await fetch(`${basePath}/stop`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        console.log('✅ Stream stopped on server');
    } catch (err) {
        console.error('Failed to stop stream on server:', err);
    }

    // Stop duration timer
    if (durationInterval) {
        clearInterval(durationInterval);
        durationInterval = null;
    }
    startTime = null;

    // Classification timer already stopped above (before server stop)

    if (streamDuration) {
        streamDuration.textContent = '00:00:00';
    }

    // Update UI
    if (startBtn) {
        startBtn.style.display = 'block';
        console.log('✅ Start button shown');
    }
    if (stopBtn) {
        stopBtn.style.display = 'none';
        console.log('✅ Stop button hidden');
    }
    if (statusBadge) {
        statusBadge.innerHTML = '<span class="badge badge-neutral">OFFLINE</span>';
        console.log('✅ Status badge updated to OFFLINE');
    }

    // Clear chat history
    if (chatMessages) {
        chatMessages.innerHTML = '<div class="text-center text-sm text-base-content/50">No messages yet</div>';
    }

    console.log('✅ Broadcast stopped');
}

// Mirror toggle
if (mirrorBtn) {
    mirrorBtn.addEventListener('click', async () => {
        isMirrored = !isMirrored;

        if (video) {
            video.style.transform = isMirrored ? 'scaleX(-1)' : 'scaleX(1)';
        }

        // Broadcast mirror state to viewers via Reverb
        const basePath = window.location.pathname.includes('/admin/live-stream')
            ? `/admin/live-stream/${streamSlug}`
            : `/live-cam/${streamSlug}`;

        try {
            await fetch(`${basePath}/mirror`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ is_mirrored: isMirrored })
            });

            console.log('🪞 Mirror state updated:', isMirrored);
        } catch (err) {
            console.error('Failed to broadcast mirror state:', err);
        }
    });
}

// Capture thumbnail
async function captureThumbnail() {
    if (!video) {
        console.warn('⚠️ Cannot capture thumbnail: camera not ready');
        return;
    }

    try {
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        const imageData = canvas.toDataURL('image/jpeg', 0.85);

        console.log(`📸 Thumbnail captured: ${canvas.width}x${canvas.height}`);

        const basePath = window.location.pathname.includes('/admin/live-stream')
            ? `/admin/live-stream/${streamSlug}`
            : `/live-cam/${streamSlug}`;

        const response = await fetch(`${basePath}/thumbnail`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ image: imageData })
        });

        const result = await response.json();

        if (result.success) {
            console.log('✅ Thumbnail uploaded:', result.thumbnail_url);
        } else {
            console.error('❌ Thumbnail upload failed:', result.error);
        }

    } catch (error) {
        console.error('❌ Thumbnail capture failed:', error);
    }
}

// Capture and classify trail condition
async function captureAndClassify() {
    if (!video || !video.videoWidth) {
        console.warn('⚠️ Cannot classify: camera not ready');
        return;
    }

    try {
        console.log('🔬 Capturing frame for classification...');

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth || 1280;
        canvas.height = video.videoHeight || 720;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Convert to base64 (API expects base64 string)
        const imageData = canvas.toDataURL('image/jpeg', 0.85);

        console.log(`📸 Sending frame for classification (${canvas.width}x${canvas.height})`);

        const response = await fetch(`/api/v1/classifications/stream/${streamId}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                image: imageData,
                delay_ms: 0
            })
        });

        const result = await response.json();

        if (result.success) {
            console.log('✅ Classification successful:', result.data);
        } else {
            console.error('❌ Classification failed:', result.message || result.error);
        }

    } catch (error) {
        console.error('❌ Classification capture failed:', error);
    }
}

// Update stream duration
function updateDuration() {
    if (!startTime || !streamDuration) return;

    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const hours = Math.floor(elapsed / 3600);
    const minutes = Math.floor((elapsed % 3600) / 60);
    const seconds = elapsed % 60;

    streamDuration.textContent =
        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
}

// Chat functionality
if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = chatInput.value.trim();
        if (!message) return;

        const username = window.chatUsername || 'Broadcaster';

        try {
            const basePath = window.location.pathname.includes('/admin/live-stream')
                ? `/admin/live-stream/${streamSlug}`
                : `/live-cam/${streamSlug}`;

            const response = await fetch(`${basePath}/chat`, {
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
            }
        } catch (err) {
            console.error('Failed to send chat message:', err);
        }
    });
}

// Load chat history
async function loadChatHistory() {
    try {
        const basePath = window.location.pathname.includes('/admin/live-stream')
            ? `/admin/live-stream/${streamSlug}`
            : `/live-cam/${streamSlug}`;

        const response = await fetch(`${basePath}/chat-history`);
        const data = await response.json();

        if (data.success && chatMessages) {
            const placeholder = chatMessages.querySelector('.text-center');
            if (placeholder) placeholder.remove();

            data.messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'text-sm';
                messageDiv.innerHTML = `<strong>${msg.username}:</strong> ${msg.message}`;
                chatMessages.appendChild(messageDiv);
            });

            chatMessages.scrollTop = chatMessages.scrollHeight;
            console.log(`💬 Loaded ${data.messages.length} messages`);
        }
    } catch (err) {
        console.error('Failed to load chat history:', err);
    }
}

// Initialize
console.log('✅ LiveKit Broadcaster initialized');

// Enable Camera button handler
const enableCameraBtn = document.getElementById('enable-camera-btn');
const noCameraDiv = document.getElementById('no-camera');

if (enableCameraBtn) {
    enableCameraBtn.addEventListener('click', async () => {
        await initializeCamera();
    });
}

// Initialize camera function
async function initializeCamera() {
    try {
        console.log('🎬 Initializing camera...');
        console.log('Video element:', video);
        console.log('No camera div:', noCameraDiv);

        // Hide "no camera" message
        if (noCameraDiv) {
            noCameraDiv.classList.add('hidden');
        }

        console.log('📹 Requesting camera access...');
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            },
            audio: false // Just preview, no audio yet
        });

        console.log('✅ Camera access granted, stream:', stream);

        if (video) {
            console.log('📺 Setting video srcObject...');
            video.srcObject = stream;
            video.muted = true;

            console.log('▶️ Playing video...');
            await video.play();
            console.log('✅ Camera preview ready');

            // Hide permission warning overlay
            const permissionWarning = document.getElementById('permission-warning');
            if (permissionWarning) {
                permissionWarning.classList.add('hidden');
                console.log('✅ Permission warning hidden');
            }

            // Enable start button
            if (startBtn) {
                startBtn.disabled = false;
                console.log('✅ Start button enabled');
            }
        } else {
            console.error('❌ Video element not found!');
        }
    } catch (err) {
        console.error('❌ Camera initialization failed:', err);
        console.error('Error name:', err.name);
        console.error('Error message:', err.message);

        // Show error message
        if (noCameraDiv) {
            noCameraDiv.classList.remove('hidden');
            const errorMsg = noCameraDiv.querySelector('p');
            if (errorMsg) {
                if (err.name === 'NotAllowedError') {
                    errorMsg.textContent = 'Camera permission denied. Please allow camera access.';
                } else if (err.name === 'NotFoundError') {
                    errorMsg.textContent = 'No camera detected. Please connect a camera.';
                } else {
                    errorMsg.textContent = 'Camera error: ' + err.message;
                }
            }
        }
    }
}

// Auto-initialize camera on page load
initializeCamera();

// Load chat history
loadChatHistory();
}

// Reverb connection status
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
});

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Reverb connection error:', err);
});
