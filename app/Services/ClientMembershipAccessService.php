<?php

namespace App\Services;

use App\Models\ClientMembership;
use App\Models\UserApp;
use Illuminate\Support\Carbon;

class ClientMembershipAccessService
{
    public const STATE_ACTIVE = 'active';
    public const STATE_GRACE = 'grace';
    public const STATE_EXPIRED = 'expired';
    public const STATE_NO_MEMBERSHIP = 'no_membership';
    public const STATE_INACTIVE_USER = 'inactive_user';

    public function forUserApp(UserApp $userApp): ClientMembershipAccessResult
    {
        $userApp->loadMissing('client');

        if (!$userApp->is_active || !$userApp->client || !$userApp->client->is_active) {
            return new ClientMembershipAccessResult(
                self::STATE_INACTIVE_USER,
                false,
                null,
                'Cuenta inactiva. Contacta a tu coach.'
            );
        }

        $membership = $this->currentMembershipFor($userApp);

        if ($membership) {
            return new ClientMembershipAccessResult(
                $this->isInGrace($membership) ? self::STATE_GRACE : self::STATE_ACTIVE,
                true,
                $membership,
                null
            );
        }

        $latest = $this->latestMembershipFor($userApp);

        if (!$latest) {
            return new ClientMembershipAccessResult(
                self::STATE_NO_MEMBERSHIP,
                false,
                null,
                'No tienes una membresia activa o vigente. Contacta a tu coach.'
            );
        }

        return new ClientMembershipAccessResult(
            self::STATE_EXPIRED,
            false,
            $latest,
            $this->expiredMessageFor($latest)
        );
    }

    public function canAccessService(UserApp $userApp): bool
    {
        return $this->forUserApp($userApp)->canAccessService;
    }

    public function currentMembershipFor(UserApp $userApp): ?ClientMembership
    {
        $userApp->loadMissing('client');

        if (!$userApp->client) {
            return null;
        }

        $today = Carbon::today();

        return ClientMembership::query()
            ->with('coachClientPlan:id,name,price,currency,billing_cycle_days,reminder_days_before,grace_days')
            ->where('client_id', $userApp->client_id)
            ->where('coach_id', $userApp->client->coach_id)
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->whereDate('starts_at', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today)
                    ->orWhereDate('grace_until', '>=', $today);
            })
            ->orderByRaw('CASE WHEN ends_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('ends_at')
            ->orderByDesc('starts_at')
            ->first();
    }

    public function latestMembershipFor(UserApp $userApp): ?ClientMembership
    {
        $userApp->loadMissing('client');

        if (!$userApp->client) {
            return null;
        }

        return ClientMembership::query()
            ->with('coachClientPlan:id,name,price,currency,billing_cycle_days,reminder_days_before,grace_days')
            ->where('client_id', $userApp->client_id)
            ->where('coach_id', $userApp->client->coach_id)
            ->whereNull('deleted_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('ends_at')
            ->first();
    }

    private function isInGrace(ClientMembership $membership): bool
    {
        $today = Carbon::today();

        return $membership->grace_until
            && Carbon::parse($membership->grace_until)->startOfDay()->gte($today)
            && $membership->ends_at
            && Carbon::parse($membership->ends_at)->startOfDay()->lt($today);
    }

    private function expiredMessageFor(ClientMembership $membership): string
    {
        if ($membership->status === 'canceled') {
            return 'Tu membresia fue cancelada. Contacta a tu coach.';
        }

        if ($membership->status === 'expired') {
            return 'Tu membresia ha vencido. Renueva para continuar.';
        }

        return 'No tienes una membresia vigente. Renueva para continuar.';
    }
}
