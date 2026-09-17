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

@Injectable({ providedIn: 'root' })
export class AthleteActivityCalendarService {
  private readonly api = inject(ApiService);

  async month(month?: string | null): Promise<ActivityCalendarResponse> {
    return this.api.get<ActivityCalendarResponse>(
      'app/activity-calendar',
      this.params(month)
    );
  }

  private params(month?: string | null): Record<string, string> | undefined {
    const value = month?.trim();
    return value ? { month: value } : undefined;
  }
}
