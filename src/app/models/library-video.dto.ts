// library-video.dto.ts

export interface TrainingType {
  id: number;
  name: string;
  description?: string;
}

export interface LibraryVideoDto {
  id: number;
  name: string;
  youtube_id: string;
  youtube_url: string;
  thumbnail_url: string;
  training_type_catalog_id: number | null;
  is_active: boolean;
  created_at: string;
}

export interface ApiOk<T> {
  ok: true;
  data: T;
}

export interface LaravelPaginator<T> {
  current_page: number;
  data: T[];
  last_page: number;
  per_page: number;
  total: number;
  next_page_url?: string | null;
  prev_page_url?: string | null;
}