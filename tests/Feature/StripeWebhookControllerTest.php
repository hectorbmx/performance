<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    private int $coachId;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.stripe_webhook_checkpoint_memory' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'services.stripe.connect_webhook_secret' => null,
            'services.stripe.webhook_secret' => null,
        ]);

        DB::setDefaultConnection('stripe_webhook_checkpoint_memory');

        foreach ([
            '2014_10_12_000000_create_users_table.php',
            '2026_01_05_044105_create_clients_table.php',
            '2026_01_05_050926_create_coach_client_plans_table.php',
            '2026_05_31_000001_add_payment_provider_to_coach_client_plans_table.php',
            '2026_01_05_051109_create_client_memberships_table.php',
            '2026_05_28_000003_add_stripe_fields_to_client_memberships.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Carbon::setTestNow('2026-09-16 10:00:00');
        $this->coachId = User::factory()->create()->id;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::purge('stripe_webhook_checkpoint_memory');
        parent::tearDown();
    }

    public function test_active_subscription_activates_pending_membership_and_is_idempotent(): void
    {
        $membership = $this->membership(ClientMembership::STATUS_PENDING_PAYMENT, 'unpaid');
        $payload = $this->subscriptionEvent($membership, 'active', 'sub_active');

        $this->postJson('/api/stripe/webhook', $payload)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->postJson('/api/stripe/webhook', $payload)
            ->assertOk();

        $membership->refresh();

        $this->assertSame('active', $membership->status);
        $this->assertSame('paid', $membership->billing_status);
        $this->assertSame('sub_active', $membership->stripe_subscription_id);
        $this->assertSame('active', $membership->stripe_status);
        $this->assertSame('2026-10-16', $membership->ends_at->toDateString());
        $this->assertSame(1, ClientMembership::query()->count());
    }

    public function test_past_due_subscription_does_not_keep_membership_active(): void
    {
        $membership = $this->membership('active', 'paid');

        $this->postJson('/api/stripe/webhook', $this->subscriptionEvent($membership, 'past_due', 'sub_past_due'))
            ->assertOk();

        $membership->refresh();

        $this->assertSame('past_due', $membership->status);
        $this->assertSame('past_due', $membership->billing_status);
        $this->assertSame('past_due', $membership->stripe_status);
    }

    public function test_incomplete_subscription_keeps_pending_membership_pending(): void
    {
        $membership = $this->membership(ClientMembership::STATUS_PENDING_PAYMENT, 'unpaid');

        $this->postJson('/api/stripe/webhook', $this->subscriptionEvent($membership, 'incomplete', 'sub_incomplete'))
            ->assertOk();

        $membership->refresh();

        $this->assertSame(ClientMembership::STATUS_PENDING_PAYMENT, $membership->status);
        $this->assertSame('unpaid', $membership->billing_status);
    }

    public function test_deleted_subscription_cancels_membership(): void
    {
        $membership = $this->membership('active', 'paid');

        $this->postJson('/api/stripe/webhook', $this->subscriptionEvent(
            $membership,
            'active',
            'sub_deleted',
            'customer.subscription.deleted'
        ))->assertOk();

        $membership->refresh();

        $this->assertSame('cancelled', $membership->status);
        $this->assertSame('canceled', $membership->billing_status);
        $this->assertSame('canceled', $membership->stripe_status);
    }

    private function membership(string $status, string $billingStatus): ClientMembership
    {
        $client = Client::query()->create([
            'coach_id' => $this->coachId,
            'first_name' => 'Atleta',
            'last_name' => 'Webhook',
            'email' => 'webhook-'.uniqid().'@example.test',
            'is_active' => true,
        ]);

        $planId = DB::table('coach_client_plans')->insertGetId([
            'coach_id' => $this->coachId,
            'name' => 'Mensual Stripe',
            'description' => null,
            'price' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'stripe',
            'billing_cycle_days' => 30,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ClientMembership::query()->create([
            'coach_id' => $this->coachId,
            'client_id' => $client->id,
            'coach_client_plan_id' => $planId,
            'plan_name_snapshot' => 'Mensual Stripe',
            'price_snapshot' => 500,
            'billing_cycle_days_snapshot' => 30,
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'status' => $status,
            'billing_status' => $billingStatus,
        ]);
    }

    private function subscriptionEvent(
        ClientMembership $membership,
        string $status,
        string $subscriptionId,
        string $type = 'customer.subscription.updated'
    ): array {
        return [
            'id' => 'evt_'.uniqid(),
            'type' => $type,
            'account' => 'acct_checkpoint',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'status' => $status,
                    'current_period_end' => Carbon::parse('2026-10-16')->timestamp,
                    'metadata' => [
                        'client_membership_id' => (string) $membership->id,
                    ],
                ],
            ],
        ];
    }
}
