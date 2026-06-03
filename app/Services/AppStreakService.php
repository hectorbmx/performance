<?php

namespace App\Services;

use App\Models\TrainingAssignment;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;

class AppStreakService
{
    public function summaryForClient(int $clientId, int $lookbackDays = 120): array
    {
        $today = Carbon::today();
        $from = $today->copy()->subDays($lookbackDays);

        $assignments = TrainingAssignment::query()
            ->where('client_id', $clientId)
            ->whereNotNull('scheduled_for')
            ->whereDate('scheduled_for', '>=', $from)
            ->whereDate('scheduled_for', '<=', $today)
            ->whereNotIn('status', ['cancelled', 'skipped'])
            ->get(['id', 'scheduled_for', 'status']);

        $days = $assignments
            ->groupBy(fn (TrainingAssignment $assignment) => $assignment->scheduled_for->toDateString())
            ->map(fn ($items, $date) => [
                'date' => $date,
                'assigned' => $items->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'is_complete' => $items->count() > 0 && $items->every(fn ($assignment) => $assignment->status === 'completed'),
            ])
            ->sortKeys();

        $current = 0;
        $lastCompletedDate = null;

        for ($cursor = $today->copy(); $cursor->gte($from); $cursor->subDay()) {
            $key = $cursor->toDateString();
            $day = $days->get($key);

            if (!$day) {
                continue;
            }

            if (!$day['is_complete']) {
                if ($cursor->isSameDay($today)) {
                    continue;
                }

                break;
            }

            $current++;
            $lastCompletedDate ??= $key;
        }

        $best = 0;
        $running = 0;

        foreach (CarbonPeriod::create($from, $today) as $date) {
            $day = $days->get($date->toDateString());

            if (!$day) {
                continue;
            }

            if ($day['is_complete']) {
                $running++;
                $best = max($best, $running);
                continue;
            }

            $running = 0;
        }

        $todayStats = $days->get($today->toDateString()) ?? [
            'date' => $today->toDateString(),
            'assigned' => 0,
            'completed' => 0,
            'is_complete' => false,
        ];

        return [
            'current' => $current,
            'best' => $best,
            'last_completed_date' => $lastCompletedDate,
            'today' => [
                'assigned' => $todayStats['assigned'],
                'completed' => $todayStats['completed'],
                'is_complete' => $todayStats['is_complete'],
            ],
            'recent_days' => $days->take(-14)->values()->all(),
        ];
    }
}
