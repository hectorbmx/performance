<?php
namespace Tests\Unit;

use App\Services\WorkoutMeasuredSummary;
use PHPUnit\Framework\TestCase;

class WorkoutMeasuredSummaryTest extends TestCase
{
    private function summary(array $records, int $sets = 3): array
    {
        $snapshot = ['sections' => [['exercises' => [['uuid' => 'squat', 'name' => 'Back Squat',
            'sets' => array_map(fn ($n) => ['uuid' => (string) $n], range(1, $sets))]]]]];
        return (new WorkoutMeasuredSummary)->summarize($snapshot, $records);
    }
    private function log(int $set, ?string $kg, int $reps = 10, string $type = 'work', string $status = 'completed'): array
    {
        return ['set_uuid' => (string) $set, 'load_kg' => $kg, 'load_mode' => $kg === null ? 'unknown' : 'total_barbell',
            'actual_reps' => $reps, 'type' => $type, 'status' => $status];
    }
    public function test_direct_muscles_count_only_completed_work_and_frozen_primary_tags(): void {
        $snapshot=['sections'=>[['exercises'=>[['uuid'=>'squat','name'=>'Squat','catalog'=>['terms'=>[
            ['kind'=>'muscle','code'=>'quadriceps','role'=>'primary'],['kind'=>'muscle','code'=>'glutes','role'=>'secondary']]],
            'sets'=>array_map(fn($n)=>['uuid'=>(string)$n],range(1,4))]]]]];
        $result=(new WorkoutMeasuredSummary)->summarize($snapshot,[$this->log(1,'75'),$this->log(2,'20',5,'warmup'),$this->log(3,'75',2,'work','failed')]);
        $this->assertSame(['quadriceps'=>1],$result['direct_muscle_series']);
        $this->assertSame(0,$result['unclassified_work_sets']);
        $this->assertSame(1,$this->summary([$this->log(1,null)],1)['unclassified_work_sets']);
    }
    public function test_reference_tonnage_is_exact_and_not_estimated(): void
    {
        $summary = $this->summary([$this->log(1, '75.000000'), $this->log(2, '75.000000'), $this->log(3, '72.500000', 9)]);
        $this->assertSame('2152.500000', $summary['tonnage_kg']);
        $this->assertTrue($summary['tonnage_complete']);
        $this->assertSame(3, $summary['work_sets_completed']);
        $this->assertSame(1, $summary['exercises_completed']);
        $this->assertSame('recorded_sets', $summary['source']);
    }
    public function test_partial_warmup_failed_and_missing_load_are_distinct(): void
    {
        $summary = $this->summary([$this->log(1, '20.000000', 5, 'warmup'), $this->log(2, '75.000000', 2, 'work', 'failed'),
            $this->log(3, null, 8)], 4);
        $this->assertSame('250.000000', $summary['tonnage_kg']);
        $this->assertSame('100.000000', $summary['warmup_tonnage_kg']);
        $this->assertSame('150.000000', $summary['work_tonnage_kg']);
        $this->assertSame(1, $summary['sets_without_load']); $this->assertSame(1, $summary['sets_unrecorded']);
        $this->assertFalse($summary['tonnage_complete']); $this->assertSame(0, $summary['exercises_completed']);
    }
    public function test_unknown_load_is_not_zero_but_explicit_zero_is_measured(): void
    {
        $this->assertNull($this->summary([$this->log(1, null)], 1)['tonnage_kg']);
        $this->assertSame('0.000000', $this->summary([$this->log(1, '0.000000')], 1)['tonnage_kg']);
    }
    public function test_skipped_or_foreign_logs_do_not_fabricate_tonnage(): void
    {
        $summary = $this->summary([$this->log(1, null, 0, 'work', 'skipped'), $this->log(99, '100.000000')], 1);
        $this->assertNull($summary['tonnage_kg']); $this->assertSame(1, $summary['sets_skipped']);
        $this->assertSame(0, $summary['sets_completed']);
    }
}
