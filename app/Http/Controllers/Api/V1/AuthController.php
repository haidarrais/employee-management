<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MFAService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @var MFAService
     */
    private MFAService $mfaService;

    public function __construct(MFAService $mfaService)
    {
        $this->mfaService = $mfaService;
    }
    /**
     * Login user and return token.
     * 
     * POST /api/v1/auth/login
     * Body: { email, password }
     * Response: { token, requires_mfa: bool }
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            // Log failed login attempt
            app(\App\Services\AuditService::class)->log(
                'login_failed',
                null,
                [
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                ]
            );

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        // Check if MFA is required (admin/management roles)
        $requiresMfa = $user->isAdmin() || $user->isManagement();

        // Create token (limited access if MFA required)
        $token = $user->createToken('auth-token', [
            $requiresMfa ? 'mfa_pending' : 'full_access'
        ])->plainTextToken;

        // Log successful login
        app(\App\Services\AuditService::class)->log(
            'login_success',
            $user->id,
            [
                'ip_address' => $request->ip(),
                'requires_mfa' => $requiresMfa,
            ]
        );

        return response()->json([
            'token' => $token,
            'requires_mfa' => $requiresMfa,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    /**
     * Logout user and revoke token.
     * 
     * POST /api/v1/auth/logout
     * Headers: Authorization: Bearer {token}
     * Response: { success }
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log logout event
        app(\App\Services\AuditService::class)->log(
            'logout',
            $user->id,
            [
                'ip_address' => $request->ip(),
            ]
        );

        // Revoke current token (get the token ID from the request)
        try {
            $request->user()->currentAccessToken()->delete();
        } catch (\BadMethodCallException $e) {
            // TransientToken doesn't have delete method - ignore
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Get current authenticated user.
     * 
     * GET /api/v1/auth/me
     * Headers: Authorization: Bearer {token}
     * Response: { user }
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'mfa_enabled' => $user->mfa_enabled,
            ],
        ]);
    }

    /**
     * Extend session timeout.
     * 
     * POST /api/v1/auth/session/extend
     * Headers: Authorization: Bearer {token}
     * Response: { success }
     * 
     * For mobile devices: Resets the 15-minute inactivity timer
     * For desktop: Uses standard session extension
     */
    public function sessionExtend(Request $request): JsonResponse
    {
        $session = $request->session();
        $isMobile = $session->get('is_mobile', false);
        
        if ($isMobile) {
            // Reset mobile session activity timestamp
            $session->put('mobile_last_activity', now()->timestamp);
            
            return response()->json([
                'success' => true,
                'message' => 'Mobile session extended',
                'expires_in_minutes' => 15,
            ]);
        }

        // Desktop session extension using standard Sanctum token expiration
        $token = $request->user()->currentAccessToken();
        
        if ($token) {
            // Extend expiration by 120 minutes (standard session) from now
            $token->expires_at = now()->addMinutes(120);
            $token->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Session extended',
            'expires_in_minutes' => 120,
        ]);
    }

    /**
     * Verify MFA code and grant full access.
     * 
     * POST /api/v1/auth/mfa/verify
     * Headers: Authorization: Bearer {token}
     * Body: { code }
     * Response: { success, redirect_url }
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        // Check if user has MFA enabled
        if (!$user->mfa_enabled || empty($user->mfa_secret)) {
            // Log MFA verification attempt for user without MFA
            app(\App\Services\AuditService::class)->log(
                'mfa_verify_failed',
                $user->id,
                [
                    'reason' => 'mfa_not_enabled',
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'MFA is not enabled for this account',
            ], 400);
        }

        // Verify the TOTP code
        $isValid = $this->mfaService->verify($user->mfa_secret, $request->code);

        if (!$isValid) {
            // Log failed MFA verification
            app(\App\Services\AuditService::class)->log(
                'mfa_verify_failed',
                $user->id,
                [
                    'ip_address' => $request->ip(),
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'Invalid MFA code. Please try again.',
            ], 401);
        }

        // MFA verified successfully - upgrade token to full access
        $token = $user->currentAccessToken();
        
        if ($token) {
            // Remove mfa_pending ability and add full_access
            $token->forceFill([
                'abilities' => ['full_access'],
            ])->save();
        }

        // Log successful MFA verification
        app(\App\Services\AuditService::class)->log(
            'mfa_verify_success',
            $user->id,
            [
                'ip_address' => $request->ip(),
            ]
        );

        // Determine redirect based on role
        $redirectUrl = match ($user->role) {
            User::ROLE_ADMIN => '/admin/dashboard',
            User::ROLE_MANAGEMENT => '/management/dashboard',
            default => '/dashboard',
        };

        return response()->json([
            'success' => true,
            'message' => 'MFA verification successful',
            'redirect_url' => $redirectUrl,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }
}