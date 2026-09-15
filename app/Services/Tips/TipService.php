<?php

namespace App\Services\Tips;

use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Models\Tip;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class TipService
{
    public function __construct(private TipImageService $images) {}

    public function save(User $actor, array $content, ?int $id = null, ?UploadedFile $image = null, bool $removeImage = false, ?string $intent = null): Tip
    {
        if ($image && $removeImage) {
            throw ValidationException::withMessages(['image' => 'No puedes subir y retirar la imagen simultáneamente.']);
        }
        if ($intent !== null && ! in_array($intent, ['submit', 'publish'], true)) {
            throw ValidationException::withMessages(['intent' => 'Acción de guardado inválida.']);
        }

        // Own the outer transaction so filesystem compensation follows the actual commit.
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('TipService::save debe administrar su propia transacción.');
        }
        $newPath = null;
        $oldDisk = $oldPath = null;
        try {
            $tip = DB::transaction(function () use ($actor, $content, $id, $image, $removeImage, $intent, &$newPath, &$oldDisk, &$oldPath) {
                if ($id !== null) {
                    $tip = Tip::query()->lockForUpdate()->findOrFail($id);
                    Gate::forUser($actor)->authorize('update', $tip);
                    $this->requireState($tip, [TipStatus::DRAFT, TipStatus::REJECTED]);
                } else {
                    Gate::forUser($actor)->authorize('create', Tip::class);
                    $tip = new Tip;
                    $tip->author_id = $actor->id;
                    $tip->scope = $actor->hasRole('admin') ? TipScope::GLOBAL : TipScope::TENANT;
                    $tip->coach_id = $tip->scope === TipScope::TENANT ? $actor->id : null;
                }

                $tip->fill(array_intersect_key($content, array_flip(['title', 'body', 'category', 'type', 'expires_at'])));
                $tip->status = TipStatus::DRAFT;
                $this->clearReview($tip);
                $tip->save();
                if ($image || $removeImage) {
                    $oldDisk = $tip->image_disk;
                    $oldPath = $tip->image_path;
                    $newPath = $image ? $this->images->store($image, $actor->id) : null;
                    $tip->image_disk = $newPath ? 'local' : null;
                    $tip->image_path = $newPath;
                    $tip->save();
                }
                if ($intent) {
                    $this->applyTransition($actor, $tip, $intent, null);
                }

                return $tip;
            });
        } catch (Throwable $exception) {
            $this->images->delete('local', $newPath);
            throw $exception;
        }
        $this->images->delete($oldDisk, $oldPath);

        return $tip;
    }

    public function transition(User $actor, int $id, string $action, ?string $reason = null): Tip
    {
        return DB::transaction(function () use ($actor, $id, $action, $reason) {
            $tip = Tip::query()->lockForUpdate()->findOrFail($id);
            $this->applyTransition($actor, $tip, $action, $reason);

            return $tip;
        });
    }

    private function applyTransition(User $actor, Tip $tip, string $action, ?string $reason): void
    {
        $states = [
            'submit' => [[TipStatus::DRAFT], TipStatus::PENDING_APPROVAL],
            'withdraw' => [[TipStatus::PENDING_APPROVAL], TipStatus::DRAFT],
            'approve' => [[TipStatus::PENDING_APPROVAL], TipStatus::PUBLISHED],
            'reject' => [[TipStatus::PENDING_APPROVAL], TipStatus::REJECTED],
            'publish' => [[TipStatus::DRAFT], TipStatus::PUBLISHED],
            'archive' => [[TipStatus::DRAFT, TipStatus::PENDING_APPROVAL, TipStatus::REJECTED, TipStatus::PUBLISHED], TipStatus::ARCHIVED],
            'restore' => [[TipStatus::ARCHIVED], TipStatus::DRAFT],
        ];
        if (! isset($states[$action])) {
            throw ValidationException::withMessages(['action' => 'Acción inválida.']);
        }
        Gate::forUser($actor)->authorize('view', $tip);
        Gate::forUser($actor)->authorize($action, $tip);
        [$from, $to] = $states[$action];
        $this->requireState($tip, $from);
        if ($action === 'reject') {
            $reason = trim($reason ?? '');
            Validator::make(['rejection_reason' => $reason], ['rejection_reason' => ['required', 'string', 'max:2000']])->validate();
        }
        if (in_array($action, ['approve', 'reject'], true)) {
            $tip->reviewed_by = $actor->id;
            $tip->reviewed_at = now();
            $tip->rejection_reason = $action === 'reject' ? $reason : null;
        }
        if (in_array($action, ['restore', 'withdraw', 'submit'], true)) {
            $this->clearReview($tip);
        }
        if ($to === TipStatus::PUBLISHED && ! $tip->published_at) {
            $tip->published_at = now();
        }
        if ($action === 'archive') {
            $tip->archived_at = now();
        } elseif ($action === 'restore') {
            $tip->archived_at = null;
        }
        $tip->status = $to;
        $tip->save();
    }

    private function requireState(Tip $tip, array $allowed): void
    {
        if (! in_array($tip->status, $allowed, true)) {
            throw new ConflictHttpException('El estado de la publicación cambió o no permite esta acción.');
        }
    }

    private function clearReview(Tip $tip): void
    {
        $tip->reviewed_by = null;
        $tip->reviewed_at = null;
        $tip->rejection_reason = null;
    }
}
