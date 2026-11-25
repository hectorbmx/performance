import { LocalExecution, SetValues } from './workout-offline.store';

function empty() {
  return { prescribed: 0, registered: 0, unrecorded: 0, completed: 0, failed: 0, skipped: 0,
    workCompleted: 0, reps: 0, missingLoad: 0, loaded: 0, micros: 0n, workMicros: 0n, warmupMicros: 0n };
}
function micros(values: SetValues): bigint | null {
  if (values.load_mode !== 'total_barbell' || values.actual_load === null || values.load_unit === null) return null;
  const parts = values.actual_load.split('.');
  const cents = BigInt(parts[0]) * 100n + BigInt((parts[1] ?? '').padEnd(2, '0'));
  return values.load_unit === 'kg' ? cents * 10000n : (cents * 45359237n + 5000n) / 10000n;
}
function finish(total: ReturnType<typeof empty>) {
  const decimal = (amount: bigint) => total.loaded === 0 ? null : `${amount / 1000000n}.${String(amount % 1000000n).padStart(6, '0')}`;
  return { ...total, tonnage: decimal(total.micros), workTonnage: decimal(total.workMicros), warmupTonnage: decimal(total.warmupMicros),
    seriesAdherencePercent:total.prescribed ? Math.round(1000*total.completed/total.prescribed)/10 : null,
    complete: total.missingLoad === 0 && total.unrecorded === 0 };
}
export function measuredSummary(session: LocalExecution) {
  const total = empty(); const directMuscleSeries:Record<string,number>={}; let unclassifiedWorkSets=0;
  const exercises = session.execution.prescription.content.sections.flatMap(section => section.exercises.map(exercise => {
    const summary = empty();
    for (const set of exercise.sets) {
      summary.prescribed++;
      const record = session.records[set.uuid];
      if (!record) { summary.unrecorded++; continue; }
      const values = record.values;
      summary.registered++; summary[values.status]++;
      if (values.status === 'completed' && values.type === 'work') summary.workCompleted++;
      if (values.status === 'skipped') continue;
      const reps = values.actual_reps ?? 0; summary.reps += reps;
      if (reps <= 0) continue;
      const load = micros(values);
      if (load === null) { summary.missingLoad++; continue; }
      const amount = load * BigInt(reps);
      summary.loaded++; summary.micros += amount;
      if (values.type === 'warmup') summary.warmupMicros += amount; else summary.workMicros += amount;
    }
    const primary=[...new Set((exercise.catalog?.terms??[]).filter(t=>t.kind==='muscle'&&t.role==='primary').map(t=>t.code))];
    if(!primary.length)unclassifiedWorkSets+=summary.workCompleted;
    for(const code of primary)directMuscleSeries[code]=(directMuscleSeries[code]??0)+summary.workCompleted;
    total.prescribed += summary.prescribed; total.registered += summary.registered; total.unrecorded += summary.unrecorded;
    total.completed += summary.completed; total.failed += summary.failed; total.skipped += summary.skipped;
    total.workCompleted += summary.workCompleted; total.reps += summary.reps; total.missingLoad += summary.missingLoad;
    total.loaded += summary.loaded; total.micros += summary.micros; total.workMicros += summary.workMicros; total.warmupMicros += summary.warmupMicros;
    return { uuid: exercise.uuid, name: exercise.name, catalogId: exercise.workout_exercise_id ?? null, ...finish(summary) };
  }));
  return { ...finish(total), exercises, directMuscleSeries, unclassifiedWorkSets, includesPending: Object.values(session.records).some(r => r.sync !== 'synced') };
}
