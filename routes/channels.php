<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Presence channel untuk viewers - PUBLIC, siapa saja bisa join tanpa auth
// CATATAN: Karena viewer adalah guest (tidak login), kita tidak bisa gunakan presence channel standar
// yang memerlukan authentication. Kita gunakan public channel saja.
// Viewer count akan dihandle oleh backend via event ViewerCountUpdated.
Broadcast::channel('stream.{id}', function () {
    // Public channel - tidak memerlukan authentication
    // Return true untuk allow semua orang
    return true;
});
