<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    /**
     * Display a listing of the team members.
     */
    public function index(Request $request)
    {
        $tenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();

        $teamMembers = User::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'created_at']);

        $primaryOwner = User::where('tenant_id', $tenantId)->orderBy('id', 'asc')->first();

        return Inertia::render('Team/Index', [
            'teamMembers' => $teamMembers,
            'primaryOwnerId' => $primaryOwner ? $primaryOwner->id : null,
        ]);
    }

    /**
     * Store a newly created team member.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', 'string', 'in:owner,admin,member'],
        ]);

        $plainTextPassword = Str::random(16);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($plainTextPassword),
            'role' => $validated['role'],
            'tenant_id' => app(\App\Services\TenantResolverService::class)->getActiveTenantId(),
        ]);

        return back()->with('success', 'Team member added successfully.')->with('generated_password', $plainTextPassword);
    }

    /**
     * Update the specified team member's role.
     */
    public function updateRole(Request $request, User $user)
    {
        // 1. Cross-tenant IDOR check
        $activeTenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        if ($user->tenant_id !== $activeTenantId) {
            abort(404);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:owner,admin,member'],
        ]);

        // 2. Ensure at least one owner remains if demoting an owner
        if ($user->isOwner() && $validated['role'] !== 'owner') {
            $ownerCount = User::where('tenant_id', $user->tenant_id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'You cannot change the role of the last remaining owner.',
                ]);
            }
        }

        $user->update([
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified team member from the system entirely.
     */
    public function removeUser(Request $request, User $user)
    {
        // 1. Cross-tenant IDOR check
        $activeTenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        if ($user->tenant_id !== $activeTenantId) {
            abort(404);
        }

        // 2. Cannot remove yourself (safety)
        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot remove your own account.',
            ]);
        }

        // 3. Ensure at least one owner remains if deleting an owner
        if ($user->isOwner()) {
            $ownerCount = User::where('tenant_id', $user->tenant_id)
                ->where('role', 'owner')
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot remove the last remaining owner.',
                ]);
            }
        }

        $user->delete();

        return back()->with('success', 'Team member removed successfully.');
    }

    /**
     * Reset the specified team member's password and show it to the owner.
     */
    public function resetPassword(Request $request, User $user)
    {
        // 1. Cross-tenant IDOR check
        $activeTenantId = app(\App\Services\TenantResolverService::class)->getActiveTenantId();
        if ($user->tenant_id !== $activeTenantId) {
            abort(404);
        }

        // 2. Prevent resetting the primary owner's password
        $primaryOwner = \App\Models\User::where('tenant_id', $user->tenant_id)->orderBy('id', 'asc')->first();
        if ($user->id === $primaryOwner->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user' => 'You cannot reset the password of the primary workspace owner.',
            ]);
        }

        $newPassword = \Illuminate\Support\Str::random(16);

        $user->update([
            'password' => \Illuminate\Support\Facades\Hash::make($newPassword),
        ]);

        return back()->with('success', 'Password reset successfully.')->with('generated_password', $newPassword);
    }
}
