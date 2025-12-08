#!/usr/bin/env node

/**
 * Simple Reverb WebSocket Connection Test
 * Tests if we can connect to Reverb server and subscribe to a channel
 */

const Pusher = require('pusher-js');
require('dotenv').config({ path: '.env.artillery' });

// Configuration from environment
const config = {
    key: process.env.REVERB_APP_KEY || 't04ejpaztc3hvzutpy42',
    host: process.env.REVERB_HOST || 'reverb.muncak.id',
    port: parseInt(process.env.REVERB_PORT || '9000'),
    scheme: process.env.REVERB_SCHEME || 'http',
    streamId: process.env.STREAM_ID || '2'
};

const useTLS = config.scheme === 'https';

console.log('╔════════════════════════════════════════════════════════════╗');
console.log('║         Reverb WebSocket Connection Test                  ║');
console.log('╚════════════════════════════════════════════════════════════╝');
console.log('');
console.log('Configuration:');
console.log(`  Host:      ${config.host}`);
console.log(`  Port:      ${config.port}`);
console.log(`  Scheme:    ${config.scheme}`);
console.log(`  Use TLS:   ${useTLS}`);
console.log(`  App Key:   ${config.key}`);
console.log(`  Stream ID: ${config.streamId}`);
console.log('');

// Build Pusher configuration
// Note: cluster is required by pusher-js 8.x but gets overridden by wsHost
const pusherConfig = {
    cluster: 'mt1',  // Dummy cluster, overridden by wsHost
    wsHost: config.host,
    forceTLS: useTLS,
    enabledTransports: ['ws', 'wss'],
    disableStats: true
};

// Set port based on TLS
if (useTLS) {
    pusherConfig.wssPort = config.port;
} else {
    pusherConfig.wsPort = config.port;
}

console.log('Pusher Config:');
console.log(JSON.stringify(pusherConfig, null, 2));
console.log('');

// Create Pusher instance
console.log('Creating Pusher client...');
const pusher = new Pusher(config.key, pusherConfig);

// Track connection state
pusher.connection.bind('state_change', (states) => {
    console.log(`[Connection] State changed: ${states.previous} → ${states.current}`);
});

pusher.connection.bind('connected', () => {
    console.log('✓ Connected to Reverb server!');
    console.log(`  Socket ID: ${pusher.connection.socket_id}`);
});

pusher.connection.bind('error', (err) => {
    console.error('✗ Connection error:', err);
});

pusher.connection.bind('disconnected', () => {
    console.log('✗ Disconnected from Reverb server');
});

// Subscribe to channel
const channelName = `stream.${config.streamId}`;
console.log(`Subscribing to channel: ${channelName}...`);

const channel = pusher.subscribe(channelName);

channel.bind('pusher:subscription_succeeded', () => {
    console.log(`✓ Successfully subscribed to ${channelName}`);
    console.log('');
    console.log('Listening for events...');
    console.log('  - ChatMessageSent');
    console.log('  - ViewerCountUpdated');
    console.log('  - QualityChanged');
    console.log('  - NewChunkAvailable');
    console.log('');
    console.log('Press Ctrl+C to exit');
});

channel.bind('pusher:subscription_error', (err) => {
    console.error(`✗ Subscription error for ${channelName}:`, err);
    process.exit(1);
});

// Listen for events
channel.bind('ChatMessageSent', (data) => {
    console.log('[Event] ChatMessageSent:', data);
});

channel.bind('ViewerCountUpdated', (data) => {
    console.log('[Event] ViewerCountUpdated:', data);
});

channel.bind('QualityChanged', (data) => {
    console.log('[Event] QualityChanged:', data);
});

channel.bind('NewChunkAvailable', (data) => {
    console.log('[Event] NewChunkAvailable:', {
        quality: data.quality,
        timestamp: data.timestamp,
        size: data.data ? data.data.length : 0
    });
});

// Handle graceful shutdown
process.on('SIGINT', () => {
    console.log('');
    console.log('Disconnecting...');
    pusher.disconnect();
    process.exit(0);
});

// Timeout after 30 seconds if no connection
setTimeout(() => {
    if (pusher.connection.state !== 'connected') {
        console.error('');
        console.error('✗ Connection timeout after 30 seconds');
        console.error(`  Current state: ${pusher.connection.state}`);
        process.exit(1);
    }
}, 30000);
