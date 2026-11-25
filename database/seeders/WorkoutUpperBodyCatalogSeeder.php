<?php
namespace Database\Seeders;

use App\Models\WorkoutExercise;
use App\Models\WorkoutTaxonomyTerm;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkoutUpperBodyCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/workout_upper_body_v1.json')), true, 512, JSON_THROW_ON_ERROR);
        $labels = [
            'region' => ['upper_body' => 'Tren superior'],
            'goal' => ['hypertrophy' => 'Hipertrofia'],
            'level' => ['intermediate' => 'Intermedio'],
            'pattern' => ['horizontal_push' => 'Empuje horizontal', 'horizontal_adduction' => 'Aducción horizontal', 'vertical_pull' => 'Tracción vertical', 'horizontal_pull' => 'Tracción horizontal', 'shoulder_extension' => 'Extensión de hombro', 'vertical_push' => 'Empuje vertical', 'shoulder_abduction' => 'Abducción de hombro', 'horizontal_abduction' => 'Abducción horizontal', 'scapular_elevation' => 'Elevación escapular', 'elbow_flexion' => 'Flexión de codo', 'elbow_extension' => 'Extensión de codo'],
            'muscle' => ['pectorals' => 'Pectorales', 'anterior_deltoids' => 'Deltoides anterior', 'lateral_deltoids' => 'Deltoides lateral', 'posterior_deltoids' => 'Deltoides posterior', 'triceps' => 'Tríceps', 'latissimus_dorsi' => 'Dorsal ancho', 'biceps' => 'Bíceps', 'upper_back' => 'Espalda alta', 'erectors' => 'Erectores espinales', 'upper_trapezius' => 'Trapecio superior', 'external_rotators' => 'Rotadores externos', 'brachialis' => 'Braquial', 'brachioradialis' => 'Braquiorradial'],
            'equipment' => ['barbell' => 'Barra y discos', 'bench' => 'Banco', 'rack' => 'Rack', 'adjustable_bench' => 'Banco ajustable', 'dumbbell' => 'Mancuernas', 'smith_machine' => 'Máquina Smith', 'chest_press_machine' => 'Máquina de press de pecho', 'dual_cable_machine' => 'Polea doble', 'pec_deck_machine' => 'Máquina pec deck', 'bodyweight' => 'Sin equipo', 'lat_pulldown_machine' => 'Máquina de jalón al pecho', 'neutral_grip_handle' => 'Agarre neutro para polea', 'pull_up_bar' => 'Barra de dominadas', 'assisted_pull_up_machine' => 'Máquina de dominadas asistidas', 'seated_row_cable' => 'Polea de remo sentado', 'row_machine' => 'Máquina de remo', 'cable_machine' => 'Polea', 'shoulder_press_machine' => 'Máquina de press de hombros', 'reverse_fly_machine' => 'Máquina de aperturas inversas', 'rope_attachment' => 'Cuerda para polea', 'ez_bar' => 'Barra Z y discos'],
            'demand' => ['shoulder_motion' => 'Movimiento de hombro', 'elbow_motion' => 'Movimiento de codo', 'external_load' => 'Carga externa', 'overhead_work' => 'Trabajo por encima de la cabeza'],
        ];
        DB::transaction(function () use ($labels, $data) {
            $terms = [];
            foreach ($labels as $kind => $items) foreach ($items as $code => $label) {
                $terms[$kind][$code] = WorkoutTaxonomyTerm::firstOrCreate(['kind' => $kind, 'code' => $code], ['names' => ['es' => $label]])->id;
            }
            foreach ($data['exercises'] as $row) {
                $parent = $row['variant_of'] ? WorkoutExercise::where('code', 'upper_body_'.$row['variant_of'])->firstOrFail()->id : null;
                $exercise = WorkoutExercise::firstOrCreate(['code' => 'upper_body_'.$row['code']], [
                    'coach_id' => null, 'variant_of_id' => $parent, 'names' => $row['names'], 'load_mode' => $row['load_mode'],
                    'fatigue' => $row['fatigue'], 'unilateral' => $row['unilateral'], 'is_active' => true,
                    'review_status' => 'pending', 'seed_version' => $data['version'],
                ]);
                // A rerun never overwrites edits, reviews, activation or curated associations.
                if (!$exercise->wasRecentlyCreated) continue;
                $pivot = [];
                $groups = ['region' => ['upper_body'], 'goal' => ['hypertrophy'], 'level' => ['intermediate'],
                    'pattern' => [$row['pattern']], 'equipment' => $row['equipment']];
                $demands = [];
                if (!in_array($row['pattern'], ['elbow_flexion', 'elbow_extension'], true)) $demands[] = 'shoulder_motion';
                if (in_array($row['pattern'], ['horizontal_push', 'vertical_push', 'horizontal_pull', 'vertical_pull', 'elbow_flexion', 'elbow_extension'], true)) $demands[] = 'elbow_motion';
                if ($row['pattern'] === 'vertical_push' || str_contains($row['code'], 'overhead')) $demands[] = 'overhead_work';
                if ($row['load_mode'] !== 'bodyweight') $demands[] = 'external_load';
                $groups['demand'] = $demands;
                foreach ($groups as $kind => $codes) foreach ($codes as $code) $pivot[$terms[$kind][$code]] = ['role' => $kind === 'equipment' ? 'required' : 'classification'];
                foreach ($row['primary_muscles'] as $code) $pivot[$terms['muscle'][$code]] = ['role' => 'primary'];
                foreach ($row['secondary_muscles'] as $code) $pivot[$terms['muscle'][$code]] = ['role' => 'secondary'];
                $exercise->terms()->sync($pivot);
            }
        });
    }
}
