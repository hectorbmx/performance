import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export interface CoachAthleteMembershipDTO {
  id: number;
  plan_name: string;
  status: string;
  billing_status: string;
  starts_at: string | null;
  ends_at: string | null;
}

export interface CoachAthleteDTO {
  id: number;
  first_name: string;
  last_name: string | null;
  full_name: string;
  email: string | null;
  phone: string | null;
  is_active: boolean;
  app_account: null | {
    id: number;
    email: string;
    is_active: boolean;
    activated_at: string | null;
  };
  active_membership: CoachAthleteMembershipDTO | null;
}

export interface CoachAthletePayload {
  first_name: string;
  last_name?: string | null;
  email?: string | null;
  phone?: string | null;
  is_active: boolean;
}

export interface CoachAthleteStoreResult {
  athlete: CoachAthleteDTO;
  activation_code: string | null;
}

export interface CoachAthleteTrainingDTO {
  assignment_id: number;
  status: string;
  status_label: string;
  scheduled_for: string | null;
  training: null | {
    id: number;
    title: string;
    duration_minutes: number | null;
    level: string;
    goal: string | null;
    type: string | null;
    sections_count: number;
  };
}

@Injectable({ providedIn: 'root' })
export class CoachAthletesService {
  constructor(private api: ApiService) {}

  async index(params?: { q?: string }): Promise<CoachAthleteDTO[]> {
    const res = await this.api.get<any>('coach/clients', params);
    return res?.data?.data ?? res?.data ?? [];
  }

  async show(id: number): Promise<CoachAthleteDTO> {
    const res = await this.api.get<any>(`coach/clients/${id}`);
    return res.data;
  }

  async trainings(id: number): Promise<CoachAthleteTrainingDTO[]> {
    const res = await this.api.get<any>(`coach/clients/${id}/trainings`);
    return res?.data?.data ?? res?.data ?? [];
  }

  async store(payload: CoachAthletePayload): Promise<CoachAthleteStoreResult> {
    const res = await this.api.post<any>('coach/clients', this.cleanPayload(payload));
    return {
      athlete: res.data,
      activation_code: res.activation_code ?? null,
    };
  }

  async update(id: number, payload: CoachAthletePayload): Promise<CoachAthleteDTO> {
    const res = await this.api.put<any>(`coach/clients/${id}`, this.cleanPayload(payload));
    return res.data;
  }

  private cleanPayload(payload: CoachAthletePayload): CoachAthletePayload {
    return {
      first_name: payload.first_name.trim(),
      last_name: payload.last_name?.trim() || null,
      email: payload.email?.trim() || null,
      phone: payload.phone?.trim() || null,
      is_active: payload.is_active,
    };
  }
}
