/**
 * LiveKit Viewer with Pure Reverb Signaling - MuncakID Livestreaming
 * 
 * Features:
 * - LiveKit SFU for video playback (ultra-low latency)
 * - Reverb (Laravel Echo) for chat & metadata signaling
 * - Mirror state sync
 * - Mid-stream join support (automatic)
 */

import { Room, RoomEvent, Track } from 'livekit-client';

const streamId = window.streamId;
const streamSlug = window.streamSlug || streamId; // Fallback to ID if slug is empty

let livekitRoom = null;
let hasJoined = false;

console.log('👁️ LiveKit Viewer (Pure Reverb) starting...');
console.log('Stream ID:', streamId);
console.log('Stream Slug/ID:', streamSlug);

// Reverb setup via Laravel Echo
const echo = window.Echo;

if (!echo) {
    console.error('❌ Laravel Echo not initialized!');
    alert('Error: Real-time connection not available. Please refresh the page.');
}

const channel = echo.channel(`stream.${streamId}`);
console.log('📡 Viewer subscribed to Reverb channel:', `stream.${streamId}`);

// DOM elements
const video = document.getElementById('video-player');
const chatMessages = document.getElementById('chat-messages');
const chatInput = document.getElementById('chat-input');
const chatForm = document.getElementById('chat-form');
const charCounter = document.getElementById('char-counter');
const viewerCountEl = document.getElementById('viewer-count');

// Listen for mirror state changes (Reverb)
channel.listen('.MirrorStateChanged', (data) => {
    console.log('🪞 Mirror state changed:', data.is_mirrored);
    if (video) {
        video.style.transform = data.is_mirrored ? 'scaleX(-1)' : 'scaleX(1)';
    }
});

// Listen for chat messages (Reverb)
channel.listen('.ChatMessageSent', (data) => {
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
channel.listen('.ViewerCountUpdated', (data) => {
    console.log('👥 Viewer count updated:', data.count);
    if (viewerCountEl) {
        viewerCountEl.textContent = data.count;
    }
    const chatViewerCount = document.getElementById('chat-viewer-count');
    if (chatViewerCount) {
        chatViewerCount.textContent = data.count;
    }
});

// Listen for stream status changes (Reverb)
channel.listen('.StreamStarted', () => {
    console.log('🟢 Stream started');
    initializeViewer();
});

channel.listen('.StreamStopped', () => {
    console.log('🔴 Stream ended');

    if (livekitRoom) {
        livekitRoom.disconnect();
        livekitRoom = null;
    }

    if (video) {
        video.srcObject = null;
    }

    const loadingIndicator = document.querySelector('.absolute.inset-0.flex.items-center.justify-center');
    if (loadingIndicator) {
        loadingIndicator.remove();
    }

    let videoContainer = null;
    if (video && video.parentElement) {
        videoContainer = video.parentElement;
    } else {
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

    setTimeout(() => {
        window.location.href = '/live-cam';
    }, 3000);
});

// Initialize viewer
async function initializeViewer() {
    try {
        console.log('🎬 Initializing LiveKit viewer...');

        const tokenResponse = await fetch(`/live-cam/${streamSlug}/livekit/token`);
        const tokenData = await tokenResponse.json();

        if (!tokenData.success) {
            throw new Error('Failed to get LiveKit token');
        }

        console.log('✅ Got LiveKit token');
        console.log('🔗 Connecting to:', tokenData.url);
        console.log('🏠 Room:', tokenData.room);

        livekitRoom = new Room({
            adaptiveStream: true,
            dynacast: true,
        });

        livekitRoom.on(RoomEvent.Connected, () => {
            console.log('✅ Connected to LiveKit room');
        });

        livekitRoom.on(RoomEvent.TrackSubscribed, (track, publication, participant) => {
            console.log('📺 Track subscribed:', track.kind, 'from', participant.identity);

            if (track.kind === Track.Kind.Video && video) {
                track.attach(video);
                video.play();
                console.log('✅ Video playing');

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

        await livekitRoom.connect(tokenData.url, tokenData.token);

        await updateViewerCount(1);
        hasJoined = true;

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

        if (action === 'leave') {
            const blob = new Blob([data], { type: 'application/json' });
            navigator.sendBeacon(url, blob);
        } else {
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
if (chatInput && charCounter) {
    chatInput.addEventListener('input', () => {
        charCounter.textContent = `${chatInput.value.length}/200`;
    });
}

if (chatForm) {
    chatForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const message = chatInput.value.trim();
        if (!message) return;

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

        if (data.messages && data.messages.length > 0 && chatMessages) {
            data.messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'chat-message';
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

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (livekitRoom && hasJoined) {
        updateViewerCount(-1);
        livekitRoom.disconnect();
    }
});

// Initialize
console.log('✅ LiveKit Viewer (Pure Reverb) initialized');

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

// Reverb connection status
echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ Connected to Reverb');
});

echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ Reverb connection error:', err);
});
