import { inject, Injectable } from '@angular/core';
import { ApiService } from './api.service';

export type AthleteTipType = 'tip' | 'note' | 'news';
export type AthleteTipScope = 'global' | 'tenant';
export type AthleteTipCategoryKey = 'nutrition' | 'training' | 'recovery' | 'wellbeing' | 'general';

export interface AthleteTipCategoryDTO {
  key: AthleteTipCategoryKey;
  label: string;
}

export interface AthleteTipCardDTO {
  id: number;
  title: string;
  type: AthleteTipType;
  scope: AthleteTipScope;
  category: AthleteTipCategoryDTO;
  excerpt: string;
  image_url: string | null;
  published_at: string | null;
  expires_at: string | null;
  updated_at: string | null;
}

export interface AthleteTipDetailDTO extends AthleteTipCardDTO {
  body: string;
  content_format: 'plain_text';
}

export interface AthleteTipsFilters {
  q?: string;
  category?: AthleteTipCategoryKey | null;
  type?: AthleteTipType | null;
  page?: number;
  per_page?: number;
}

export interface AthleteTipsMetaDTO {
  current_page: number;
  per_page: number;
  last_page: number;
  total: number;
}

export interface AthleteTipsIndexResult {
  data: AthleteTipCardDTO[];
  meta: AthleteTipsMetaDTO;
}

interface AthleteTipsCategoriesResponse {
  ok: boolean;
  data: AthleteTipCategoryDTO[];
}

interface AthleteTipsIndexResponse {
  ok: boolean;
  data: AthleteTipCardDTO[];
  meta: AthleteTipsMetaDTO;
}

interface AthleteTipShowResponse {
  ok: boolean;
  data: AthleteTipDetailDTO;
}

@Injectable({ providedIn: 'root' })
export class AthleteTipsService {
  private readonly api = inject(ApiService);

  async categories(): Promise<AthleteTipCategoryDTO[]> {
    const res = await this.api.get<AthleteTipsCategoriesResponse>('app/tips/categories');
    return res.data ?? [];
  }

  async index(filters?: AthleteTipsFilters): Promise<AthleteTipsIndexResult> {
    const res = await this.api.get<AthleteTipsIndexResponse>('app/tips', this.cleanFilters(filters));

    return {
      data: res.data ?? [],
      meta: res.meta ?? {
        current_page: 1,
        per_page: filters?.per_page ?? 20,
        last_page: 1,
        total: 0,
      },
    };
  }

  async show(id: number): Promise<AthleteTipDetailDTO> {
    const res = await this.api.get<AthleteTipShowResponse>(`app/tips/${id}`);
    return res.data;
  }

  async imageBlob(id: number): Promise<Blob> {
    return this.api.getBlob(`app/tips/${id}/image`);
  }

  objectUrlFor(blob: Blob): string {
    return URL.createObjectURL(blob);
  }

  revokeObjectUrl(url: string | null | undefined): void {
    if (url) {
      URL.revokeObjectURL(url);
    }
  }

  private cleanFilters(filters?: AthleteTipsFilters): Record<string, string | number> | undefined {
    if (!filters) return undefined;

    const q = filters.q?.trim() ?? '';

    return {
      ...(q ? { q } : {}),
      ...(filters.category ? { category: filters.category } : {}),
      ...(filters.type ? { type: filters.type } : {}),
      ...(filters.page ? { page: filters.page } : {}),
      ...(filters.per_page ? { per_page: filters.per_page } : {}),
    };
  }
}
