import { Injectable } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpHeaders, HttpParams } from '@angular/common/http';
import { firstValueFrom, timeout } from 'rxjs';
import { authStorageKey } from './auth-storage';
import { environment } from 'src/environments/environment';
import { Preferences } from '@capacitor/preferences';



type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

interface ApiRequestOptions {
  body?: any;
  params?: Record<string, any>;
  headers?: Record<string, string>;
  isFormData?: boolean;
  suppressMembershipRedirect?: boolean;
}

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

export interface ApiError extends Error {
  status?: number;
  code?: string;
  access_state?: string;
  raw?: HttpErrorResponse;
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
    result_type: string;
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

  async setToken(token: string, persistent: boolean = true): Promise<void> {
    if (persistent) {
      sessionStorage.removeItem(authStorageKey('auth_token'));
      await Preferences.set({ key: authStorageKey('auth_token'), value: token });
      return;
    }

    sessionStorage.setItem(authStorageKey('auth_token'), token);
    await Preferences.remove({ key: authStorageKey('auth_token') });
  }

  async getToken(): Promise<string | null> {
    const sessionToken = sessionStorage.getItem(authStorageKey('auth_token'));
    if (sessionToken) return sessionToken;

    const { value } = await Preferences.get({ key: authStorageKey('auth_token') });
    return value ?? null;
  }

  async clearToken(): Promise<void> {
    sessionStorage.removeItem(authStorageKey('auth_token'));
    await Preferences.remove({ key: authStorageKey('auth_token') });
  }

  async revokeToken(path: 'coach/logout' | 'app/logout', token: string): Promise<void> {
    // Pin the old credential; a late response must not affect a subsequent login.
    await firstValueFrom(this.http.post(this.normalizeUrl(path), {}, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    }).pipe(timeout(5000)));
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
    options?: ApiRequestOptions
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

      return await firstValueFrom(obs.pipe(timeout(20000)));
    } catch (err) {
      throw this.normalizeError(err, {
        suppressMembershipRedirect: options?.suppressMembershipRedirect ?? false,
      });
    }
  }

  // Helpers para uso cómodo
  get<T>(path: string, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('GET', path, { params, headers, ...options });
  }

  post<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('POST', path, { body, params, headers, ...options });
  }

  postForm<T>(path: string, body: FormData, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('POST', path, { body, params, headers, isFormData: true, ...options });
  }

  put<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('PUT', path, { body, params, headers, ...options });
  }

  patch<T>(path: string, body?: any, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('PATCH', path, { body, params, headers, ...options });
  }

  delete<T>(path: string, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
    return this.request<T>('DELETE', path, { params, headers, ...options });
  }

  async getBlob(path: string, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>): Promise<Blob> {
    const url = this.normalizeUrl(path);
    const requestHeaders = await this.buildHeaders(headers, { isFormData: true });
    const requestParams = this.buildParams(params);

    try {
      const obs = this.http.get(url, {
        headers: requestHeaders,
        params: requestParams,
        responseType: 'blob',
      });

      return await firstValueFrom(obs.pipe(timeout(20000)));
    } catch (err) {
      throw this.normalizeError(err, {
        suppressMembershipRedirect: options?.suppressMembershipRedirect ?? false,
      });
    }
  }


  // private normalizeUrl(path: string): string {
  //   // permite pasar "app/login" o "/app/login"
  //   const cleanPath = path.startsWith('/') ? path.slice(1) : path;
    
  //   return `${this.baseUrl}/${cleanPath}`;
  // }

  private normalizeUrl(path: string): string {
  // 1. Si por error el path ya trae "api/v1" o "/api/v1", lo removemos 
  // para que no se duplique con la baseUrl
  let cleanPath = path.replace(/^\/?api\/v1\//, '');

  // 2. Quitamos el slash inicial si existe
  cleanPath = cleanPath.startsWith('/') ? cleanPath.slice(1) : cleanPath;
  
  // 3. Limpiamos la baseUrl de un posible slash final
  const cleanBase = this.baseUrl.endsWith('/') ? this.baseUrl.slice(0, -1) : this.baseUrl;

  return `${cleanBase}/${cleanPath}`;
}
  private normalizeError(err: unknown, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>): ApiError {
    if (err instanceof HttpErrorResponse) {
      const msg =
        err.error?.message ||
        (typeof err.error === 'string' ? err.error : null) ||
        (err.status === 0 ? 'No se pudo conectar con el servidor. Revisa la conexión o la dirección de la API.' : `HTTP ${err.status}: ${err.statusText || 'Error'}`);

      const e = new Error(msg) as ApiError;
      e.status = err.status;
      e.code = err.error?.code;
      e.access_state = err.error?.access_state;
      e.raw = err;

      if (
        !options?.suppressMembershipRedirect &&
        e.status === 403 &&
        e.code === 'membership_expired' &&
        !err.url?.includes('/app/login')
      ) {
        window.dispatchEvent(new CustomEvent('app:membership-expired', {
          detail: {
            message: e.message,
            access_state: e.access_state,
          },
        }));
      }

      return e;
    }

    return err instanceof Error ? err as ApiError : new Error('Error inesperado en la petición.') as ApiError;
  }
  postFormData<T>(path: string, formData: FormData, params?: Record<string, any>, headers?: Record<string, string>, options?: Pick<ApiRequestOptions, 'suppressMembershipRedirect'>) {
  return this.request<T>('POST', path, { body: formData, params, headers, isFormData: true, ...options });
}

}
