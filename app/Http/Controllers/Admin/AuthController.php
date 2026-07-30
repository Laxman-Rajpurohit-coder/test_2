<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Displays the admin login page.
     *
     * @return \Inertia\Response The rendered admin login page.
     */
    public function create()
    {
        return Inertia::render('Admin/Auth/Login');
    }

    /**
     * Authenticates an administrator and redirects based on the login result.
     *
     * @return \Illuminate\Http\RedirectResponse A redirect to the intended admin page on success or back to the login form with an error on failure.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            // If they were trying to go somewhere before being redirected to login
            return redirect()->intended(route('admin.tenants.index'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our admin records.',
        ])->onlyInput('email');
    }

    /**
     * Logs out the authenticated administrator and redirects to the application root.
     *
     * @param Request $request The current HTTP request.
     * @return \Illuminate\Http\RedirectResponse The redirect response to the application root.
     */
    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
