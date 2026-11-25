import { Injectable, signal } from '@angular/core';
import { Router } from '@angular/router';
import { AUTH_API_SCOPE, authStorageKey } from './auth-storage';
import { Preferences } from '@capacitor/preferences';
import { ApiService } from './api.service';
import { BiometricAuthService, type StoredBiometricSession } from './biometric-auth.service';

export type ActorType = 'client' | 'coach';

export interface AuthContext {
  user_app_id: number;
  client_id: number | null;
  coach_id: number | null;
}

export interface AppUserDTO {
  id: number;
  email: string;
  client_id: number | null;
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
  client?: ClientDTO | null;
  athlete_profile?: AthleteProfileDTO | null;
  coach?: any;
  subscription?: any;
  access?: {
    access_state: string;
    can_access_service: boolean;
    message?: string | null;
  };
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
  client: ClientDTO | null;
  athlete_profile?: AthleteProfileDTO | null;
  membership?: any | null;
  access?: {
    access_state: string;
    can_access_service: boolean;
    message?: string | null;
  };
  notifications?: AppNotificationDTO[];
}

export interface AthleteProfileDTO {
  first_name: string; last_name: string; email: string; email_verified: boolean; privacy_version: string | null;
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly CONTEXT_KEY = authStorageKey('auth_context');
  private readonly USER_KEY = authStorageKey('auth_user');
  private readonly CLIENT_KEY = authStorageKey('auth_client');
  private readonly COACH_KEY = authStorageKey('auth_coach');
  private readonly ACTOR_TYPE_KEY = authStorageKey('auth_actor_type');
  private readonly NOTIFICATIONS_KEY = authStorageKey('auth_notifications');
  private readonly PROFILE_KEY = authStorageKey('athlete_profile');
  private readonly COMMERCIAL_ACCESS_KEY = authStorageKey('auth_commercial_access');

  readonly sessionState = signal<'unknown'|'checking'|'authenticated'|'offline'|'anonymous'>('unknown');
  readonly sessionNotice = signal('');
  private epoch = 0;
  private verifiedToken: string | null = null;
  private verifiedAt = 0;
  private validation: {token:string;epoch:number;promise:Promise<boolean>} | null = null;
  private invalidating: Promise<void> | null = null;
  pendingWorkoutChanges = signal(0);
  sessionChanging = signal(false);
  user = signal<any | null>(null);
  client = signal<ClientDTO | null>(null);
  athleteProfile = signal<AthleteProfileDTO | null>(null);
  coachServiceAllowed = signal<boolean | null>(null);
  coach = signal<any | null>(null);
  context = signal<AuthContext | null>(null);
  actorType = signal<ActorType | null>(null);
  notifications = signal<AppNotificationDTO[]>([]);

  constructor(
    private api: ApiService,
    private biometricAuth: BiometricAuthService,
    private router: Router,
  ) {}

  async hydrateFromStorage(): Promise<void> {
    const [ctx, usr, cli, coach, actor, notifications, profile, commercialAccess] = await Promise.all([
      Preferences.get({ key: this.CONTEXT_KEY }),
      Preferences.get({ key: this.USER_KEY }),
      Preferences.get({ key: this.CLIENT_KEY }),
      Preferences.get({ key: this.COACH_KEY }),
      Preferences.get({ key: this.ACTOR_TYPE_KEY }),
      Preferences.get({ key: this.NOTIFICATIONS_KEY }),
      Preferences.get({ key: this.PROFILE_KEY }),
      Preferences.get({ key: this.COMMERCIAL_ACCESS_KEY }),
    ]);

    this.context.set(this.safeParse<AuthContext>(ctx.value));
    this.user.set(this.safeParse<any>(usr.value));
    this.client.set(this.safeParse<ClientDTO>(cli.value));
    this.coach.set(this.safeParse<any>(coach.value));
    this.actorType.set(actor.value === 'coach' ? 'coach' : actor.value === 'client' ? 'client' : null);
    this.notifications.set(this.safeParse<AppNotificationDTO[]>(notifications.value) ?? []);
    this.athleteProfile.set(this.safeParse<AthleteProfileDTO>(profile.value));
    this.coachServiceAllowed.set(this.safeParse<boolean>(commercialAccess.value));
  }

  async login(email: string, password: string, rememberSession: boolean = true): Promise<LoginResponse> {
    if(this.invalidating) await this.invalidating;
    this.epoch++;
    this.sessionChanging.set(true);
    try {
      const res = await this.loginClientOrCoach(email, password);

      if (!res?.ok || !res?.token) {
        throw new Error('Respuesta invalida del servidor (token no recibido).');
      }

      if (res.actor_type === 'client') {
        // Older app/login responses put the ownership fields only in context.
        const context = res.context;
        if (context && res.user && res.client &&
            res.user.client_id === undefined && res.client.coach_id === undefined &&
            typeof context.coach_id === 'number' && context.coach_id > 0) {
          res.user = { ...res.user, client_id: context.client_id, is_active: res.user.is_active ?? true };
          res.client = { ...res.client, coach_id: context.coach_id, is_active: res.client.is_active ?? true };
        }
      }

      if (res.actor_type === 'client' && (!res.user?.id || !res.context ||
          res.context.user_app_id !== res.user.id || (res.user.client_id ?? null) !== (res.client?.id ?? null) ||
          res.context.client_id !== (res.client?.id ?? null) || res.context.coach_id !== (res.client?.coach_id ?? null))) {
        throw new Error('Respuesta de sesión atleta inválida.');
      }

      await this.api.setToken(res.token, rememberSession);

      if ((res.actor_type ?? (res.coach ? 'coach' : 'client')) === 'coach') {
        await this.persistCoachSession(res.user, res.coach, res.subscription);
      } else {
        await this.persistClientSession(res.context as AuthContext, res.user, res.client ?? null, [], res.athlete_profile ?? null, res.access?.can_access_service ?? null);
        window.dispatchEvent(new Event('app:client-login'));
      }

      this.verifiedToken = res.token; this.verifiedAt = Date.now();
      this.sessionState.set('authenticated'); this.sessionNotice.set('');
      return res;
    } catch (err: any) {
      const message = err?.message || 'No se pudo iniciar sesion.';

      if (message === 'Cuenta pendiente de activación.' || message === 'Cuenta pendiente de activaciÃ³n.') {
        throw { needsActivation: true, message };
      }

      throw {
        message,
        status: err?.status,
        code: err?.code,
        access_state: err?.access_state,
      };
    } finally {
      this.sessionChanging.set(false);
    }
  }

  private async loginClientOrCoach(email: string, password: string): Promise<LoginResponse> {
    try {
      const res = await this.api.post<LoginResponse>('app/login', { email, password });
      return { ...res, actor_type: 'client', redirect_to: 'client' };
    } catch (clientErr: any) {
      const message = clientErr?.message || '';
      const status = clientErr?.status;

      if (message === 'Cuenta pendiente de activación.' || message === 'Cuenta pendiente de activaciÃ³n.') {
        throw clientErr;
      }

      if (status !== 422) {
        throw clientErr;
      }

      const res = await this.api.post<LoginResponse>('coach/login', { email, password });
      return { ...res, actor_type: 'coach', redirect_to: 'coach' };
    }
  }

  async me(): Promise<MeResponse> {
    const epoch = this.epoch; const token = await this.api.getToken();
    const stillCurrent = async () => {
      if (epoch !== this.epoch || !token || token !== await this.api.getToken()) throw new Error('La sesión cambió durante la validación.');
    };
    if ((await this.getActorType()) === 'coach') {
      const res = await this.api.get<any>('coach/me');
      await stillCurrent();
      if(!res?.ok || res.actor_type !== 'coach' || !res.user?.id || !res.coach?.id) throw new Error('Respuesta de sesión coach inválida.');
      await this.persistCoachSession(res.user, res.coach, res.subscription);
      await stillCurrent();
      this.verifiedToken=token;this.verifiedAt=Date.now();this.sessionState.set('authenticated');this.sessionNotice.set('');
      return res;
    }

    const res = await this.api.get<MeResponse>('app/me');

    if (!res?.ok) {
      throw new Error('Respuesta invalida del servidor (me).');
    }

    await stillCurrent();
    if(!res.user?.id || (res.user.client_id ?? null) !== (res.client?.id ?? null)) throw new Error('Respuesta de sesión atleta inválida.');
    const ctx: AuthContext = {
      user_app_id: res.user.id,
      client_id: res.client?.id ?? null,
      coach_id: res.client?.coach_id ?? null,
    };

    await this.persistClientSession(ctx, res.user, res.client, res.notifications ?? [], res.athlete_profile ?? null, res.access?.can_access_service ?? null);
    await stillCurrent();
    this.verifiedToken=token;this.verifiedAt=Date.now();this.sessionState.set('authenticated');this.sessionNotice.set('');
    return res;
  }

  async resumeWithBiometricSession(session: StoredBiometricSession): Promise<void> {
    if(session.apiUrl !== AUTH_API_SCOPE) throw new Error('Inicia sesión para este servidor antes de usar biometría.');
    this.epoch++;
    this.sessionChanging.set(true);
    try {
      await this.api.setToken(session.token, true);
      await Preferences.set({ key: this.ACTOR_TYPE_KEY, value: session.actorType });
      this.actorType.set(session.actorType);
      try {
        await this.me();
      } catch (err) {
        await this.clearLocalSession();
        throw err;
      }
    } finally { this.sessionChanging.set(false); }
  }

  async logout(): Promise<void> {
    if (this.pendingWorkoutChanges() > 0) window.alert('Tienes registros pendientes de sincronizar. Se conservarán en este dispositivo y podrás enviarlos al volver con la misma cuenta.');
    this.epoch++; this.sessionChanging.set(true);
    try {
      const token = await this.api.getToken(); const actor = await this.getActorType();
      await this.clearLocalSession();
      // Remote revocation is best effort; going offline must never trap the user.
      if(token) void this.api.revokeToken(actor === 'coach' ? 'coach/logout' : 'app/logout', token).catch(() => undefined);
      await this.biometricAuth.clearSession().catch(() => undefined);
    } finally { this.sessionChanging.set(false); }
  }

  async handleUnauthorized(expectedToken: string): Promise<void> {
    if(this.invalidating) return this.invalidating;
    // Ignore old requests returning after logout or a different login.
    if(!expectedToken) return;
    this.invalidating = (async () => {
      if(expectedToken !== await this.api.getToken()) return;
      this.epoch++; this.sessionChanging.set(true);
      try {
        await this.clearLocalSession();
        await this.biometricAuth.clearSession().catch(() => undefined);
        this.sessionNotice.set('Tu sesión venció o ya no es válida. Inicia sesión de nuevo.');
        await this.router.navigateByUrl('/login', { replaceUrl: true });
      } finally { this.sessionChanging.set(false); }
    })();
    try { await this.invalidating; } finally { this.invalidating = null; }
  }

  async isLoggedIn(force = false): Promise<boolean> {
    const token = await this.api.getToken();
    if(!token) { this.sessionState.set('anonymous'); return false; }
    if(this.sessionChanging()) return false;
    if(!force && this.verifiedToken === token && Date.now()-this.verifiedAt < 30000) return true;
    if(this.validation?.token === token && this.validation.epoch === this.epoch) return this.validation.promise;
    const epoch=this.epoch;
    const promise=this.validateSession(token,epoch);
    this.validation={token,epoch,promise};
    try {return await promise;} finally {if(this.validation?.promise===promise)this.validation=null;}
  }

  private async validateSession(token:string,epoch:number):Promise<boolean> {
    this.sessionState.set('checking');
    if(!this.actorType()) await this.hydrateFromStorage();
    const actor=await this.getActorType();
    if(!actor) {await this.handleUnauthorized(token);return false;}
    const cached=()=>actor==='coach' ? !!this.user()?.id && this.user()?.id===this.coach()?.id
      : !!this.user()?.id && (this.user()?.client_id ?? null)===(this.client()?.id ?? null) && this.context()?.user_app_id===this.user()?.id
        && (this.context()?.client_id ?? null)===(this.client()?.id ?? null) && (this.context()?.coach_id ?? null)===(this.client()?.coach_id ?? null);
    const unverified=()=>{
      this.sessionState.set('offline');
      this.sessionNotice.set('No se pudo verificar la sesión con el servidor. Puedes reintentar o cerrar sesión; tus registros locales se conservan.');
      return cached();
    };
    if(!navigator.onLine) return unverified();
    try {
      await this.me();
      if(epoch!==this.epoch || token!==await this.api.getToken()) return false;
      this.verifiedToken=token;this.verifiedAt=Date.now();this.sessionNotice.set('');this.sessionState.set('authenticated');return true;
    } catch(err:any) {
      if(epoch!==this.epoch || token!==await this.api.getToken()) return false;
      if(err?.status===401) {await this.handleUnauthorized(token);return false;}
      // A network/server/permission error does not prove that the credential expired.
      return unverified();
    }
  }

  async revalidateSession():Promise<void> {
    if(this.sessionChanging() || !await this.api.getToken()) return;
    await this.isLoggedIn(true);
  }

  async getActorType(): Promise<ActorType | null> {
    const current = this.actorType();
    if (current) return current;

    const { value } = await Preferences.get({ key: this.ACTOR_TYPE_KEY });
    return value === 'coach' ? 'coach' : value === 'client' ? 'client' : null;
  }

  async getDefaultRoute(): Promise<string> {
    return (await this.getActorType()) === 'coach' ? '/coach/athletes'
      : this.client()?.id ? this.coachServiceAllowed() === false ? '/tabs/gym' : '/tabs' : '/tabs/gym';
  }

  getClientDisplayName(): string {
    const profile = this.athleteProfile();
    if (profile) return `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim();
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
    client: ClientDTO | null,
    notifications: AppNotificationDTO[] = [],
    profile: AthleteProfileDTO | null = null,
    commercialAccess: boolean | null = null,
  ): Promise<void> {
    await Promise.all([
      Preferences.set({ key: this.ACTOR_TYPE_KEY, value: 'client' }),
      Preferences.set({ key: this.CONTEXT_KEY, value: JSON.stringify(ctx || {}) }),
      Preferences.set({ key: this.USER_KEY, value: JSON.stringify(user || {}) }),
      Preferences.set({ key: this.CLIENT_KEY, value: JSON.stringify(client) }),
      Preferences.set({ key: this.PROFILE_KEY, value: JSON.stringify(profile) }),
      Preferences.set({ key: this.COMMERCIAL_ACCESS_KEY, value: JSON.stringify(commercialAccess) }),
      Preferences.set({ key: this.NOTIFICATIONS_KEY, value: JSON.stringify(notifications || []) }),
      Preferences.remove({ key: this.COACH_KEY }),
    ]);

    this.actorType.set('client');
    this.context.set(ctx ?? null);
    this.user.set(user ?? null);
    this.client.set(client ?? null);
    this.athleteProfile.set(profile);
    this.coachServiceAllowed.set(commercialAccess);
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
      Preferences.remove({ key: this.PROFILE_KEY }),
      Preferences.remove({ key: this.COMMERCIAL_ACCESS_KEY }),
    ]);

    this.actorType.set('coach');
    this.user.set(user ?? null);
    this.coach.set(coachWithSubscription);
    this.client.set(null);
    this.athleteProfile.set(null);
    this.coachServiceAllowed.set(null);
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

  private async clearLocalSession(): Promise<void> {
    this.verifiedToken=null;this.verifiedAt=0;this.sessionState.set('anonymous');this.sessionNotice.set('');
    this.actorType.set(null);this.context.set(null);this.user.set(null);this.client.set(null);this.coach.set(null);this.notifications.set([]);
    await this.api.clearToken();
    await Promise.all([
      Preferences.remove({ key: this.ACTOR_TYPE_KEY }),
      Preferences.remove({ key: this.CONTEXT_KEY }),
      Preferences.remove({ key: this.USER_KEY }),
      Preferences.remove({ key: this.CLIENT_KEY }),
      Preferences.remove({ key: this.COACH_KEY }),
      Preferences.remove({ key: this.NOTIFICATIONS_KEY }),
      Preferences.remove({ key: this.PROFILE_KEY }),
      Preferences.remove({ key: this.COMMERCIAL_ACCESS_KEY }),
    ]);

    this.actorType.set(null);
    this.context.set(null);
    this.user.set(null);
    this.client.set(null);
    this.athleteProfile.set(null);
    this.coachServiceAllowed.set(null);
    this.coach.set(null);
    this.notifications.set([]);
  }
}
