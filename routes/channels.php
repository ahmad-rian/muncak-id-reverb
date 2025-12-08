<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel untuk viewers - PUBLIC, siapa saja bisa join tanpa auth
// CATATAN: Karena viewer adalah guest (tidak login), kita tidak bisa gunakan presence channel standar
// yang memerlukan authentication. Kita gunakan public channel saja.
// Viewer count akan dihandle oleh backend via event ViewerCountUpdated.
Broadcast::channel('stream.{id}', function ($user, $id) {
    // ✅ OPTIMIZATION: Cache stream lookup for 5 minutes to reduce database queries
    // Public channel - no authentication required
    $stream = \Illuminate\Support\Facades\Cache::remember("stream_auth_{$id}", 300, function() use ($id) {
        return \App\Models\Stream::find($id);
    });

    return $stream !== null;
});
