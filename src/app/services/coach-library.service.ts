import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type CoachLibraryVideoSource = 'youtube' | 'upload';

export interface CoachLibraryTypeDTO {
  id: number;
  name: string;
}

export interface CoachLibraryVideoDTO {
  id: number;
  name: string;
  source: CoachLibraryVideoSource;
  youtube_url: string | null;
  youtube_id: string | null;
  video_path: string | null;
  thumbnail_url: string | null;
  playback_url: string | null;
  training_type_catalog_id: number | null;
  type: CoachLibraryTypeDTO | null;
  created_at: string | null;
}

export interface CoachLibraryPayload {
  name: string;
  source: CoachLibraryVideoSource;
  youtube_url?: string | null;
  video_file?: File | null;
  training_type_catalog_id?: number | null;
}

@Injectable({ providedIn: 'root' })
export class CoachLibraryService {
  constructor(private api: ApiService) {}

  async index(params?: { q?: string }): Promise<CoachLibraryVideoDTO[]> {
    return (await this.page(params)).items;
  }

  async page(params?: { q?: string; page?: number }): Promise<{ items: CoachLibraryVideoDTO[]; hasMore: boolean }> {
    const res = await this.api.get<{ data: { data: CoachLibraryVideoDTO[]; next_page_url: string | null } }>('coach/library', params);
    return { items: res.data.data, hasMore: !!res.data.next_page_url };
  }

  async meta(): Promise<{ types: CoachLibraryTypeDTO[] }> {
    const res = await this.api.get<any>('coach/library/meta');
    return res.data;
  }

  async store(payload: CoachLibraryPayload): Promise<CoachLibraryVideoDTO> {
    const form = new FormData();
    form.append('name', payload.name.trim());
    form.append('source', payload.source);

    if (payload.training_type_catalog_id) {
      form.append('training_type_catalog_id', String(payload.training_type_catalog_id));
    }

    if (payload.source === 'youtube') {
      form.append('youtube_url', payload.youtube_url?.trim() ?? '');
    }

    if (payload.source === 'upload' && payload.video_file) {
      form.append('video_file', payload.video_file);
    }

    const res = await this.api.postForm<any>('coach/library', form);
    return res.data;
  }

  async destroy(id: number): Promise<void> {
    await this.api.delete<any>(`coach/library/${id}`);
  }
}
