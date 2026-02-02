// import { Injectable } from '@angular/core';
// import { ApiService } from './api.service';
// import { Preferences } from '@capacitor/preferences';

// export interface LoginContext {
//   user_app_id: number;
//   client_id: number;
//   coach_id: number;
// }

// export interface LoginUser {
//   id: number;
//   email: string;
// }

// export interface LoginClient {
//   id: number;
//   first_name: string;
//   last_name: string;
// }

// export interface LoginResponse {
//   ok: boolean;
//   token: string;
//   context: LoginContext;
//   user: LoginUser;
//   client: LoginClient;
// }
// export interface MeUser {
//   id: number;
//   email: string;
//   client_id: number;
//   is_active: boolean;
// }

// export interface MeClient {
//   id: number;
//   first_name: string;
//   last_name: string;
//   coach_id: number;
//   is_active: boolean;
// }

// export interface MeResponse {
//   ok: boolean;
//   user: MeUser;
//   client: MeClient;
// }

// @Injectable({ providedIn: 'root' })
// export class AuthService {
//   private readonly CONTEXT_KEY = 'auth_context';
//   private readonly USER_KEY = 'auth_user';
//   private readonly CLIENT_KEY = 'auth_client';

//   constructor(private api: ApiService) {}

//   // ==========================
//   // AUTH
//   // ==========================
//   async login(email: string, password: string): Promise<LoginResponse> {
//     const res = await this.api.post<LoginResponse>('app/login', { email, password });

//     if (!res?.ok || !res?.token) {
//       throw new Error('Respuesta inválida del servidor (token no recibido).');
//     }

//     // Token para Authorization: Bearer ...
//     await this.api.setToken(res.token);

//     // Guardar contexto y datos básicos para uso offline/rápido
//     await Preferences.set({ key: this.CONTEXT_KEY, value: JSON.stringify(res.context || {}) });
//     await Preferences.set({ key: this.USER_KEY, value: JSON.stringify(res.user || {}) });
//     await Preferences.set({ key: this.CLIENT_KEY, value: JSON.stringify(res.client || {}) });

//     return res;
//   }

// async me(): Promise<MeResponse> {
//   const res = await this.api.get<MeResponse>('app/me');

//   // refrescar cache local
//   await Preferences.set({ key: this.USER_KEY, value: JSON.stringify(res.user || {}) });
//   await Preferences.set({ key: this.CLIENT_KEY, value: JSON.stringify(res.client || {}) });

//   // contexto derivado (por si lo quieres uniforme con login)
//   const context = {
//     user_app_id: res.user?.id,
//     client_id: res.user?.client_id,
//     coach_id: res.client?.coach_id,
//   };
//   await Preferences.set({ key: this.CONTEXT_KEY, value: JSON.stringify(context) });

//   return res;
// }


//   async logout(): Promise<void> {
//     try {
//       await this.api.post<any>('app/logout', {});
//     } finally {
//       await this.api.clearToken();
//       await Preferences.remove({ key: this.CONTEXT_KEY });
//       await Preferences.remove({ key: this.USER_KEY });
//       await Preferences.remove({ key: this.CLIENT_KEY });
//     }
//   }

//   async isLoggedIn(): Promise<boolean> {
//     const token = await this.api.getToken();
//     return !!token;
//   }

//   // ==========================
//   // CONTEXTO LOCAL (para app)
//   // ==========================
//   async getContext(): Promise<LoginContext | null> {
//     const { value } = await Preferences.get({ key: this.CONTEXT_KEY });
//     if (!value) return null;
//     try {
//       return JSON.parse(value) as LoginContext;
//     } catch {
//       return null;
//     }
//   }

//   async getStoredUser(): Promise<LoginUser | null> {
//     const { value } = await Preferences.get({ key: this.USER_KEY });
//     if (!value) return null;
//     try {
//       return JSON.parse(value) as LoginUser;
//     } catch {
//       return null;
//     }
//   }

//   async getStoredClient(): Promise<LoginClient | null> {
//     const { value } = await Preferences.get({ key: this.CLIENT_KEY });
//     if (!value) return null;
//     try {
//       return JSON.parse(value) as LoginClient;
//     } catch {
//       return null;
//     }
//   }
// }
import { Injectable, signal } from '@angular/core';
import { ApiService } from './api.service';
import { Preferences } from '@capacitor/preferences';
import { HttpErrorResponse } from '@angular/common/http';


export interface AuthContext {
  user_app_id: number;
  client_id: number;
  coach_id: number;
}

export interface AppUserDTO {
  id: number;         // user_apps.id
  email: string;
  client_id: number;
  is_active: boolean;
}

export interface ClientDTO {
  id: number;
  first_name: string;
  last_name: string;
  coach_id: number;
  is_active: boolean;
}

export interface LoginResponse {
  ok: boolean;
  token: string;
  context: AuthContext;
  user: AppUserDTO;
  client: ClientDTO;
}

export interface MeResponse {
  ok: boolean;
  user: AppUserDTO;
  client: ClientDTO;
}
export interface AppNotificationDTO {
  id: string;
  type: 'info' | 'warning' | 'danger';
  title: string;
  message: string;
  action?: string;
  meta?: any;
}

export interface MeResponse {
  ok: boolean;
  user: AppUserDTO;
  client: ClientDTO;
  membership?: any | null;
  notifications?: AppNotificationDTO[];
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly CONTEXT_KEY = 'auth_context';
  private readonly USER_KEY = 'auth_user';
  private readonly CLIENT_KEY = 'auth_client';
  private readonly NOTIFICATIONS_KEY = 'auth_notifications';

  // Estado en memoria (para UI)
  user = signal<AppUserDTO | null>(null);
  client = signal<ClientDTO | null>(null);
  context = signal<AuthContext | null>(null);
  notifications = signal<AppNotificationDTO[]>([]);

  constructor(private api: ApiService) {}

  // Carga rápida desde Preferences (útil al iniciar app / entrar a Home)
  async hydrateFromStorage(): Promise<void> {
    const [ctx, usr, cli] = await Promise.all([
      Preferences.get({ key: this.CONTEXT_KEY }),
      Preferences.get({ key: this.USER_KEY }),
      Preferences.get({ key: this.CLIENT_KEY }),
    ]);

    this.context.set(this.safeParse<AuthContext>(ctx.value));
    this.user.set(this.safeParse<AppUserDTO>(usr.value));
    this.client.set(this.safeParse<ClientDTO>(cli.value));
  }

  private safeParse<T>(value: string | null): T | null {
    if (!value) return null;
    try { return JSON.parse(value) as T; } catch { return null; }
  }


async login(email: string, password: string): Promise<LoginResponse> {
  try {
    const res = await this.api.post<LoginResponse>('app/login', { email, password });

    if (!res?.ok || !res?.token) {
      throw new Error('Respuesta inválida del servidor (token no recibido).');
    }

    await this.api.setToken(res.token);
    await this.persistSession(res.context, res.user, res.client);

    this.context.set(res.context ?? null);
    this.user.set(res.user ?? null);
    this.client.set(res.client ?? null);

    return res;

  } catch (err: any) {
    // Tu ApiService está lanzando Error("Cuenta pendiente de activación.")
    const message = err?.message || 'No se pudo iniciar sesión.';

    if (message === 'Cuenta pendiente de activación.') {
      throw { needsActivation: true, message };
    }

    throw { message };
  }
}


  async me(): Promise<MeResponse> {
    const res = await this.api.get<MeResponse>('app/me');

    if (!res?.ok) {
      throw new Error('Respuesta inválida del servidor (me).');
    }

    // Construir contexto consistente
    const ctx: AuthContext = {
      user_app_id: res.user.id,      // user_apps.id
      client_id: res.user.client_id, // clients.id
      coach_id: res.client.coach_id, // users.id (coach)
    };

    // await this.persistSession(ctx, res.user, res.client);
    await this.persistSession(ctx, res.user, res.client, res.notifications ?? []);


    // memoria (UI)
    this.context.set(ctx);
    this.user.set(res.user);
    this.client.set(res.client);
    this.notifications.set(res.notifications ?? []);

    return res;
  }

  private async persistSession(
    ctx: AuthContext, 
    user: AppUserDTO, 
    client: ClientDTO,
    notifications: AppNotificationDTO[] = []
  ) {
    await Promise.all([
      Preferences.set({ key: this.CONTEXT_KEY, value: JSON.stringify(ctx || {}) }),
      Preferences.set({ key: this.USER_KEY, value: JSON.stringify(user || {}) }),
      Preferences.set({ key: this.CLIENT_KEY, value: JSON.stringify(client || {}) }),
      Preferences.set({ key: this.NOTIFICATIONS_KEY, value: JSON.stringify(notifications || []),
      }),
    ]);
  }

  async logout(): Promise<void> {
    try {
      await this.api.post<any>('app/logout', {});
    } finally {
      await this.api.clearToken();
      await Promise.all([
        Preferences.remove({ key: this.CONTEXT_KEY }),
        Preferences.remove({ key: this.USER_KEY }),
        Preferences.remove({ key: this.CLIENT_KEY }),
      ]);

      this.context.set(null);
      this.user.set(null);
      this.client.set(null);
    }
  }

  async isLoggedIn(): Promise<boolean> {
    const token = await this.api.getToken();
    return !!token;
  }

  // Helpers para UI
  getClientDisplayName(): string {
    const c = this.client();
    if (!c) return '';
    return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim();
  }
}
