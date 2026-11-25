import { OwnershipProof, personalOwner, assignedOwner, legacyOwners, migrateAccounts } from './workout-offline.migration';
import type { GeneratedWorkoutInput, WorkoutBuilderDraft } from './workout-builder.types';
import { DestroyRef, Injectable, computed, effect, signal, untracked } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom, Subject, takeUntil, timeout } from 'rxjs';
import { App } from '@capacitor/app';
import { AuthService } from './auth.service';
import { ApiService } from './api.service';
import { environment } from 'src/environments/environment';
import { addExecution, acknowledgeSet, enqueueSet, resolveSet, WorkoutOfflineStore,
  WorkoutAccount, Execution, SetValues, RemoteSet, PendingCompletion, enqueueCompletion, nextCompletion, acknowledgeCompletion } from './workout-offline.store';

export function workoutUuid(): string {
  const bytes = crypto.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
  const hex = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

@Injectable({ providedIn: 'root' })
export class WorkoutOfflineService {
  readonly state = signal<WorkoutAccount | null>(null);
  readonly error = signal<string | null>(null);
  readonly syncing = signal(false);
  readonly hasAssigned = computed(()=>!!this.auth.context()?.client_id && !!this.auth.context()?.coach_id);
  readonly scope = signal<'personal'|'assigned'>('personal');
  readonly migrationNotice = signal<string|null>(null);
  private migrationAt = new Map<string,number>();
  readonly owner = computed(() => {
    const ctx = this.auth.context();
    if (this.auth.sessionChanging() || this.auth.actorType() !== 'client' || !ctx?.user_app_id) return null;
    if(this.scope()==='personal')return personalOwner(environment.apiUrl,ctx.user_app_id);
    return ctx.client_id && ctx.coach_id ? assignedOwner(environment.apiUrl,ctx.user_app_id,[ctx.client_id,ctx.coach_id]) : null;
  });
  private store = new WorkoutOfflineStore();
  private cancel = new Subject<void>();
  private worker = false;
  private workerId = workoutUuid();

  constructor(private auth: AuthService, private api: ApiService, private http: HttpClient, destroy: DestroyRef) {
    effect(() => {
      const owner = this.owner();
      this.cancel.next(); this.state.set(null); this.error.set(null); this.migrationNotice.set(null);
      this.auth.pendingWorkoutChanges.set(0);
      if (owner) untracked(()=>void this.refresh(owner).then(() => this.sync()).catch(err => this.report(owner, err)));
    });
    const resume = () => { void this.sync(); };
    window.addEventListener('online', resume);
    window.addEventListener('focus', resume);
    const foreground = App.addListener('appStateChange', state => { if (state.isActive) void this.sync(); });
    const timer = setInterval(resume, 15000);
    destroy.onDestroy(() => {
      clearInterval(timer); this.cancel.next(); this.cancel.complete();
      window.removeEventListener('online', resume); window.removeEventListener('focus', resume);
      void foreground.then(handle => handle.remove()).catch(() => undefined);
      void this.store.close();
    });
  }

  private publish(owner: string, state: WorkoutAccount): void {
    if (this.owner() !== owner) return;
    this.state.set(state); void this.countPending(owner).catch(err=>this.report(owner,err));
  }
  private report(owner: string, err: unknown): void {
    if (this.owner() === owner) this.error.set(err instanceof Error ? err.message : 'No se pudo guardar en el dispositivo.');
  }
  private requireOwner(): string {
    const owner = this.owner();
    if (!owner) throw new Error('Inicia sesión como atleta para acceder a tus registros.');
    return owner;
  }
  async refresh(owner = this.requireOwner()): Promise<void> {
    await this.recoverLegacy(owner); this.publish(owner, await this.store.read(owner));await this.countPending(owner);
  }
  async useScope(scope:'personal'|'assigned'):Promise<void> {
    this.scope.set(scope);await this.refresh();
  }
  async selectExecution(uuid:string):Promise<void> {
    const ctx=this.auth.context();if(!ctx?.user_app_id)return;
    const personal=personalOwner(environment.apiUrl,ctx.user_app_id);
    await this.useScope('personal');
    if((await this.store.read(personal)).executions[uuid]){await this.useScope('personal');return;}
    if(ctx.client_id&&ctx.coach_id) {
      const assigned=assignedOwner(environment.apiUrl,ctx.user_app_id,[ctx.client_id,ctx.coach_id]);
      if((await this.store.read(assigned)).executions[uuid])await this.useScope('assigned');
    }
  }
  private async countPending(owner:string):Promise<void> {
    const ctx=this.auth.context();if(!ctx?.user_app_id)return;
    const rows=await this.store.accounts();const personal=personalOwner(environment.apiUrl,ctx.user_app_id);
    const assigned=ctx.client_id&&ctx.coach_id?assignedOwner(environment.apiUrl,ctx.user_app_id,[ctx.client_id,ctx.coach_id]):null;
    let count=0;
    for(const row of rows) {
      if(row.owner===personal||row.owner===assigned||row.owner.startsWith(`${environment.apiUrl.replace(/\/$/,'')}|assigned-v2|${ctx.user_app_id}|`))count+=row.queue.length+Object.keys(row.closes??{}).length+Object.keys(row.substitutions??{}).length+Object.keys(row.generatedStarts??{}).length+Object.keys(row.starts).length;
      else if(legacyOwners([row],environment.apiUrl,ctx.user_app_id).length) {
        const moved=row.transferred??[];
        count+=row.queue.filter(op=>!moved.includes('execution:'+op.execution)).length;
        count+=Object.keys(row.closes??{}).filter(id=>!moved.includes('execution:'+id)).length;
        count+=Object.keys(row.substitutions??{}).filter(id=>!moved.includes('execution:'+id)).length;
        count+=Object.keys(row.starts).filter(id=>!moved.includes('start:'+id)).length;
        if(!moved.includes('draft'))count+=Object.keys(row.generatedStarts??{}).length;
      }
    }
    if(this.owner()===owner)this.auth.pendingWorkoutChanges.set(count);
  }
  private async recoverLegacy(owner:string):Promise<void> {
    const ctx=this.auth.context();if(!ctx?.user_app_id || this.owner()!==owner)return;
    const key=personalOwner(environment.apiUrl,ctx.user_app_id);
    if((this.migrationAt.get(key)??0)>Date.now()-60000)return;
    const rows=await this.store.accounts();if(!legacyOwners(rows,environment.apiUrl,ctx.user_app_id).length)return;
    if(!navigator.onLine){this.migrationNotice.set('Tus registros anteriores están conservados. Conéctate una vez para verificar su propiedad.');return;}
    this.migrationAt.set(key,Date.now());
    try {
      const proof=await this.request<{data:OwnershipProof}>(owner,'GET','/workout-ownership',undefined,true);
      if(this.owner()!==owner)return;
      const held=await this.store.migrate(all=>migrateAccounts(all,environment.apiUrl,ctx.user_app_id,proof.data));
      if(this.owner()===owner)this.migrationNotice.set(held?'Hay '+held+' registros anteriores conservados sin trasladar: propiedad sin confirmar, uso en otra ventana o versiones distintas. No se sobrescribieron.':null);
    } catch(err) {
      if(this.owner()===owner)this.migrationNotice.set('Los registros anteriores están conservados. No se pudo verificar su propiedad; vuelve a intentar con conexión.');
    }
  }
  private async change(owner: string, edit: (state: WorkoutAccount) => void): Promise<WorkoutAccount> {
    if (this.owner() !== owner) throw new Error('La cuenta cambió. Vuelve a abrir tus sesiones.');
    const state = await this.store.change(owner, edit); this.publish(owner, state); await this.countPending(owner); return state;
  }
  private async request<T>(owner: string, method: 'GET' | 'POST' | 'PUT', path: string, body?: unknown, appPath = false): Promise<T> {
    const token = await this.api.getToken();
    if (this.owner() !== owner || !token) throw new Error('La sesión cambió. Inicia sesión de nuevo.');
    // Pin both identity verification and payload to the same token. This also
    // protects recovery after an interrupted login left mismatched preferences.
    const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` };
    const base = environment.apiUrl.replace(/\/$/, '');
    const identity = await firstValueFrom(this.http.get<{ user: { id: number; client_id: number }; client: { id: number; coach_id: number } }>(`${base}/app/me`, { headers })
      .pipe(timeout(20000), takeUntil(this.cancel)));
    if (this.owner() !== owner) throw new Error('La cuenta cambió durante la sincronización.');
    const verifiedOwner = owner.includes('|gym-v2|') ? `${base}|gym-v2|${identity.user?.id}` : `${base}|assigned-v2|${identity.user?.id}|${identity.client?.id}|${identity.client?.coach_id}`;
    if (verifiedOwner !== owner) throw new HttpErrorResponse({ status: 403, error: { message: 'La identidad de la sesión cambió. Vuelve a iniciar sesión con tu cuenta.' } });
    const response = await firstValueFrom(this.http.request<T>(method, `${base}${appPath ? '/app' + path : '/app/workout-executions' + path}`, { body, headers })
      .pipe(timeout(20000), takeUntil(this.cancel)));
    if (this.owner() !== owner) throw new Error('La cuenta cambió durante la solicitud.');
    return response;
  }

  async appRequest<T>(method: 'GET'|'POST'|'PUT', path: string, body?: unknown): Promise<T> {
    return this.request<T>(this.requireOwner(), method, path, body, true);
  }
  async saveBuilderDraft(draft: WorkoutBuilderDraft): Promise<void> {
    if(this.scope()!=='personal')throw new Error('Abre GYM para crear tu rutina personal.');
    const owner = this.requireOwner();
    await this.change(owner, state => { state.builderDraft = structuredClone(draft); if(draft.equipment.length)state.equipmentPreferences=[...draft.equipment]; });
  }
  async startGenerated(draft: WorkoutBuilderDraft, body: GeneratedWorkoutInput): Promise<string> {
    if(this.scope()!=='personal')throw new Error('Abre GYM para crear tu rutina personal.');
    const owner = this.requireOwner();
    if (!navigator.onLine) throw new Error('Conéctate para preparar la rutina nueva. Tu borrador permanece guardado.');
    await this.change(owner, state => {
      state.generatedStarts ??= {};
      state.generatedStarts[draft.uuid] ??= { requestId: workoutUuid(), payload: structuredClone(body) };
    });
    const state = await this.store.read(owner); const pending = state.generatedStarts![draft.uuid];
    try {
      const response = await this.request<{data: Execution}>(owner, 'POST', '/workout-generator/executions', {
        ...pending.payload, client_execution_id: pending.requestId,
      }, true);
      await this.change(owner, account => {
        addExecution(account, response.data, null); delete account.generatedStarts![draft.uuid];
        account.builderDraft = { ...structuredClone(draft), uuid: workoutUuid(),equipment:[],exercises:[],acknowledge_short_session:false,pain_reported:false,technique_issue:false };
      });
      void navigator.storage?.persist?.().catch(() => false);
      return response.data.uuid;
    } catch (err) {
      if (err instanceof HttpErrorResponse && err.status === 422) await this.change(owner, account => { delete account.generatedStarts![draft.uuid]; });
      throw err;
    }
  }

  async start(assignment: number): Promise<string> {
    await this.useScope('assigned');
    const owner = this.requireOwner();
    if (!navigator.onLine) throw new Error('Conéctate para preparar una sesión nueva. Puedes continuar las descargadas.');
    await this.change(owner, state => { state.starts[String(assignment)] ??= workoutUuid(); });
    const state = await this.store.read(owner);
    const response = await this.request<{ data: Execution }>(owner, 'POST', '', {
      client_execution_id: state.starts[String(assignment)], training_assignment_id: assignment,
    });
    await this.change(owner, draft => {
      addExecution(draft, response.data, assignment); delete draft.starts[String(assignment)];
    });
    // Advisory only: IndexedDB success is still required, persistent-storage permission is best effort.
    void navigator.storage?.persist?.().catch(() => false);
    return response.data.uuid;
  }

  async downloadHistory(): Promise<void> {
    const owner = this.requireOwner();
    for(let page=1; ;page++) {
      const result=await this.request<{data:Array<{uuid:string;ownership_scope?:string}>;meta?:{last_page:number}}>(owner,'GET',`?per_page=50&page=${page}`);
      for(const header of result.data) {
        if(header.ownership_scope && header.ownership_scope!==(this.scope()==='personal'?'personal_gym':'coach_assigned'))continue;
        if((await this.store.read(owner)).executions[header.uuid])continue;
        const detail=await this.request<{data:Execution}>(owner,'GET',`/${header.uuid}`);
        const expected=this.scope()==='personal'?'personal_gym':'coach_assigned';
        if(detail.data.ownership_scope!==expected)continue;
        await this.change(owner,state=>addExecution(state,detail.data,null));
      }
      if(page>=(result.meta?.last_page??1))break;
    }
  }

  async save(execution: string, set: string, values: SetValues): Promise<void> {
    const owner = this.requireOwner();
    // Completion of this promise means the values AND operation committed to IndexedDB.
    await this.change(owner, state => {
      const record = state.executions[execution]?.records[set];
      if (record?.sync === 'error') {
        state.queue = state.queue.filter(op => op.execution !== execution || op.set !== set);
        record.sync = 'synced'; record.message = undefined;
      }
      enqueueSet(state, execution, set, values, workoutUuid());
    });
    void this.sync();
  }
  async resolve(execution: string, set: string, keepLocal: boolean): Promise<void> {
    const owner = this.requireOwner();
    await this.change(owner, state => resolveSet(state, execution, set, keepLocal, workoutUuid()));
    void this.sync();
  }
  async substitute(execution:string,target:string,replacement:GeneratedWorkoutInput['exercises'][number]):Promise<void>{
    const owner=this.requireOwner();
    if(!navigator.onLine)throw new Error('Conéctate para sustituir un ejercicio. Las series ya guardadas se conservan.');
    await this.change(owner,state=>{
      if(state.queue.some(op=>op.execution===execution)||state.closes?.[execution])throw new Error('Sincroniza y resuelve las series pendientes antes de sustituir.');
      state.substitutions??={};
      state.substitutions[execution]??={operation_id:workoutUuid(),expected_execution_revision:state.executions[execution].execution.revision,target_exercise_uuid:target,replacement:structuredClone(replacement)};
    });
    const pending=(await this.store.read(owner)).substitutions![execution];
    const {conflict,message,...body}=pending;
    try{
      await this.request(owner,'POST',`/${execution}/replace-exercise`,body);
      const response=await this.request<{data:Execution}>(owner,'GET',`/${execution}`);
      await this.change(owner,state=>{this.importReplacement(state,response.data);delete state.substitutions![execution];});
    }catch(err){
      if(err instanceof HttpErrorResponse&&err.status===422)await this.change(owner,state=>{delete state.substitutions![execution];});
      else if(err instanceof HttpErrorResponse&&err.status===409)await this.change(owner,state=>{
        const current=state.substitutions?.[execution];if(current){current.conflict=true;current.message='La programación cambió. Revisa la sesión del servidor antes de continuar.';}
      });
      throw err;
    }
  }
  async reviewSubstitution(execution:string):Promise<void>{
    const owner=this.requireOwner();
    const state=await this.store.read(owner);
    if(!state.substitutions?.[execution]?.conflict)throw new Error('Primero reintenta para conocer el resultado de la sustitución.');
    const response=await this.request<{data:Execution}>(owner,'GET',`/${execution}`);
    await this.change(owner,account=>{this.importReplacement(account,response.data);delete account.substitutions![execution];});
  }
  private importReplacement(state:WorkoutAccount,remote:Execution):void{
    if(state.queue.some(op=>op.execution===remote.uuid))throw new Error('Resuelve los registros pendientes antes de actualizar la programación.');
    const local=state.executions[remote.uuid];
    if(local&&local.execution.revision>remote.revision)return;
    const assignment=local?.assignment??null;delete state.executions[remote.uuid];addExecution(state,remote,assignment);
  }

  async complete(execution: string): Promise<void> {
    const owner = this.requireOwner();
    await this.change(owner, state => enqueueCompletion(state, execution, workoutUuid()));
    void this.sync();
  }
  async cancelCompletion(execution: string): Promise<void> {
    const owner = this.requireOwner();
    await this.change(owner, state => {
      const close = state.closes?.[execution];
      if (close?.sent) throw new Error('El cierre ya fue enviado. Revisa su estado antes de continuar.');
      if (state.closes) delete state.closes[execution];
    });
  }
  async reviewCompletion(execution: string): Promise<void> {
    const owner = this.requireOwner();
    const response = await this.request<{ data: Execution }>(owner, 'GET', `/${execution}`);
    await this.change(owner, state => {
      if (state.queue.some(op => op.execution === execution)) throw new Error('Resuelve las series pendientes antes de revisar el cierre.');
      const close = state.closes?.[execution];
      if (!close || close.state === 'pending') throw new Error('Espera la respuesta del cierre.');
      // Only after explicit review: refresh acknowledged values and remove the rejected close.
      delete state.executions[execution];
      addExecution(state, response.data, null);
      delete state.closes![execution];
    });
  }
  private async sendCompletion(owner: string, close: PendingCompletion): Promise<boolean> {
    try {
      const response = await this.request<{ data: { execution_revision: number; completed_at: string } }>(owner, 'POST', `/${close.execution}/complete`, {
        operation_id: close.id, expected_execution_revision: close.revision,
      });
      await this.change(owner, state => acknowledgeCompletion(state, close.id, response.data.execution_revision, response.data.completed_at));
      return true;
    } catch (err) {
      if (this.owner() !== owner) return false;
      const http = err instanceof HttpErrorResponse ? err : null;
      await this.change(owner, state => {
        const current = state.closes?.[close.execution];
        if (!current || current.id !== close.id) return;
        if (http && http.status >= 400 && http.status < 500 && ![408, 429].includes(http.status)) {
          current.state = http.status === 409 ? 'conflict' : 'error';
          current.message = http.error?.message ?? 'No se pudo confirmar el cierre.';
          if ([401, 403].includes(http.status)) state.blocked = 'Revisa la cuenta y sus permisos antes de reintentar.';
        } else {
          state.failures++;
          state.retryAt = Date.now() + Math.min(120000, 1000 * 2 ** Math.min(state.failures, 7)) + Math.floor(Math.random() * 1000);
        }
      });
      return !!http && http.status >= 400 && http.status < 500 && ![401, 403, 408, 429].includes(http.status);
    }
  }

  async retry(): Promise<void> {
    this.migrationAt.clear();await this.refresh();
    this.error.set(null);
    const owner = this.requireOwner();
    await this.change(owner, state => {
      state.blocked = undefined; state.retryAt = 0; state.failures = 0;
      for (const close of Object.values(state.closes ?? {})) if (close.state === 'error') { close.state = 'pending'; close.message = undefined; }
      for (const session of Object.values(state.executions)) for (const record of Object.values(session.records)) {
        if (record.sync === 'error') { record.sync = 'pending'; record.message = undefined; }
      }
    });
    await this.sync();
  }

  async sync(): Promise<void> {
    const owner = this.owner();
    if (!owner || this.worker || !navigator.onLine) return;
    this.worker = true; this.syncing.set(true);
    let leased = false;
    try {
      await this.recoverLegacy(owner);
      const initial = await this.store.read(owner);
      this.publish(owner, initial);
      if ((!initial.queue.length && !Object.keys(initial.closes ?? {}).length) || initial.blocked || initial.retryAt > Date.now()) return;
      await this.change(owner, state => {
        if (state.lease && state.lease.until > Date.now() && state.lease.id !== this.workerId) return;
        state.lease = { id: this.workerId, until: Date.now() + 45000 }; leased = true;
      });
      if (!leased) return;
      while (this.owner() === owner && navigator.onLine) {
        let selected: WorkoutAccount['queue'][number] | undefined;
        let closing: PendingCompletion | undefined;
        await this.change(owner, state => {
          if (state.lease?.id !== this.workerId || state.blocked || state.retryAt > Date.now()) return;
          state.lease.until = Date.now() + 45000;
          const next = state.queue.find(op => state.executions[op.execution]?.records[op.set]?.sync === 'pending');
          if (next) { next.sent = true; selected = structuredClone(next); }
          else { const close = nextCompletion(state); if (close) closing = structuredClone(close); }
        });
        const op = selected;
        if (!op) {
          if (!closing) break;
          if (!(await this.sendCompletion(owner, closing))) break;
          continue;
        }
        try {
          const response = await this.request<{ data: { set: RemoteSet; execution_revision: number } }>(owner, 'PUT', `/${op.execution}/sets/${op.set}`, {
            ...op.values, operation_id: op.id, expected_revision: op.expected_revision,
          });
          await this.change(owner, state => acknowledgeSet(state, op.id, response.data.set, response.data.execution_revision));
        } catch (err) {
          if (this.owner() !== owner) break;
          const http = err instanceof HttpErrorResponse ? err : null;
          await this.change(owner, state => {
            const record = state.executions[op.execution].records[op.set];
            if (http?.status === 409 && http.error?.code === 'revision_conflict') {
              record.sync = 'conflict'; record.remote = http.error.data.current;
              record.message = 'Hay cambios de otro dispositivo. Compara antes de continuar.';
            } else if (http && http.status >= 400 && http.status < 500 && ![408, 429].includes(http.status)) {
              record.sync = 'error'; record.message = http.error?.message ?? 'No se pudo enviar el registro.';
              if ([401, 403].includes(http.status)) state.blocked = 'Inicia sesión con la misma cuenta y revisa sus permisos antes de reintentar.';
            } else {
              state.failures++;
              state.retryAt = Date.now() + Math.min(120000, 1000 * 2 ** Math.min(state.failures, 7)) + Math.floor(Math.random() * 1000);
            }
          });
          if (!http || http.status === 0 || http.status >= 500 || [401, 403, 408, 429].includes(http.status)) break;
        }
      }
    } catch (err) { this.report(owner, err); }
    finally {
      if (leased) {
        try {
          const state = await this.store.change(owner, draft => { if (draft.lease?.id === this.workerId) delete draft.lease; });
          this.publish(owner, state);
        } catch (err) { this.report(owner, err); }
      }
      this.worker = false; this.syncing.set(false);
    }
  }
}
