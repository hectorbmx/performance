<?php

namespace App\Policies;

use App\Models\Tip;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TipPolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'coach']);
    }

    public function view(User $user, Tip $tip): Response
    {
        return $user->hasRole('admin') || ($user->hasRole('coach') && $tip->author_id === $user->id)
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function update(User $user, Tip $tip): Response
    {
        return $this->create($user) && $tip->author_id === $user->id
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function submit(User $user, Tip $tip): bool
    {
        return $user->hasRole('coach') && $tip->author_id === $user->id && $tip->coach_id === $user->id;
    }

    public function withdraw(User $user, Tip $tip): bool
    {
        return $this->submit($user, $tip);
    }

    public function publish(User $user, Tip $tip): bool
    {
        return $user->hasRole('admin') && $tip->author_id === $user->id && $tip->coach_id === null;
    }

    public function approve(User $user, Tip $tip): bool
    {
        return $user->hasRole('admin') && $tip->coach_id !== null;
    }

    public function reject(User $user, Tip $tip): bool
    {
        return $this->approve($user, $tip);
    }

    public function archive(User $user, Tip $tip): Response
    {
        return $this->view($user, $tip);
    }

    public function restore(User $user, Tip $tip): Response
    {
        return $this->update($user, $tip);
    }
}
