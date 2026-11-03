import { inject, Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type ActivityCalendarDayStatus =
  | 'completed'
  | 'missed'
  | 'scheduled'
  | 'free_completed'
  | 'rest';

export interface ActivityCalendarRangeDTO {
  from: string;
  to: string;
}

export interface ActivityCalendarSummaryDTO {
  completed_days: number;
  missed_days: number;
  scheduled_days: number;
  free_completed_days: number;
  rest_days: number;
  training_days: number;
  activity_days: number;
}

export interface ActivityCalendarDayDTO {
  date: string;
  status: ActivityCalendarDayStatus;
  assigned_count: number;
  assigned_completed_count: number;
  assigned_in_progress_count: number;
  free_completed_count: number;
  has_activity: boolean;
}

export interface ActivityCalendarResponse {
  ok: boolean;
  month: string;
  range: ActivityCalendarRangeDTO;
  summary: ActivityCalendarSummaryDTO;
  days: ActivityCalendarDayDTO[];
}

export interface LiftingPerformanceExerciseDTO {
  exercise_key: string;
  exercise_name: string;
  sets_prescribed: number;
  sets_executed: number;
  reps_prescribed: number;
  reps_executed: number;
  rep_adherence_pct: number;
  average_intensity_pct: number | null;
  estimated_tonnage: number;
}

export interface LiftingTrendBucketDTO {
  key: string;
  label: string;
  sets_prescribed: number;
  sets_executed: number;
  reps_prescribed: number;
  reps_executed: number;
  relative_volume: number;
  intensity_reps: number;
  estimated_tonnage: number;
  rep_adherence_pct: number;
  average_intensity_pct: number | null;
}

export interface LiftingExerciseTrendDTO {
  exercise_key: string;
  exercise_name: string;
  weekly: LiftingTrendBucketDTO[];
  monthly: LiftingTrendBucketDTO[];
  totals: {
    reps_prescribed: number;
    reps_executed: number;
    estimated_tonnage: number;
  };
}

export interface LiftingPerformanceResponse {
  ok: boolean;
  data: {
    has_data: boolean;
    by_exercise: LiftingPerformanceExerciseDTO[];
    trends_by_exercise: LiftingExerciseTrendDTO[];
  };
}

@Injectable({ providedIn: 'root' })
export class AthleteActivityCalendarService {
  private readonly api = inject(ApiService);

  async month(month?: string | null): Promise<ActivityCalendarResponse> {
    return this.api.get<ActivityCalendarResponse>(
      'app/activity-calendar',
      this.params(month)
    );
  }

  async liftingPerformance(): Promise<LiftingPerformanceResponse> {
    return this.api.get<LiftingPerformanceResponse>('app/lifting-performance');
  }

  private params(month?: string | null): Record<string, string> | undefined {
    const value = month?.trim();
    return value ? { month: value } : undefined;
  }
}
