// DOM elements
const video = document.getElementById('video-player');

// ✅ FIX: Wait for Echo to be available with retry logic
let echo = null;
let echoInitAttempts = 0;
const MAX_ECHO_INIT_ATTEMPTS = 20;

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
            return false;
        }
    }
    
    console.log('✅ Laravel Echo initialized');
    setupReverbConnection();
    return true;
}

function setupReverbConnection() {
    // Setup connection event handlers
    echo.connector.pusher.connection.bind('connected', () => {
        console.log('✅ Connected to Reverb');
        reverbReady = true;
        reconnectAttempts = 0;
        
        if (subscriptionQueue.length > 0) {
            console.log('🔄 Processing queued subscription...');
            subscriptionQueue = [];
            subscribeToChannel();
        } else {
            subscribeToChannel();
        }
    });

    // ...existing connection handlers...
}

function subscribeToChannel() {
    // ...existing subscribe logic with fixes...
}

function setupChannelListeners() {
    if (!channelSubscription) {
        console.warn('⚠️ Cannot setup listeners: channel not subscribed');
        return;
    }
    
    const channel = channelSubscription;
    
    // ...existing event listeners...
}

// Initialize
initializeEcho();