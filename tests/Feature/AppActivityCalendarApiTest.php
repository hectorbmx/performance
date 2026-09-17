<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\UserApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppActivityCalendarApiTest extends TestCase
{
    private int $coachId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.activity_calendar_api_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);

        DB::setDefaultConnection('activity_calendar_api_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_050926_create_coach_client_plans_table.php',
            '2026_01_05_051109_create_client_memberships_table.php',
            '2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_044345_create_training_assignments_table.php',
            '2026_01_15_025204_add_scheduled_for_to_training_assignments_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Carbon::setTestNow('2026-09-16 10:00:00');
        $this->coachId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('activity_calendar_api_memory');
        parent::tearDown();
    }

    public function test_active_athlete_can_read_month_activity_calendar(): void
    {
        $athlete = $this->athlete('active');
        $this->assignment($athlete->client_id, 'assigned', 'completed', '2026-09-10');
        $this->assignment($athlete->client_id, 'assigned', 'scheduled', '2026-09-11');
        $this->assignment($athlete->client_id, 'free', 'in_progress', '2026-09-12');
        $this->assignment($athlete->client_id, 'assigned', 'scheduled', '2026-09-16');

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/activity-calendar?month=2026-09')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('month', '2026-09')
            ->assertJsonPath('range.from', '2026-09-01')
            ->assertJsonPath('range.to', '2026-09-30')
            ->assertJsonPath('summary.completed_days', 1)
            ->assertJsonPath('summary.missed_days', 1)
            ->assertJsonPath('summary.scheduled_days', 1)
            ->assertJsonPath('summary.free_completed_days', 1)
            ->assertJsonPath('summary.rest_days', 26)
            ->assertJsonPath('days.9.status', 'completed')
            ->assertJsonPath('days.10.status', 'missed')
            ->assertJsonPath('days.11.status', 'free_completed')
            ->assertJsonPath('days.15.status', 'scheduled');
    }

    public function test_invalid_month_is_rejected(): void
    {
        Sanctum::actingAs($this->athlete('active'));

        $this->getJson('/api/v1/app/activity-calendar?month=2026-99')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('month');
    }

    public function test_expired_athlete_is_blocked_by_membership_middleware(): void
    {
        Sanctum::actingAs($this->athlete('expired'));

        $this->getJson('/api/v1/app/activity-calendar?month=2026-09')
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_expired')
            ->assertJsonPath('access_state', 'expired');
    }

    private function athlete(string $membershipState): UserApp
    {
        $client = Client::query()->create([
            'coach_id' => $this->coachId,
            'first_name' => 'Atleta',
            'last_name' => $membershipState,
            'email' => 'activity-'.$membershipState.'-'.uniqid().'@example.test',
        ]);

        $athlete = UserApp::query()->create([
            'client_id' => $client->id,
            'email' => 'app-'.$membershipState.'-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $planId = DB::table('coach_client_plans')->insertGetId([
            'coach_id' => $this->coachId,
            'name' => 'Mensual',
            'description' => null,
            'price' => 500,
            'billing_cycle_days' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $expired = $membershipState === 'expired';

        DB::table('client_memberships')->insert([
            'coach_id' => $this->coachId,
            'client_id' => $client->id,
            'coach_client_plan_id' => $planId,
            'plan_name_snapshot' => 'Mensual',
            'price_snapshot' => 500,
            'billing_cycle_days_snapshot' => 30,
            'starts_at' => $expired ? '2026-08-01' : '2026-09-01',
            'ends_at' => $expired ? '2026-08-31' : '2026-09-30',
            'next_renewal_at' => null,
            'reminder_days_before' => 5,
            'status' => $expired ? 'expired' : 'active',
            'billing_status' => 'paid',
            'grace_until' => null,
            'paid_at' => $expired ? '2026-08-01' : '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $athlete;
    }

    private function assignment(int $clientId, string $visibility, string $status, string $scheduledFor): void
    {
        $sessionId = DB::table('training_sessions')->insertGetId([
            'coach_id' => $this->coachId,
            'title' => ucfirst($visibility).' '.$scheduledFor,
            'scheduled_at' => $scheduledFor,
            'duration_minutes' => 45,
            'level' => 'beginner',
            'goal' => 'mixed',
            'type' => 'fitness',
            'visibility' => $visibility,
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('training_assignments')->insert([
            'training_session_id' => $sessionId,
            'client_id' => $clientId,
            'scheduled_for' => $scheduledFor,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
