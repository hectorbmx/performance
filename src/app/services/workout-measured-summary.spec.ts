import { addExecution, emptyAccount, enqueueSet, Execution, SetValues } from './workout-offline.store';
import { measuredSummary } from './workout-measured-summary';

describe('Measured workout summary', () => {
  function session() {
    const account = emptyAccount('owner');
    const execution: Execution = { uuid: 'exec', client_execution_id: 'start', started_at: '2026-11-14T10:00:00Z', status: 'in_progress', revision: 1, sets: [],
      prescription: { content: { title: 'Piernas', sections: [{ uuid: 's', name: 'Fuerza', description: null, exercises: [{ uuid: 'e', name: 'Squat', notes: null,
        sets: ['a', 'b', 'c'].map(uuid => ({ uuid, set_number: 1, prescribed: { reps_min: 10, reps_max: 10, percentage: null, rest_seconds: null } })) }] }] } } };
    addExecution(account, execution, 5); return account;
  }
  function values(load: string | null, reps = 10): SetValues {
    return { status: 'completed', type: 'work', load_mode: load === null ? 'unknown' : 'total_barbell', actual_load: load,
      load_unit: load === null ? null : 'kg', actual_reps: reps, actual_rir: null, notes: null, performed_at: '2026-11-14T10:00:00Z' };
  }
  it('counts only completed work for frozen primary muscles and exposes missing classification',()=>{
    const account=session(); const exercise=account.executions['exec'].execution.prescription.content.sections[0].exercises[0];
    exercise.catalog={terms:[{kind:'muscle',code:'quadriceps',role:'primary'},{kind:'muscle',code:'glutes',role:'secondary'}]};
    enqueueSet(account,'exec','a',values(null),'a');enqueueSet(account,'exec','b',{...values('20'),type:'warmup'},'b');
    enqueueSet(account,'exec','c',{...values('75'),status:'failed'},'c');
    expect(measuredSummary(account.executions['exec']).directMuscleSeries).toEqual({quadriceps:1});
    delete exercise.catalog;expect(measuredSummary(account.executions['exec']).unclassifiedWorkSets).toBe(1);
  });
  it('matches the 2152.5 kg reference exactly including pending local records', () => {
    const account = session(); enqueueSet(account, 'exec', 'a', values('75'), 'a'); enqueueSet(account, 'exec', 'b', values('75'), 'b'); enqueueSet(account, 'exec', 'c', values('72.50', 9), 'c');
    const summary = measuredSummary(account.executions['exec']);
    expect(summary.tonnage).toBe('2152.500000'); expect(summary.complete).toBeTrue(); expect(summary.includesPending).toBeTrue();
  });
  it('distinguishes unknown load, explicit zero and warmup workload', () => {
    const account = session(); enqueueSet(account, 'exec', 'a', values(null), 'a');
    expect(measuredSummary(account.executions['exec']).tonnage).toBeNull();
    enqueueSet(account, 'exec', 'b', { ...values('20', 5), type: 'warmup' }, 'b');
    enqueueSet(account, 'exec', 'c', values('0'), 'c');
    const summary = measuredSummary(account.executions['exec']);
    expect(summary.tonnage).toBe('100.000000'); expect(summary.warmupTonnage).toBe('100.000000'); expect(summary.workTonnage).toBe('0.000000');
    expect(summary.missingLoad).toBe(1); expect(summary.complete).toBeFalse();
  });
});
