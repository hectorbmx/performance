import { Injectable } from '@angular/core';
import { Health } from '@capgo/capacitor-health';
import { ApiService } from './api.service';

export type HealthMetricKey = 'steps' | 'calories' | 'active_minutes';

export interface DailyHealthMetric {
  date: string;
  steps: number;
  calories: number;
  active_minutes: number;
  source?: string;
}

@Injectable({ providedIn: 'root' })
export class HealthMetricsService {
  constructor(private api: ApiService) {}

  async getToday(): Promise<DailyHealthMetric> {
    const [deviceToday] = await this.readDeviceHistory(1);
    if (deviceToday) return deviceToday;

    const res = await this.api.get<{ ok: boolean; data: DailyHealthMetric[] }>('app/health-metrics', { days: 1 });
    const [savedToday] = this.fillMissingDays(res.data ?? [], 1);
    return savedToday ?? this.emptyDay(this.isoDate(new Date()));
  }

  async getHistory(days = 30): Promise<DailyHealthMetric[]> {
    const deviceItems = await this.readDeviceHistory(days);

    if (deviceItems.length > 0) {
      this.sync(deviceItems).catch(err => console.warn('health sync failed', err));
      return deviceItems;
    }

    const res = await this.api.get<{ ok: boolean; data: DailyHealthMetric[] }>('app/health-metrics', { days });
    return this.fillMissingDays(res.data ?? [], days);
  }

  async sync(items: DailyHealthMetric[]): Promise<void> {
    await this.api.post('app/health-metrics/sync', { items });
  }

  private async readDeviceHistory(days: number): Promise<DailyHealthMetric[]> {
    try {
      const availability = await Health.isAvailable();
      if (!availability.available) return [];

      await (Health.requestAuthorization as any)({
        read: ['steps', 'calories', 'workouts'],
        write: [],
      });

      const start = this.startOfDay(new Date());
      start.setDate(start.getDate() - (days - 1));
      const end = this.endOfDay(new Date());

      const [steps, calories, active] = await Promise.all([
        this.queryDailySum('steps', start, end),
        this.queryDailySum('calories', start, end),
        this.queryWorkoutMinutes(start, end),
      ]);

      return this.daysBetween(start, days).map(date => ({
        date,
        steps: Math.round(steps.get(date) ?? 0),
        calories: Math.round(calories.get(date) ?? 0),
        active_minutes: Math.round(active.get(date) ?? 0),
        source: 'device',
      }));
    } catch (err) {
      console.warn('native health unavailable', err);
      return [];
    }
  }

  private async queryDailySum(dataType: 'steps' | 'calories', start: Date, end: Date): Promise<Map<string, number>> {
    const res = await Health.queryAggregated({
      dataType,
      startDate: start.toISOString(),
      endDate: end.toISOString(),
      bucket: 'day',
      aggregation: 'sum',
    });

    return new Map((res.samples ?? []).map(sample => [
      this.isoDate(new Date(sample.startDate)),
      Number(sample.value ?? 0),
    ]));
  }

  private async queryWorkoutMinutes(start: Date, end: Date): Promise<Map<string, number>> {
    try {
      const res = await Health.queryWorkouts({
        startDate: start.toISOString(),
        endDate: end.toISOString(),
        ascending: true,
        limit: 200,
      });

      const map = new Map<string, number>();
      for (const workout of res.workouts ?? []) {
        const date = this.isoDate(new Date(workout.startDate));
        map.set(date, (map.get(date) ?? 0) + (Number(workout.duration ?? 0) / 60));
      }

      return map;
    } catch (err) {
      console.warn('workouts unavailable for active minutes', err);
      return new Map();
    }
  }

  private fillMissingDays(items: DailyHealthMetric[], days: number): DailyHealthMetric[] {
    const byDate = new Map(items.map(item => [item.date, item]));
    const start = this.startOfDay(new Date());
    start.setDate(start.getDate() - (days - 1));

    return this.daysBetween(start, days).map(date => byDate.get(date) ?? this.emptyDay(date));
  }

  private daysBetween(start: Date, days: number): string[] {
    return Array.from({ length: days }, (_, index) => {
      const d = new Date(start);
      d.setDate(start.getDate() + index);
      return this.isoDate(d);
    });
  }

  private emptyDay(date: string): DailyHealthMetric {
    return { date, steps: 0, calories: 0, active_minutes: 0, source: 'empty' };
  }

  private startOfDay(date: Date): Date {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
  }

  private endOfDay(date: Date): Date {
    const d = new Date(date);
    d.setHours(23, 59, 59, 999);
    return d;
  }

  private isoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
}
