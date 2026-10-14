<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_mfa_token_cannot_reach_protected_api_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('t', ['mfa_pending'])->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/auth/me', $headers)->assertForbidden();
        $this->getJson('/api/user', $headers)->assertForbidden();
        $this->getJson('/api/v1/attendance/today', $headers)->assertForbidden();
        $this->postJson('/api/v1/qr/generate', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/qr/validate', [], $headers)->assertForbidden();
    }

    public function test_admin_login_issues_a_mfa_pending_token(): void
    {
        User::factory()->admin()->create(['email' => 'a@example.test']);

        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('requires_mfa', true);

        $this->assertSame(['mfa_pending'], \Laravel\Sanctum\PersonalAccessToken::first()->abilities);
    }

    public function test_employee_token_has_full_access(): void
    {
        User::factory()->create(['email' => 'e@example.test']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'e@example.test', 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();
    }

    public function test_login_is_throttled(): void
    {
        // throttle:5,1 permits five attempts, so all five return 422 and the
        // sixth is the first refused with 429.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'bad'])
                ->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'x@example.test', 'password' => 'bad'])
            ->assertStatus(429);
    }
}
