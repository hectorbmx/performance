<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use App\Models\UserApp;
use App\Services\ClientMembershipAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientMembershipAccessServiceTest extends TestCase
{
    private int $coachId;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.membership_checkpoint_memory' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);

        DB::setDefaultConnection('membership_checkpoint_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_050926_create_coach_client_plans_table.php',
            '2026_05_31_000001_add_payment_provider_to_coach_client_plans_table.php',
            '2026_01_05_051109_create_client_memberships_table.php',
            '2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Carbon::setTestNow('2026-09-16 10:00:00');
        $this->coachId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('membership_checkpoint_memory');
        parent::tearDown();
    }

    public function test_paid_active_membership_can_access_service(): void
    {
        $athlete = $this->athlete();
        $this->membership($athlete, [
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'status' => 'active',
            'billing_status' => 'paid',
        ]);

        $result = app(ClientMembershipAccessService::class)->forUserApp($athlete);

        $this->assertTrue($result->canAccessService);
        $this->assertSame(ClientMembershipAccessService::STATE_ACTIVE, $result->state);
    }

    public function test_expired_membership_cannot_access_service(): void
    {
        $athlete = $this->athlete();
        $this->membership($athlete, [
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'status' => 'expired',
            'billing_status' => 'paid',
        ]);

        $result = app(ClientMembershipAccessService::class)->forUserApp($athlete);

        $this->assertFalse($result->canAccessService);
        $this->assertSame(ClientMembershipAccessService::STATE_EXPIRED, $result->state);
    }

    public function test_unpaid_membership_inside_grace_can_access_service(): void
    {
        $athlete = $this->athlete();
        $this->membership($athlete, [
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-09-15',
            'grace_until' => '2026-09-20',
            'status' => 'active',
            'billing_status' => 'unpaid',
        ]);

        $result = app(ClientMembershipAccessService::class)->forUserApp($athlete);

        $this->assertTrue($result->canAccessService);
        $this->assertSame(ClientMembershipAccessService::STATE_GRACE, $result->state);
    }

    public function test_pending_payment_membership_cannot_access_service(): void
    {
        $athlete = $this->athlete();
        $this->membership($athlete, [
            'starts_at' => '2026-09-16',
            'ends_at' => '2026-10-16',
            'status' => ClientMembership::STATUS_PENDING_PAYMENT,
            'billing_status' => 'unpaid',
        ]);

        $result = app(ClientMembershipAccessService::class)->forUserApp($athlete);

        $this->assertFalse($result->canAccessService);
        $this->assertSame(ClientMembershipAccessService::STATE_PENDING_PAYMENT, $result->state);
        $this->assertStringContainsString('pendiente de pago', $result->message);
    }

    public function test_mobile_future_checkout_starts_as_pending_payment(): void
    {
        $athlete = $this->athlete();
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

        Sanctum::actingAs($athlete);

        $this->postJson('/api/v1/app/memberships/future', [
            'coach_client_plan_id' => $planId,
            'checkout' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('membership.status', ClientMembership::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('membership.billing_status', 'unpaid');
    }

    public function test_mobile_future_checkout_reuses_pending_membership(): void
    {
        $athlete = $this->athlete();
        $planId = DB::table('coach_client_plans')->insertGetId([
            'coach_id' => $this->coachId,
            'name' => 'Mensual',
            'description' => null,
            'price' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'stripe',
            'billing_cycle_days' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($athlete);

        $first = $this->postJson('/api/v1/app/memberships/future', [
            'coach_client_plan_id' => $planId,
            'checkout' => false,
        ])->assertCreated();

        $second = $this->postJson('/api/v1/app/memberships/future', [
            'coach_client_plan_id' => $planId,
            'checkout' => false,
        ])->assertCreated();

        $this->assertSame($first->json('membership.id'), $second->json('membership.id'));
        $this->assertDatabaseCount('client_memberships', 1);
    }

    public function test_mobile_renewal_starts_today_for_expired_athlete(): void
    {
        $athlete = $this->athlete();
        $this->membership($athlete, [
            'starts_at' => '2026-08-01',
            'ends_at' => '2026-08-31',
            'status' => 'expired',
            'billing_status' => 'paid',
        ]);
        $planId = DB::table('coach_client_plans')->insertGetId([
            'coach_id' => $this->coachId,
            'name' => 'Renovacion mensual',
            'description' => null,
            'price' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'stripe',
            'billing_cycle_days' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($athlete);

        $this->postJson('/api/v1/app/memberships/renewal-checkout', [
            'coach_client_plan_id' => $planId,
            'checkout' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('membership.status', ClientMembership::STATUS_PENDING_PAYMENT)
            ->assertJsonPath('membership.starts_at', '2026-09-16');
    }

    public function test_mobile_renewal_reuses_pending_membership(): void
    {
        $athlete = $this->athlete();
        $pending = $this->membership($athlete, [
            'starts_at' => '2026-09-16',
            'ends_at' => '2026-10-16',
            'status' => ClientMembership::STATUS_PENDING_PAYMENT,
            'billing_status' => 'unpaid',
        ]);
        DB::table('coach_client_plans')->where('id', $pending->coach_client_plan_id)->update([
            'payment_provider' => 'stripe',
        ]);

        Sanctum::actingAs($athlete);

        $this->postJson('/api/v1/app/memberships/renewal-checkout', [
            'coach_client_plan_id' => $pending->coach_client_plan_id,
            'checkout' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('membership.id', $pending->id);

        $this->assertDatabaseCount('client_memberships', 1);
    }

    private function athlete(): UserApp
    {
        $client = Client::query()->create([
            'coach_id' => $this->coachId,
            'first_name' => 'Atleta',
            'last_name' => 'Checkpoint',
            'email' => 'checkpoint-'.uniqid().'@example.test',
            'is_active' => true,
        ]);

        return UserApp::query()->create([
            'client_id' => $client->id,
            'email' => 'app-checkpoint-'.uniqid().'@example.test',
            'password' => bcrypt('password'),
            'is_active' => true,
            'activated_at' => now(),
        ]);
    }

    private function membership(UserApp $athlete, array $overrides = []): ClientMembership
    {
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

        return ClientMembership::query()->create(array_merge([
            'coach_id' => $this->coachId,
            'client_id' => $athlete->client_id,
            'coach_client_plan_id' => $planId,
            'plan_name_snapshot' => 'Mensual',
            'price_snapshot' => 500,
            'billing_cycle_days_snapshot' => 30,
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'next_renewal_at' => null,
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'grace_until' => null,
            'paid_at' => '2026-09-01',
        ], $overrides));
    }
}
