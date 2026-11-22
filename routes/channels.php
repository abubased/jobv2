<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('applications', function ($user) {
    return $user !== null;
});
Broadcast::channel('lalcoin-applications', function ($user) {
    return $user !== null;
});
Broadcast::channel('all-applications', function ($user) {
    return method_exists($user, 'isAdmin') && $user->isAdmin();
});
