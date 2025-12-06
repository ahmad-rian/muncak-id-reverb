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
const enableCameraBtn = document.getElementById('enable-camera-btn');
const noCameraDiv = document.getElementById('no-camera');
const permissionWarning = document.getElementById('permission-warning');
const permissionAlert = document.getElementById('permissionAlert');
const permissionSuccess = document.getElementById('permissionSuccess');
const requestPermissionBtn = document.getElementById('requestPermissionBtn');
const cameraSelect = document.getElementById('cameraSelect');
const micSelect = document.getElementById('micSelect');

if (enableCameraBtn) {
    enableCameraBtn.addEventListener('click', async () => {
        await initializeCamera();
    });
}

if (requestPermissionBtn) {
    requestPermissionBtn.addEventListener('click', async () => {
        await initializeCamera();
    });
}

const stopBtn = document.getElementById('stop-button');
const mirrorBtn = document.getElementById('mirror-camera');
const statusBadge = document.getElementById('stream-status');
const streamDuration = document.getElementById('stream-duration');
const chatMessages = document.getElementById('chat-monitor');
const chatInput = document.getElementById('chat-input');
const chatForm = document.getElementById('chat-form');

// Track selected devices
let selectedCameraId = null;
let selectedMicId = null;
let currentStream = null;

// Camera select change event
if (cameraSelect) {
    cameraSelect.addEventListener('change', async (e) => {
        const deviceId = e.target.value;
        await switchCamera(deviceId);
    });
}

// Microphone select change event
if (micSelect) {
    micSelect.addEventListener('change', async (e) => {
        const deviceId = e.target.value;
        await switchMicrophone(deviceId);
    });
}

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

            // Use existing stream or get new one
            let stream = currentStream;

            if (!stream || !stream.active) {
                console.log('📹 Getting fresh camera/microphone access...');
                stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        deviceId: selectedCameraId ? { exact: selectedCameraId } : undefined,
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        frameRate: { ideal: 30 }
                    },
                    audio: {
                        deviceId: selectedMicId ? { exact: selectedMicId } : undefined
                    }
                });
                currentStream = stream;
            }

            console.log('✅ Camera access granted');

            // Show preview
            if (video && !video.srcObject) {
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
                body: JSON.stringify({
                    quality: '720p'
                })
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
            if (startBtn) startBtn.classList.add('hidden');
            if (stopBtn) stopBtn.classList.remove('hidden');
            if (statusBadge) {
                statusBadge.innerHTML = '<span class="badge badge-success">LIVE</span>';
            }

            // Update stream status in stats
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
    if (startBtn) startBtn.classList.remove('hidden');
    if (stopBtn) stopBtn.classList.add('hidden');
    if (statusBadge) {
        statusBadge.innerHTML = '<span class="badge badge-neutral">OFFLINE</span>';
    }

    // Update stream status in stats
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

        // Convert to base64 and remove data URL prefix
        const imageDataUrl = canvas.toDataURL('image/jpeg', 0.85);
        // Remove "data:image/jpeg;base64," prefix for Gemini API
        const imageData = imageDataUrl.replace(/^data:image\/jpeg;base64,/, '');

        console.log(`📸 Sending frame for classification (${canvas.width}x${canvas.height})`);

        const response = await fetch(`/api/v1/classifications/stream/${streamId}/process`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                image: imageData,
                delay_ms: 0,
                timestamp: Math.floor(Date.now() / 1000) // Unix timestamp in seconds
            })
        });

        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.warn('⚠️ Classification endpoint returned non-JSON response');
            console.log('Response status:', response.status);

            // If endpoint doesn't exist or returns HTML, disable classification
            if (response.status === 404 || response.status === 500) {
                console.warn('⚠️ Classification endpoint not available, disabling auto-classification');
                // Stop the classification interval
                if (window.classificationInterval) {
                    clearInterval(window.classificationInterval);
                    window.classificationInterval = null;
                }
            }
            return;
        }

        const result = await response.json();

        if (result.success) {
            console.log('✅ Classification successful:', result.data);

            // Classification data is already saved to database
            // Viewers will see it when they load the page
        } else {
            console.error('❌ Classification failed:', result.message || result.error);
        }

    } catch (error) {
        console.error('❌ Classification capture failed:', error);
        // Don't let classification errors stop the stream
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

// Enumerate and populate device selects
async function enumerateDevices() {
    try {
        const devices = await navigator.mediaDevices.enumerateDevices();

        const videoDevices = devices.filter(device => device.kind === 'videoinput');
        const audioDevices = devices.filter(device => device.kind === 'audioinput');

        console.log(`📹 Found ${videoDevices.length} cameras, ${audioDevices.length} microphones`);

        // Populate camera select
        if (cameraSelect) {
            cameraSelect.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(option);
            });

            if (videoDevices.length > 0) {
                selectedCameraId = videoDevices[0].deviceId;
            }
        }

        // Populate microphone select
        if (micSelect) {
            micSelect.innerHTML = '';
            audioDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Microphone ${index + 1}`;
                micSelect.appendChild(option);
            });

            if (audioDevices.length > 0) {
                selectedMicId = audioDevices[0].deviceId;
            }
        }

        return { videoDevices, audioDevices };
    } catch (err) {
        console.error('❌ Failed to enumerate devices:', err);
        return { videoDevices: [], audioDevices: [] };
    }
}

// Switch camera function (can be called during live stream)
async function switchCamera(deviceId) {
    try {
        console.log('🔄 Switching camera to:', deviceId);

        // Get new stream with selected camera
        const newStream = await navigator.mediaDevices.getUserMedia({
            video: {
                deviceId: deviceId ? { exact: deviceId } : undefined,
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            },
            audio: false // Audio stays the same
        });

        const newVideoTrack = newStream.getVideoTracks()[0];

        // Update preview
        if (video && video.srcObject) {
            const oldStream = video.srcObject;
            const oldVideoTrack = oldStream.getVideoTracks()[0];

            // Replace track in preview
            oldStream.removeTrack(oldVideoTrack);
            oldStream.addTrack(newVideoTrack);

            // Stop old track
            oldVideoTrack.stop();
        } else {
            video.srcObject = newStream;
        }

        // If streaming, replace track in LiveKit
        if (livekitRoom && livekitRoom.localParticipant) {
            // Find current video publication
            const videoPublication = Array.from(livekitRoom.localParticipant.videoTracks.values())[0];

            if (videoPublication) {
                // Replace the track
                await livekitRoom.localParticipant.unpublishTrack(videoPublication.track);
                await livekitRoom.localParticipant.publishTrack(newVideoTrack, {
                    name: 'camera',
                    simulcast: true,
                });

                console.log('✅ Camera switched during live stream');
            }
        }

        selectedCameraId = deviceId;
        console.log('✅ Camera switched successfully');

    } catch (err) {
        console.error('❌ Failed to switch camera:', err);
        alert('Failed to switch camera: ' + err.message);
    }
}

// Switch microphone function (can be called during live stream)
async function switchMicrophone(deviceId) {
    try {
        console.log('🔄 Switching microphone to:', deviceId);

        // Get new stream with selected microphone
        const newStream = await navigator.mediaDevices.getUserMedia({
            video: false,
            audio: {
                deviceId: deviceId ? { exact: deviceId } : undefined
            }
        });

        const newAudioTrack = newStream.getAudioTracks()[0];

        // Update preview stream
        if (video && video.srcObject) {
            const oldStream = video.srcObject;
            const oldAudioTrack = oldStream.getAudioTracks()[0];

            if (oldAudioTrack) {
                // Replace track in preview
                oldStream.removeTrack(oldAudioTrack);
                oldStream.addTrack(newAudioTrack);

                // Stop old track
                oldAudioTrack.stop();
            } else {
                // No audio track yet, just add it
                oldStream.addTrack(newAudioTrack);
            }
        }

        // If streaming, replace track in LiveKit
        if (livekitRoom && livekitRoom.localParticipant) {
            // Find current audio publication
            const audioPublication = Array.from(livekitRoom.localParticipant.audioTracks.values())[0];

            if (audioPublication) {
                // Replace the track
                await livekitRoom.localParticipant.unpublishTrack(audioPublication.track);
                await livekitRoom.localParticipant.publishTrack(newAudioTrack, {
                    name: 'microphone',
                });

                console.log('✅ Microphone switched during live stream');
            }
        }

        selectedMicId = deviceId;
        console.log('✅ Microphone switched successfully');

    } catch (err) {
        console.error('❌ Failed to switch microphone:', err);
        alert('Failed to switch microphone: ' + err.message);
    }
}

// Initialize camera function
async function initializeCamera() {
    try {
        console.log('🎬 Initializing camera...');

        // Request permissions first
        const stream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
        });

        // Store current stream
        currentStream = stream;

        // Hide permission warnings
        if (permissionWarning) {
            permissionWarning.style.display = 'none';
        }
        if (permissionAlert) {
            permissionAlert.style.display = 'none';
        }
        if (requestPermissionBtn) {
            requestPermissionBtn.style.display = 'none';
        }

        // Show success message
        if (permissionSuccess) {
            permissionSuccess.style.display = 'flex';
            setTimeout(() => {
                permissionSuccess.style.display = 'none';
            }, 3000);
        }

        // Enumerate devices (now we have permission, labels will be available)
        await enumerateDevices();

        // Get camera with ideal settings
        const finalStream = await navigator.mediaDevices.getUserMedia({
            video: {
                deviceId: selectedCameraId ? { exact: selectedCameraId } : undefined,
                width: { ideal: 1280 },
                height: { ideal: 720 },
                frameRate: { ideal: 30 }
            },
            audio: {
                deviceId: selectedMicId ? { exact: selectedMicId } : undefined
            }
        });

        // Stop initial stream
        stream.getTracks().forEach(track => track.stop());

        // Update current stream
        currentStream = finalStream;

        // Show preview
        if (video) {
            video.srcObject = finalStream;
            video.muted = true;
            await video.play();
            console.log('✅ Camera preview ready');
        }

        // Enable start button
        if (startBtn) {
            startBtn.disabled = false;
        }

    } catch (err) {
        console.error('❌ Camera initialization failed:', err);

        // Show permission alert
        if (permissionAlert) {
            permissionAlert.style.display = 'flex';
        }

        // Show permission warning overlay
        if (permissionWarning) {
            permissionWarning.style.display = 'flex';
        }

        // Show request button
        if (requestPermissionBtn) {
            requestPermissionBtn.style.display = 'inline-flex';
        }

        // Update error message
        if (err.name === 'NotAllowedError') {
            console.error('Camera permission denied');
        } else if (err.name === 'NotFoundError') {
            console.error('No camera detected');
        }
    }
}

// Check permission status on page load
async function checkPermissionStatus() {
    try {
        // Check if permissions API is available
        if (!navigator.permissions) {
            console.log('⚠️ Permissions API not available');
            return;
        }

        const cameraPermission = await navigator.permissions.query({ name: 'camera' });
        const micPermission = await navigator.permissions.query({ name: 'microphone' });

        console.log('📹 Camera permission:', cameraPermission.state);
        console.log('🎤 Microphone permission:', micPermission.state);

        // If both granted, auto-initialize
        if (cameraPermission.state === 'granted' && micPermission.state === 'granted') {
            console.log('✅ Permissions already granted, auto-initializing...');
            await initializeCamera();
        } else if (cameraPermission.state === 'prompt' || micPermission.state === 'prompt') {
            // Show request button
            if (requestPermissionBtn) {
                requestPermissionBtn.style.display = 'inline-flex';
            }
        } else if (cameraPermission.state === 'denied' || micPermission.state === 'denied') {
            // Show error message
            if (permissionAlert) {
                permissionAlert.style.display = 'flex';
            }
            if (permissionWarning) {
                permissionWarning.style.display = 'flex';
            }
        }

        // Listen for permission changes
        cameraPermission.addEventListener('change', async () => {
            console.log('📹 Camera permission changed to:', cameraPermission.state);
            if (cameraPermission.state === 'granted') {
                await initializeCamera();
            }
        });

        micPermission.addEventListener('change', async () => {
            console.log('🎤 Microphone permission changed to:', micPermission.state);
            if (micPermission.state === 'granted') {
                await initializeCamera();
            }
        });

    } catch (err) {
        console.warn('⚠️ Could not check permissions:', err);
        // Try to initialize anyway
        await initializeCamera();
    }
}

// Initialize
console.log('✅ LiveKit Broadcaster initialized');

// Check permissions on page load
checkPermissionStatus();

// Load chat history
loadChatHistory();

// Reverb connection status
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
});

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Reverb connection error:', err);
});
