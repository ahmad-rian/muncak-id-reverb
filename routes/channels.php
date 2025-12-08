<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel untuk viewers - PUBLIC, siapa saja bisa join tanpa auth
// CATATAN: Karena viewer adalah guest (tidak login), kita tidak bisa gunakan presence channel standar
// yang memerlukan authentication. Kita gunakan public channel saja.
// Viewer count akan dihandle oleh backend via event ViewerCountUpdated.
// Stream channels - Public channels (no authentication required)
// ✅ OPTIMIZATION: For public channels, return true directly to avoid DB queries
// This prevents unnecessary database load during channel subscription
Broadcast::channel('stream.{id}', function ($user, $id) {
    // Public channel - all viewers can join without DB lookup
    // Stream existence is already validated when viewer accesses /live-cam/{id}
    return true;
});
