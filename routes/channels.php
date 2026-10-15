<?php

use App\Models\Hazard;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('hazard.{hazardId}', function ($user, $hazardId) {
    $hazard = Hazard::find($hazardId);
    if (!$hazard) {
        return false;
    }
    return $user->id === $hazard->user_id || $user->isAdmin();
});
