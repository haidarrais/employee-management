<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Check if MFA is required
        $requiresMfa = $user->isAdmin() || $user->isManagement();

        if ($requiresMfa && $user->mfa_enabled) {
            // Store user ID in session for MFA verification
            session(['pending_user_id' => $user->id]);
            
            // Log login attempt
            app(AuditService::class)->logLogin($user->id, true, ['mfa_pending' => true]);
            
            return redirect()->route('mfa.verify.form');
        }

        // Log user in with session (no MFA required or not set up)
        Auth::login($user);
        $request->session()->regenerate();
        
        // Create API token for future API calls
        $token = $user->createToken('auth-token')->plainTextToken;
        session(['api_token' => $token]);
        
        // Log login
        app(AuditService::class)->logLogin($user->id, true, ['mfa_pending' => false]);

        return redirect()->route('dashboard');
    }

    public function showMfaForm()
    {
        if (!session()->has('pending_user_id')) {
            return redirect()->route('login');
        }
        
        return view('auth.mfa');
    }

    public function verifyMfa(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if (!session()->has('pending_user_id')) {
            return redirect()->route('login');
        }

        $user = User::findOrFail(session('pending_user_id'));

        // Verify MFA code
        $mfaService = app(\App\Services\MFAService::class);
        
        if (!$user->mfa_secret || !$mfaService->verify($user->mfa_secret, $request->code)) {
            // Log failed MFA
            app(AuditService::class)->log(
                'mfa_verification_failed',
                $user->id,
                ['ip_address' => $request->ip()]
            );
            
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        // MFA verified - complete login
        Auth::login($user);
        $request->session()->regenerate();
        
        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;
        session(['api_token' => $token]);
        
        // Clear pending user
        session()->forget('pending_user_id');
        
        // Log successful MFA
        app(AuditService::class)->log(
            'mfa_verification_success',
            $user->id,
            ['ip_address' => $request->ip()]
        );

        return redirect()->route('dashboard');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();
        
        if ($user) {
            app(AuditService::class)->logLogout($user->id);
        }
        
        // Clear session
        session()->forget('api_token');
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('login');
    }

    // ─────────────────────────────────────────────────────────────
    // Password confirmation (sudo mode)
    // ─────────────────────────────────────────────────────────────

    /**
     * Show the password confirmation form.
     * Laravel's RequirePassword middleware redirects here automatically.
     */
    public function showConfirmPassword()
    {
        return view('auth.confirm-password');
    }

    /**
     * Verify the password and stamp the session so the middleware
     * considers the user confirmed for the next 3 hours.
     */
    public function confirmPassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($request->password, $request->user()->password_hash)) {
            return back()->withErrors([
                'password' => 'The password you entered is incorrect.',
            ]);
        }

        // Stamp the session — Laravel's RequirePassword middleware reads this
        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard'));
    }
}