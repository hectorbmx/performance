<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    private function unverifiedUser(string $role): User
    {
        $user = new User(['name' => 'Panel test', 'email_verified_at' => null]);
        $user->id = 999999;
        $user->setRelation('roles', new Collection([new Role(['name' => $role, 'guard_name' => 'web'])]));

        return $user;
    }

    public function test_unverified_admin_reaches_admin_dashboard_without_coach(): void
    {
        $this->actingAs($this->unverifiedUser('admin'))
            ->get('/dashboard')->assertRedirect(route('admin.dashboard'));
    }

    public function test_admin_can_leave_a_previous_verification_screen(): void
    {
        $this->actingAs($this->unverifiedUser('admin'))
            ->get('/verify-email')->assertRedirect(route('admin.dashboard'));
    }

    public function test_unverified_coach_still_requires_verification(): void
    {
        $this->actingAs($this->unverifiedUser('coach'))
            ->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_guest_cannot_open_dashboard_or_tips(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/coach/tips')->assertRedirect(route('login'));
    }

    public function test_admin_does_not_get_coach_tips_access(): void
    {
        $this->actingAs($this->unverifiedUser('admin'))
            ->get('/coach/tips')->assertForbidden();
    }
}
