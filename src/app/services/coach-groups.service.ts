import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export interface CoachGroupDTO {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  clients_count: number;
  training_assignments_count: number;
  created_at: string | null;
  clients?: CoachGroupClientDTO[];
  available_clients?: CoachGroupClientDTO[];
}

export interface CoachGroupClientDTO {
  id: number;
  full_name: string;
  email: string | null;
}

export interface CoachGroupPayload {
  name: string;
  description?: string | null;
  is_active: boolean;
}

@Injectable({ providedIn: 'root' })
export class CoachGroupsService {
  constructor(private api: ApiService) {}

  async index(params?: { q?: string }): Promise<CoachGroupDTO[]> {
    const res = await this.api.get<any>('coach/groups', params);
    return res?.data?.data ?? res?.data ?? [];
  }

  async store(payload: CoachGroupPayload): Promise<CoachGroupDTO> {
    const res = await this.api.post<any>('coach/groups', this.cleanPayload(payload));
    return res.data;
  }

  async update(id: number, payload: CoachGroupPayload): Promise<CoachGroupDTO> {
    const res = await this.api.put<any>(`coach/groups/${id}`, this.cleanPayload(payload));
    return res.data;
  }

  async show(id: number): Promise<CoachGroupDTO> {
    const res = await this.api.get<any>(`coach/groups/${id}`);
    return res.data;
  }

  async addClient(groupId: number, clientId: number): Promise<CoachGroupDTO> {
    const res = await this.api.post<any>(`coach/groups/${groupId}/clients`, { client_id: clientId });
    return res.data;
  }

  async removeClient(groupId: number, clientId: number): Promise<CoachGroupDTO> {
    const res = await this.api.delete<any>(`coach/groups/${groupId}/clients/${clientId}`);
    return res.data;
  }

  private cleanPayload(payload: CoachGroupPayload): CoachGroupPayload {
    return {
      name: payload.name.trim(),
      description: payload.description?.trim() || null,
      is_active: payload.is_active,
    };
  }
}
