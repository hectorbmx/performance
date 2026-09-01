<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\ClientPayment;
use App\Models\CoachClientPlan;
use App\Models\CoachSubscription;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_coach_cannot_pay_membership_from_another_coach(): void
    {
        $coach = $this->createCoach();
        $otherCoach = $this->createCoach();
        $membership = $this->createClientMembership($otherCoach);

        $response = $this
            ->actingAs($coach)
            ->post(route('coach.client-payments.store', $membership), $this->validPaymentPayload($coach, $membership));

        $response->assertForbidden();
        $this->assertDatabaseCount('client_payments', 0);
    }

    public function test_paid_membership_does_not_create_another_payment(): void
    {
        $coach = $this->createCoach();
        $membership = $this->createClientMembership($coach, ['billing_status' => 'paid', 'paid_at' => now()->toDateString()]);

        $response = $this
            ->actingAs($coach)
            ->post(route('coach.client-payments.store', $membership), $this->validPaymentPayload($coach, $membership));

        $response
            ->assertRedirect(route('coach.clients.index'))
            ->assertSessionHas('error', 'Esta membresía ya está pagada.');

        $this->assertDatabaseCount('client_payments', 0);
    }

    public function test_valid_payment_creates_payment_and_marks_membership_paid(): void
    {
        $coach = $this->createCoach();
        $membership = $this->createClientMembership($coach);
        $payload = $this->validPaymentPayload($coach, $membership);

        $response = $this
            ->actingAs($coach)
            ->post(route('coach.client-payments.store', $membership), $payload);

        $response
            ->assertRedirect(route('coach.clients.index'))
            ->assertSessionHas('success', 'Pago registrado exitosamente.');

        $this->assertDatabaseHas('client_payments', [
            'coach_id' => $coach->id,
            'client_membership_id' => $membership->id,
            'final_amount' => 450,
            'payment_method' => 'efectivo',
            'idempotency_key' => $payload['idempotency_key'],
        ]);

        $this->assertDatabaseHas('client_memberships', [
            'id' => $membership->id,
            'billing_status' => 'paid',
            'paid_at' => $payload['payment_date'],
        ]);
    }

    public function test_reposting_same_idempotency_key_does_not_create_duplicate_payment(): void
    {
        $coach = $this->createCoach();
        $membership = $this->createClientMembership($coach);
        $payload = $this->validPaymentPayload($coach, $membership);

        $this
            ->actingAs($coach)
            ->post(route('coach.client-payments.store', $membership), $payload)
            ->assertRedirect(route('coach.clients.index'));

        $this->withSession([
            $this->paymentIdempotencySessionKey($coach, $membership) => $payload['idempotency_key'],
        ]);

        $this
            ->actingAs($coach)
            ->post(route('coach.client-payments.store', $membership), $payload)
            ->assertRedirect(route('coach.clients.index'))
            ->assertSessionHas('error', 'Esta membresía ya está pagada.');

        $this->assertDatabaseCount('client_payments', 1);
    }

    private function createCoach(): User
    {
        Role::findOrCreate('coach');

        $coach = User::factory()->create();
        $coach->assignRole('coach');

        $plan = MembershipPlan::create([
            'name' => 'Plan SaaS',
            'amount' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'manual',
            'billing_cycle_days' => 30,
            'is_active' => true,
        ]);

        CoachSubscription::create([
            'coach_id' => $coach->id,
            'membership_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'billing_cycle_days_snapshot' => 30,
            'client_limit_snapshot' => null,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'next_renewal_at' => now()->addMonth()->toDateString(),
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'paid',
            'paid_at' => now()->toDateString(),
        ]);

        return $coach;
    }

    private function createClientMembership(User $coach, array $overrides = []): ClientMembership
    {
        $client = Client::create([
            'coach_id' => $coach->id,
            'first_name' => 'Atleta',
            'last_name' => 'Prueba',
            'email' => 'athlete-' . Str::uuid() . '@example.com',
            'phone' => '5555555555',
            'is_active' => true,
        ]);

        $plan = CoachClientPlan::create([
            'coach_id' => $coach->id,
            'name' => 'Mensual',
            'description' => null,
            'price' => 500,
            'currency' => 'MXN',
            'payment_provider' => 'manual',
            'billing_cycle_days' => 30,
            'reminder_days_before' => 5,
            'grace_days' => 0,
            'status' => 'active',
        ]);

        return ClientMembership::create(array_merge([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'coach_client_plan_id' => $plan->id,
            'plan_name_snapshot' => $plan->name,
            'price_snapshot' => 500,
            'billing_cycle_days_snapshot' => 30,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'next_renewal_at' => now()->addMonth()->toDateString(),
            'reminder_days_before' => 5,
            'status' => 'active',
            'billing_status' => 'unpaid',
            'grace_until' => null,
            'paid_at' => null,
        ], $overrides));
    }

    private function validPaymentPayload(User $coach, ClientMembership $membership): array
    {
        $idempotencyKey = (string) Str::uuid();

        $this->withSession([
            $this->paymentIdempotencySessionKey($coach, $membership) => $idempotencyKey,
        ]);

        return [
            'amount' => 500,
            'discount' => 50,
            'payment_method' => 'efectivo',
            'payment_date' => now()->toDateString(),
            'notes' => 'Pago de prueba',
            'idempotency_key' => $idempotencyKey,
        ];
    }

    private function paymentIdempotencySessionKey(User $coach, ClientMembership $membership): string
    {
        return "client_payment_idempotency.{$coach->id}.{$membership->id}";
    }
}
