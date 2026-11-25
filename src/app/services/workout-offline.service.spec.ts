import { TestBed } from '@angular/core/testing';
import { signal } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, TestRequest, provideHttpClientTesting } from '@angular/common/http/testing';
import { AuthService } from './auth.service';
import { ApiService } from './api.service';
import { WorkoutOfflineService } from './workout-offline.service';
import { addExecution, Execution, SetValues, WorkoutOfflineStore } from './workout-offline.store';

const values: SetValues = { status: 'completed', type: 'work', load_mode: 'total_barbell', actual_reps: 10,
  actual_load: '75.00', load_unit: 'kg', actual_rir: null, notes: null, performed_at: '2026-11-13T16:00:00Z' };
function execution(): Execution {
  return { uuid: 'execution', client_execution_id: 'start', status: 'in_progress', revision: 1,
    started_at: values.performed_at, sets: [], prescription: { content: { title: 'Piernas', sections: [{
      uuid: 'section', name: 'Fuerza', description: null, exercises: [{ uuid: 'exercise', name: 'Squat', notes: null,
        sets: [{ uuid: 'set', set_number: 1, prescribed: { reps_min: 10, reps_max: 10, percentage: null, rest_seconds: null } }] }],
    }] } } };
}
async function until(predicate: () => boolean): Promise<void> {
  const limit = Date.now() + 3000;
  while (!predicate()) { if (Date.now() > limit) throw new Error('Timed out waiting for local transaction'); await new Promise(resolve => setTimeout(resolve, 5)); }
}

describe('WorkoutOfflineService synchronization', () => {
  let service: WorkoutOfflineService;
  let store: WorkoutOfflineStore;
  let http: HttpTestingController;
  let owner: string;
  let name: string;
  let auth: { context: ReturnType<typeof signal<any>>; actorType: ReturnType<typeof signal<any>>; sessionChanging: ReturnType<typeof signal<boolean>>; pendingWorkoutChanges: ReturnType<typeof signal<number>> };
  let api: jasmine.SpyObj<ApiService>;

  beforeEach(async () => {
    auth = { context: signal({ user_app_id: 1, client_id: 5, coach_id: 10 }), actorType: signal('client'), sessionChanging: signal(false), pendingWorkoutChanges: signal(0) };
    api = jasmine.createSpyObj<ApiService>('ApiService', ['getToken']); api.getToken.and.resolveTo('token-first');
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting(), { provide: AuthService, useValue: auth }, { provide: ApiService, useValue: api }] });
    service = TestBed.inject(WorkoutOfflineService);
    name = 'worker-test-' + crypto.randomUUID(); store = new WorkoutOfflineStore(name);
    (service as any).store = store;
    http = TestBed.inject(HttpTestingController);
    TestBed.flushEffects(); await until(() => !!service.state());
    owner = service.owner()!;
    await store.change(owner, state => addExecution(state, execution(), 5)); await service.refresh();
  });
  afterEach(async () => {
    auth.sessionChanging.set(true); TestBed.flushEffects(); await until(() => !service.syncing());
    http.verify({ ignoreCancelled: true }); TestBed.resetTestingModule(); await store.close();
    await new Promise<void>((resolve, reject) => { const r = indexedDB.deleteDatabase(name); r.onsuccess = () => resolve(); r.onerror = () => reject(r.error); });
  });
  async function request(method = 'PUT'): Promise<TestRequest> {
    let result: TestRequest | undefined;
    await until(() => {
      for (const check of http.match(req => req.url.endsWith('/app/me'))) {
        const ctx = auth.context(); check.flush({ user: { id: ctx.user_app_id, client_id: ctx.client_id }, client: { id: ctx.client_id, coach_id: ctx.coach_id } });
      }
      const found = http.match(req => req.method === method); result = found[0]; expect(found.length).toBeLessThanOrEqual(1); return !!result;
    });
    return result!;
  }
  function success(req: TestRequest, revision = 1): void {
    req.flush({ data: { set: { ...values, set_uuid: 'set', revision, load_kg: '75.000000' }, execution_revision: revision + 1 } });
  }
  it('keeps the same operation ID and body when a response is lost', async () => {
    await service.save('execution', 'set', values);
    const first = await request(); const body = first.request.body;
    expect(first.request.headers.get('Authorization')).toBe('Bearer token-first');
    first.error(new ProgressEvent('network-error')); await until(() => !service.syncing());
    expect(service.state()!.queue[0].id).toBe(body.operation_id);
    const retried = service.retry(); const second = await request();
    expect(second.request.body).toEqual(body); success(second); await retried;
    expect(service.state()!.queue.length).toBe(0); expect(service.state()!.executions['execution'].records['set'].sync).toBe('synced');
  });
  it('supports a verified athlete with no client or coach using a personal namespace',async()=>{
    auth.context.set({user_app_id:1,client_id:null,coach_id:null});TestBed.flushEffects();
    await until(()=>!!service.state()&&service.state()!.owner.includes('|gym-v2|1'));
    const personal=service.owner()!;await store.change(personal,state=>addExecution(state,execution(),null));await service.refresh();
    await service.save('execution','set',values);const req=await request();success(req);await until(()=>!service.syncing());
    expect(service.state()!.queue.length).toBe(0);expect(service.state()!.executions['execution'].records['set'].sync).toBe('synced');
    expect((await store.read(owner)).executions['execution']).toBeDefined();
  });
  it('cancels sends on account change and leaves the original queue isolated', async () => {
    await service.save('execution', 'set', values); const first = await request();
    auth.sessionChanging.set(true); TestBed.flushEffects(); await until(() => !service.syncing());
    expect(first.cancelled).toBeTrue();
    auth.context.set({ user_app_id: 2, client_id: 6, coach_id: 10 }); api.getToken.and.resolveTo('token-other');
    auth.sessionChanging.set(false); TestBed.flushEffects();
    await until(() => !!service.state() && service.state()!.owner !== owner);
    expect(service.state()!.queue.length).toBe(0); expect((await store.read(owner)).queue.length).toBe(1);
    http.expectNone(req => req.method === 'PUT');
    auth.sessionChanging.set(true); TestBed.flushEffects();
    auth.context.set({ user_app_id: 1, client_id: 5, coach_id: 10 }); api.getToken.and.resolveTo('token-first');
    auth.sessionChanging.set(false); TestBed.flushEffects();
    const resumed = await request(); expect(resumed.request.headers.get('Authorization')).toBe('Bearer token-first'); success(resumed);
    await until(() => !service.syncing()); expect(service.state()!.queue.length).toBe(0);
  });
  it('keeps personal ownership and the pending body when the coach changes',async()=>{
    await service.save('execution','set',values);const first=await request();const body=first.request.body;
    auth.context.set({user_app_id:1,client_id:6,coach_id:20});TestBed.flushEffects();
    expect(service.owner()).toBe(owner);expect(first.cancelled).toBeFalse();success(first);await until(()=>!service.syncing());
    expect(service.state()!.executions['execution'].records['set'].values.actual_reps).toBe(body.actual_reps);
  });
  it('verifies legacy ownership with the pinned token before importing an unchanged queue',async()=>{
    await until(()=>!service.syncing());
    const base=owner.split('|')[0],legacy=base+'|1|5|10';
    await store.change(legacy,row=>{
      const original={...execution(),uuid:'legacy-execution'};addExecution(row,original,null);
      row.queue=[{id:'legacy-operation',execution:'legacy-execution',set:'set',expected_revision:0,values,sent:true}];
    });
    const original=(await store.read(legacy)).queue;
    const refresh=service.refresh();const proof=await request('GET');
    expect(proof.request.url).toContain('/app/workout-ownership');expect(proof.request.headers.get('Authorization')).toBe('Bearer token-first');
    proof.flush({data:{user_app_id:1,contexts:[[5,10]],current_context:[5,10],personal:['legacy-execution'],assigned:[],assignments:[]}});await refresh;
    expect(service.state()!.queue).toEqual(original);expect((await store.read(legacy)).queue).toEqual(original);
    expect(service.migrationNotice()).toBeNull();http.expectNone(req=>req.method==='PUT');
  });
  it('pauses a conflicting series and sends an explicit correction with a new operation', async () => {
    await service.save('execution', 'set', values); const first = await request(); const oldId = first.request.body.operation_id;
    first.flush({ code: 'revision_conflict', data: { current: { ...values, actual_reps: 8, revision: 4, set_uuid: 'set', load_kg: '75.000000' } } }, { status: 409, statusText: 'Conflict' });
    await until(() => !service.syncing()); expect(service.state()!.executions['execution'].records['set'].sync).toBe('conflict');
    await service.resolve('execution', 'set', true); const correction = await request();
    expect(correction.request.body.expected_revision).toBe(4); expect(correction.request.body.operation_id).not.toBe(oldId); success(correction, 5);
    await until(() => !service.syncing());
  });
  it('does not send or acknowledge a series if local persistence fails', async () => {
    spyOn(store, 'change').and.rejectWith(new DOMException('Quota exceeded', 'QuotaExceededError'));
    await expectAsync(service.save('execution', 'set', values)).toBeRejected();
    http.expectNone(req => req.method === 'PUT'); expect(service.state()!.queue.length).toBe(0);
  });
  it('does not transmit queued values when a restored token belongs to a different identity', async () => {
    await service.save('execution', 'set', values);
    let check: TestRequest | undefined;
    await until(() => { check = http.match(req => req.url.endsWith('/app/me'))[0]; return !!check; });
    check!.flush({ user: { id: 99, client_id: 99 }, client: { id: 99, coach_id: 10 } });
    await until(() => !service.syncing());
    http.expectNone(req => req.method === 'PUT');
    expect(service.state()!.blocked).toBeTruthy(); expect(service.state()!.queue.length).toBe(1);
  });
  it('sends closure only after series and preserves the closure operation on response loss', async () => {
    await service.save('execution', 'set', values); const series = await request();
    await service.complete('execution');
    http.expectNone(req => req.url.endsWith('/complete'));
    success(series); const close = await request('POST');
    expect(close.request.url).toContain('/complete'); expect(close.request.body.expected_execution_revision).toBe(2);
    const original = close.request.body;
    close.error(new ProgressEvent('network-error')); await until(() => !service.syncing());
    expect(service.state()!.executions['execution'].execution.status).toBe('in_progress');
    const retry = service.retry(); const again = await request('POST'); expect(again.request.body).toEqual(original);
    again.flush({ data: { execution_revision: 3, completed_at: '2026-11-14T12:00:00Z' } }); await retry;
    expect(service.state()!.executions['execution'].execution.status).toBe('completed');
    expect(service.state()!.closes!['execution']).toBeUndefined();
  });
  it('recovers a generated start using the identical stored body and UUID', async () => {
    const draft={uuid:'draft',title:'Mi rutina',equipment:['barbell'],exercise_count:3,exercises:[],acknowledge_short_session:true,pain_reported:false,technique_issue:false};
    const body={title:draft.title,equipment:draft.equipment,acknowledge_short_session:true,exercises:[]};
    await service.saveBuilderDraft(draft);
    const firstStart=service.startGenerated(draft,body); const first=await request('POST');
    expect(first.request.url).toContain('/workout-generator/executions'); const original=first.request.body;
    first.error(new ProgressEvent('network-error')); await expectAsync(firstStart).toBeRejected();
    const retry=service.startGenerated(draft,{...body,title:'Changed after uncertain response'}); const again=await request('POST');
    expect(again.request.body).toEqual(original);
    again.flush({data:{...execution(),uuid:'generated',client_execution_id:original.client_execution_id}});
    expect(await retry).toBe('generated'); expect(service.state()!.generatedStarts!['draft']).toBeUndefined();
    expect(service.state()!.executions['generated'].execution.uuid).toBe('generated');
    expect(service.state()!.builderDraft!.equipment).toEqual([]);
    expect(service.state()!.builderDraft!.exercises).toEqual([]);
    expect(service.state()!.equipmentPreferences).toEqual(['barbell']);
  });
  it('persists an uncertain replacement and reuses the same operation before importing the revision',async()=>{
    const replacement={exercise_id:2,sets:3,reps_min:8,reps_max:12,target_rir:2,rest_seconds:90,unit:'kg' as const,chosen_load:null,notes:'',suggestion_id:null};
    const firstCall=service.substitute('execution','exercise',replacement);const first=await request('POST');const body=first.request.body;
    expect(first.request.url).toContain('/replace-exercise');first.error(new ProgressEvent('network-error'));await expectAsync(firstCall).toBeRejected();
    expect(service.state()!.substitutions!['execution'].operation_id).toBe(body.operation_id);
    await expectAsync(service.save('execution','set',values)).toBeRejected();
    const retry=service.substitute('execution','exercise',replacement);const again=await request('POST');expect(again.request.body).toEqual(body);again.flush({ok:true});
    const detail=await request('GET');detail.flush({data:{...execution(),revision:2}});await retry;
    expect(service.state()!.substitutions!['execution']).toBeUndefined();expect(service.state()!.executions['execution'].execution.revision).toBe(2);
  });
  it('reuses a durable start UUID after an uncertain response', async () => {
    const starting = service.start(5); const first = await request('POST'); const body = first.request.body;
    first.error(new ProgressEvent('network-error')); await expectAsync(starting).toBeRejected();
    const retry = service.start(5); const second = await request('POST'); expect(second.request.body).toEqual(body);
    second.flush({ data: { ...execution(), client_execution_id: body.client_execution_id } });
    expect(await retry).toBe('execution'); expect(service.state()!.starts['5']).toBeUndefined();
  });
  it('downloads every page into the requested scope without overwriting local records',async()=>{
    const download=service.downloadHistory();const page1=await request('GET');
    expect(page1.request.url).toContain('page=1');
    page1.flush({data:[{uuid:'execution',ownership_scope:'personal_gym'},{uuid:'coach-only',ownership_scope:'coach_assigned'}],meta:{last_page:2}});
    const page2=await request('GET');expect(page2.request.url).toContain('page=2');
    page2.flush({data:[{uuid:'personal-new',ownership_scope:'personal_gym'}],meta:{last_page:2}});
    const detail=await request('GET');expect(detail.request.url).toContain('/personal-new');
    detail.flush({data:{...execution(),uuid:'personal-new',ownership_scope:'personal_gym'}});await download;
    expect(Object.keys(service.state()!.executions).sort()).toEqual(['execution','personal-new']);
    http.expectNone(req=>req.url.endsWith('/coach-only')||req.url.endsWith('/execution'));
  });
});
