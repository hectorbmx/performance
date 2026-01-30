import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from 'src/environments/environment';
import { Preferences } from '@capacitor/preferences';



type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export type TrainingStatus = 'scheduled' | 'in_progress' | 'completed' | 'skipped' | 'cancelled';
export type TrainingSource = 'personal' | 'group' | 'free';

export interface TrainingSessionDTO {
  id: number;
  coach_id: number;
  title: string;
  duration_minutes: number | null;
  level: 'beginner' | 'intermediate' | 'advanced' | string;
  goal: string | null;
  type: string | null;
  visibility: string | null;
  notes: string | null;
}

export interface TrainingFeedItemDTO {
  assignment_id: number | null;
  source: TrainingSource;
  status: TrainingStatus;
  scheduled_for: string | null; // YYYY-MM-DD
  training_session: TrainingSessionDTO;

  group: null | {
    id: number;
    name: string;
  };

  progress: {
    sections_total: number;
    sections_with_results: number;
    pct: number;
  };
}

export interface TrainingsIndexResponse {
  ok: boolean;
  data: TrainingFeedItemDTO[];
}

export interface TrainingSectionDTO {
  id: number;
  order: number;
  name: string;
  description: string | null;
  accepts_results: boolean;
  unit_default: string | null;
  latest_result: null | {
    id: number;
    result_type: string; // "number" por MVP
    value: number | string;
    unit: string | null;
    notes: string | null;
    created_at: string;
  };
}

export interface TrainingAssignmentShowResponse {
  ok: boolean;
  data: {
    assignment: {
      id: number;
      status: TrainingStatus;
      scheduled_for: string | null;
    };
    training_session: TrainingSessionDTO | null;
    sections: TrainingSectionDTO[];
    progress: {
      sections_total: number;
      sections_with_results: number;
      pct: number;
    };
  };
}

export interface StoreSectionResultBody {
  training_assignment_id: number;
  value: number | string | boolean | any[];
  unit?: string;
  notes?: string;
  recorded_at?: string; // opcional
}

export interface StoreSectionResultResponse {
  ok: boolean;
  data: {
    id: number;
    training_assignment_id: number;
    training_section_id: number;
    result_type: string;
    value: any;
    unit: string | null;
    notes: string | null;
    created_at: string;
  };
}


@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly baseUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

    async setToken(token: string): Promise<void> {
    await Preferences.set({ key: 'auth_token', value: token });
  }

  async getToken(): Promise<string | null> {
    const { value } = await Preferences.get({ key: 'auth_token' });
    return value ?? null;
  }

  async clearToken(): Promise<void> {
    await Preferences.remove({ key: 'auth_token' });
  }

private async buildHeaders(
  extra?: Record<string, string>,
  options?: { isFormData?: boolean }
): Promise<HttpHeaders> {
  const base: Record<string, string> = {
    'Accept': 'application/json',
    ...(extra ?? {}),
  };

  // Solo setea JSON Content-Type si NO es FormData
  if (!options?.isFormData) {
    base['Content-Type'] = 'application/json';
  }

  let headers = new HttpHeaders(base);

  const token = await this.getToken();
  if (token) {
    headers = headers.set('Authorization', `Bearer ${token}`);
  }

  return headers;
}


  private buildParams(params?: Record<string, any>): HttpParams | undefined {
    if (!params) return undefined;

    let httpParams = new HttpParams();
    Object.entries(params).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') return;

      // arrays: key[]=a&key[]=b
      if (Array.isArray(value)) {
        value.forEach(v => {
          if (v !== null && v !== undefined && v !== '') {
            httpParams = httpParams.append(`${key}[]`, String(v));
          }
        });
        return;
      }

      httpParams = httpParams.set(key, String(value));
    });

    return httpParams;
  }
  // ==========================
  // REQUEST WRAPPER
  // ==========================
  async request<T>(
    method: HttpMethod,
    path: string,
    options?: {
      body?: any;
      params?: Record<string, any>;
      headers?: Record<string, string>;
      isFormData?: boolean;
    }
  ): Promise<T> {
    const url = this.normalizeUrl(path);
    // const headers = await this.buildHeaders(options?.headers);
    const headers = await this.buildHeaders(options?.headers, { isFormData: options?.isFormData });

    const params = this.buildParams(options?.params);

    try {
      const obs = this.http.request<T>(method, url, {
        headers,
        params,
        body: options?.body,
      });

      return await firstValueFrom(obs);
    } catch (err) {
      throw this.normalizeError(err);
    }
  }

  // Helpers para uso cómodo
  get<T>(path: string, params?: Record<string, any>, headers?: Record<string, string>) {
    return this.request<T>('GET', path, { params, headers });
  }

  post<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>) {
    return this.request<T>('POST', path, { body, params, headers });
  }

  put<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>) {
    return this.request<T>('PUT', path, { body, params, headers });
  }

  patch<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>) {
    return this.request<T>('PATCH', path, { body, params, headers });
  }

  delete<T>(path: string, params?: Record<string, any>, headers?: Record<string, string>) {
    return this.request<T>('DELETE', path, { params, headers });
  }


  private normalizeUrl(path: string): string {
    // permite pasar "app/login" o "/app/login"
    const cleanPath = path.startsWith('/') ? path.slice(1) : path;
    return `${this.baseUrl}/${cleanPath}`;
  }

  private normalizeError(err: unknown): Error {
    if (err instanceof HttpErrorResponse) {
      const msg =
        err.error?.message ||
        (typeof err.error === 'string' ? err.error : null) ||
        `HTTP ${err.status}: ${err.statusText || 'Error'}`;

      const e = new Error(msg);
      (e as any).status = err.status;
      (e as any).raw = err;
      return e;
    }

    return err instanceof Error ? err : new Error('Error inesperado en la petición.');
  }
  postFormData<T>(path: string, formData: FormData, params?: Record<string, any>, headers?: Record<string, string>) {
  return this.request<T>('POST', path, { body: formData, params, headers, isFormData: true });
}

}
