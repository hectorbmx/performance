<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AthleteActivityCalendarService
{
    private const ACTIVE_STATUSES = ['in_progress', 'completed'];

    public function month(int $clientId, ?string $month = null): array
    {
        $timezone = config('app.timezone', 'UTC');
        $start = $this->monthStart($month, $timezone);
        $end = $start->endOfMonth();
        $today = CarbonImmutable::today($timezone)->toDateString();

        $rowsByDate = DB::table('training_assignments as ta')
            ->join('training_sessions as ts', 'ts.id', '=', 'ta.training_session_id')
            ->where('ta.client_id', $clientId)
            ->whereNull('ts.deleted_at')
            ->whereBetween('ta.scheduled_for', [$start->toDateString(), $end->toDateString()])
            ->select([
                'ta.scheduled_for',
                'ta.status',
                'ts.visibility',
            ])
            ->get()
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->scheduled_for)->toDateString());

        $days = [];
        $summary = [
            'completed_days' => 0,
            'missed_days' => 0,
            'scheduled_days' => 0,
            'free_completed_days' => 0,
            'rest_days' => 0,
            'training_days' => 0,
            'activity_days' => 0,
        ];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $dayRows = $rowsByDate->get($dateString, collect());
            $day = $this->buildDay($dateString, $today, $dayRows);

            $summary[$day['status'].'_days']++;

            if ($day['assigned_count'] > 0) {
                $summary['training_days']++;
            }

            if ($day['has_activity']) {
                $summary['activity_days']++;
            }

            $days[] = $day;
        }

        return [
            'ok' => true,
            'month' => $start->format('Y-m'),
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'summary' => $summary,
            'days' => $days,
        ];
    }

    private function monthStart(?string $month, string $timezone): CarbonImmutable
    {
        if (!$month) {
            return CarbonImmutable::now($timezone)->startOfMonth();
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $timezone)->startOfMonth();
    }

    private function buildDay(string $date, string $today, $rows): array
    {
        $assignedRows = $rows->where('visibility', 'assigned');
        $freeRows = $rows->where('visibility', 'free');

        $assignedCompletedCount = $assignedRows->where('status', 'completed')->count();
        $assignedInProgressCount = $assignedRows->where('status', 'in_progress')->count();
        $freeCompletedCount = $freeRows
            ->filter(fn ($row) => in_array($row->status, self::ACTIVE_STATUSES, true))
            ->count();

        $assignedCount = $assignedRows->count();
        $hasActivity = ($assignedCompletedCount + $assignedInProgressCount + $freeCompletedCount) > 0;

        return [
            'date' => $date,
            'status' => $this->statusFor($assignedCount, $hasActivity, $freeCompletedCount, $date, $today),
            'assigned_count' => $assignedCount,
            'assigned_completed_count' => $assignedCompletedCount,
            'assigned_in_progress_count' => $assignedInProgressCount,
            'free_completed_count' => $freeCompletedCount,
            'has_activity' => $hasActivity,
        ];
    }

    private function statusFor(
        int $assignedCount,
        bool $hasActivity,
        int $freeCompletedCount,
        string $date,
        string $today
    ): string {
        if ($assignedCount > 0 && $hasActivity) {
            return 'completed';
        }

        if ($assignedCount > 0) {
            return $date < $today ? 'missed' : 'scheduled';
        }

        if ($freeCompletedCount > 0) {
            return 'free_completed';
        }

        return 'rest';
    }
}
