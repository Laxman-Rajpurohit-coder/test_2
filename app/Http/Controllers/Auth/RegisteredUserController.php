<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TenantInvite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request, $token)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $invite = TenantInvite::where('token', $token)->firstOrFail();

        if ($invite->accepted_at) {
            abort(403, 'This invitation has already been used.');
        }

        if ($invite->expires_at->isPast()) {
            abort(403, 'This invitation has expired.');
        }

        return Inertia::render('Auth/Register', [
            'email' => $invite->email,
            'token' => $token,
            'tenant_name' => $invite->tenant->name,
        ]);
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request)
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'token' => 'required|string|exists:tenant_invites,token',
        ]);

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            // Use lockForUpdate to prevent race conditions where two simultaneous requests use the same valid invite
            $invite = TenantInvite::where('token', $request->token)->lockForUpdate()->firstOrFail();

            if ($invite->accepted_at) {
                abort(403, 'This invitation has already been used.');
            }

            if ($invite->expires_at->isPast()) {
                abort(403, 'This invitation has expired.');
            }

            $existingUser = User::where('email', $invite->email)->first();

            if ($existingUser) {
                $existingUser->update([
                    'name' => $request->name ?: $existingUser->name,
                    'password' => Hash::make($request->password),
                    'tenant_id' => $invite->tenant_id,
                ]);
                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $invite->email,
                    'password' => Hash::make($request->password),
                    'tenant_id' => $invite->tenant_id,
                ]);
            }

            $invite->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
