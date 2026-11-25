import type { GeneratedWorkoutInput, WorkoutBuilderDraft } from './workout-builder.types';
export interface SetValues {
  status: 'completed' | 'failed' | 'skipped';
  type: 'warmup' | 'work';
  load_mode: 'total_barbell' | 'per_dumbbell' | 'external_total' | 'machine_indicated' | 'unknown';
  actual_reps: number | null;
  actual_load: string | null;
  load_unit: 'kg' | 'lb' | null;
  actual_rir: string | null;
  notes: string | null;
  performed_at: string;
}
export interface RemoteSet extends SetValues { set_uuid: string; revision: number; load_kg: string | null; }
export interface PrescribedSet {
  uuid: string; set_number: number;
  prescribed: { reps_min: number; reps_max: number; rir?:number|null; load?:{value:string;unit:string;mode:string}|null; percentage: number | null; rest_seconds: number | null };
}
export interface Execution {
  ownership_scope?: 'personal_gym' | 'coach_assigned' | 'legacy_coach';
  uuid: string; client_execution_id: string; status: string; revision: number; started_at: string;
  prescription: { content: { title: string; source?:string; equipment?:string[]; sections: Array<{
    uuid: string; name: string; description: string | null;
    exercises: Array<{ uuid: string; workout_exercise_id?: number | null; name: string; notes: string | null; load_mode?:string; catalog?: { load_mode?:string; terms: Array<{kind:string;code:string;role:string}> } | null; sets: PrescribedSet[] }>;
  }> } };
  sets: RemoteSet[]; completed_at?: string | null;
}
export interface LocalSet {
  values: SetValues; serverRevision: number;
  sync: 'pending' | 'synced' | 'conflict' | 'error';
  message?: string; remote?: RemoteSet | null;
}
export interface LocalExecution { execution: Execution; assignment: number | null; records: Record<string, LocalSet>; }
export interface PendingSet {
  id: string; execution: string; set: string; expected_revision: number; values: SetValues; sent: boolean;
}
export interface PendingCompletion {
  id: string; execution: string; revision: number | null; sent: boolean;
  state: 'pending' | 'error' | 'conflict'; message?: string;
}
export interface WorkoutAccount {
  transferred?: string[];
  substitutions?: Record<string,{ operation_id:string; expected_execution_revision:number; target_exercise_uuid:string; replacement:GeneratedWorkoutInput['exercises'][number]; conflict?:boolean; message?:string }>;
  builderDraft?: WorkoutBuilderDraft;
  equipmentPreferences?: string[];
  generatedStarts?: Record<string, { requestId: string; payload: GeneratedWorkoutInput }>;
  owner: string; executions: Record<string, LocalExecution>; queue: PendingSet[];
  closes?: Record<string, PendingCompletion>;
  starts: Record<string, string>; lease?: { id: string; until: number };
  retryAt: number; failures: number; blocked?: string;
}
export function emptyAccount(owner: string): WorkoutAccount {
  return { owner, executions: {}, queue: [], starts: {}, retryAt: 0, failures: 0 };
}
export function addExecution(account: WorkoutAccount, execution: Execution, assignment: number | null): void {
  // A start replay must not overwrite newer local changes.
  if (account.executions[execution.uuid]) return;
  const records: Record<string, LocalSet> = {};
  for (const set of execution.sets) records[set.set_uuid] = { values: set, serverRevision: set.revision, sync: 'synced' };
  account.executions[execution.uuid] = { execution, assignment, records };
}
export function enqueueSet(account: WorkoutAccount, execution: string, set: string, values: SetValues, id: string, resolving = false): void {
  const local = account.executions[execution];
  if (!local || local.execution.status !== 'in_progress' || account.substitutions?.[execution] || (account.closes?.[execution] && !(resolving && !account.closes[execution].sent))) throw new Error('La sesiÃ³n no estÃ¡ disponible.');
  const exists = local.execution.prescription.content.sections.some(s => s.exercises.some(e => e.sets.some(item => item.uuid === set)));
  if (!exists) throw new Error('La serie no pertenece a esta sesiÃ³n.');
  const record = local.records[set];
  if (record?.sync === 'conflict' || record?.sync === 'error') throw new Error('Resuelve el registro pendiente antes de editarlo.');
  const waiting = account.queue.filter(op => op.execution === execution && op.set === set).length;
  account.queue.push({ id, execution, set, expected_revision: (record?.serverRevision ?? 0) + waiting, values, sent: false });
  local.records[set] = { values, serverRevision: record?.serverRevision ?? 0, sync: 'pending' };
}
export function acknowledgeSet(account: WorkoutAccount, id: string, remote: RemoteSet, executionRevision: number): void {
  const op = account.queue.find(item => item.id === id);
  if (!op) return;
  account.queue = account.queue.filter(item => item.id !== id);
  const local = account.executions[op.execution];
  local.execution.revision = Math.max(local.execution.revision, executionRevision);
  const newer = account.queue.some(item => item.execution === op.execution && item.set === op.set);
  const record = local.records[op.set];
  local.records[op.set] = {
    values: newer ? record.values : remote,
    serverRevision: Math.max(record.serverRevision, remote.revision), sync: newer ? 'pending' : 'synced',
  };
  account.retryAt = 0; account.failures = 0;
}
export function resolveSet(account: WorkoutAccount, execution: string, set: string, keepLocal: boolean, id: string): void {
  const local = account.executions[execution];
  const record = local.records[set];
  if (record?.sync !== 'conflict') throw new Error('No hay conflicto que resolver.');
  const values = record.values;
  const remote = record.remote;
  account.queue = account.queue.filter(op => op.execution !== execution || op.set !== set);
  if (remote) local.records[set] = { values: remote, serverRevision: remote.revision, sync: 'synced' };
  else delete local.records[set];
  if (keepLocal) enqueueSet(account, execution, set, values, id, true);
}

export function enqueueCompletion(account: WorkoutAccount, execution: string, id: string): void {
  const local = account.executions[execution];
  if (!local || local.execution.status !== 'in_progress' || account.substitutions?.[execution]) throw new Error('La sesiÃ³n estÃ¡ finalizada o tiene una sustituciÃ³n pendiente.');
  account.closes ??= {};
  account.closes[execution] ??= { id, execution, revision: null, sent: false, state: 'pending' };
}
export function nextCompletion(account: WorkoutAccount): PendingCompletion | undefined {
  const close = Object.values(account.closes ?? {}).find(item => item.state === 'pending'
    && !account.queue.some(op => op.execution === item.execution));
  if (!close) return undefined;
  // Fix revision only after every preceding series has been acknowledged.
  close.revision ??= account.executions[close.execution].execution.revision;
  close.sent = true;
  return close;
}
export function acknowledgeCompletion(account: WorkoutAccount, id: string, revision: number, completedAt: string): void {
  const close = Object.values(account.closes ?? {}).find(item => item.id === id);
  if (!close) return;
  const execution = account.executions[close.execution].execution;
  execution.status = 'completed'; execution.revision = Math.max(execution.revision, revision); execution.completed_at = completedAt;
  delete account.closes![close.execution];
  account.retryAt = 0; account.failures = 0;
}

/** One account row holds session state and outbox: both commit or neither does. */
export class WorkoutOfflineStore {
  private opened?: Promise<IDBDatabase>;
  constructor(private name = 'coach-workout-offline-v1', private factory: IDBFactory | undefined = globalThis.indexedDB) {}
  private open(): Promise<IDBDatabase> {
    if (!this.factory) return Promise.reject(new Error('Este dispositivo no permite guardar sesiones localmente.'));
    const factory = this.factory;
    if (!this.opened) this.opened = new Promise((resolve, reject) => {
      const request = factory.open(this.name, 1);
      request.onupgradeneeded = () => request.result.createObjectStore('accounts', { keyPath: 'owner' });
      request.onsuccess = () => { request.result.onversionchange = () => request.result.close(); resolve(request.result); };
      request.onerror = () => { this.opened = undefined; reject(new Error('No se pudo abrir el almacenamiento del dispositivo.')); };
      request.onblocked = () => reject(new Error('Cierra otras ventanas de COACH y vuelve a intentar.'));
    });
    return this.opened;
  }
  async read(owner: string): Promise<WorkoutAccount> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('accounts', 'readonly');
      const request = tx.objectStore('accounts').get(owner);
      let result: WorkoutAccount;
      request.onsuccess = () => { result = request.result ?? emptyAccount(owner); };
      tx.oncomplete = () => resolve(result);
      tx.onerror = tx.onabort = () => reject(tx.error ?? new Error('No se pudo leer la sesiÃ³n local.'));
    });
  }
  async accounts():Promise<WorkoutAccount[]> {
    const db=await this.open();return new Promise((resolve,reject)=>{
      const tx=db.transaction('accounts','readonly');const request=tx.objectStore('accounts').getAll();
      tx.oncomplete=()=>resolve(request.result);tx.onerror=tx.onabort=()=>reject(tx.error);
    });
  }
  async migrate(edit:(rows:WorkoutAccount[])=>{rows:WorkoutAccount[];held:number}):Promise<number> {
    const db=await this.open();return new Promise((resolve,reject)=>{
      const tx=db.transaction('accounts','readwrite',{durability:'strict'});const store=tx.objectStore('accounts');
      const request=store.getAll();let held=0;let failure:unknown;
      request.onsuccess=()=>{try{const result=edit(request.result);held=result.held;for(const row of result.rows)store.put(row);}catch(err){failure=err;tx.abort();}};
      tx.oncomplete=()=>resolve(held);tx.onerror=tx.onabort=()=>reject(failure??tx.error);
    });
  }
  async change(owner: string, edit: (account: WorkoutAccount) => void): Promise<WorkoutAccount> {
    const db = await this.open();
    return new Promise((resolve, reject) => {
      const tx = db.transaction('accounts', 'readwrite', { durability: 'strict' });
      const store = tx.objectStore('accounts');
      const request = store.get(owner);
      let result: WorkoutAccount; let failure: unknown;
      request.onsuccess = () => {
        try { result = request.result ?? emptyAccount(owner); edit(result); store.put(result); }
        catch (err) { failure = err; tx.abort(); }
      };
      tx.oncomplete = () => resolve(result);
      tx.onerror = tx.onabort = () => reject(failure ?? tx.error ?? new Error('No se guardÃ³ la serie. Revisa el espacio disponible.'));
    });
  }
  async removeAthlete(api: string, userAppId: number): Promise<void> {
    if (!Number.isSafeInteger(userAppId) || userAppId <= 0) throw new Error('No se pudo verificar la cuenta del almacenamiento local.');
    const base = api.replace(/\/$/, '');
    const legacy = `${base}|${userAppId}|`; const personal = `${base}|gym-v2|${userAppId}`;
    const db = await this.open();
    await new Promise<void>((resolve, reject) => {
      const tx = db.transaction('accounts', 'readwrite', {durability: 'strict'});
      const request = tx.objectStore('accounts').openCursor();
      request.onsuccess = () => {
        const cursor = request.result;
        if (!cursor) return;
        const key = String(cursor.key);
        if (key.startsWith(legacy) || key === personal || key.startsWith(`${base}|assigned-v2|${userAppId}|`)) cursor.delete();
        cursor.continue();
      };
      tx.oncomplete = () => resolve();
      tx.onerror = tx.onabort = () => reject(tx.error ?? new Error('No se pudieron eliminar los registros locales.'));
    });
  }
  async close(): Promise<void> { if (this.opened) (await this.opened).close(); this.opened = undefined; }
}
