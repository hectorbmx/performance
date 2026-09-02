<?php

namespace App\Services;

use App\Models\ClientMembership;

class ClientMembershipAccessResult
{
    public function __construct(
        public readonly string $state,
        public readonly bool $canAccessService,
        public readonly ?ClientMembership $membership,
        public readonly ?string $message = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->state === ClientMembershipAccessService::STATE_ACTIVE;
    }

    public function isInGrace(): bool
    {
        return $this->state === ClientMembershipAccessService::STATE_GRACE;
    }

    public function toArray(): array
    {
        return [
            'access_state' => $this->state,
            'can_access_service' => $this->canAccessService,
            'message' => $this->message,
        ];
    }
}
