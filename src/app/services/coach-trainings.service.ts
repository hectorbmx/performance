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

export interface CoachTrainingLiftingRowDTO {
  id: number;
  percentage: number | null;
  reps: number;
  sets: number;
  rest_seconds: number | null;
  notes: string | null;
  order: number;
}

export interface CoachTrainingLiftingBlockDTO {
  id: number;
  exercise_catalog_id: number | null;
  exercise_name: string;
  notes: string | null;
  order: number;
  rows: CoachTrainingLiftingRowDTO[];
}

export interface CoachTrainingLiftingRowPayload {
  id?: number;
  percentage?: number | string | null;
  reps: number | string;
  sets: number | string;
  rest_seconds?: number | string | null;
  notes?: string | null;
}

export interface CoachTrainingLiftingBlockPayload {
  id?: number;
  exercise_catalog_id?: number | string | null;
  exercise_name: string;
  notes?: string | null;
  rows?: CoachTrainingLiftingRowPayload[];
}

export interface CoachTrainingSectionPayload {
  id?: number;
  name: string;
  description?: string | null;
  video_url?: string | null;
  video_file?: File | null;
  result_type?: string | null;
  unit_id?: number | null;
  library_video_ids?: number[];
  lifting_blocks?: CoachTrainingLiftingBlockPayload[];
}

export interface CoachTrainingPayload {
  id?: number;
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
  cover_image?: File | null;
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

  async show(id: number): Promise<CoachTrainingPayload> {
    const res = await this.api.get<any>(`coach/trainings/${id}`);
    const training = res.data;

    return {
      id: training.id,
      title: training.title ?? '',
      scheduled_at: training.scheduled_at ?? '',
      duration_minutes: training.duration_minutes ?? null,
      level: training.level,
      goal: training.goal ?? null,
      type: training.type ?? null,
      training_goal_catalog_id: training.training_goal_catalog_id ?? null,
      training_type_catalog_id: training.training_type_catalog_id ?? null,
      visibility: training.visibility,
      notes: training.notes ?? '',
      tag_color: training.tag_color ?? '#2563eb',
      cover_image: null,
      assigned_client_ids: (training.assigned_clients ?? []).map((client: { id: number }) => client.id),
      assigned_group_ids: (training.assigned_groups ?? []).map((group: { id: number }) => group.id),
      sections: (training.sections ?? []).map((section: any) => ({
        id: section.id,
        name: section.name ?? '',
        description: section.description ?? '',
        video_url: section.video_source === 'upload' ? '' : (section.video_playback_url ?? ''),
        video_file: null,
        result_type: section.result_type ?? 'none',
        unit_id: section.unit_id ?? null,
        library_video_ids: (section.library_videos ?? []).map((video: { id: number }) => video.id),
        lifting_blocks: (section.lifting_blocks ?? []).map((block: any) => ({
          id: block.id,
          exercise_catalog_id: block.exercise_catalog_id ?? null,
          exercise_name: block.exercise_name ?? '',
          notes: block.notes ?? '',
          rows: (block.rows ?? []).map((row: any) => ({
            id: row.id,
            percentage: row.percentage ?? null,
            reps: row.reps ?? 1,
            sets: row.sets ?? 1,
            rest_seconds: row.rest_seconds ?? null,
            notes: row.notes ?? '',
          })),
        })),
      })),
    };
  }

  async meta(): Promise<CoachTrainingMetaDTO> {
    const res = await this.api.get<any>('coach/trainings/meta');
    return res.data;
  }

  async store(payload: CoachTrainingPayload): Promise<CoachTrainingDTO> {
    const cleanPayload = this.cleanPayload(payload);
    const res = await this.api.postForm<any>('coach/trainings', this.toFormData(cleanPayload));
    return res.data;
  }

  async update(id: number, payload: CoachTrainingPayload): Promise<CoachTrainingDTO> {
    const cleanPayload = this.cleanPayload(payload);
    const res = await this.api.postForm<any>(`coach/trainings/${id}`, this.toFormData(cleanPayload, true));
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
        video_file: section.video_file ?? null,
        result_type: section.result_type && section.result_type !== 'none' ? section.result_type : 'none',
        unit_id: section.result_type && section.result_type !== 'none' ? (section.unit_id ?? null) : null,
        library_video_ids: (section.library_video_ids ?? []).map((id) => Number(id)).filter((id) => Number.isFinite(id)),
        lifting_blocks: section.lifting_blocks?.map((block) => ({
          ...(block.id ? { id: block.id } : {}),
          exercise_catalog_id: this.optionalNumber(block.exercise_catalog_id),
          exercise_name: block.exercise_name.trim(),
          notes: block.notes?.trim() || null,
          rows: (block.rows ?? []).map((row) => ({
            ...(row.id ? { id: row.id } : {}),
            percentage: this.optionalNumber(row.percentage),
            reps: this.requiredNumber(row.reps),
            sets: this.requiredNumber(row.sets),
            rest_seconds: this.optionalNumber(row.rest_seconds),
            notes: row.notes?.trim() || null,
          })),
        })),
      })),
    };
  }

  private toFormData(payload: CoachTrainingPayload, updating = false): FormData {
    const form = new FormData();
    if (updating && payload.id) {
      form.append('_method', 'PATCH');
    }
    const append = (key: string, value: unknown) => {
      if (value === null || value === undefined || value === '') return;
      form.append(key, String(value));
    };

    append('title', payload.title);
    append('scheduled_at', payload.scheduled_at);
    append('duration_minutes', payload.duration_minutes);
    append('level', payload.level);
    append('goal', payload.goal);
    append('type', payload.type);
    append('training_goal_catalog_id', payload.training_goal_catalog_id);
    append('training_type_catalog_id', payload.training_type_catalog_id);
    append('visibility', payload.visibility);
    append('notes', payload.notes);
    append('tag_color', payload.tag_color);
    if (payload.cover_image) {
      form.append('cover_image', payload.cover_image);
    }

    (payload.assigned_client_ids ?? []).forEach((id) => append('assigned_client_ids[]', id));
    (payload.assigned_group_ids ?? []).forEach((id) => append('assigned_group_ids[]', id));

    payload.sections.forEach((section, sectionIndex) => {
      const prefix = `sections[${sectionIndex}]`;
      append(`${prefix}[id]`, section.id);
      append(`${prefix}[name]`, section.name);
      append(`${prefix}[description]`, section.description);
      append(`${prefix}[video_url]`, section.video_url);
      append(`${prefix}[result_type]`, section.result_type);
      append(`${prefix}[unit_id]`, section.unit_id);

      if (section.video_file) {
        form.append(`${prefix}[video_file]`, section.video_file);
      }

      (section.library_video_ids ?? []).forEach((id) => append(`${prefix}[library_video_ids][]`, id));

      (section.lifting_blocks ?? []).forEach((block, blockIndex) => {
        const blockPrefix = `${prefix}[lifting_blocks][${blockIndex}]`;
        append(`${blockPrefix}[id]`, block.id);
        append(`${blockPrefix}[exercise_catalog_id]`, block.exercise_catalog_id);
        append(`${blockPrefix}[exercise_name]`, block.exercise_name);
        append(`${blockPrefix}[notes]`, block.notes);

        (block.rows ?? []).forEach((row, rowIndex) => {
          const rowPrefix = `${blockPrefix}[rows][${rowIndex}]`;
          append(`${rowPrefix}[id]`, row.id);
          append(`${rowPrefix}[percentage]`, row.percentage);
          append(`${rowPrefix}[reps]`, row.reps);
          append(`${rowPrefix}[sets]`, row.sets);
          append(`${rowPrefix}[rest_seconds]`, row.rest_seconds);
          append(`${rowPrefix}[notes]`, row.notes);
        });
      });
    });

    return form;
  }

  private optionalNumber(value: number | string | null | undefined): number | null {
    if (value === null || value === undefined || value === '') return null;

    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  }

  private requiredNumber(value: number | string): number {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : 0;
  }
}
