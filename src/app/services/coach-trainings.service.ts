import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type TrainingVisibility = 'free' | 'assigned';
export type TrainingLevel = 'beginner' | 'intermediate' | 'advanced';

export interface CoachTrainingDTO {
  id: number;
  title: string;
  scheduled_at: string | null;
  duration_minutes: number | null;
  level: TrainingLevel | string;
  goal: string | null;
  type: string | null;
  training_goal_catalog_id: number | null;
  training_type_catalog_id: number | null;
  visibility: TrainingVisibility;
  notes: string | null;
  tag_color: string | null;
  sections_count: number;
  assignments_count: number;
}

export interface CoachTrainingMetaDTO {
  clients: Array<{ id: number; label: string; email: string | null }>;
  groups: Array<{ id: number; label: string }>;
  types: Array<{ id: number; name: string }>;
  goals: Array<{ id: number; name: string }>;
  units: Array<{ id: number; result_type: string; name: string; symbol: string | null; code: string | null }>;
  result_types: string[];
}

export interface CoachTrainingSectionPayload {
  name: string;
  description?: string | null;
  video_url?: string | null;
  result_type?: string | null;
  unit_id?: number | null;
}

export interface CoachTrainingPayload {
  title: string;
  scheduled_at: string;
  duration_minutes?: number | null;
  level: TrainingLevel;
  goal?: string | null;
  type?: string | null;
  training_goal_catalog_id?: number | null;
  training_type_catalog_id?: number | null;
  visibility: TrainingVisibility;
  notes?: string | null;
  tag_color?: string | null;
  assigned_client_ids?: number[];
  assigned_group_ids?: number[];
  sections: CoachTrainingSectionPayload[];
}

@Injectable({ providedIn: 'root' })
export class CoachTrainingsService {
  constructor(private api: ApiService) {}

  async index(params?: { visibility?: string; date?: string }): Promise<CoachTrainingDTO[]> {
    const res = await this.api.get<any>('coach/trainings', params);
    return res?.data?.data ?? res?.data ?? [];
  }

  async meta(): Promise<CoachTrainingMetaDTO> {
    const res = await this.api.get<any>('coach/trainings/meta');
    return res.data;
  }

  async store(payload: CoachTrainingPayload): Promise<CoachTrainingDTO> {
    const res = await this.api.post<any>('coach/trainings', this.cleanPayload(payload));
    return res.data;
  }

  private cleanPayload(payload: CoachTrainingPayload): CoachTrainingPayload {
    return {
      ...payload,
      title: payload.title.trim(),
      notes: payload.notes?.trim() || null,
      assigned_client_ids: payload.visibility === 'assigned' ? (payload.assigned_client_ids ?? []) : [],
      assigned_group_ids: payload.visibility === 'assigned' ? (payload.assigned_group_ids ?? []) : [],
      sections: payload.sections.map((section) => ({
        name: section.name.trim(),
        description: section.description?.trim() || null,
        video_url: section.video_url?.trim() || null,
        result_type: section.result_type && section.result_type !== 'none' ? section.result_type : 'none',
        unit_id: section.result_type && section.result_type !== 'none' ? (section.unit_id ?? null) : null,
      })),
    };
  }
}
