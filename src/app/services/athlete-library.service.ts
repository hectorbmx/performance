import { Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type AthleteLibraryVideoSource = 'youtube' | 'upload';

export interface AthleteLibraryVideoDTO {
  id: number;
  name: string;
  source: AthleteLibraryVideoSource;
  youtube_id: string | null;
  youtube_url: string | null;
  video_path: string | null;
  thumbnail_url: string | null;
  playback_url: string | null;
  training_type_catalog_id: number | null;
  type?: {
    id: number;
    name: string;
  } | null;
}

export interface AthleteLibraryTypeDTO {
  id: number;
  name: string;
}

interface PaginatedVideosResponse {
  ok: boolean;
  data: {
    data: AthleteLibraryVideoDTO[];
  };
}

interface CatalogResponse {
  ok: boolean;
  data: AthleteLibraryTypeDTO[];
}

@Injectable({ providedIn: 'root' })
export class AthleteLibraryService {
  constructor(private api: ApiService) {}

  async index(params?: { q?: string; training_type_catalog_id?: number | null; per_page?: number }): Promise<AthleteLibraryVideoDTO[]> {
    const res = await this.api.get<PaginatedVideosResponse>('library/videos', params);
    return res.data?.data ?? [];
  }

  async catalog(): Promise<AthleteLibraryTypeDTO[]> {
    const res = await this.api.get<CatalogResponse>('training/catalog');
    return res.data ?? [];
  }
}
