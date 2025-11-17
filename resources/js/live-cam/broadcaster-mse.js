/**
 * MediaRecorder-based Live Streaming Broadcaster
 * Simple, scalable live streaming like YouTube
 * No peer-to-peer, no SDP, no codec issues
 */

let localStream = null;
let mediaRecorder = null;
let isStreaming = false;
let chunkCounter = 0;
let echoChannel = null;
let currentStreamId = null;

console.log('🎥 MSE Broadcaster module loading...');

// Initialize Echo channel (called after DOM is ready and Echo is available)
function initializeBroadcasterEcho(streamId) {
    currentStreamId = streamId;
    console.log('🎥 MSE Broadcaster initializing Echo for stream:', streamId);

    if (!window.Echo) {
        console.error('❌ Echo is not available yet!');
        return;
    }

    // Subscribe to PUBLIC channel (no auth required - like Pusher version)
    echoChannel = window.Echo.channel(`stream.${streamId}`);
    console.log('📡 Broadcaster subscribed to channel:', `stream.${streamId}`);

    // Listen for viewer count updates
    echoChannel.listen('.viewer-count-updated', (data) => {
        console.log('👥 Viewer count updated:', data.count);
        updateViewerCount(data.count);
    });

    // Listen for chat messages
    echoChannel.listen('.chat-message', (data) => {
        console.log('💬 Chat message:', data);
        addChatMessage(data.message);
    });
}

function updateViewerCount(count) {
    const viewerCountEl = document.getElementById('viewerCount');
    if (viewerCountEl) {
        viewerCountEl.textContent = count;
    }
}

function addChatMessage(message) {
    const chatContainer = document.getElementById('chatMessages');
    if (!chatContainer) return;

    // Remove "no messages" placeholder
    const placeholder = chatContainer.querySelector('.text-gray-500');
    if (placeholder && placeholder.textContent.includes('No messages')) {
        chatContainer.innerHTML = '';
    }

    const messageEl = document.createElement('div');
    messageEl.className = 'mb-3 rounded-lg bg-base-100 p-3';
    messageEl.innerHTML = `
        <div class="flex items-start gap-2">
            <div class="flex-1">
                <p class="text-sm font-semibold">${escapeHtml(message.username)}</p>
                <p class="text-sm">${escapeHtml(message.message)}</p>
                <p class="mt-1 text-xs text-gray-500">${new Date(message.created_at).toLocaleTimeString()}</p>
            </div>
        </div>
    `;

    chatContainer.appendChild(messageEl);
    chatContainer.scrollTop = chatContainer.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Setup camera (already handled by main script)
// The main broadcast.blade.php script handles camera initialization

// Start MediaRecorder
function startRecording() {
    try {
        if (!localStream) {
            console.error('❌ No local stream available');
            alert('Camera not ready. Please allow camera access first.');
            return;
        }

        // Optimized settings for efficiency
        const options = {
            mimeType: 'video/webm;codecs=vp8,opus',
            videoBitsPerSecond: 800000,  // 800 Kbps - efficient for 720p
            audioBitsPerSecond: 64000    // 64 Kbps for audio
        };

        mediaRecorder = new MediaRecorder(localStream, options);

        mediaRecorder.ondataavailable = async (event) => {
            if (event.data && event.data.size > 0 && isStreaming) {
                console.log(`📦 Chunk ${chunkCounter}: ${(event.data.size / 1024).toFixed(2)} KB`);
                await uploadChunk(event.data, chunkCounter++);
            }
        };

        mediaRecorder.onerror = (event) => {
            console.error('❌ MediaRecorder error:', event.error);
        };

        mediaRecorder.onstop = () => {
            console.log('🛑 MediaRecorder stopped');
        };

        // Record in 1-second chunks (lower latency)
        mediaRecorder.start(1000);
        console.log('🎬 Recording started - 1s chunks at 800 Kbps');

    } catch (err) {
        console.error('❌ Failed to start MediaRecorder:', err);
        alert('Your browser does not support video recording: ' + err.message);
    }
}

// Upload chunk to server
async function uploadChunk(blob, index) {
    if (!currentStreamId) {
        console.error('❌ Stream ID not set! Call initializeBroadcasterEcho first.');
        return;
    }

    // Detect if we're on admin or public page
    const basePath = window.location.pathname.includes('/admin/')
        ? `/admin/live-stream/${currentStreamId}`
        : `/live-cam/${currentStreamId}`;

    const formData = new FormData();
    formData.append('chunk', blob);
    formData.append('index', index);
    formData.append('timestamp', Date.now());

    try {
        const response = await fetch(`${basePath}/upload-chunk`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            console.log(`✅ Chunk ${index} uploaded`);
            // Note: Backend will broadcast NewChunkAvailable event via Reverb
        } else {
            console.error(`❌ Failed to upload chunk ${index}:`, data.error);
        }

    } catch (err) {
        console.error(`❌ Upload error for chunk ${index}:`, err);
    }
}

// Stop streaming
function stopRecording() {
    console.log('🛑 Stopping recording...');

    // Stop MediaRecorder
    if (mediaRecorder && mediaRecorder.state !== 'inactive') {
        mediaRecorder.stop();
    }

    isStreaming = false;
    chunkCounter = 0;
}

// Setup camera immediately when module loads
async function setupCamera() {
    console.log('📷 Setting up camera...');

    const permissionWarning = document.getElementById('permission-warning');
    const localVideo = document.getElementById('localVideo');
    const permissionAlert = document.getElementById('permissionAlert');
    const requestPermissionBtn = document.getElementById('requestPermissionBtn');

    try {
        // Check if we already have permission
        const devices = await navigator.mediaDevices.enumerateDevices();
        const hasLabels = devices.some(device => device.label !== '');

        if (hasLabels) {
            console.log('✅ Already have camera permission');
            // Already have permission, setup media directly
            await requestMediaAccess();
        } else {
            console.log('⚠️ No camera permission yet, showing request button');
            // Show permission request UI
            if (permissionAlert) permissionAlert.style.display = 'flex';
            if (requestPermissionBtn) {
                requestPermissionBtn.style.display = 'block';
                requestPermissionBtn.addEventListener('click', requestMediaAccess);
            }
        }
    } catch (error) {
        console.error('❌ Camera setup error:', error);
        if (permissionAlert) permissionAlert.style.display = 'flex';
        if (requestPermissionBtn) {
            requestPermissionBtn.style.display = 'block';
            requestPermissionBtn.addEventListener('click', requestMediaAccess);
        }
    }
}

async function requestMediaAccess() {
    console.log('📷 Requesting camera access...');

    const permissionWarning = document.getElementById('permission-warning');
    const localVideo = document.getElementById('localVideo');
    const permissionAlert = document.getElementById('permissionAlert');
    const permissionSuccess = document.getElementById('permissionSuccess');
    const requestPermissionBtn = document.getElementById('requestPermissionBtn');
    const startStreamBtn = document.getElementById('startStreamBtn');
    const cameraSelect = document.getElementById('cameraSelect');
    const micSelect = document.getElementById('micSelect');

    try {
        // Request camera and microphone permission
        const tempStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: true
        });

        console.log('✅ Permission granted!');

        // Stop temp stream
        tempStream.getTracks().forEach(track => track.stop());

        // Now enumerate devices with labels
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');
        const audioDevices = devices.filter(d => d.kind === 'audioinput');

        console.log(`📹 Found ${videoDevices.length} cameras, ${audioDevices.length} microphones`);

        // Populate device selects
        if (cameraSelect) {
            cameraSelect.innerHTML = videoDevices.map((device, index) =>
                `<option value="${device.deviceId}">${device.label || `Camera ${index + 1}`}</option>`
            ).join('');
        }

        if (micSelect) {
            micSelect.innerHTML = audioDevices.map((device, index) =>
                `<option value="${device.deviceId}">${device.label || `Microphone ${index + 1}`}</option>`
            ).join('');
        }

        // Start camera preview
        await startCameraPreview();

        // Update UI
        if (permissionWarning) permissionWarning.style.display = 'none';
        if (permissionAlert) permissionAlert.style.display = 'none';
        if (requestPermissionBtn) requestPermissionBtn.style.display = 'none';
        if (permissionSuccess) {
            permissionSuccess.style.display = 'flex';
            setTimeout(() => {
                permissionSuccess.style.display = 'none';
            }, 3000);
        }
        if (startStreamBtn) startStreamBtn.disabled = false;

        // Add device change listeners
        if (cameraSelect) cameraSelect.addEventListener('change', startCameraPreview);
        if (micSelect) micSelect.addEventListener('change', startCameraPreview);

        console.log('✅ Camera setup complete');

    } catch (error) {
        console.error('❌ Permission denied or error:', error);
        alert('Camera/Microphone permission denied. Please allow access in your browser settings and reload the page.');
    }
}

async function startCameraPreview() {
    console.log('🎬 Starting camera preview...');

    const localVideo = document.getElementById('localVideo');
    const cameraSelect = document.getElementById('cameraSelect');
    const micSelect = document.getElementById('micSelect');
    const permissionWarning = document.getElementById('permission-warning');
    const qualityInputs = document.getElementsByName('quality');
    const selectedQuality = Array.from(qualityInputs).find(input => input.checked)?.value || '720p';

    const qualitySettings = {
        '360p': { width: 640, height: 360 },
        '720p': { width: 1280, height: 720 },
        '1080p': { width: 1920, height: 1080 }
    };

    const constraints = {
        video: {
            deviceId: cameraSelect?.value ? { exact: cameraSelect.value } : undefined,
            ...qualitySettings[selectedQuality]
        },
        audio: {
            deviceId: micSelect?.value ? { exact: micSelect.value } : undefined,
            echoCancellation: true,
            noiseSuppression: true
        }
    };

    try {
        // Stop existing stream if any
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
        }

        // Get new stream
        localStream = await navigator.mediaDevices.getUserMedia(constraints);

        if (localVideo) {
            localVideo.srcObject = localStream;
            await localVideo.play();
        }

        if (permissionWarning) permissionWarning.style.display = 'none';

        console.log(`✅ Camera preview started (${selectedQuality})`);

    } catch (error) {
        console.error('❌ Failed to start camera preview:', error);
        if (permissionWarning) permissionWarning.style.display = 'flex';
    }
}

// Export functions to global scope
window.broadcasterMSE = {
    initializeBroadcasterEcho,
    startRecording,
    stopRecording,
    setupCamera,
    requestMediaAccess,
    setLocalStream: (stream) => {
        localStream = stream;
    },
    setIsStreaming: (value) => {
        isStreaming = value;
    }
};

console.log('✅ Broadcaster MSE module loaded');

// Auto-initialize camera setup when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        console.log('📄 DOM ready, initializing broadcaster...');
        setupCamera();
    });
} else {
    // DOM already loaded
    console.log('📄 DOM already ready, initializing broadcaster immediately...');
    setupCamera();
}
