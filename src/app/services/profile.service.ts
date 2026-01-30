import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type HealthProfilePayload = {
  state?: string | null;
  city?: string | null;
  zip_code?: string | null;
  birth_date?: string | null;
  gender?: string | null;
  height_cm?: number | null;
};

export type BodyRecordPayload = {
  weight_kg: number;
  recorded_at?: string;
  notes?: string | null;
};

export type MetricRecordPayload = {
  training_metric_id?: number;
  metric_code?: string;
  value: number;
  recorded_at?: string;
  notes?: string | null;
};

@Injectable({
  providedIn: 'root'
})
export class ProfileService {
  constructor(private api: ApiService) {}

  getMyProfile(): Promise<any> {
    return this.api.get<any>('/app/me/profile');
  }

  updateHealthProfile(payload: HealthProfilePayload): Promise<any> {
    return this.api.patch<any>('/app/me/health-profile', payload);
  }

  addBodyRecord(payload: BodyRecordPayload): Promise<any> {
    return this.api.post<any>('/app/me/body-records', payload);
  }

  addMetricRecord(payload: MetricRecordPayload): Promise<any> {
    return this.api.post<any>('/app/me/metric-records', payload);
  }
}
