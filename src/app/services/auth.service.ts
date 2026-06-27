import { Injectable, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { Capacitor } from '@capacitor/core';
import { ApiService } from './api.service';

export type ActorType = 'client' | 'coach';

export interface AuthContext {
  user_app_id: number;
  client_id: number;
  coach_id: number;
}

export interface AppUserDTO {
  id: number;
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
  avatar_url: string;
}

export interface LoginResponse {
  ok: boolean;
  token: string;
  actor_type?: ActorType;
  redirect_to?: 'client' | 'coach';
  context?: AuthContext;
  user: any;
  client?: ClientDTO;
  coach?: any;
  subscription?: any;
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
  private readonly COACH_KEY = 'auth_coach';
  private readonly ACTOR_TYPE_KEY = 'auth_actor_type';
  private readonly NOTIFICATIONS_KEY = 'auth_notifications';

  user = signal<any | null>(null);
  client = signal<ClientDTO | null>(null);
  coach = signal<any | null>(null);
  context = signal<AuthContext | null>(null);
  actorType = signal<ActorType | null>(null);
  notifications = signal<AppNotificationDTO[]>([]);

  constructor(private api: ApiService) {}

  async hydrateFromStorage(): Promise<void> {
    const [ctx, usr, cli, coach, actor, notifications] = await Promise.all([
      Preferences.get({ key: this.CONTEXT_KEY }),
      Preferences.get({ key: this.USER_KEY }),
      Preferences.get({ key: this.CLIENT_KEY }),
      Preferences.get({ key: this.COACH_KEY }),
      Preferences.get({ key: this.ACTOR_TYPE_KEY }),
      Preferences.get({ key: this.NOTIFICATIONS_KEY }),
    ]);

    this.context.set(this.safeParse<AuthContext>(ctx.value));
    this.user.set(this.safeParse<any>(usr.value));
    this.client.set(this.safeParse<ClientDTO>(cli.value));
    this.coach.set(this.safeParse<any>(coach.value));
    this.actorType.set(actor.value === 'coach' ? 'coach' : actor.value === 'client' ? 'client' : null);
    this.notifications.set(this.safeParse<AppNotificationDTO[]>(notifications.value) ?? []);
  }

  async login(email: string, password: string): Promise<LoginResponse> {
    try {
      const res = await this.loginClientOrCoach(email, password);

      if (!res?.ok || !res?.token) {
        throw new Error('Respuesta invalida del servidor (token no recibido).');
      }

      await this.api.setToken(res.token);

      if ((res.actor_type ?? (res.coach ? 'coach' : 'client')) === 'coach') {
        await this.persistCoachSession(res.user, res.coach, res.subscription);
      } else {
        await this.persistClientSession(res.context as AuthContext, res.user, res.client as ClientDTO);
        await this.registerPendingPushToken();
      }

      return res;
    } catch (err: any) {
      const message = err?.message || 'No se pudo iniciar sesion.';

      if (message === 'Cuenta pendiente de activación.' || message === 'Cuenta pendiente de activaciÃ³n.') {
        throw { needsActivation: true, message };
      }

      throw { message };
    }
  }

  private async loginClientOrCoach(email: string, password: string): Promise<LoginResponse> {
    try {
      const res = await this.api.post<LoginResponse>('app/login', { email, password });
      return { ...res, actor_type: 'client', redirect_to: 'client' };
    } catch (clientErr: any) {
      const message = clientErr?.message || '';

      if (message === 'Cuenta pendiente de activación.' || message === 'Cuenta pendiente de activaciÃ³n.') {
        throw clientErr;
      }

      const res = await this.api.post<LoginResponse>('coach/login', { email, password });
      return { ...res, actor_type: 'coach', redirect_to: 'coach' };
    }
  }

  async me(): Promise<MeResponse> {
    if ((await this.getActorType()) === 'coach') {
      const res = await this.api.get<any>('coach/me');
      await this.persistCoachSession(res.user, res.coach, res.subscription);
      return res;
    }

    const res = await this.api.get<MeResponse>('app/me');

    if (!res?.ok) {
      throw new Error('Respuesta invalida del servidor (me).');
    }

    const ctx: AuthContext = {
      user_app_id: res.user.id,
      client_id: res.user.client_id,
      coach_id: res.client.coach_id,
    };

    await this.persistClientSession(ctx, res.user, res.client, res.notifications ?? []);

    return res;
  }

  async logout(): Promise<void> {
    const actorType = await this.getActorType();

    try {
      await this.api.post<any>(actorType === 'coach' ? 'coach/logout' : 'app/logout', {});
    } finally {
      await this.api.clearToken();
      await Promise.all([
        Preferences.remove({ key: this.ACTOR_TYPE_KEY }),
        Preferences.remove({ key: this.CONTEXT_KEY }),
        Preferences.remove({ key: this.USER_KEY }),
        Preferences.remove({ key: this.CLIENT_KEY }),
        Preferences.remove({ key: this.COACH_KEY }),
        Preferences.remove({ key: this.NOTIFICATIONS_KEY }),
      ]);

      this.actorType.set(null);
      this.context.set(null);
      this.user.set(null);
      this.client.set(null);
      this.coach.set(null);
      this.notifications.set([]);
    }
  }

  async isLoggedIn(): Promise<boolean> {
    const token = await this.api.getToken();
    return !!token;
  }

  async getActorType(): Promise<ActorType | null> {
    const current = this.actorType();
    if (current) return current;

    const { value } = await Preferences.get({ key: this.ACTOR_TYPE_KEY });
    return value === 'coach' ? 'coach' : value === 'client' ? 'client' : null;
  }

  async getDefaultRoute(): Promise<string> {
    return (await this.getActorType()) === 'coach' ? '/coach/athletes' : '/tabs';
  }

  getClientDisplayName(): string {
    const c = this.client();
    if (!c) return '';
    return `${c.first_name ?? ''} ${c.last_name ?? ''}`.trim();
  }

  getClientAvatarUrl(): string {
    const c = this.client();
    if (!c) return '';
    return `${c.avatar_url ?? ''}`.trim();
  }

  private async persistClientSession(
    ctx: AuthContext,
    user: AppUserDTO,
    client: ClientDTO,
    notifications: AppNotificationDTO[] = [],
  ): Promise<void> {
    await Promise.all([
      Preferences.set({ key: this.ACTOR_TYPE_KEY, value: 'client' }),
      Preferences.set({ key: this.CONTEXT_KEY, value: JSON.stringify(ctx || {}) }),
      Preferences.set({ key: this.USER_KEY, value: JSON.stringify(user || {}) }),
      Preferences.set({ key: this.CLIENT_KEY, value: JSON.stringify(client || {}) }),
      Preferences.set({ key: this.NOTIFICATIONS_KEY, value: JSON.stringify(notifications || []) }),
      Preferences.remove({ key: this.COACH_KEY }),
    ]);

    this.actorType.set('client');
    this.context.set(ctx ?? null);
    this.user.set(user ?? null);
    this.client.set(client ?? null);
    this.coach.set(null);
    this.notifications.set(notifications ?? []);
  }

  private async persistCoachSession(user: any, coach: any, subscription?: any): Promise<void> {
    const coachWithSubscription = { ...(coach || {}), subscription: subscription ?? null };

    await Promise.all([
      Preferences.set({ key: this.ACTOR_TYPE_KEY, value: 'coach' }),
      Preferences.set({ key: this.USER_KEY, value: JSON.stringify(user || {}) }),
      Preferences.set({ key: this.COACH_KEY, value: JSON.stringify(coachWithSubscription) }),
      Preferences.remove({ key: this.CONTEXT_KEY }),
      Preferences.remove({ key: this.CLIENT_KEY }),
      Preferences.remove({ key: this.NOTIFICATIONS_KEY }),
    ]);

    this.actorType.set('coach');
    this.user.set(user ?? null);
    this.coach.set(coachWithSubscription);
    this.client.set(null);
    this.context.set(null);
    this.notifications.set([]);
  }

  private safeParse<T>(value: string | null): T | null {
    if (!value) return null;
    try {
      return JSON.parse(value) as T;
    } catch {
      return null;
    }
  }

  private async registerPendingPushToken(): Promise<void> {
    const { value } = await Preferences.get({ key: 'pending_push_token' });
    if (!value) return;

    try {
      await this.api.post('app/register-device', {
        token: value,
        platform: Capacitor.getPlatform(),
      });
      await Preferences.remove({ key: 'pending_push_token' });
    } catch (err) {
      console.warn('No se pudo registrar el token push pendiente', err);
    }
  }
}
