export interface CatalogExercise {
  id: number; code: string; names: { es: string; en?: string }; load_mode: string; fatigue: string; unilateral: boolean;
  variant_of_id: number | null; review_status: string; is_active: boolean; scope: string; can_edit: boolean; notes: string | null;
  terms: Array<{ kind: string; code: string; role: string; names: { es: string } }>;
}
export interface PrescriptionResult {
  suggestion_id: string; rule_version: string;
  prescribed: { sets: number; reps_min: number; reps_max: number; target_rir: number; rest_seconds: number };
  suggested_load: { value: string; unit: 'kg' | 'lb'; value_kg: string; increment: string } | null;
  reason: string; adjustment: string; basis: { kind: string; value_kg: string; confidence: string; measured_at: string } | null;
}
export interface BuilderExercise {
  required_equipment?: string[];
  exercise_id: number; name: string; load_mode: string; sets: number; reps_min: number; reps_max: number; target_rir: number;
  rest_seconds: number; unit: 'kg' | 'lb'; increment: string; chosen_load: string | number | null; notes: string;
  suggestion_id: string | null; suggestion?: PrescriptionResult;
}
export interface WorkoutBuilderDraft {
  region?: 'lower_body' | 'upper_body'; initial_muscle?: string;
  uuid: string; title: string; equipment: string[]; exercise_count: number; exercises: BuilderExercise[];
  acknowledge_short_session: boolean; pain_reported: boolean; technique_issue: boolean;
}
export interface GeneratedWorkoutInput {
  region?: 'lower_body' | 'upper_body';
  title: string; equipment: string[]; acknowledge_short_session: boolean;
  exercises: Array<Pick<BuilderExercise, 'exercise_id'|'sets'|'reps_min'|'reps_max'|'target_rir'|'rest_seconds'|'unit'|'chosen_load'|'notes'|'suggestion_id'>>;
}
