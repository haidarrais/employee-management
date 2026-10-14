<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\SystemConfig;
use App\Models\User;
use App\Services\GeolocationService;
use App\Services\QRCodeService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The workplace position and the accepted radius are operator settings, not
 * constants. These tests pin the behaviour that matters: the admin page writes
 * both, and the check-in endpoint honours whatever is configured.
 */
class GeofenceSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = -6.2088;
    private const LNG = 106.8456;

    /** Roughly 111 m north of the workplace: 0.001 degrees of latitude. */
    private const LAT_OFFSET_100M = 0.0009;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-14 07:00:00', config('app.timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setWorkplace(float $lat, float $lng, ?float $radius = null): void
    {
        $value = ['latitude' => $lat, 'longitude' => $lng];
        if ($radius !== null) {
            $value['radius_meters'] = $radius;
        }

        SystemConfig::updateOrCreate(
            ['key' => GeolocationService::CONFIG_KEY],
            ['value' => json_encode($value)]
        );
    }

    /**
     * Act as an admin who has already passed the sudo (password-confirm) gate.
     *
     * Every admin route is behind `password.confirm`, which redirects to
     * /password/confirm until the session carries a recent confirmation. Tests
     * set that timestamp directly rather than driving the confirm form.
     */
    private function actingAsConfirmedAdmin(): User
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
             ->withSession(['auth.password_confirmed_at' => time()]);

        return $admin;
    }

    private function scanAs(User $employee, array $overrides = [])
    {
        $qr = app(QRCodeService::class)->generate(User::factory()->admin()->create()->id);
        Sanctum::actingAs($employee, ['full_access']);

        return $this->postJson('/api/v1/qr/validate', array_merge([
            'token'     => $qr->encrypted_token,
            'latitude'  => self::LAT,
            'longitude' => self::LNG,
            'timestamp' => now()->timestamp,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────
    // The configured radius is what the endpoint enforces
    // ─────────────────────────────────────────────────────────────

    public function test_a_wide_configured_radius_admits_a_distance_the_default_would_reject(): void
    {
        $this->setWorkplace(self::LAT, self::LNG, 500);

        $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + self::LAT_OFFSET_100M])
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->assertSame(1, AttendanceRecord::count());
    }

    public function test_a_tight_configured_radius_rejects_a_distance_the_default_would_admit(): void
    {
        // 0.00009 degrees is ~10 m: inside the default, outside a 5 m fence.
        $this->setWorkplace(self::LAT, self::LNG, 5);

        $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + 0.00009])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'GEOFENCE_VIOLATION');

        $this->assertSame(0, AttendanceRecord::count());
    }

    public function test_the_reported_allowed_radius_is_the_configured_one(): void
    {
        $this->setWorkplace(self::LAT, self::LNG, 250);

        $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + self::LAT_OFFSET_100M])
            ->assertOk();

        $response = $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + 0.05])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'GEOFENCE_VIOLATION');

        $this->assertSame(250, (int) $response->json('error.details.allowed_radius'));
    }

    // ─────────────────────────────────────────────────────────────
    // Backward compatibility with rows written before the radius existed
    // ─────────────────────────────────────────────────────────────

    public function test_a_row_without_a_radius_falls_back_to_the_default(): void
    {
        $this->setWorkplace(self::LAT, self::LNG);   // no radius_meters key

        $this->assertSame(
            (float) GeolocationService::DEFAULT_GEOFENCE_RADIUS,
            app(GeolocationService::class)->getGeofenceRadius()
        );
    }

    public function test_a_row_without_coordinates_is_treated_as_not_configured(): void
    {
        SystemConfig::updateOrCreate(
            ['key' => GeolocationService::CONFIG_KEY],
            ['value' => json_encode(['radius_meters' => 50])]
        );

        $this->assertNull(app(GeolocationService::class)->getWorkplaceConfig());

        // Fails closed, and says so, rather than admitting the check-in.
        $this->scanAs(User::factory()->create())
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'GEOFENCE_NOT_CONFIGURED');
    }

    public function test_an_out_of_range_stored_radius_is_clamped(): void
    {
        $this->setWorkplace(self::LAT, self::LNG, 0.5);

        $this->assertSame(
            (float) GeolocationService::MIN_GEOFENCE_RADIUS,
            app(GeolocationService::class)->getGeofenceRadius()
        );
    }

    // ─────────────────────────────────────────────────────────────
    // The admin page is the only writer
    // ─────────────────────────────────────────────────────────────

    public function test_an_admin_can_save_the_position_and_tolerance(): void
    {
        $this->actingAsConfirmedAdmin();

        $this->put(route('admin.location.update'), [
                'latitude'      => '-6.9175',
                'longitude'     => '107.6191',
                'radius_meters' => '75',
            ])
            ->assertRedirect(route('admin.location'));

        $config = app(GeolocationService::class)->getWorkplaceConfig();

        $this->assertEqualsWithDelta(-6.9175, $config['latitude'], 0.0000001);
        $this->assertEqualsWithDelta(107.6191, $config['longitude'], 0.0000001);
        $this->assertEqualsWithDelta(75.0, $config['radius_meters'], 0.0001);
    }

    public function test_the_saved_tolerance_takes_effect_immediately(): void
    {
        // Start strict, then widen from the admin page and re-check the same
        // distance: the endpoint must follow the setting, not a constant.
        $this->setWorkplace(self::LAT, self::LNG, 5);

        $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + self::LAT_OFFSET_100M])
            ->assertStatus(422);

        // Become the admin *after* the scan: scanAs() calls Sanctum::actingAs(),
        // which replaces the acting user, so the admin session has to be
        // established again before the PUT or the role middleware bounces it.
        $this->actingAsConfirmedAdmin();

        $this->put(route('admin.location.update'), [
            'latitude'      => self::LAT,
            'longitude'     => self::LNG,
            'radius_meters' => '300',
        ])->assertRedirect(route('admin.location'));

        // The setting itself must now be the widened one...
        $this->assertEqualsWithDelta(
            300.0,
            app(GeolocationService::class)->getGeofenceRadius(),
            0.0001
        );

        // ...and the endpoint must honour it for the very same distance that
        // was refused a moment ago.
        $this->scanAs(User::factory()->create(), ['latitude' => self::LAT + self::LAT_OFFSET_100M])
            ->assertOk();
    }

    public function test_a_tolerance_below_the_supported_minimum_is_rejected(): void
    {
        $this->actingAsConfirmedAdmin();

        $this->put(route('admin.location.update'), [
                'latitude'      => self::LAT,
                'longitude'     => self::LNG,
                'radius_meters' => '1',
            ])
            ->assertSessionHasErrors('radius_meters');
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->actingAsConfirmedAdmin();

        $this->put(route('admin.location.update'), [
                'latitude' => '91', 'longitude' => self::LNG, 'radius_meters' => '50',
            ])
            ->assertSessionHasErrors('latitude');

        $this->put(route('admin.location.update'), [
                'latitude' => self::LAT, 'longitude' => '181', 'radius_meters' => '50',
            ])
            ->assertSessionHasErrors('longitude');
    }

    public function test_a_non_admin_cannot_read_or_write_the_location(): void
    {
        $employee = User::factory()->create();

        // RoleMiddleware redirects a non-JSON request to the dashboard rather
        // than returning 403, so assert the redirect and that nothing was written.
        $this->actingAs($employee)
            ->get(route('admin.location'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($employee)
            ->put(route('admin.location.update'), [
                'latitude' => self::LAT, 'longitude' => self::LNG, 'radius_meters' => '50',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseCount('system_configs', 0);
    }

    public function test_changing_the_location_is_audited_with_before_and_after(): void
    {
        $this->setWorkplace(self::LAT, self::LNG, 10);

        $this->actingAsConfirmedAdmin();

        $this->put(route('admin.location.update'), [
                'latitude'      => self::LAT,
                'longitude'     => self::LNG,
                'radius_meters' => '400',
            ])->assertRedirect(route('admin.location'));

        $log = \App\Models\AuditLog::where('action', 'workplace_location_updated')->firstOrFail();

        // AuditLog casts `metadata` to array, so it is already decoded here.
        $meta = $log->metadata;

        $this->assertSame(10, (int) $meta['before']['radius_meters']);
        $this->assertSame(400, (int) $meta['after']['radius_meters']);
    }
}
