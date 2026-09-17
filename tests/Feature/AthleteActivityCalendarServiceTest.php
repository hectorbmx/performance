<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Services\AthleteActivityCalendarService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AthleteActivityCalendarServiceTest extends TestCase
{
    private int $clientId;
    private int $coachId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.activity_calendar_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);

        DB::setDefaultConnection('activity_calendar_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_06_042508_create_training_sessions_table.php',
            '2026_01_06_044345_create_training_assignments_table.php',
            '2026_01_15_025204_add_scheduled_for_to_training_assignments_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Carbon::setTestNow('2026-09-16 10:00:00');

        $coach = User::factory()->create();
        $client = Client::query()->create([
            'coach_id' => $coach->id,
            'first_name' => 'Atleta',
            'last_name' => 'Calendario',
            'email' => 'athlete-calendar@example.test',
        ]);

        $this->coachId = $coach->id;
        $this->clientId = $client->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('activity_calendar_memory');
        parent::tearDown();
    }

    public function test_month_builds_activity_statuses_and_summary_from_assignments(): void
    {
        $this->assignment('assigned', 'completed', '2026-09-10');
        $this->assignment('assigned', 'scheduled', '2026-09-11');
        $this->assignment('free', 'in_progress', '2026-09-12');
        $this->assignment('assigned', 'scheduled', '2026-09-13');
        $this->assignment('free', 'completed', '2026-09-13');
        $this->assignment('assigned', 'scheduled', '2026-09-16');

        $calendar = app(AthleteActivityCalendarService::class)->month($this->clientId, '2026-09');
        $days = collect($calendar['days'])->keyBy('date');

        $this->assertSame('2026-09', $calendar['month']);
        $this->assertSame(['from' => '2026-09-01', 'to' => '2026-09-30'], $calendar['range']);

        $this->assertSame('completed', $days['2026-09-10']['status']);
        $this->assertSame('missed', $days['2026-09-11']['status']);
        $this->assertSame('free_completed', $days['2026-09-12']['status']);
        $this->assertSame('completed', $days['2026-09-13']['status']);
        $this->assertSame('rest', $days['2026-09-14']['status']);
        $this->assertSame('scheduled', $days['2026-09-16']['status']);

        $this->assertSame([
            'completed_days' => 2,
            'missed_days' => 1,
            'scheduled_days' => 1,
            'free_completed_days' => 1,
            'rest_days' => 25,
            'training_days' => 4,
            'activity_days' => 3,
        ], $calendar['summary']);
    }

    private function assignment(string $visibility, string $status, string $scheduledFor): void
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
            'client_id' => $this->clientId,
            'scheduled_for' => $scheduledFor,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
