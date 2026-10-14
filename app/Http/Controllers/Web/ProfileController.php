<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\MFAService;
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

        // Only columns that actually exist on `users` are validated and saved.
        // `phone` was previously accepted here but has no column and is not
        // fillable, so the write was silently discarded while the UI still
        // reported success.
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
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

        // The hash lives in `password_hash`, not `password`. Writing
        // `password` is a no-op because the column does not exist and the
        // attribute is not fillable — the old password stayed valid while the
        // UI reported success. Assign through the model's password mutator,
        // which hashes into the correct column.
        $user->password = $validated['password'];
        $user->save();

        app(AuditService::class)->log('password_changed', $user->id, [
            'self_service' => true,
        ]);

        return redirect()->route('profile')->with('success', 'Password updated successfully.');
    }

    /**
     * Send the user to the MFA setup flow, or turn MFA off.
     *
     * MFA can only be switched ON through the setup flow, which proves the
     * user has a working authenticator before the flag is set. Previously this
     * method set `mfa_enabled` directly and then redirected to a route that
     * does not exist, so it either 500'd or — if the route had existed — would
     * have locked the account out with no secret enrolled.
     */
    public function updateMfa(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'mfa_enabled' => ['required', 'boolean'],
        ]);

        if ($validated['mfa_enabled']) {
            return redirect()->route('mfa.setup');
        }

        // Disabling always works, and clears the enrolled secret so a later
        // re-enrolment starts from a fresh secret.
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret'  => null,
        ]);

        app(AuditService::class)->log('mfa_disabled', $user->id);

        return redirect()->route('profile')->with('success', 'Two-factor authentication disabled.');
    }

    /**
     * Show the MFA enrolment page.
     *
     * Generates a fresh secret each time it is opened. The secret is held in
     * the session (not persisted) until the user proves they can generate a
     * valid code, so an abandoned setup never leaves a half-enrolled account.
     */
    public function showMfaSetup(MFAService $mfa)
    {
        $user = Auth::user();

        $secret = session('mfa_setup_secret') ?: $mfa->generateSecret();
        session(['mfa_setup_secret' => $secret]);

        return view('profile.mfa-setup', [
            'user'       => $user,
            'secret'     => $secret,
            'qrDataUri'  => $mfa->generateQRCodeDataUri($secret, $user->email),
        ]);
    }

    /**
     * Confirm the code from the authenticator app and switch MFA on.
     *
     * `mfa_enabled` is only set after the submitted code validates against the
     * pending secret, which is what makes the lockout impossible.
     */
    public function confirmMfaSetup(Request $request, MFAService $mfa)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $secret = session('mfa_setup_secret');

        if (!$secret) {
            return redirect()->route('mfa.setup')
                ->withErrors(['code' => 'Your setup session expired. Please start again.']);
        }

        if (!$mfa->verify($secret, $validated['code'])) {
            app(AuditService::class)->log('mfa_setup_failed', $user->id);

            return back()->withErrors([
                'code' => 'That code is not valid. Check your authenticator app and try again.',
            ]);
        }

        $user->update([
            'mfa_secret'  => $secret,
            'mfa_enabled' => true,
        ]);

        session()->forget('mfa_setup_secret');

        app(AuditService::class)->log('mfa_enabled', $user->id);

        return redirect()->route('profile')->with('success', 'Two-factor authentication enabled.');
    }
}