<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // A token without the required ability (e.g. MFA still pending) is a 403, not a 500.
        $this->renderable(function (MissingAbilityException $e, $request) {
            return response()->json([
                'error' => [
                    'code'    => 'INSUFFICIENT_TOKEN_ABILITY',
                    'message' => 'This token is not allowed to access this resource. Complete MFA first.',
                ],
            ], 403);
        });
    }
}
