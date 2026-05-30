<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function index()
    {
        $user = Auth::user();
        
        return view('profile.index', compact('user'));
    }

    /**
     * Update the user's profile.
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($validated);

        return redirect()->route('profile')->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        return redirect()->route('profile')->with('success', 'Password updated successfully.');
    }

    /**
     * Enable or disable MFA for the user.
     */
    public function updateMfa(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'mfa_enabled' => ['required', 'boolean'],
        ]);

        // If enabling MFA, require TOTP secret setup (handled by MFA service)
        if ($validated['mfa_enabled'] && !$user->mfa_enabled) {
            // MFA will be enabled after user completes TOTP setup
            return redirect()->route('mfa.setup')->with('pending_mfa_setup', true);
        }

        $user->update(['mfa_enabled' => $validated['mfa_enabled']]);

        $status = $validated['mfa_enabled'] ? 'enabled' : 'disabled';
        return redirect()->route('profile')->with('success', "MFA {$status} successfully.");
    }
}