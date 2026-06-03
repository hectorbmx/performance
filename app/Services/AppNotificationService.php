<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientMembership;
use App\Models\PushNotification;
use App\Models\TrainingSession;
use App\Models\UserApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

class AppNotificationService
{
    public function currentMembershipFor(UserApp $userApp): ?ClientMembership
    {
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
            ->orderByDesc('ends_at')
            ->orderByDesc('starts_at')
            ->first();
    }

    public function forUserApp(UserApp $userApp, ?ClientMembership $membership = null): array
    {
        $membership ??= $this->currentMembershipFor($userApp);

        return array_values(array_merge(
            $this->membershipNotifications($membership),
            $this->storedNotifications($userApp)
        ));
    }

    public function notifyTrainingAssigned(array $clientIds, TrainingSession $training): void
    {
        $userApps = UserApp::query()
            ->whereIn('client_id', collect($clientIds)->unique()->values())
            ->where('is_active', true)
            ->get();

        foreach ($userApps as $userApp) {
            $this->sendToUserApp(
                $userApp,
                'training_assigned',
                'Nuevo entrenamiento para ti',
                'Tu coach agrego un nuevo entrenamiento: ' . $training->title,
                [
                    'action' => 'open_training',
                    'training_session_id' => $training->id,
                    'scheduled_for' => optional($training->scheduled_at)->toDateString(),
                    'source' => 'assigned',
                ]
            );
        }
    }

    public function notifyFreeTrainingCreated(int $coachId, TrainingSession $training): void
    {
        $clientIds = Client::query()
            ->where('coach_id', $coachId)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $userApps = UserApp::query()
            ->whereIn('client_id', $clientIds)
            ->where('is_active', true)
            ->get();

        foreach ($userApps as $userApp) {
            $this->sendToUserApp(
                $userApp,
                'training_free_created',
                'Nuevo entrenamiento libre',
                'Agregaron un nuevo entrenamiento libre: ' . $training->title,
                [
                    'action' => 'open_training',
                    'training_session_id' => $training->id,
                    'scheduled_for' => optional($training->scheduled_at)->toDateString(),
                    'source' => 'free',
                ]
            );
        }
    }

    public function sendToUserApp(UserApp $userApp, string $type, string $title, string $body, array $data = []): PushNotification
    {
        $notification = PushNotification::create([
            'user_id' => $userApp->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'status' => 'queued',
            'provider' => 'fcm',
        ]);

        $tokens = $userApp->devices()
            ->where('is_enabled', true)
            ->whereNotNull('token')
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            return $notification;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($this->stringPayload(array_merge($data, [
                    'type' => $type,
                    'notification_id' => $notification->id,
                ])));

            $report = app('firebase.messaging')->sendMulticast($message, $tokens);
            $failures = $report->failures();

            $notification->update([
                'status' => $report->successes()->count() > 0 ? 'sent' : 'failed',
                'error' => $failures->count() > 0
                    ? json_encode(collect($failures->getItems())->map(fn ($failure) => [
                        'token' => $failure->target()->value(),
                        'error' => $failure->error()->getMessage(),
                    ])->values(), JSON_UNESCAPED_SLASHES)
                    : null,
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    private function membershipNotifications(?ClientMembership $membership): array
    {
        if (!$membership) {
            return [];
        }

        $notifications = [];
        $today = Carbon::today();

        if (in_array($membership->billing_status, ['unpaid', 'past_due'], true)) {
            $notifications[] = [
                'id' => 'membership_payment_pending',
                'type' => 'warning',
                'title' => 'Pago pendiente',
                'message' => 'Tu membresia no esta pagada. Regulariza tu pago para evitar la suspension del servicio.',
                'action' => 'open_membership',
                'meta' => [
                    'billing_status' => $membership->billing_status,
                    'ends_at' => optional($membership->ends_at)->toDateString(),
                    'grace_until' => optional($membership->grace_until)->toDateString(),
                ],
            ];
        }

        if (!$membership->ends_at) {
            return $notifications;
        }

        $endsAt = Carbon::parse($membership->ends_at)->startOfDay();
        $reminderDays = (int) ($membership->reminder_days_before ?? $membership->coachClientPlan?->reminder_days_before ?? 0);
        $daysLeft = $today->diffInDays($endsAt, false);

        if ($daysLeft < 0) {
            $notifications[] = [
                'id' => 'membership_expired',
                'type' => 'danger',
                'title' => 'Membresia vencida',
                'message' => 'Tu plan ya vencio. Renueva tu membresia para mantener el acceso.',
                'action' => 'open_membership',
                'meta' => [
                    'ends_at' => optional($membership->ends_at)->toDateString(),
                    'grace_until' => optional($membership->grace_until)->toDateString(),
                ],
            ];
        } elseif ($reminderDays > 0 && $daysLeft <= $reminderDays) {
            $notifications[] = [
                'id' => 'membership_expiring',
                'type' => 'warning',
                'title' => 'Tu plan esta por vencer',
                'message' => "Tu membresia vence en {$daysLeft} dia(s). Puedes contratar tu siguiente plan desde Mis membresias.",
                'action' => 'open_membership',
                'meta' => [
                    'ends_at' => optional($membership->ends_at)->toDateString(),
                    'days_left' => $daysLeft,
                ],
            ];
        }

        return $notifications;
    }

    private function storedNotifications(UserApp $userApp): array
    {
        return PushNotification::query()
            ->where('user_id', $userApp->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (PushNotification $notification) => [
                'id' => 'push_' . $notification->id,
                'type' => $this->uiTypeFor($notification->type),
                'title' => $notification->title,
                'message' => $notification->body,
                'action' => $notification->data['action'] ?? null,
                'meta' => array_merge($notification->data ?? [], [
                    'notification_id' => $notification->id,
                    'notification_type' => $notification->type,
                    'status' => $notification->status,
                    'created_at' => optional($notification->created_at)->toISOString(),
                    'read_at' => optional($notification->read_at)->toISOString(),
                ]),
            ])
            ->all();
    }

    private function uiTypeFor(string $type): string
    {
        return match ($type) {
            'membership_expired' => 'danger',
            'membership_expiring', 'membership_payment_pending' => 'warning',
            default => 'info',
        };
    }

    private function stringPayload(array $data): array
    {
        return collect($data)
            ->mapWithKeys(fn ($value, $key) => [$key => is_scalar($value) || $value === null ? (string) $value : json_encode($value)])
            ->all();
    }
}
