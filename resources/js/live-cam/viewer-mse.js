/**
 * MediaSource-based Live Streaming Viewer
 * Progressive chunk loading for scalable live streaming
 */

const streamId = window.streamId;
let mediaSource = null;
let sourceBuffer = null;
let queue = [];
let isUpdating = false;
let lastChunkIndex = -1;
let fetchInterval = null;
let isStreamActive = false;
let fetchingChunks = new Set(); // Track chunks being fetched to prevent duplicates

console.log('👁️ MSE Viewer starting...');
console.log('Stream ID:', streamId);

const video = document.getElementById('video-player');
const loadingIndicator = document.getElementById('loading-indicator');
const offlinePlaceholder = document.getElementById('offline-placeholder');

// Add video event listeners to handle loading state and auto-play
if (video) {
    video.addEventListener('loadeddata', () => {
        console.log('📹 Video loaded data');
    });

    video.addEventListener('canplay', () => {
        console.log('📹 Video can play');
        if (loadingIndicator) loadingIndicator.classList.add('hidden');

        // Try to auto-play and unmute
        if (video.paused) {
            video.play().then(() => {
                // Unmute after successful play
                video.muted = false;
                console.log('🔊 Video auto-playing with audio');
            }).catch((err) => {
                console.log('⚠️ Auto-play prevented by browser:', err.message);
            });
        }
    });

    video.addEventListener('playing', () => {
        console.log('📹 Video is playing');
        if (loadingIndicator) loadingIndicator.classList.add('hidden');
    });

    // Removed 'waiting' event listener to prevent buffering indicator from showing

    video.addEventListener('error', (e) => {
        console.error('❌ Video element error:', e);
    });
}

// Subscribe to PUBLIC channel (no auth required - like Pusher version)
const channel = window.Echo.channel(`stream.${streamId}`);
console.log('📡 Viewer subscribed to channel:', `stream.${streamId}`);

// Debug: Log all events
channel.listenForWhisper('*', (event) => {
    console.log('🔔 Whisper event:', event);
});

window.Echo.connector.pusher.connection.bind('state_change', function (states) {
    console.log('🔌 Echo connection state:', states.current);
});

window.Echo.connector.pusher.connection.bind('connected', function () {
    console.log('✅ Echo connected to Reverb');
});

window.Echo.connector.pusher.connection.bind('error', function (err) {
    console.error('❌ Echo connection error:', err);
});

// Track viewer presence
let hasNotifiedPresence = false;
async function notifyViewerJoined() {
    if (hasNotifiedPresence) return;
    try {
        const resp = await fetch(`/live-cam/${streamId}/viewer-count`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ action: 'join' })
        });
        if (resp.ok) {
            hasNotifiedPresence = true;
            console.log('👋 Notified viewer joined');
        } else {
            const txt = await resp.text();
            console.error('Failed to notify viewer joined:', resp.status, txt);
        }
    } catch (err) {
        console.error('Failed to notify viewer joined:', err);
    }
}

async function notifyViewerLeft() {
    if (!hasNotifiedPresence) return;
    try {
        const resp = await fetch(`/live-cam/${streamId}/viewer-count`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ action: 'leave' })
        });
        if (resp.ok) {
            console.log('👋 Notified viewer left');
        } else {
            const txt = await resp.text();
            console.error('Failed to notify viewer left:', resp.status, txt);
        }
    } catch (err) {
        console.error('Failed to notify viewer left:', err);
    }
}

// Notify when viewer leaves
window.addEventListener('beforeunload', notifyViewerLeft);
window.addEventListener('pagehide', notifyViewerLeft);

// Listen for new chunks
channel.listen('.new-chunk', (data) => {
    console.log('📨 New chunk available:', data.index);
    if (isStreamActive) {
        // Only fetch the next sequential chunk
        if (data.index === lastChunkIndex + 1) {
            fetchAndAppendChunk(data.index);
        }
    }
});

// Listen for stream status changes
channel.listen('.stream-started', () => {
    console.log('🎬 Stream started');
    cleanupMediaSource(); // Cleanup old MediaSource first
    isStreamActive = true;
    lastChunkIndex = -1; // Reset chunk index
    queue = []; // Clear queue
    initializeMediaSource();
});

channel.listen('.stream-ended', () => {
    console.log('🛑 Stream ended');
    isStreamActive = false;
    cleanupMediaSource();
    if (loadingIndicator) loadingIndicator.classList.add('hidden');
    if (offlinePlaceholder) offlinePlaceholder.classList.remove('hidden');
});

// Listen for viewer count updates
channel.listen('.viewer-count-updated', (data) => {
    console.log('👥 Viewer count updated:', data.count);
    const viewerCountEl = document.getElementById('viewer-count');
    if (viewerCountEl) {
        viewerCountEl.textContent = data.count;
    }
    const chatViewerCountEl = document.getElementById('chat-viewer-count');
    if (chatViewerCountEl) {
        chatViewerCountEl.textContent = data.count;
    }
});

// Listen for chat messages
channel.listen('.chat-message', (data) => {
    console.log('💬 Chat message received:', data);
    // The event now sends username, message, created_at directly
    const messageData = {
        username: data.username,
        message: data.message,
        created_at: data.created_at
    };
    console.log('💬 Processed message data:', messageData);
    console.log('💬 Chat container exists:', !!document.getElementById('chat-messages'));
    addChatMessage(messageData);
    console.log('💬 Message added to chat');
});

// Cleanup MediaSource
function cleanupMediaSource() {
    console.log('🧹 Cleaning up MediaSource...');

    if (fetchInterval) {
        clearInterval(fetchInterval);
        fetchInterval = null;
    }

    queue = [];
    isUpdating = false;
    fetchingChunks.clear(); // Clear fetching tracker

    if (mediaSource) {
        try {
            if (mediaSource.readyState === 'open') {
                if (sourceBuffer && !sourceBuffer.updating) {
                    mediaSource.endOfStream();
                }
            }
        } catch (err) {
            console.log('MediaSource already closed');
        }
        mediaSource = null;
        sourceBuffer = null;
    }

    if (video) {
        video.src = '';
        video.load();
    }
}

// Initialize MediaSource
function initializeMediaSource() {
    if (!video) {
        console.error('❌ Video element not found');
        return;
    }

    if (!window.MediaSource) {
        console.error('❌ MediaSource not supported');
        alert('Your browser does not support live streaming. Please use a modern browser.');
        return;
    }

    console.log('🎬 Initializing MediaSource...');

    mediaSource = new MediaSource();
    video.src = URL.createObjectURL(mediaSource);

    mediaSource.addEventListener('sourceopen', () => {
        console.log('✅ MediaSource opened');

        try {
            // Use WebM with VP8 only (video only, no audio)
            sourceBuffer = mediaSource.addSourceBuffer('video/webm; codecs="vp8"');

            sourceBuffer.addEventListener('updateend', () => {
                isUpdating = false;
                processQueue();
            });

            sourceBuffer.addEventListener('error', (e) => {
                console.error('❌ SourceBuffer error:', e);
                // Stop streaming on error
                isStreamActive = false;
                cleanupMediaSource();
            });

            // Hide loading, show video
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
            if (offlinePlaceholder) offlinePlaceholder.classList.add('hidden');

            // Start fetching chunks
            startFetching();

        } catch (err) {
            console.error('❌ Failed to create SourceBuffer:', err);
            alert('Failed to initialize video player: ' + err.message);
        }
    });

    mediaSource.addEventListener('sourceended', () => {
        console.log('🏁 MediaSource ended');
    });

    mediaSource.addEventListener('sourceclose', () => {
        console.log('🔌 MediaSource closed');
    });
}

// Start fetching chunks
function startFetching() {
    console.log('📡 Starting chunk fetching...');

    // Fetch initial chunk
    fetchAndAppendChunk(0);

    // Polling fallback: check for next chunk if Echo event is missed
    // This ensures we don't miss chunks even if network is slow
    const pollingInterval = setInterval(() => {
        if (!isStreamActive || !mediaSource || mediaSource.readyState !== 'open') {
            clearInterval(pollingInterval);
            return;
        }

        // Try to fetch the next expected chunk
        const nextIndex = lastChunkIndex + 1;
        fetchAndAppendChunk(nextIndex);
    }, 2400); // Poll every 2.4 seconds (slightly longer than 2s chunk interval)
}

// Fetch and append chunk
async function fetchAndAppendChunk(index) {
    // Prevent duplicate fetches
    if (!isStreamActive) {
        return;
    }

    if (index <= lastChunkIndex) {
        return;
    }

    // Check if chunk is already in queue
    if (queue.some(item => item.index === index)) {
        return;
    }

    // Check if already fetching this chunk
    if (fetchingChunks.has(index)) {
        return;
    }

    // Mark as fetching
    fetchingChunks.add(index);

    try {
        const response = await fetch(`/live-cam/${streamId}/chunk/${index}`);

        if (!response.ok) {
            fetchingChunks.delete(index);
            return;
        }

        const arrayBuffer = await response.arrayBuffer();

        if (arrayBuffer.byteLength === 0) {
            fetchingChunks.delete(index);
            return;
        }

        console.log(`📦 Fetched chunk ${index}: ${(arrayBuffer.byteLength / 1024).toFixed(2)} KB`);

        queue.push({
            index: index,
            data: arrayBuffer
        });

        lastChunkIndex = index;
        fetchingChunks.delete(index);
        processQueue();

    } catch (err) {
        fetchingChunks.delete(index);
        // Silently fail - chunk might not be available yet
    }
}

// Process queue
function processQueue() {
    if (isUpdating || queue.length === 0) {
        return;
    }

    if (!sourceBuffer || sourceBuffer.updating) {
        return;
    }

    if (!mediaSource || mediaSource.readyState !== 'open') {
        return;
    }

    if (!isStreamActive) {
        return;
    }

    const chunk = queue.shift();

    try {
        isUpdating = true;
        sourceBuffer.appendBuffer(chunk.data);
        console.log(`✅ Appended chunk ${chunk.index}`);

        // Auto-play is handled by video 'canplay' event listener

    } catch (err) {
        console.error(`❌ Failed to append chunk ${chunk.index}:`, err);
        isUpdating = false;
    }
}

// Check stream status on load
async function checkStreamStatus() {
    try {
        const response = await fetch(`/live-cam/${streamId}/status`);
        const data = await response.json();

        if (data.is_live) {
            console.log('🟢 Stream is live');
            isStreamActive = true;
            if (loadingIndicator) loadingIndicator.classList.remove('hidden');
            if (offlinePlaceholder) offlinePlaceholder.classList.add('hidden');
            initializeMediaSource();
        } else {
            console.log('⚫ Stream is offline');
            isStreamActive = false;
            if (loadingIndicator) loadingIndicator.classList.add('hidden');
            if (offlinePlaceholder) offlinePlaceholder.classList.remove('hidden');
        }
    } catch (err) {
        console.error('❌ Failed to check stream status:', err);
        if (loadingIndicator) loadingIndicator.classList.add('hidden');
        if (offlinePlaceholder) offlinePlaceholder.classList.remove('hidden');
    }
}

// Load chat history on page load
async function loadChatHistory() {
    try {
        const response = await fetch(`/live-cam/${streamId}/chat-history`);
        const data = await response.json();

        if (data.messages && data.messages.length > 0) {
            console.log(`📜 Loading ${data.messages.length} chat messages from history`);
            data.messages.forEach(message => {
                addChatMessage(message, true); // true = from history
            });
        } else {
            console.log('📜 No chat history found');
        }
    } catch (err) {
        console.error('❌ Failed to load chat history:', err);
    }
}

// Chat functions
function addChatMessage(message, fromHistory = false) {
    const chatContainer = document.getElementById('chat-messages');
    if (!chatContainer) return;

    // Remove welcome message if exists (only for first message)
    const welcomeMsg = chatContainer.querySelector('.text-center');
    if (welcomeMsg) {
        welcomeMsg.remove();
    }

    const messageEl = document.createElement('div');
    messageEl.className = 'p-3 rounded-lg bg-base-100 border border-base-300';
    messageEl.innerHTML = `
        <div class="flex items-start gap-2">
            <div class="avatar placeholder flex-shrink-0">
                <div class="bg-neutral text-neutral-content rounded-full w-8 h-8">
                    <span class="text-xs font-semibold">${escapeHtml(message.username.charAt(0).toUpperCase())}</span>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-baseline justify-between gap-2 mb-1">
                    <p class="text-sm font-semibold truncate">${escapeHtml(message.username)}</p>
                    <p class="text-xs text-base-content/50 flex-shrink-0">${formatTime(message.created_at)}</p>
                </div>
                <p class="text-sm break-words">${escapeHtml(message.message)}</p>
            </div>
        </div>
    `;

    chatContainer.appendChild(messageEl);

    // Smooth scroll to bottom
    chatContainer.scrollTo({
        top: chatContainer.scrollHeight,
        behavior: 'smooth'
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatTime(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

// Chat form handler
const chatForm = document.getElementById('chat-form');
const chatInput = document.getElementById('chat-input');
const chatUsername = window.chatUsername;

if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // IMPORTANT: Prevent page reload!

        const message = chatInput.value.trim();
        if (!message) return;

        try {
            const response = await fetch(`/live-cam/${streamId}/chat`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    username: chatUsername,
                    message: message
                })
            });

            if (response.ok) {
                const data = await response.json();
                chatInput.value = '';
                // Don't add message locally - it will come back via broadcast
                console.log('✅ Chat message sent');
            } else {
                const errorText = await response.text();
                console.error('Failed to send chat:', response.status, errorText);
                alert('Failed to send message: ' + errorText);
            }
        } catch (err) {
            console.error('Error sending chat:', err);
            alert('Error sending message: ' + err.message);
        }
    });
}

// Character counter
if (chatInput) {
    const charCounter = document.getElementById('char-counter');
    chatInput.addEventListener('input', () => {
        if (charCounter) {
            charCounter.textContent = `${chatInput.value.length}/200`;
        }
    });
}

// Initialize - wait for page to fully load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        checkStreamStatus();
        notifyViewerJoined();
        loadChatHistory();
    });
} else {
    checkStreamStatus();
    notifyViewerJoined();
    loadChatHistory();
}

console.log('✅ MSE Viewer initialized');
