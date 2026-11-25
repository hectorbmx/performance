import { WorkoutOfflineStore, emptyAccount, addExecution, enqueueSet, acknowledgeSet, resolveSet, enqueueCompletion, nextCompletion, acknowledgeCompletion, Execution, SetValues, RemoteSet } from './workout-offline.store';

export function sampleExecution(): Execution {
  return { uuid: 'execution', client_execution_id: 'request', status: 'in_progress', revision: 1,
    started_at: '2026-11-13T16:00:00Z', sets: [], prescription: { content: { title: 'Piernas', sections: [{
      uuid: 'section', name: 'Fuerza', description: null, exercises: [{ uuid: 'exercise', name: 'Back Squat', notes: null,
        sets: ['a', 'b', 'c'].map(uuid => ({ uuid, set_number: 1, prescribed: { reps_min: 8, reps_max: 12, percentage: null, rest_seconds: 90 } })) }],
    }] } } };
}
export function sampleValues(reps = 10): SetValues {
  return { status: 'completed', type: 'work', load_mode: 'total_barbell', actual_reps: reps,
    actual_load: '75.00', load_unit: 'kg', actual_rir: null, notes: null, performed_at: '2026-11-13T16:00:00Z' };
}
function remote(reps = 10, revision = 1): RemoteSet { return { ...sampleValues(reps), set_uuid: 'a', revision, load_kg: '75.000000' }; }

describe('WorkoutOfflineStore (real IndexedDB)', () => {
  let store: WorkoutOfflineStore;
  let name: string;
  beforeEach(() => { name = 'workout-test-' + crypto.randomUUID(); store = new WorkoutOfflineStore(name); });
  afterEach(async () => {
    await store.close();
    await new Promise<void>((resolve, reject) => { const request = indexedDB.deleteDatabase(name); request.onsuccess = () => resolve(); request.onerror = () => reject(request.error); });
  });
  it('recovers three series and their unchanged operations after reopening', async () => {
    await store.change('owner', account => {
      addExecution(account, sampleExecution(), 5);
      for (const set of ['a', 'b', 'c']) enqueueSet(account, 'execution', set, sampleValues(), 'op-' + set);
      account.queue[0].sent = true;
    });
    await store.close(); store = new WorkoutOfflineStore(name);
    const account = await store.read('owner');
    expect(Object.keys(account.executions['execution'].records).length).toBe(3);
    expect(account.queue.map(op => op.id)).toEqual(['op-a', 'op-b', 'op-c']);
    expect(account.queue[0].sent).toBeTrue();
  });
  it('commits values and operation together or rolls both back', async () => {
    await store.change('owner', account => addExecution(account, sampleExecution(), 5));
    await expectAsync(store.change('owner', account => {
      enqueueSet(account, 'execution', 'a', sampleValues(), 'op');
      throw new DOMException('Quota exceeded', 'QuotaExceededError');
    })).toBeRejected();
    const account = await store.read('owner');
    expect(account.queue.length).toBe(0); expect(account.executions['execution'].records['a']).toBeUndefined();
  });
  it('isolates accounts and starting request identifiers', async () => {
    await store.change('first', account => { account.starts['5'] = 'start-first'; addExecution(account, sampleExecution(), 5); enqueueSet(account, 'execution', 'a', sampleValues(), 'op'); });
    const other = await store.read('second');
    expect(other.queue.length).toBe(0); expect(other.starts['5']).toBeUndefined(); expect(other.executions['execution']).toBeUndefined();
    expect((await store.read('first')).queue.length).toBe(1);
  });
  it('deletes only the verified athlete namespaces in the current API after an account deletion',async()=>{
    const api='https://local.test/api/v1';
    const remove=[`${api}|3|8|7`,`${api}|3|9|11`,`${api}|gym-v2|3`,`${api}|assigned-v2|3|8|7`];
    const keep=[`${api}|30|8|7`,`${api}|4|8|7`,`${api}|gym-v2|30`,`${api}|assigned-v2|30|8|7`,'https://other.test/api/v1|3|8|7'];
    for(const owner of [...remove,...keep]) await store.change(owner,s=>{addExecution(s,sampleExecution(),5);enqueueSet(s,'execution','a',sampleValues(),'op');});
    await store.removeAthlete(api,3);
    for(const owner of remove) expect((await store.read(owner)).queue.length).toBe(0);
    for(const owner of keep) expect((await store.read(owner)).queue.length).toBe(1);
    await expectAsync(store.removeAthlete(api,NaN)).toBeRejected();
  });
  it('serializes concurrent writes from two database connections', async () => {
    await store.change('owner', account => addExecution(account, sampleExecution(), 5));
    const second = new WorkoutOfflineStore(name);
    await Promise.all([
      store.change('owner', account => enqueueSet(account, 'execution', 'a', sampleValues(), 'op-a')),
      second.change('owner', account => enqueueSet(account, 'execution', 'b', sampleValues(), 'op-b')),
    ]);
    expect((await store.read('owner')).queue.length).toBe(2); await second.close();
  });
});
describe('Workout outbox reconciliation', () => {
  function account() { const state = emptyAccount('owner'); addExecution(state, sampleExecution(), 5); return state; }
  it('preserves newer edits while acknowledging an earlier operation', () => {
    const state = account(); enqueueSet(state, 'execution', 'a', sampleValues(10), 'one'); enqueueSet(state, 'execution', 'a', sampleValues(9), 'two');
    expect(state.queue.map(op => op.expected_revision)).toEqual([0, 1]);
    acknowledgeSet(state, 'one', remote(10, 1), 2);
    expect(state.executions['execution'].records['a'].values.actual_reps).toBe(9); expect(state.executions['execution'].records['a'].sync).toBe('pending');
    acknowledgeSet(state, 'two', remote(9, 2), 3);
    expect(state.queue.length).toBe(0); expect(state.executions['execution'].records['a'].serverRevision).toBe(2); expect(state.executions['execution'].records['a'].sync).toBe('synced');
  });
  it('does not overwrite pending values with a start replay', () => {
    const state = account(); enqueueSet(state, 'execution', 'a', sampleValues(9), 'op'); addExecution(state, sampleExecution(), 5);
    expect(state.executions['execution'].records['a'].values.actual_reps).toBe(9);
  });
  it('resolves a conflict with a new operation and explicit remote revision', () => {
    const state = account(); enqueueSet(state, 'execution', 'a', sampleValues(9), 'old');
    state.executions['execution'].records['a'].sync = 'conflict'; state.executions['execution'].records['a'].remote = remote(8, 4);
    resolveSet(state, 'execution', 'a', true, 'new');
    expect(state.queue.length).toBe(1); expect(state.queue[0].id).toBe('new'); expect(state.queue[0].expected_revision).toBe(4); expect(state.queue[0].values.actual_reps).toBe(9);
  });
  it('accepts the remote version only after explicit resolution', () => {
    const state = account(); enqueueSet(state, 'execution', 'a', sampleValues(9), 'old');
    state.executions['execution'].records['a'].sync = 'conflict'; state.executions['execution'].records['a'].remote = remote(8, 4);
    resolveSet(state, 'execution', 'a', false, 'unused');
    expect(state.queue.length).toBe(0); expect(state.executions['execution'].records['a'].values.actual_reps).toBe(8);
  });
  it('rejects a series outside the snapshot', () => {
    const state = account(); expect(() => enqueueSet(state, 'execution', 'foreign', sampleValues(), 'op')).toThrow(); expect(state.queue.length).toBe(0);
  });
  it('ignores repeated acknowledgments without lowering revisions', () => {
    const state = account(); enqueueSet(state, 'execution', 'a', sampleValues(), 'op');
    acknowledgeSet(state, 'op', remote(10, 1), 3); acknowledgeSet(state, 'op', remote(9, 0), 2);
    expect(state.executions['execution'].execution.revision).toBe(3); expect(state.executions['execution'].records['a'].serverRevision).toBe(1);
  });
});

describe('Workout completion ordering', () => {
  it('does not select closure before all series have been acknowledged', () => {
    const state = emptyAccount('owner'); addExecution(state, sampleExecution(), 5);
    enqueueSet(state, 'execution', 'a', sampleValues(), 'set-op');
    enqueueCompletion(state, 'execution', 'close-op');
    expect(nextCompletion(state)).toBeUndefined();
    acknowledgeSet(state, 'set-op', remote(10, 1), 2);
    const close = nextCompletion(state)!; expect(close.revision).toBe(2); expect(close.id).toBe('close-op');
    state.executions['execution'].execution.revision = 3;
    expect(nextCompletion(state)!.revision).toBe(2); // replay body stays immutable
    acknowledgeCompletion(state, 'close-op', 3, '2026-11-14T12:00:00Z');
    expect(state.executions['execution'].execution.status).toBe('completed');
    expect(Object.keys(state.executions['execution'].records).length).toBe(1);
    expect(state.closes!['execution']).toBeUndefined();
  });
  it('preserves a waiting close while explicitly resolving a series conflict', () => {
    const state = emptyAccount('owner'); addExecution(state, sampleExecution(), 5);
    enqueueSet(state, 'execution', 'a', sampleValues(), 'old'); enqueueCompletion(state, 'execution', 'close');
    const record = state.executions['execution'].records['a']; record.sync = 'conflict'; record.remote = remote(9, 4);
    resolveSet(state, 'execution', 'a', true, 'new');
    expect(state.queue[0].id).toBe('new'); expect(nextCompletion(state)).toBeUndefined();
    expect(() => enqueueSet(state, 'execution', 'b', sampleValues(), 'extra')).toThrow();
  });
});
