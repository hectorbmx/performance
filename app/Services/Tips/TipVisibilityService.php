<?php

namespace App\Services\Tips;

use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Models\Tip;
use App\Models\UserApp;
use App\Services\ClientMembershipAccessResult;
use App\Services\ClientMembershipAccessService;
use Illuminate\Database\Eloquent\Builder;

class TipVisibilityService
{
    public function __construct(
        private readonly ClientMembershipAccessService $membershipAccess
    ) {
    }

    public function accessFor(UserApp $userApp): ClientMembershipAccessResult
    {
        $userApp->load('client:id,coach_id,is_active,deleted_at');

        return $this->membershipAccess->forUserApp($userApp);
    }

    public function visibleQuery(UserApp $userApp): Builder
    {
        $access = $this->accessFor($userApp);
        $userApp->load('client:id,coach_id,is_active,deleted_at');
        $coachId = $userApp->client?->coach_id;

        $query = Tip::query()
            ->where('status', TipStatus::PUBLISHED->value)
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('archived_at')
                    ->orWhere('status', '!=', TipStatus::ARCHIVED->value);
            });

        if ($access->state === ClientMembershipAccessService::STATE_INACTIVE_USER) {
            return $query->whereRaw('1 = 0');
        }

        if ($access->state === ClientMembershipAccessService::STATE_EXPIRED) {
            return $query->where('scope', TipScope::GLOBAL->value);
        }

        if (! $access->canAccessService || ! $coachId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($coachId): void {
            $query->where('scope', TipScope::GLOBAL->value)
                ->orWhere(function (Builder $query) use ($coachId): void {
                    $query->where('scope', TipScope::TENANT->value)
                        ->where('coach_id', $coachId);
                });
        });
    }

    public function findVisible(UserApp $userApp, int $tipId): Tip
    {
        return $this->visibleQuery($userApp)->whereKey($tipId)->firstOrFail();
    }
}
