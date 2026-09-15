<?php

namespace Tests\Feature;

use App\Enums\TipCategory;
use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Enums\TipType;
use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\CoachClientPlan;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppTipsApiTest extends TestCase
{
    private User $coach;
    private User $otherCoach;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tips_api_memory' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);

        DB::setDefaultConnection('tips_api_memory');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_050926_create_coach_client_plans_table.php',
            '2026_05_28_000002_add_stripe_fields_to_coach_client_plans.php',
            '2026_05_28_000005_add_notification_defaults_to_coach_client_plans.php',
            '2026_05_31_000001_add_payment_provider_to_coach_client_plans_table.php',
            '2026_01_05_051109_create_client_memberships_table.php',
            '2026_05_28_000003_add_stripe_fields_to_client_memberships.php',
            '2026_01_05_230635_create_users_app.php',
            '2026_01_06_022715_rename_users_app_to_user_apps_table.php',
            '2026_02_17_052909_add_stripe_customer_id_to_user_apps_table.php',
            '2026_05_28_000004_add_stripe_customer_account_to_user_apps.php',
            '2026_09_11_000001_create_tips_table.php',
            '2026_09_15_000001_add_expires_at_to_tips_table.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Storage::fake('local');
        Carbon::setTestNow('2026-09-13 12:00:00');

        $this->coach = User::factory()->create();
        $this->otherCoach = User::factory()->create();
        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('tips_api_memory');
        parent::tearDown();
    }

    public function test_active_athlete_lists_global_and_own_coach_tips_only(): void
    {
        $athlete = $this->athleteFor($this->coach, 'active');
        $global = $this->publishedTip('Global visible', TipScope::GLOBAL);
        $tenant = $this->publishedTip('Tenant visible', TipScope::TENANT, $this->coach);
        $foreign = $this->publishedTip('Tenant ajeno', TipScope::TENANT, $this->otherCoach);
        $draft = $this->draftTip('Borrador oculto', $this->coach);

        Sanctum::actingAs($athlete);

        $response = $this->getJson('/api/v1/app/tips?per_page=10');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('ok', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonFragment(['title' => 'Global visible'])
            ->assertJsonFragment(['title' => 'Tenant visible'])
            ->assertJsonMissing(['title' => 'Tenant ajeno'])
            ->assertJsonMissing(['title' => 'Borrador oculto']);

        $this->getJson('/api/v1/app/tips/'.$global->id)->assertOk()->assertJsonPath('data.content_format', 'plain_text');
        $this->getJson('/api/v1/app/tips/'.$tenant->id)->assertOk();
        $this->getJson('/api/v1/app/tips/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/app/tips/'.$draft->id)->assertNotFound();
    }

    public function test_expired_athlete_sees_only_global_tips_and_trainings_remain_blocked(): void
    {
        $athlete = $this->athleteFor($this->coach, 'expired');
        $this->publishedTip('Global para vencidos', TipScope::GLOBAL);
        $this->publishedTip('Tenant bloqueado', TipScope::TENANT, $this->coach);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/tips')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['title' => 'Global para vencidos'])
            ->assertJsonMissing(['title' => 'Tenant bloqueado']);

        $this->getJson('/api/v1/app/trainings')
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_expired')
            ->assertJsonPath('access_state', 'expired');
    }

    public function test_grace_athlete_can_see_tenant_tips(): void
    {
        $athlete = $this->athleteFor($this->coach, 'grace');
        $tenant = $this->publishedTip('Tenant en gracia', TipScope::TENANT, $this->coach);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/tips/'.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.title', 'Tenant en gracia');
    }

    public function test_inactive_athlete_and_non_app_user_are_rejected(): void
    {
        $inactive = $this->athleteFor($this->coach, 'active', ['is_active' => false]);

        Sanctum::actingAs($inactive);
        $this->getJson('/api/v1/app/tips')
            ->assertForbidden()
            ->assertJsonPath('code', 'membership_expired')
            ->assertJsonPath('access_state', 'inactive_user');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/app/tips')->assertForbidden();
    }

    public function test_filters_categories_dates_metadata_and_image_contract(): void
    {
        $athlete = $this->athleteFor($this->coach, 'active');
        $imagePath = 'tips/api/visible.png';
        Storage::disk('local')->put($imagePath, 'image-bytes');
        $tip = $this->publishedTip('Recuperacion al 100%', TipScope::TENANT, $this->coach, [
            'body' => "Linea uno\n\nLinea dos para excerpt",
            'type' => TipType::NEWS,
            'category' => TipCategory::RECOVERY,
            'image_disk' => 'local',
            'image_path' => $imagePath,
            'published_at' => Carbon::parse('2026-09-12 10:00:00'),
        ]);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/tips/categories')
            ->assertOk()
            ->assertJsonFragment(['key' => 'recovery', 'label' => 'Recuperación']);

        $this->getJson('/api/v1/app/tips?q=100%25&category=recovery&type=news&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('data.0.id', $tip->id)
            ->assertJsonPath('data.0.category.key', 'recovery')
            ->assertJsonPath('data.0.image_url', route('app.tips.image', $tip->id))
            ->assertJsonPath('data.0.published_at', '2026-09-12T10:00:00.000000Z')
            ->assertJsonMissingPath('data.0.author_id')
            ->assertJsonMissingPath('data.0.coach_id');

        $this->get('/api/v1/app/tips/'.$tip->id.'/image')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->getJson('/api/v1/app/tips?category=bad')->assertUnprocessable();
    }

    public function test_change_of_coach_affects_next_visibility_query(): void
    {
        $athlete = $this->athleteFor($this->coach, 'active');
        $own = $this->publishedTip('Coach original', TipScope::TENANT, $this->coach);
        $newCoachTip = $this->publishedTip('Coach nuevo', TipScope::TENANT, $this->otherCoach);

        Sanctum::actingAs($athlete);
        $this->getJson('/api/v1/app/tips/'.$own->id)->assertOk();

        $athlete->client()->update(['coach_id' => $this->otherCoach->id]);
        ClientMembership::query()->delete();
        $this->membershipFor($athlete->client()->first(), $this->otherCoach, 'active');

        $this->getJson('/api/v1/app/tips/'.$own->id)->assertNotFound();
        $this->getJson('/api/v1/app/tips/'.$newCoachTip->id)->assertOk();
    }

    public function test_expired_tips_are_hidden_from_app_visibility(): void
    {
        $athlete = $this->athleteFor($this->coach, 'active');
        $visible = $this->publishedTip('Vigente', TipScope::TENANT, $this->coach, [
            'expires_at' => now()->addDay(),
        ]);
        $expired = $this->publishedTip('Expirado', TipScope::GLOBAL, null, [
            'expires_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($athlete);

        $this->getJson('/api/v1/app/tips')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Vigente'])
            ->assertJsonPath('data.0.expires_at', $visible->expires_at->utc()->toISOString())
            ->assertJsonMissing(['title' => 'Expirado']);

        $this->getJson('/api/v1/app/tips/'.$expired->id)->assertNotFound();
    }

    private function athleteFor(User $coach, string $membershipState, array $userOverrides = []): UserApp
    {
        $client = Client::query()->create([
            'coach_id' => $coach->id,
            'first_name' => 'Atleta',
            'last_name' => 'Demo',
            'email' => uniqid('athlete_', true).'@example.test',
            'is_active' => $userOverrides['client_is_active'] ?? true,
        ]);

        $userApp = UserApp::query()->create(array_merge([
            'client_id' => $client->id,
            'email' => uniqid('app_', true).'@example.test',
            'password' => 'secret',
            'is_active' => true,
        ], $userOverrides));

        $this->membershipFor($client, $coach, $membershipState);

        return $userApp;
    }

    private function membershipFor(Client $client, User $coach, string $state): ClientMembership
    {
        $plan = CoachClientPlan::query()->create([
            'coach_id' => $coach->id,
            'name' => 'Plan',
            'description' => null,
            'price' => 100,
            'currency' => 'mxn',
            'payment_provider' => 'manual',
            'billing_cycle_days' => 30,
            'reminder_days_before' => 5,
            'grace_days' => 7,
            'status' => 'active',
        ]);

        $dates = match ($state) {
            'expired' => ['starts_at' => '2026-08-01', 'ends_at' => '2026-08-31', 'grace_until' => null],
            'grace' => ['starts_at' => '2026-08-01', 'ends_at' => '2026-09-10', 'grace_until' => '2026-09-20'],
            default => ['starts_at' => '2026-09-01', 'ends_at' => '2026-09-30', 'grace_until' => null],
        };

        return ClientMembership::query()->create(array_merge([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'coach_client_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'price_snapshot' => $plan->price,
            'billing_cycle_days_snapshot' => $plan->billing_cycle_days,
            'next_renewal_at' => null,
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'paid_at' => '2026-09-01',
        ], $dates));
    }

    private function publishedTip(string $title, TipScope $scope, ?User $coach = null, array $overrides = []): Tip
    {
        $tip = new Tip();
        $tip->title = $title;
        $tip->body = $overrides['body'] ?? 'Contenido publicado';
        $tip->type = $overrides['type'] ?? TipType::TIP;
        $tip->category = $overrides['category'] ?? TipCategory::GENERAL;
        $tip->scope = $scope;
        $tip->author_id = $scope === TipScope::GLOBAL ? $this->admin->id : $coach->id;
        $tip->coach_id = $scope === TipScope::GLOBAL ? null : $coach->id;
        $tip->status = TipStatus::PUBLISHED;
        $tip->published_at = $overrides['published_at'] ?? now();
        $tip->expires_at = $overrides['expires_at'] ?? null;
        $tip->image_disk = $overrides['image_disk'] ?? null;
        $tip->image_path = $overrides['image_path'] ?? null;
        $tip->save();

        return $tip;
    }

    private function draftTip(string $title, User $coach): Tip
    {
        $tip = new Tip();
        $tip->title = $title;
        $tip->body = 'Contenido no visible';
        $tip->type = TipType::TIP;
        $tip->category = TipCategory::GENERAL;
        $tip->scope = TipScope::TENANT;
        $tip->author_id = $coach->id;
        $tip->coach_id = $coach->id;
        $tip->status = TipStatus::DRAFT;
        $tip->save();

        return $tip;
    }
}
