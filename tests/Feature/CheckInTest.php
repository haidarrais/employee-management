<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\QRCode;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\QRCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -6.2088;
    private const LNG = 106.8456;

    private User $employee;
    private QRCode $qr;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday 07:00 in the business timezone: exactly on time.
        Carbon::setTestNow(Carbon::parse('2026-10-14 07:00:00', config('app.timezone')));

        SystemConfig::create([
            'key'   => 'workplace_coordinates',
            'value' => json_encode(['latitude' => self::LAT, 'longitude' => self::LNG]),
        ]);

        $admin          = User::factory()->admin()->create();
        $this->employee = User::factory()->create();
        $this->qr       = app(QRCodeService::class)->generate($admin->id);

        Sanctum::actingAs($this->employee, ['full_access']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scan(?string $payload = null, array $overrides = [])
    {
        return $this->postJson('/api/v1/qr/validate', array_merge([
            'token'     => $payload ?? $this->qr->encrypted_token,
            'latitude'  => self::LAT,
            'longitude' => self::LNG,
            'timestamp' => now()->timestamp,
        ], $overrides));
    }

    public function test_scanning_the_displayed_qr_records_attendance(): void
    {
        $this->scan()->assertOk()->assertJsonPath('valid', true)->assertJsonPath('data.late_minutes', 0);

        $record = AttendanceRecord::firstOrFail();
        $this->assertSame($this->employee->id, $record->user_id);
        $this->assertSame('confirmed', $record->status);
        $this->assertNotNull($record->check_in_at);
        $this->assertSame(QRCode::STATUS_USED, $this->qr->fresh()->status);
    }

    public function test_late_minutes_are_recorded_after_the_grace_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 08:00:00', config('app.timezone')));

        $qr = app(QRCodeService::class)->generate(User::factory()->admin()->create()->id);
        $this->scan($qr->encrypted_token)->assertOk();

        // Start 07:00 + 15 min grace; 08:00 is 45 min past the deadline.
        $this->assertSame(45, AttendanceRecord::firstOrFail()->late_minutes);
    }

    public function test_a_payload_that_is_not_our_encrypted_token_is_rejected(): void
    {
        $this->scan('not-a-real-token')->assertStatus(422)->assertJsonPath('error.code', 'INVALID_TOKEN');
        $this->scan($this->qr->token_hash)->assertStatus(422)->assertJsonPath('error.code', 'INVALID_TOKEN');

        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(QRCode::STATUS_PENDING, $this->qr->fresh()->status);
    }

    public function test_outside_the_geofence_is_rejected_and_does_not_burn_the_code(): void
    {
        $this->scan(null, ['latitude' => self::LAT + 0.01])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'GEOFENCE_VIOLATION');

        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(QRCode::STATUS_PENDING, $this->qr->fresh()->status);
    }

    public function test_geofence_fails_closed_when_the_workplace_is_not_configured(): void
    {
        SystemConfig::where('key', 'workplace_coordinates')->delete();

        $this->scan()->assertStatus(503)->assertJsonPath('error.code', 'GEOFENCE_NOT_CONFIGURED');
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_a_code_can_only_be_used_once(): void
    {
        $this->scan()->assertOk();

        Sanctum::actingAs(User::factory()->create(), ['full_access']);
        $this->scan()->assertStatus(422)->assertJsonPath('error.code', 'INVALID_TOKEN');

        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_claim_is_atomic(): void
    {
        $service = app(QRCodeService::class);

        $this->assertTrue($service->claim($this->qr));
        $this->assertFalse($service->claim(QRCode::find($this->qr->id)));
    }

    public function test_an_expired_code_cannot_be_claimed(): void
    {
        $this->qr->update(['expires_at' => now()->subSecond()]);

        $this->assertFalse(app(QRCodeService::class)->claim($this->qr));
        $this->scan()->assertStatus(422);
        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_a_second_check_in_on_the_same_day_is_refused_without_burning_the_code(): void
    {
        $this->scan()->assertOk();

        $second = app(QRCodeService::class)->generate(User::factory()->admin()->create()->id);
        $this->scan($second->encrypted_token)->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_CHECKED_IN');

        $this->assertSame(1, AttendanceRecord::count());
        $this->assertSame(QRCode::STATUS_PENDING, $second->fresh()->status);
    }

    public function test_a_stale_client_timestamp_is_rejected(): void
    {
        $this->scan(null, ['timestamp' => now()->subHour()->timestamp])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'STALE_TIMESTAMP');
    }
}
