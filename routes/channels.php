<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversations.{id}', function ($user, $id) {
    return true; // Authorize all logged in dashboard agents
});

Broadcast::channel('tenant.{id}', function ($user, $id) {
    // Only allow users belonging to this tenant, or admins actively impersonating this tenant
    if (session()->has('impersonating_tenant_id') && session('impersonating_tenant_id') == $id) {
        return true;
    }
    
    if (!$user || !isset($user->tenant_id)) {
        return false;
    }
    
    return (int) $user->tenant_id === (int) $id;
});
