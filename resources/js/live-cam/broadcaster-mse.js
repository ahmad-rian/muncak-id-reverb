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
let classificationInterval = null;
const CLASSIFICATION_INTERVAL_MS = 2 * 60 * 1000; // 2 minutes (testing)

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
        console.log('💬 Chat message received:', data);
        // The event now sends username, message, created_at directly
        const messageData = {
            username: data.username,
            message: data.message,
            created_at: data.created_at
        };
        console.log('💬 Adding to chat monitor:', messageData);
        addChatMessage(messageData);
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

        // Optimized settings for efficiency - video only, no audio
        const options = {
            mimeType: 'video/webm;codecs=vp8',
            videoBitsPerSecond: 600000,  // 600 Kbps - more stable for 720p
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

        // Record in 2-second chunks (more stable, less buffering)
        mediaRecorder.start(2000);
        console.log('🎬 Recording started - 2s chunks at 600 Kbps (video only)');

        // Capture thumbnail on first start
        setTimeout(captureThumbnail, 2000);

        // Start classification interval for AI analysis
        startClassificationInterval();

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

    // Stop classification interval
    stopClassificationInterval();

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
        // Request camera permission only (no audio)
        const tempStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        });

        console.log('✅ Permission granted!');

        // Stop temp stream
        tempStream.getTracks().forEach(track => track.stop());

        // Now enumerate devices with labels
        const devices = await navigator.mediaDevices.enumerateDevices();
        const videoDevices = devices.filter(d => d.kind === 'videoinput');

        console.log(`📹 Found ${videoDevices.length} cameras`);

        // Populate camera select
        if (cameraSelect) {
            cameraSelect.innerHTML = videoDevices.map((device, index) =>
                `<option value="${device.deviceId}">${device.label || `Camera ${index + 1}`}</option>`
            ).join('');
        }

        // Hide mic select since we don't use audio
        if (micSelect && micSelect.parentElement) {
            micSelect.parentElement.style.display = 'none';
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
            ...qualitySettings[selectedQuality],
            facingMode: 'user' // Front camera by default
        },
        audio: false // No audio
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
            // Mirror the video for front camera
            localVideo.style.transform = 'scaleX(-1)';
            await localVideo.play();
        }

        if (permissionWarning) permissionWarning.style.display = 'none';

        console.log(`✅ Camera preview started (${selectedQuality}) - video only`);

    } catch (error) {
        console.error('❌ Failed to start camera preview:', error);
        if (permissionWarning) permissionWarning.style.display = 'flex';
    }
}

// Toggle mirror camera
function toggleMirror() {
    const localVideo = document.getElementById('localVideo');
    if (localVideo) {
        const currentTransform = localVideo.style.transform;
        localVideo.style.transform = currentTransform.includes('scaleX(-1)') ? 'scaleX(1)' : 'scaleX(-1)';
        console.log('🔄 Mirror toggled');
    }
}

// Capture thumbnail on stream start
async function captureThumbnail() {
    const localVideo = document.getElementById('localVideo');
    if (!localVideo || !localVideo.videoWidth || !currentStreamId) {
        console.log('⏭️ Skipping thumbnail - video not ready');
        return;
    }

    try {
        console.log('📸 Capturing thumbnail...');

        const canvas = document.createElement('canvas');
        canvas.width = localVideo.videoWidth;
        canvas.height = localVideo.videoHeight;
        const ctx = canvas.getContext('2d');

        // Handle mirror transform
        const isMirrored = localVideo.style.transform.includes('scaleX(-1)');
        if (isMirrored) {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        }
        ctx.drawImage(localVideo, 0, 0);

        const base64Image = canvas.toDataURL('image/jpeg', 0.8).split(',')[1];

        const basePath = window.location.pathname.includes('/admin/')
            ? `/admin/live-stream/${currentStreamId}`
            : `/live-cam/${currentStreamId}`;

        const response = await fetch(`${basePath}/save-thumbnail`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ image: base64Image })
        });

        const data = await response.json();
        if (data.success) {
            console.log('✅ Thumbnail saved');
        } else {
            console.error('❌ Thumbnail save failed:', data.error);
        }
    } catch (error) {
        console.error('❌ Error capturing thumbnail:', error);
    }
}

// Capture frame for classification
async function captureFrameForClassification() {
    if (!localStream || !isStreaming) {
        console.log('⏭️ Skipping classification - stream not active');
        return;
    }

    const localVideo = document.getElementById('localVideo');
    if (!localVideo || !localVideo.videoWidth) {
        console.log('⏭️ Skipping classification - video not ready');
        return;
    }

    try {
        console.log('📸 Capturing frame for classification...');

        // Create canvas to capture frame
        const canvas = document.createElement('canvas');
        canvas.width = localVideo.videoWidth;
        canvas.height = localVideo.videoHeight;
        const ctx = canvas.getContext('2d');

        // Draw current video frame (handle mirror transform)
        const isMirrored = localVideo.style.transform.includes('scaleX(-1)');
        if (isMirrored) {
            ctx.translate(canvas.width, 0);
            ctx.scale(-1, 1);
        }
        ctx.drawImage(localVideo, 0, 0);

        // Convert to base64 JPEG
        const base64Image = canvas.toDataURL('image/jpeg', 0.8).split(',')[1];

        // Send to server for classification
        await sendFrameForClassification(base64Image);

    } catch (error) {
        console.error('❌ Error capturing frame:', error);
    }
}

// Send frame to server for Gemini classification
async function sendFrameForClassification(base64Image) {
    if (!currentStreamId) {
        console.error('❌ Stream ID not set for classification');
        return;
    }

    const basePath = window.location.pathname.includes('/admin/')
        ? `/admin/live-stream/${currentStreamId}`
        : `/live-cam/${currentStreamId}`;

    try {
        const response = await fetch(`${basePath}/classify-frame`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                image: base64Image,
                timestamp: Date.now()
            })
        });

        const data = await response.json();

        if (data.success) {
            console.log('✅ Frame classified:', data.classification);
        } else {
            console.error('❌ Classification failed:', data.error);
        }

    } catch (error) {
        console.error('❌ Error sending frame for classification:', error);
    }
}

// Start classification interval
function startClassificationInterval() {
    console.log(`⏰ Classification timer started (every ${CLASSIFICATION_INTERVAL_MS / 60000} minutes)`);

    // Initial classification after 30 seconds
    setTimeout(() => {
        console.log('🚀 Running initial classification...');
        captureFrameForClassification();
    }, 30000);

    // Then repeat every CLASSIFICATION_INTERVAL_MS
    classificationInterval = setInterval(() => {
        console.log('⏰ Auto-classification triggered');
        captureFrameForClassification();
    }, CLASSIFICATION_INTERVAL_MS);
}

// Stop classification interval
function stopClassificationInterval() {
    if (classificationInterval) {
        clearInterval(classificationInterval);
        classificationInterval = null;
        console.log('🛑 Classification interval stopped');
    }
}

// Export functions to global scope
window.broadcasterMSE = {
    initializeBroadcasterEcho,
    startRecording,
    stopRecording,
    setupCamera,
    requestMediaAccess,
    toggleMirror,
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
