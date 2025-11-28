/**
 * LiveKit Broadcaster with Pure Reverb Signaling - MuncakID Livestreaming
 * 
 * Features:
 * - LiveKit SFU for video streaming (ultra-low latency)
 * - Reverb (Laravel Echo) for chat & metadata signaling
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

console.log('🎥 LiveKit Broadcaster (Pure Reverb) starting...');
console.log('Stream ID:', streamId);
console.log('Stream Slug/ID:', streamSlug);

// Reverb setup via Laravel Echo (already initialized in echo.js)
const echo = window.Echo;

if (!echo) {
    console.error('❌ Laravel Echo not initialized!');
    alert('Error: Real-time connection not available. Please refresh the page.');
}

// Subscribe to stream channel
const channel = echo.channel(`stream.${streamId}`);
console.log('📡 Broadcaster subscribed to Reverb channel:', `stream.${streamId}`);

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
channel.listen('.ViewerCountUpdated', (data) => {
    console.log('👥 Viewer count:', data.count);
    const viewerCountEl = document.getElementById('viewer-count');
    if (viewerCountEl) {
        viewerCountEl.textContent = data.count;
    }
});

// Listen for chat messages (Reverb)
channel.listen('.ChatMessageSent', (data) => {
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
                simulcast: true,
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
                },
                body: JSON.stringify({ quality: '720p' }) // Default to 720p (adaptive bitrate)
            });

            if (!startResponse.ok) {
                throw new Error('Failed to start stream on server');
            }

            console.log('✅ Stream started on server');

            // Capture thumbnail
            setTimeout(() => captureThumbnail(), 1000);

            // Start trail classification
            setTimeout(() => captureAndClassify(), 2000);
            const classificationInterval = setInterval(() => captureAndClassify(), 1800000);
            window.classificationInterval = classificationInterval;

            // Start duration timer
            startTime = Date.now();
            durationInterval = setInterval(updateDuration, 1000);

            // Update UI
            if (startBtn) {
                startBtn.classList.add('hidden');
                console.log('✅ Start button hidden');
            }
            if (stopBtn) {
                stopBtn.classList.remove('hidden');
                console.log('✅ Stop button shown');
            }
            if (statusBadge) {
                statusBadge.innerHTML = '<span class="badge badge-success gap-2"><span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-white opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-white"></span></span>LIVE</span>';
                console.log('✅ Status badge updated to LIVE');
            }

            // Update stream status card
            const streamStatus = document.getElementById('streamStatus');
            const streamStatusDesc = document.getElementById('streamStatusDesc');
            if (streamStatus) {
                streamStatus.textContent = 'LIVE';
                streamStatus.classList.add('text-success');
            }
            if (streamStatusDesc) {
                streamStatusDesc.textContent = 'Broadcasting now';
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

    // Stop classification timer
    if (window.classificationInterval) {
        clearInterval(window.classificationInterval);
        window.classificationInterval = null;
        console.log('🛑 Classification stopped');
    }

    // Unpublish tracks from LiveKit
    if (livekitRoom && livekitRoom.localParticipant) {
        try {
            if (livekitRoom.localParticipant.videoTracks) {
                livekitRoom.localParticipant.videoTracks.forEach((publication) => {
                    livekitRoom.localParticipant.unpublishTrack(publication.track);
                });
            }

            if (livekitRoom.localParticipant.audioTracks) {
                livekitRoom.localParticipant.audioTracks.forEach((publication) => {
                    livekitRoom.localParticipant.unpublishTrack(publication.track);
                });
            }
        } catch (err) {
            console.warn('Failed to unpublish tracks:', err);
        }

        await livekitRoom.disconnect();
        livekitRoom = null;
    }

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

    if (streamDuration) {
        streamDuration.textContent = '00:00:00';
    }

    // Update UI
    if (startBtn) {
        startBtn.classList.remove('hidden');
        console.log('✅ Start button shown');
    }
    if (stopBtn) {
        stopBtn.classList.add('hidden');
        console.log('✅ Stop button hidden');
    }
    if (statusBadge) {
        statusBadge.innerHTML = '<span class="badge badge-neutral">OFFLINE</span>';
        console.log('✅ Status badge updated to OFFLINE');
    }

    // Update stream status card
    const streamStatus = document.getElementById('streamStatus');
    const streamStatusDesc = document.getElementById('streamStatusDesc');
    if (streamStatus) {
        streamStatus.textContent = 'OFFLINE';
        streamStatus.classList.remove('text-success');
    }
    if (streamStatusDesc) {
        streamStatusDesc.textContent = 'Not broadcasting';
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

        // Strip data URL prefix (backend expects pure base64)
        const base64Data = imageData.replace(/^data:image\/jpeg;base64,/, '');

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
            body: JSON.stringify({ image: base64Data })
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

        const imageData = canvas.toDataURL('image/jpeg', 0.85);

        // Strip data URL prefix for API (Gemini expects pure base64)
        const base64Data = imageData.replace(/^data:image\/jpeg;base64,/, '');

        console.log(`📸 Sending frame for classification (${canvas.width}x${canvas.height})`);

        const response = await fetch(`/api/v1/classifications/stream/${streamId}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                image: base64Data,
                timestamp: Date.now()
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

// Initialize camera function
async function initializeCamera() {
    try {
        console.log('🎬 Initializing camera...');
        console.log('Video element:', video);

        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            },
            audio: false
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
    }
}

// Initialize
console.log('✅ LiveKit Broadcaster (Pure Reverb) initialized');

// Auto-initialize camera on page load
initializeCamera();

// Load chat history
loadChatHistory();

// Reverb connection status
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
});

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Reverb connection error:', err);
});
