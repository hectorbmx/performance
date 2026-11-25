import { TestBed } from '@angular/core/testing';
import { HttpTestingController, TestRequest, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { Router } from '@angular/router';
import { Preferences } from '@capacitor/preferences';
import { AuthService } from './auth.service';
import { ApiService } from './api.service';
import { BiometricAuthService } from './biometric-auth.service';
import { authSessionInterceptor } from './auth-session.interceptor';
import { AUTH_API_SCOPE,authStorageKey } from './auth-storage';

async function until(predicate:()=>boolean):Promise<void>{const limit=Date.now()+3000;while(!predicate()){if(Date.now()>limit)throw new Error('Timed out');await new Promise(r=>setTimeout(r,5));}}
describe('Session validation and isolation',()=>{
  let auth:AuthService;let api:ApiService;let http:HttpTestingController;let router:jasmine.SpyObj<Router>;
  const coach={ok:true,actor_type:'coach',user:{id:1},coach:{id:1},subscription:null};
  beforeEach(async()=>{
    for(const key of ['auth_token','auth_actor_type','auth_user','auth_coach','auth_client','auth_context','auth_notifications','athlete_profile','auth_commercial_access'])await Preferences.remove({key:authStorageKey(key)});
    sessionStorage.removeItem(authStorageKey('auth_token'));router=jasmine.createSpyObj('Router',['navigateByUrl']);router.navigateByUrl.and.resolveTo(true);
    TestBed.configureTestingModule({providers:[provideHttpClient(withInterceptors([authSessionInterceptor])),provideHttpClientTesting(),
      {provide:Router,useValue:router},{provide:BiometricAuthService,useValue:{clearSession:jasmine.createSpy().and.resolveTo()}}]});
    auth=TestBed.inject(AuthService);api=TestBed.inject(ApiService);http=TestBed.inject(HttpTestingController);
  });
  afterEach(async()=>{http.verify();sessionStorage.removeItem(authStorageKey('auth_token'));
    for(const key of ['auth_token','auth_actor_type','auth_user','auth_coach','auth_client','auth_context','auth_notifications','athlete_profile','auth_commercial_access'])await Preferences.remove({key:authStorageKey(key)});
    for(const key of ['auth_token','auth_token:foreign','auth_token:'+encodeURIComponent('https://other.test/api/v1')])await Preferences.remove({key});
  });
  async function seed(token='token-a',actor='coach'):Promise<void>{
    await Preferences.set({key:authStorageKey('auth_token'),value:token});await Preferences.set({key:authStorageKey('auth_actor_type'),value:actor});
    await Preferences.set({key:authStorageKey('auth_user'),value:JSON.stringify({id:1})});await Preferences.set({key:authStorageKey('auth_coach'),value:JSON.stringify({id:1})});
  }
  async function request(path:string):Promise<TestRequest>{let req:TestRequest|undefined;await until(()=>{req=http.match(AUTH_API_SCOPE+'/'+path)[0];return !!req;});return req!;}

  const legacyAthlete = {
    ok: true,
    token: 'legacy-athlete',
    context: { user_app_id: 9, client_id: 8, coach_id: 7 },
    user: { id: 9, email: 'athlete@example.test' },
    client: { id: 8, first_name: 'Ana', last_name: 'Pérez' },
    access: { access_state: 'active', can_access_service: true },
  };

  it('signs in an athlete using the legacy app/login response', async () => {
    const login = auth.login('athlete@example.test', 'password');
    (await request('app/login')).flush(legacyAthlete);
    await login;

    expect(await api.getToken()).toBe('legacy-athlete');
    expect(auth.user()?.client_id).toBe(8);
    expect(auth.client()?.coach_id).toBe(7);
    expect(auth.client()?.is_active).toBeTrue();
    expect(await auth.getDefaultRoute()).toBe('/tabs');

    const me = auth.me();
    const profileRequest = await request('app/me');
    expect(profileRequest.request.headers.get('Authorization')).toBe('Bearer legacy-athlete');
    profileRequest.flush({
      ok: true,
      user: { ...legacyAthlete.user, client_id: 8, is_active: true },
      client: { ...legacyAthlete.client, coach_id: 7, is_active: true },
      access: legacyAthlete.access,
    });
    await me;
    expect(auth.context()).toEqual(legacyAthlete.context);
  });

  it('rejects a legacy athlete login when the context belongs to another client', async () => {
    const login = auth.login('athlete@example.test', 'password');
    const rejection = expectAsync(login).toBeRejectedWith(jasmine.objectContaining({
      message: 'Respuesta de sesión atleta inválida.',
    }));
    (await request('app/login')).flush({
      ...legacyAthlete,
      context: { ...legacyAthlete.context, client_id: 42 },
    });
    await rejection;
    expect(await api.getToken()).toBeNull();
  });
  it('persists and restores a standalone account without inventing a client or coach', async()=>{
    const payload={ok:true,token:'personal',user:{id:9,email:'personal@example.test',client_id:null,is_active:true},client:null,
      context:{user_app_id:9,client_id:null,coach_id:null},athlete_profile:{first_name:'Ana',last_name:'',email:'personal@example.test',email_verified:true,privacy_version:'2026-11-15'}};
    const login=auth.login('personal@example.test','password');(await request('app/login')).flush(payload);await login;
    expect(auth.client()).toBeNull();expect(auth.context()?.coach_id).toBeNull();expect(await auth.getDefaultRoute()).toBe('/tabs/gym');
    expect(auth.getClientDisplayName()).toBe('Ana');
    auth.user.set(null);auth.context.set(null);auth.athleteProfile.set(null);await auth.hydrateFromStorage();
    expect(auth.user()?.id).toBe(9);expect(auth.client()).toBeNull();expect(auth.getClientDisplayName()).toBe('Ana');
    const me=auth.me();(await request('app/me')).flush(payload);await me;expect(auth.context()?.user_app_id).toBe(9);
  });
  it('does not try coach credentials when email verification is required',async()=>{
    const login=auth.login('personal@example.test','password').catch(e=>e);
    (await request('app/login')).flush({message:'Confirma tu correo.',code:'email_verification_required'},{status:403,statusText:'Forbidden'});
    expect((await login).code).toBe('email_verification_required');http.expectNone(AUTH_API_SCOPE+'/coach/login');expect(await api.getToken()).toBeNull();
  });
  it('keeps expired coach membership separate from athlete login and the personal GYM entry',async()=>{
    const payload={ok:true,token:'personal',user:{id:9,email:'personal@example.test',client_id:8,is_active:true},
      client:{id:8,coach_id:7,first_name:'Ana',last_name:'',is_active:false},context:{user_app_id:9,client_id:8,coach_id:7},
      access:{access_state:'expired',can_access_service:false}};
    const login=auth.login('personal@example.test','password');(await request('app/login')).flush(payload);await login;
    expect(await auth.getDefaultRoute()).toBe('/tabs/gym');expect(await auth.isLoggedIn()).toBeTrue();
    auth.coachServiceAllowed.set(null);await auth.hydrateFromStorage();expect(await auth.getDefaultRoute()).toBe('/tabs/gym');
    const me=auth.me();(await request('app/me')).flush({...payload,access:{access_state:'active',can_access_service:true}});await me;
    expect(await auth.getDefaultRoute()).toBe('/tabs');expect(auth.context()?.user_app_id).toBe(9);
  });
  it('restores a standalone identity offline and rejects a mismatched linked identity',async()=>{
    await seed('personal','client');await Preferences.remove({key:authStorageKey('auth_coach')});
    await Preferences.set({key:authStorageKey('auth_user'),value:JSON.stringify({id:9,client_id:null})});
    await Preferences.set({key:authStorageKey('auth_context'),value:JSON.stringify({user_app_id:9,client_id:null,coach_id:null})});
    spyOnProperty(navigator,'onLine','get').and.returnValue(false);
    expect(await auth.isLoggedIn()).toBeTrue();expect(await auth.getDefaultRoute()).toBe('/tabs/gym');
    auth.user.set({id:9,client_id:3});expect(await auth.isLoggedIn(true)).toBeFalse();http.expectNone(()=>true);
  });
  it('ignores unscoped and foreign-server credentials without sending them',async()=>{
    await Preferences.set({key:'auth_token',value:'legacy-prod'});await Preferences.set({key:'auth_token:'+encodeURIComponent('https://other.test/api/v1'),value:'other-server'});
    expect(await auth.isLoggedIn()).toBeFalse();http.expectNone(()=>true);
    expect((await Preferences.get({key:'auth_token'})).value).toBe('legacy-prod');
  });
  it('validates coach identity on reload and shares simultaneous validation',async()=>{
    await seed();const first=auth.isLoggedIn(),second=auth.isLoggedIn();const req=await request('coach/me');
    expect(req.request.headers.get('Authorization')).toBe('Bearer token-a');req.flush(coach);
    expect(await first).toBeTrue();expect(await second).toBeTrue();expect(auth.sessionState()).toBe('authenticated');
    expect(await auth.isLoggedIn()).toBeTrue();http.expectNone(()=>true);
  });
  it('invalidates a rejected token and sends the user to login',async()=>{
    await seed();const checking=auth.isLoggedIn();const req=await request('coach/me');req.flush({message:'Unauthenticated'},{status:401,statusText:'Unauthorized'});
    expect(await checking).toBeFalse();expect(await api.getToken()).toBeNull();expect(auth.coach()).toBeNull();expect(auth.sessionState()).toBe('anonymous');
    expect(router.navigateByUrl).toHaveBeenCalledWith('/login',{replaceUrl:true});
  });
  it('keeps the stored identity and token when connectivity fails, and can retry',async()=>{
    await seed();const checking=auth.isLoggedIn();(await request('coach/me')).error(new ProgressEvent('network'));
    expect(await checking).toBeTrue();expect(await api.getToken()).toBe('token-a');expect(auth.sessionState()).toBe('offline');expect(router.navigateByUrl).not.toHaveBeenCalled();
    const retry=auth.revalidateSession();(await request('coach/me')).flush(coach);await retry;expect(auth.sessionNotice()).toBe('');
  });
  it('restores an athlete identity immediately in airplane mode without touching its workout outbox',async()=>{
    await seed('athlete','client');
    await Preferences.set({key:authStorageKey('auth_user'),value:JSON.stringify({id:3,client_id:5})});
    await Preferences.set({key:authStorageKey('auth_client'),value:JSON.stringify({id:5,coach_id:10})});
    await Preferences.set({key:authStorageKey('auth_context'),value:JSON.stringify({user_app_id:3,client_id:5,coach_id:10})});
    spyOnProperty(navigator,'onLine','get').and.returnValue(false);
    expect(await auth.isLoggedIn()).toBeTrue();expect(auth.context()?.client_id).toBe(5);expect(await api.getToken()).toBe('athlete');
    http.expectNone(()=>true);expect(auth.sessionState()).toBe('offline');
  });
  it('does not authorize an offline session without a matching stored identity',async()=>{
    await seed('athlete','client');spyOnProperty(navigator,'onLine','get').and.returnValue(false);
    expect(await auth.isLoggedIn()).toBeFalse();expect(await api.getToken()).toBe('athlete');http.expectNone(()=>true);
  });
  it('does not erase authentication for permissions or server failures',async()=>{
    for(const status of [403,500]){await seed();const checking=auth.isLoggedIn(true);(await request('coach/me')).flush({message:'Restricted'},{status,statusText:'Error'});
      expect(await checking).toBeTrue();expect(await api.getToken()).toBe('token-a');}
    expect(router.navigateByUrl).not.toHaveBeenCalled();
  });
  it('logs out locally before an unreachable revocation finishes and leaves foreign sessions intact',async()=>{
    await seed();await Preferences.set({key:'auth_token:foreign',value:'keep'});await auth.hydrateFromStorage();await auth.logout();
    expect(await api.getToken()).toBeNull();expect(auth.actorType()).toBeNull();expect((await Preferences.get({key:'auth_token:foreign'})).value).toBe('keep');
    const req=await request('coach/logout');expect(req.request.headers.get('Authorization')).toBe('Bearer token-a');req.error(new ProgressEvent('offline'));
    expect(await auth.isLoggedIn()).toBeFalse();
  });
  it('ignores a late 401 from the previous credential after another login',async()=>{
    await seed();const old=api.get('coach/trainings').catch(()=>undefined);const req=await request('coach/trainings');await api.setToken('token-b');
    req.flush({}, {status:401,statusText:'Unauthorized'});await old;expect(await api.getToken()).toBe('token-b');expect(router.navigateByUrl).not.toHaveBeenCalled();
  });
  it('handles a 401 from an ordinary protected request centrally',async()=>{
    await seed();const result=api.get('coach/trainings').catch(()=>undefined);(await request('coach/trainings')).flush({}, {status:401,statusText:'Unauthorized'});await result;
    expect(await api.getToken()).toBeNull();expect(router.navigateByUrl).toHaveBeenCalled();
  });
  it('does not resurrect identity from a response arriving after logout',async()=>{
    await seed();const checking=auth.isLoggedIn();const me=await request('coach/me');await auth.logout();(await request('coach/logout')).flush({ok:true});me.flush(coach);
    expect(await checking).toBeFalse();expect(auth.user()).toBeNull();expect(await api.getToken()).toBeNull();
  });
  it('tries coach login only after rejected client credentials, not after a connectivity error',async()=>{
    const failed=auth.login('coach@test.invalid','password').catch(()=>undefined);
    (await request('app/login')).error(new ProgressEvent('network'));await failed;
    http.expectNone(AUTH_API_SCOPE+'/coach/login');
    const success=auth.login('coach@test.invalid','password');
    (await request('app/login')).flush({message:'Credenciales invalidas'},{status:422,statusText:'Unprocessable Entity'});
    (await request('coach/login')).flush({...coach,token:'new-coach'});await success;
    expect(await api.getToken()).toBe('new-coach');expect(auth.actorType()).toBe('coach');expect(await auth.isLoggedIn()).toBeTrue();
  });
  it('does not transmit a biometric token saved for another API',async()=>{
    await expectAsync(auth.resumeWithBiometricSession({token:'prod',actorType:'coach',savedAt:'now',apiUrl:'https://other.test/api/v1'})).toBeRejected();
    expect(await api.getToken()).toBeNull();http.expectNone(()=>true);
  });
});
